<?php
namespace App\Mpesa;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Log;

class Auth{
    protected const sandboxAuthUrl = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    protected const productionAuthUrl = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    /**
     * Auth constructor.
     */
    public function __construct(){}

    /**
     * Generated the access_token based on the environment [Sandbox, Production]
     * @param $app_name
     * @param string $env
     * @return mixed
     */
    public static function authenticate(string $appName, string $env='sandbox'): mixed {
        try{
            $authUrl = ($env == 'production') ? self::productionAuthUrl : self::sandboxAuthUrl;
            $credentials = self::getCredentials($appName);
            $ch = curl_init($authUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic '.$credentials]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            curl_close($ch);
            if (curl_errno($ch)) {
                Log::channel('mpesa')->error('Curl Error: ' . curl_error($ch));
                return null;
            }
            $response = json_decode($result);
            if (!isset($response->access_token)) {
                Log::channel('mpesa')->error('Failed to retrieve access token: ' . $result);
                return null;
            }
            return $response->access_token;
        }catch(Exception $e){
            // Log the exception or handle it as needed
            Log::channel('mpesa')->error('MPESA Authentication Error: ' . $e->getMessage());
            return null;
        } 
    }


    /**
     * Retrieves the credentials for the specified active application.
     *
     * @param string $active_app The name of the active application.
     * @return string The concatenated consumer key and secret for the active application.
     * @throws Exception If the active application is not listed or credentials are not set.
     */
    public static function getCredentials(string $active_app): string {
        $apps = config('mpesa.apps');
        if(!isset($apps[$active_app])){throw new Exception("No active apps listed.");}
        $key = $apps[$active_app]['consumer_key'];
        $secret = $apps[$active_app]['consumer_secret'];
        if(empty($key) || empty($secret)){throw new Exception("No Consumer Key and Secret Set for $active_app.");}
        return base64_encode("$key:$secret");
        //return $key.':'.$secret;

    }

    /**
     * Generates auth credentials for LMNO
     * @return string
     */
    public static function secureCredentials(): string {
        $short_code = config('mpesa.lnmo.short_code');
        $pass_key = config('mpesa.lnmo.passkey');
        return base64_encode($short_code.$pass_key.date('YmdHis'));
    }
}
