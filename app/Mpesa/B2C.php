<?php
namespace App\Mpesa;

class B2C{
    protected const url = 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
    protected const productionUrl = 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
    protected const transactionUrl = 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query';
    protected const balanceUrl = 'https://api.safaricom.co.ke/mpesa/accountbalance/v1/query';

    public function __construct(){}
    public static function submit(array $userParams=[]): bool|string {
        return Core::requestB2C(self::productionUrl, $userParams);
    }
    public static function transactionStatus($userParams=[]): bool|string
    {
        return Core::requestTransactionStatusB2C(self::transactionUrl, $userParams);
    }
    public static function accountBalance(): bool|string
    {
        return Core::requestAccountBalanceB2C(self::balanceUrl);
    }

}
