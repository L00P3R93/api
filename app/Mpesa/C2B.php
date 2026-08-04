<?php
namespace App\Mpesa;

class C2B{
    protected const productionUrl = 'https://api.safaricom.co.ke/mpesa/c2b/v2/registerurl';
    protected const balanceUrl = 'https://api.safaricom.co.ke/mpesa/accountbalance/v1/query';

    protected const url = 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
    protected const urlSimulate = 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate';


    /**
     * C2B constructor.
     */
    public function __construct(){}
    /**
     * Calls request to register C2B URLs
     * @return bool|string
     */
    public static function submit(): bool|string {
        return Core::requestC2BRegister(self::productionUrl);
    }
    /**
     * Simulates C2B Payment
     * @param array $userParams
     * @return bool|string
     */
    public static function submitSimulate(array $userParams=[]): bool|string {
        return Core::requestC2BSimulate(self::urlSimulate, $userParams);
    }

    /**
     * Queries For C2B Paybill Account Balance
     * @return bool|string
     */
    public static function accountBalance(): bool|string {
        return Core::requestAccountBalanceC2B(self::balanceUrl);
    }
}
