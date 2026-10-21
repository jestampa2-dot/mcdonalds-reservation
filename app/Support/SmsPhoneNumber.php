<?php

namespace App\Support;

class SmsPhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $number = preg_replace('/[\s().-]/', '', trim($phone ?? ''));

        if (preg_match('/^(?:\+?63|0)?(9\d{9})$/', $number, $matches)) {
            return '+63'.$matches[1];
        }

        return null;
    }
}
