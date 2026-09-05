<?php

namespace App\Services;

use App\Mpesa\Auth;
use App\Mpesa\B2C;
use App\Mpesa\C2B;
use App\Mpesa\LNMO;

class MpesaService
{
    public function authenticate(string $appName, string $env = 'sandbox'): mixed
    {
        return Auth::authenticate($appName, $env);
    }

    public function stkPush(array $params = []): bool|string
    {
        return LNMO::submit($params);
    }

    public function b2c(array $params = []): bool|string
    {
        return B2C::submit($params);
    }

    public function b2cTransactionStatus(array $params = []): bool|string
    {
        return B2C::transactionStatus($params);
    }

    public function b2cAccountBalance(): bool|string
    {
        return B2C::accountBalance();
    }

    public function c2bRegister(): bool|string
    {
        return C2B::submit();
    }

    public function c2bSimulate(array $params = []): bool|string
    {
        return C2B::submitSimulate($params);
    }

    public function c2bAccountBalance(): bool|string
    {
        return C2B::accountBalance();
    }
}
