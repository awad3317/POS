<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Store;
use App\Models\Supplier;

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
        if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
            $digits = '967' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '7')) {
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

    public static function forOrder(Order $order, Store $store): string
    {
        $order->loadMissing(['customer', 'items']);
        $currency = $store->currency_symbol ?: 'ر.ي';
        $dp = (int) $store->decimal_places;

        $totalFmt = number_format((float) $order->total_amount, $dp) . ' ' . $currency;
        $paidFmt = number_format((float) $order->paid_amount, $dp) . ' ' . $currency;
        $creditFmt = number_format((float) $order->credit_amount, $dp) . ' ' . $currency;
        $title = $order->type === 'return' ? 'سند مرتجع مبيعات' : 'فاتورة مبيعات';

        $lines = [
            "🧾 *{$title} — {$store->name}*",
            "رقم الفاتورة: *{$order->invoice_number}*",
            'التاريخ: ' . ($order->sold_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i')),
            'العميل: ' . ($order->customer?->name ?? 'عميل نقدي'),
            '----------------',
        ];

        foreach ($order->items->take(15) as $item) {
            $qty = rtrim(rtrim((string) $item->quantity, '0'), '.');
            $net = number_format((float) $item->net_total, $dp);
            $lines[] = "• {$item->product_name} ({$qty}) = {$net} {$currency}";
        }

        if ($order->items->count() > 15) {
            $extra = $order->items->count() - 15;
            $lines[] = "... (+{$extra} أصناف أخرى)";
        }

        $lines[] = '----------------';
        $lines[] = "الإجمالي: *{$totalFmt}*";
        $lines[] = "المدفوع: {$paidFmt}";

        if (MoneyMath::isPositive($order->credit_amount)) {
            $lines[] = "المتبقي آجل: *{$creditFmt}*";
        }

        $footer = $store->receipt_settings['footer_note'] ?? null;
        if ($footer) {
            $lines[] = $footer;
        }

        $phone = $order->customer?->whatsapp ?: $order->customer?->phone;

        return self::build($phone, implode("\n", $lines));
    }

    public static function forCustomerDebt(Customer $customer, Store $store): string
    {
        $currency = $store->currency_symbol ?: 'ر.ي';
        $dp = (int) $store->decimal_places;
        $balanceFmt = number_format((float) $customer->current_balance, $dp) . ' ' . $currency;

        $message = "عزيزنا العميل / {$customer->name}\n"
            . "تحية طيبة من {$store->name}\n"
            . "نود تذكيركم بأن الرصيد المستحق في حسابكم هو: *{$balanceFmt}*.\n"
            . 'شكراً لتعاملكم معنا.';

        return self::build($customer->whatsapp ?: $customer->phone, $message);
    }

    public static function forSupplierPayment(Supplier $supplier, Store $store, ?string $closingBalance = null): string
    {
        $currency = $store->currency_symbol ?: 'ر.ي';
        $dp = (int) $store->decimal_places;
        $balance = $closingBalance ?? (string) $supplier->current_balance;
        $balanceFmt = number_format((float) $balance, $dp) . ' ' . $currency;

        $message = "الإخوة / {$supplier->name}\n"
            . "تحية طيبة من {$store->name}\n"
            . "الرصيد الحالي المستحق لكم في دفاترنا: *{$balanceFmt}*.\n"
            . 'مع خالص التقدير.';

        return self::build($supplier->whatsapp ?: $supplier->phone, $message);
    }

    public static function forEmployeeStatement(Employee $employee, Store $store, ?string $closingBalance = null): string
    {
        $currency = $store->currency_symbol ?: 'ر.ي';
        $dp = (int) $store->decimal_places;
        $balance = $closingBalance ?? (string) $employee->current_balance;
        $balanceFmt = number_format((float) $balance, $dp) . ' ' . $currency;

        $message = "كشف حساب الراتب والسلف — {$store->name}\n"
            . "الموظف: {$employee->name}\n"
            . "صافي الرصيد الحالي: *{$balanceFmt}*.";

        return self::build($employee->whatsapp ?: $employee->phone, $message);
    }
}
