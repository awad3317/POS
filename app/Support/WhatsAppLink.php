<?php

namespace App\Support;

final class WhatsAppLink
{
    public static function formatPhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '967' . $digits;
        }

        return $digits;
    }

    public static function build(?string $phone, string $message): string
    {
        $cleanPhone = self::formatPhone($phone);
        $encoded = rawurlencode($message);

        return $cleanPhone !== ''
            ? "https://wa.me/{$cleanPhone}?text={$encoded}"
            : "https://wa.me/?text={$encoded}";
    }
}
