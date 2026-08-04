<?php
namespace App\Mpesa;

class LNMO{
    protected const url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    protected const productionUrl = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    public function __construct(){}

    public static function submit($userParams=[]): bool|string {
        return Core::requestSTK(self::productionUrl, $userParams);
    }
}
