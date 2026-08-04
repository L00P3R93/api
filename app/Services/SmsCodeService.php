<?php

namespace App\Services;

use App\Models\SMSCode;

class SmsCodeService {
    /**
     * Expire all sms verification codes that are either pending or sent, but older than 2 minutes.
     *
     * @return int The number of expired codes.
     */
    public function expireOldCodes(): int {
        return SMSCode::whereIn('status', [1,2])
            ->where('created_at', '<', now()->subMinutes(2))
            ->update(['status' => 4]); // 4 = expired
    }
}
