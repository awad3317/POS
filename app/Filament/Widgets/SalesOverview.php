<?php

namespace App\App\Filament\Widgets;

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $currency_symbol = config('settings.currency_symbol');

        $totalOrdersLast30Days = Order::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $totalIncomeLast30Days = Order::where('created_at', '>=', Carbon::now()->subDays(30))->sum('total_price');
        $totalcustomersLast30Days = Customer::where('created_at', '>=', Carbon::now()->subDays(30))->count();

        return [
            // 1. كارت عدد الطلبات مَعرب
            Stat::make('عدد الطلبات', $totalOrdersLast30Days)
                    ->description("إجمالي الطلبات في آخر 30 يوماً")
                    ->descriptionIcon('heroicon-o-inbox-stack', IconPosition::Before)
                    ->chart([1,5,10,50])
                    ->color('success'),

            // 2. كارت إجمالي الدخل مَعرب مع العملة
            Stat::make('إجمالي الدخل', $currency_symbol . ' ' . number_format($totalIncomeLast30Days, 2))
                    ->description("إجمالي المبيعات في آخر 30 يوماً")
                    ->descriptionIcon('heroicon-o-banknotes', IconPosition::Before)
                    ->chart([1,5,30, 50])
                    ->color('success'),
            
            // 3. كارت عدد العملاء الجدد مَعرب
            Stat::make('العملاء الجدد', $totalcustomersLast30Days)
                    ->description("العملاء المسجلين في آخر 30 يوماً")
                    ->descriptionIcon('heroicon-o-user-group', IconPosition::Before)
                    ->chart([1,5,15, 25])
                    ->color('success'),       
        ];
    }
}