<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;

final class DisplayFormat
{
    /**
     * Format quantities cleanly without trailing zeros (e.g. 20.000 -> 20, 2.500 -> 2.5, 0.375 -> 0.375).
     */
    public static function formatQty(string|int|float|null $qty): string
    {
        if ($qty === null || trim((string) $qty) === '') {
            return '0';
        }

        $normalized = BigDecimal::of((string) $qty)
            ->toScale(3, RoundingMode::HALF_UP)
            ->__toString();

        $trimmed = rtrim(rtrim($normalized, '0'), '.');

        return ($trimmed === '' || $trimmed === '-0') ? '0' : $trimmed;
    }

    /**
     * Format monetary amounts for display using store decimal_places (default 0 for YER: 7000.0000 -> 7,000),
     * or plain unformatted decimal without trailing zeros when $forInput = true.
     */
    public static function formatMoney(
        string|int|float|null $amount,
        ?int $decimals = null,
        bool $forInput = false
    ): string {
        if ($forInput) {
            return self::formatInput($amount);
        }

        if ($amount === null || trim((string) $amount) === '') {
            $amount = '0';
        }

        $resolvedDecimals = $decimals ?? self::resolveStoreDecimals();

        $normalized = BigDecimal::of((string) $amount)
            ->toScale(max(0, $resolvedDecimals), RoundingMode::HALF_UP)
            ->__toString();

        $isNegative = str_starts_with($normalized, '-');
        $unsigned = $isNegative ? substr($normalized, 1) : $normalized;

        [$intPart, $decPart] = array_pad(explode('.', $unsigned, 2), 2, '');
        $formattedInt = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $intPart);

        $formatted = $decPart !== '' ? "{$formattedInt}.{$decPart}" : $formattedInt;

        if ($isNegative && ! preg_match('/^0(\.0+)?$/', $unsigned)) {
            return "-{$formatted}";
        }

        return $formatted;
    }

    /**
     * Format decimal numbers for HTML <input> values without commas or trailing zeros (7000.0000 -> 7000).
     */
    public static function formatInput(string|int|float|null $value, bool $nullable = false): string
    {
        if ($value === null || trim((string) $value) === '') {
            return $nullable ? '' : '0';
        }

        $str = trim((string) $value);
        if (! is_numeric($str)) {
            return $str;
        }

        $normalized = BigDecimal::of($str)->__toString();
        if (str_contains($normalized, '.')) {
            $normalized = rtrim(rtrim($normalized, '0'), '.');
        }

        return ($normalized === '' || $normalized === '-0') ? '0' : $normalized;
    }

    public static function productTypeLabel(?string $type): string
    {
        return match ($type) {
            'simple' => 'عادي',
            'variant' => 'متغيرات',
            'weighted' => 'موزون',
            default => $type ?? 'عادي',
        };
    }

    protected static function resolveStoreDecimals(): int
    {
        try {
            if (Auth::hasUser()) {
                $user = Auth::user();
                if ($user && $user->relationLoaded('store') && $user->store) {
                    return (int) $user->store->decimal_places;
                }
            }
        } catch (\Throwable) {
            // Fallback to 0 for YER
        }

        return 0;
    }
}
