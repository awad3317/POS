<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing(['roles.permissions', 'permissions']);
        $store = $user->store;

        $terminals = Terminal::forStore($store->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'branch_id', 'code', 'name', 'last_invoice_seq']);

        $openShifts = Shift::forStore($store->id)
            ->where('status', 'open')
            ->get(['id', 'terminal_id', 'branch_id', 'client_uuid', 'opening_cash', 'cash_sales_total', 'cash_returns_total', 'cash_in_total', 'cash_out_total', 'expected_cash', 'opened_at', 'notes']);

        $bootConfig = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'business_type' => $store->business_type,
                'currency_symbol' => $store->currency_symbol,
                'currency_code' => $store->currency_code,
                'decimal_places' => $store->decimal_places,
                'feature_flags' => $store->feature_flags ?? [],
                'receipt_settings' => $store->receipt_settings ?? [],
            ],
            'terminals' => $terminals->toArray(),
            'open_shifts' => $openShifts->toArray(),
        ];

        return view('pos.index', compact('store', 'user', 'terminals', 'bootConfig'));
    }
}
