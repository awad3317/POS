<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class MoneyMath
{
    public static function money(string|int|BigDecimal $val): string
    {
        return BigDecimal::of($val)->toScale(4, RoundingMode::HALF_UP)->__toString();
    }

    public static function qty(string|int|BigDecimal $val): string
    {
        return BigDecimal::of($val)->toScale(3, RoundingMode::HALF_UP)->__toString();
    }

    public static function add(string|int|BigDecimal $a, string|int|BigDecimal $b, int $scale = 4): string
    {
        return BigDecimal::of($a)
            ->plus(BigDecimal::of($b))
            ->toScale($scale, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function sub(string|int|BigDecimal $a, string|int|BigDecimal $b, int $scale = 4): string
    {
        return BigDecimal::of($a)
            ->minus(BigDecimal::of($b))
            ->toScale($scale, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function mul(string|int|BigDecimal $a, string|int|BigDecimal $b, int $scale = 4): string
    {
        return BigDecimal::of($a)
            ->multipliedBy(BigDecimal::of($b))
            ->toScale($scale, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function div(string|int|BigDecimal $a, string|int|BigDecimal $b, int $scale = 4): string
    {
        return BigDecimal::of($a)
            ->dividedBy(BigDecimal::of($b), $scale, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function percent(string|int|BigDecimal $amount, string|int|BigDecimal $rate, int $scale = 4): string
    {
        return BigDecimal::of($amount)
            ->multipliedBy(BigDecimal::of($rate))
            ->dividedBy(BigDecimal::of('100'), $scale, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function cmp(string|int|BigDecimal $a, string|int|BigDecimal $b): int
    {
        return BigDecimal::of($a)->compareTo(BigDecimal::of($b));
    }

    public static function isZero(string|int|BigDecimal $val): bool
    {
        return BigDecimal::of($val)->isZero();
    }

    public static function isNegative(string|int|BigDecimal $val): bool
    {
        return BigDecimal::of($val)->isNegative();
    }

    public static function isPositive(string|int|BigDecimal $val): bool
    {
        return BigDecimal::of($val)->isPositive();
    }

    public static function isGreaterThan(string|int|BigDecimal $a, string|int|BigDecimal $b): bool
    {
        return self::cmp($a, $b) > 0;
    }

    public static function isLessThan(string|int|BigDecimal $a, string|int|BigDecimal $b): bool
    {
        return self::cmp($a, $b) < 0;
    }

    public static function abs(string|int|BigDecimal $val, int $scale = 4): string
    {
        return BigDecimal::of($val)->abs()->toScale($scale, RoundingMode::HALF_UP)->__toString();
    }

    public static function negate(string|int|BigDecimal $val, int $scale = 4): string
    {
        return BigDecimal::of($val)->negated()->toScale($scale, RoundingMode::HALF_UP)->__toString();
    }

    /**
     * @param  iterable<string|int|BigDecimal>  $values
     */
    public static function sum(iterable $values, int $scale = 4): string
    {
        $total = BigDecimal::zero();
        foreach ($values as $val) {
            $total = $total->plus(BigDecimal::of($val));
        }

        return $total->toScale($scale, RoundingMode::HALF_UP)->__toString();
    }

    public static function formatQty(string|int|float|null $qty): string
    {
        return DisplayFormat::formatQty($qty);
    }

    public static function formatMoney(string|int|float|null $amount, ?int $decimals = null, bool $forInput = false): string
    {
        return DisplayFormat::formatMoney($amount, $decimals, $forInput);
    }

    public static function formatInput(string|int|float|null $value, bool $nullable = false): string
    {
        return DisplayFormat::formatInput($value, $nullable);
    }
}
