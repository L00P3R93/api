<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class SMSCode extends Model {
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'sms_codes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'phone_no',
        'code',
        'status',
        'body'
    ];

    public static function generateCode(): string {
        do {
            try {
                //Generate 6-digit random code
                $code = (string) random_int(100000, 999999);
            } catch (\Exception $e) {
                //Handle failure gracefully
                Log::error("Failed to generate verification code: {$e->getMessage()}");
                abort(500, 'Unable to generate verification code.');
            }
        } while (self::where('code', $code)->exists());
        return $code;
    }
}
