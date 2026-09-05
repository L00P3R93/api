<?php

namespace App\Services;

use App\Exceptions\MpesaApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    private const SANDBOX_BASE = 'https://sandbox.safaricom.co.ke';

    private const PRODUCTION_BASE = 'https://api.safaricom.co.ke';

    private bool $production;

    public function __construct(?bool $production = null)
    {
        $this->production = $production ?? (config('app.env') === 'production');
    }

    // -------------------------------------------------------------------------
    // Access Token
    // -------------------------------------------------------------------------

    /**
     * Get a cached OAuth access token for the given app.
     */
    public function getAccessToken(string $app = 'c2b'): string
    {
        $cacheKey = "mpesa_access_token_{$app}";

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($app) {
            return $this->fetchAccessToken($app);
        });
    }

    /**
     * Fetch a fresh OAuth access token from Safaricom.
     *
     * @throws MpesaApiException
     */
    private function fetchAccessToken(string $app): string
    {
        $credentials = $this->getConsumerCredentials($app);
        $url = $this->baseUrl().'/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$credentials,
        ])->get($url);

        $body = $response->json();

        if (! $response->successful() || ! isset($body['access_token'])) {
            Log::channel('mpesa')->error('Failed to retrieve access token', [
                'app' => $app,
                'status' => $response->status(),
                'response' => $body,
            ]);

            throw new MpesaApiException(
                'Failed to retrieve M-Pesa access token: '.($body['error_description'] ?? 'Unknown error'),
                statusCode: $response->status(),
                responseBody: $body,
            );
        }

        return $body['access_token'];
    }

    // -------------------------------------------------------------------------
    // STK Push (Lipa Na M-Pesa Online)
    // -------------------------------------------------------------------------

    /**
     * Initiate an STK Push request.
     *
     * @param  array{Amount: int, PhoneNumber: string, AccountReference?: string}  $params
     *
     * @throws MpesaApiException
     */
    public function stkPush(array $params): array
    {
        $config = config('mpesa.lnmo');

        $payload = array_merge([
            'BusinessShortCode' => $config['short_code'],
            'Password' => $this->lnmoPassword(),
            'Timestamp' => now()->format('YmdHis'),
            'TransactionType' => $config['default_transaction_type'],
            'PartyB' => $config['short_code'],
            'CallBackURL' => $config['callback'],
            'TransactionDesc' => 'WalletDeposit',
        ], $params);

        return $this->request('/mpesa/stkpush/v1/processrequest', $payload, 'c2b');
    }

    // -------------------------------------------------------------------------
    // B2C (Business to Customer — Payouts)
    // -------------------------------------------------------------------------

    /**
     * Initiate a B2C payment request (e.g. withdrawal payout).
     *
     * @param  array{Amount: int, PartyB: string, Remarks?: string, Occasion?: string}  $params
     *
     * @throws MpesaApiException
     */
    public function b2c(array $params): array
    {
        $config = config('mpesa.b2c');

        $payload = array_merge([
            'InitiatorName' => $config['initiator_name'],
            'SecurityCredential' => $this->securityCredential($config['security_credential']),
            'CommandID' => $config['default_command_id'],
            'PartyA' => $config['short_code'],
            'QueueTimeOutURL' => $config['timeout_url'],
            'ResultURL' => $config['result_url'],
        ], $params);

        return $this->request('/mpesa/b2c/v1/paymentrequest', $payload, 'b2c');
    }

    /**
     * Query the status of a B2C transaction.
     *
     * @param  array{TransactionID: string}  $params
     *
     * @throws MpesaApiException
     */
    public function b2cTransactionStatus(array $params): array
    {
        $b2cConfig = config('mpesa.b2c');
        $statusConfig = config('mpesa.transaction_status_b2c');

        $payload = array_merge([
            'Initiator' => $b2cConfig['initiator_name'],
            'SecurityCredential' => $this->securityCredential($b2cConfig['security_credential']),
            'CommandID' => 'TransactionStatusQuery',
            'PartyA' => $b2cConfig['short_code'],
            'IdentifierType' => '4',
            'Remarks' => 'Transaction Status Query',
            'Occasion' => '',
            'QueueTimeOutURL' => $statusConfig['timeout_url'],
            'ResultURL' => $statusConfig['result_url'],
        ], $params);

        return $this->request('/mpesa/transactionstatus/v1/query', $payload, 'b2c');
    }

    /**
     * Query B2C account balance.
     *
     * @throws MpesaApiException
     */
    public function b2cAccountBalance(): array
    {
        $b2cConfig = config('mpesa.b2c');
        $balanceConfig = config('mpesa.account_balance_b2c');

        $payload = [
            'Initiator' => $b2cConfig['initiator_name'],
            'SecurityCredential' => $this->securityCredential($b2cConfig['security_credential']),
            'CommandID' => 'AccountBalance',
            'PartyA' => $b2cConfig['short_code'],
            'IdentifierType' => '4',
            'Remarks' => 'Account Balance Query',
            'QueueTimeOutURL' => $balanceConfig['timeout_url'] ?: $b2cConfig['timeout_url'],
            'ResultURL' => $balanceConfig['result_url'] ?: $b2cConfig['result_url'],
        ];

        return $this->request('/mpesa/accountbalance/v1/query', $payload, 'b2c');
    }

    // -------------------------------------------------------------------------
    // C2B (Customer to Business — Deposits)
    // -------------------------------------------------------------------------

    /**
     * Register C2B callback URLs (called once).
     *
     * @throws MpesaApiException
     */
    public function c2bRegister(): array
    {
        $config = config('mpesa.c2b');

        $payload = [
            'ShortCode' => $config['short_code'],
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $config['confirmation_url'],
            'ValidationURL' => $config['validation_url'],
        ];

        return $this->request('/mpesa/c2b/v2/registerurl', $payload, 'c2b');
    }

    /**
     * Simulate a C2B payment (sandbox only).
     *
     * @param  array{Amount?: string, Msisdn?: string, BillRefNumber?: string}  $params
     *
     * @throws MpesaApiException
     */
    public function c2bSimulate(array $params = []): array
    {
        $config = config('mpesa.c2b');

        $payload = array_merge([
            'CommandID' => $config['default_command_id'],
            'Amount' => '10',
            'Msisdn' => '254708374149',
            'BillRefNumber' => 'TRIPPINMAD',
            'ShortCode' => $config['short_code'],
        ], $params);

        return $this->request('/mpesa/c2b/v1/simulate', $payload, 'c2b');
    }

    /**
     * Query C2B account balance.
     *
     * @throws MpesaApiException
     */
    public function c2bAccountBalance(): array
    {
        $c2bConfig = config('mpesa.c2b');
        $balanceConfig = config('mpesa.account_balance_c2b');

        $payload = [
            'Initiator' => $c2bConfig['initiator_name'],
            'SecurityCredential' => $this->securityCredential($c2bConfig['security_credential']),
            'CommandID' => 'AccountBalance',
            'PartyA' => $c2bConfig['short_code'],
            'IdentifierType' => '4',
            'Remarks' => 'Account Balance Query',
            'QueueTimeOutURL' => $balanceConfig['timeout_url'] ?: $c2bConfig['confirmation_url'],
            'ResultURL' => $balanceConfig['result_url'] ?: $c2bConfig['validation_url'],
        ];

        return $this->request('/mpesa/accountbalance/v1/query', $payload, 'c2b');
    }

    // -------------------------------------------------------------------------
    // B2C Reversal
    // -------------------------------------------------------------------------

    /**
     * Reverse a B2C transaction.
     *
     * @param  array{TransactionID: string, Amount: string, ReceiverParty: string}  $params
     *
     * @throws MpesaApiException
     */
    public function reverseB2C(array $params): array
    {
        $b2cConfig = config('mpesa.b2c');

        $payload = array_merge([
            'InitiatorName' => $b2cConfig['initiator_name'],
            'SecurityCredential' => $this->securityCredential($b2cConfig['security_credential']),
            'CommandID' => 'TransactionReversal',
            'ReceiverParty' => $b2cConfig['short_code'],
            'ReceiverIdentifierType' => '4',
            'ResultURL' => '',
            'QueueTimeOutURL' => '',
            'Remarks' => '',
            'Occasion' => '',
        ], $params);

        return $this->request('/mpesa/reversal/v1/request', $payload, 'b2c');
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Make an authenticated POST request to the M-Pesa API.
     *
     * @throws MpesaApiException
     */
    private function request(string $path, array $payload, string $app = 'c2b'): array
    {
        $url = $this->baseUrl().$path;
        $accessToken = $this->getAccessToken($app);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        $body = $response->json();

        if (! $response->successful()) {
            Log::channel('mpesa')->error('M-Pesa API error', [
                'path' => $path,
                'status' => $response->status(),
                'response' => $body,
                'payload' => $payload,
            ]);

            throw new MpesaApiException(
                'M-Pesa API error: '.($body['errorMessage'] ?? $body['ResponseDescription'] ?? 'Unknown error'),
                statusCode: $response->status(),
                errorCode: $body['errorCode'] ?? '',
                responseBody: is_array($body) ? $body : null,
            );
        }

        return is_array($body) ? $body : ['ResponseDescription' => $body];
    }

    private function baseUrl(): string
    {
        return $this->production ? self::PRODUCTION_BASE : self::SANDBOX_BASE;
    }

    private function getConsumerCredentials(string $app): string
    {
        $apps = config('mpesa.apps');

        if (! isset($apps[$app])) {
            throw new \InvalidArgumentException("No M-Pesa app credentials defined for [{$app}].");
        }

        $key = $apps[$app]['consumer_key'] ?? '';
        $secret = $apps[$app]['consumer_secret'] ?? '';

        if (empty($key) || empty($secret)) {
            throw new \InvalidArgumentException("M-Pesa [{$app}] consumer key/secret is not set.");
        }

        return base64_encode("$key:$secret");
    }

    /**
     * Generate the LNMO (STK Push) password.
     */
    private function lnmoPassword(): string
    {
        $config = config('mpesa.lnmo');

        return base64_encode($config['short_code'].$config['passkey'].now()->format('YmdHis'));
    }

    /**
     * Encrypt the security credential using the Safaricom public certificate.
     */
    private function securityCredential(?string $initiatorPass): string
    {
        if (empty($initiatorPass)) {
            throw new \InvalidArgumentException('M-Pesa security credential is not configured.');
        }

        $certFile = $this->production
            ? dirname(__DIR__, 2).'/app/Mpesa/productionCert.txt'
            : dirname(__DIR__, 2).'/app/Mpesa/sandboxCert.txt';

        $publicKey = openssl_pkey_get_public(file_get_contents($certFile));

        openssl_public_encrypt($initiatorPass, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }
}
