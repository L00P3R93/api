<?php
namespace App\Mpesa;

trait MpesaTraits{
    public static function stkPush($userParams=[]): bool|string {
        return LNMO::submit($userParams);
    }
    public static function c2b(): bool|string {
        return C2B::submit();
    }
    public static function c2bSimulate($userParams=[]): bool|string {
        return C2B::submitSimulate($userParams);
    }
    public static function c2bAccountBalance(): bool|string {
        return C2B::accountBalance();
    }
    public static function b2c($userParams=[]): bool|string {
        return B2C::submit($userParams);
    }
    public static function b2cTransactionStatus($userParams=[]): bool|string {
       return B2C::transactionStatus($userParams);
    }
    public static function b2cAccountBalance(): bool|string {
        return B2C::accountBalance();
    }
}
