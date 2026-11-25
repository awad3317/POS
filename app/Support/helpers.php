<?php

use App\Support\DisplayFormat;

if (! function_exists('format_qty')) {
    function format_qty(string|int|float|null $qty): string
    {
        return DisplayFormat::formatQty($qty);
    }
}

if (! function_exists('format_money')) {
    function format_money(string|int|float|null $amount, ?int $decimals = null, bool $forInput = false): string
    {
        return DisplayFormat::formatMoney($amount, $decimals, $forInput);
    }
}

if (! function_exists('format_input')) {
    function format_input(string|int|float|null $value, bool $nullable = false): string
    {
        return DisplayFormat::formatInput($value, $nullable);
    }
}

if (! function_exists('product_type_label')) {
    function product_type_label(?string $type): string
    {
        return DisplayFormat::productTypeLabel($type);
    }
}
