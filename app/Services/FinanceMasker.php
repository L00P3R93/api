<?php

namespace App\Services;

/**
 * Hides most of any phone number in a value, so finance reports and CSV exports can be shared
 * without exposing customer contact details.
 */
class FinanceMasker
{
    /**
     * Masks every run of 9 or more digits, keeping the first and last four: 254712345678 becomes 2547****5678.
     */
    public function phone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace_callback('/\d{9,}/', function (array $match) {
            $digits = $match[0];

            return substr($digits, 0, 4).str_repeat('*', strlen($digits) - 8).substr($digits, -4);
        }, $value);
    }
}
