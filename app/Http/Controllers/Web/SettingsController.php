<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Terminal;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\StoreSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class SettingsController extends Controller
{
    public function __construct(
        protected StoreSettingsService $settingsService,
        protected ExchangeRateService $exchangeRateService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $users = User::forStore($store->id)->with('roles')->orderBy('id')->get();
        $terminals = Terminal::forStore($store->id)->with('branch')->orderBy('id')->get();
        $branches = Branch::forStore($store->id)->get();

        return view('admin.settings.index', compact('store', 'users', 'terminals', 'branches'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'business_type' => ['required', 'string', 'in:grocery,clothing,perfumes,general'],
            'secondary_currency_code' => ['nullable', 'string', 'max:10'],
            'paper_width' => ['required', 'string', 'in:80mm,58mm'],
            'header_note' => ['nullable', 'string', 'max:255'],
            'footer_note' => ['nullable', 'string', 'max:255'],
            'feature_flags' => ['nullable', 'array'],
        ]);

        $store = $request->user()->store;
        $flags = $validated['feature_flags'] ?? [];

        $store->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'business_type' => $validated['business_type'],
            'secondary_currency_code' => $validated['secondary_currency_code'] ?: null,
            'feature_flags' => [
                'variants' => ! empty($flags['variants']),
                'multi_units' => ! empty($flags['multi_units']),
                'scale_plu' => ! empty($flags['scale_plu']),
                'expiry' => ! empty($flags['expiry']),
                'quick_pick' => ! empty($flags['quick_pick']),
                'exchange_rate' => ! empty($flags['exchange_rate']),
                'tax' => ! empty($flags['tax']),
            ],
            'receipt_settings' => array_merge($store->receipt_settings ?? [], [
                'paper_width' => $validated['paper_width'],
                'header_note' => $validated['header_note'] ?? '',
                'footer_note' => $validated['footer_note'] ?? '',
            ]),
        ]);

        $this->settingsService->clearCache($store->id);

        return redirect()->route('settings.index')->with('success', 'تم حفظ إعدادات المتجر بنجاح.');
    }

    public function updateExchangeRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $user = $request->user();
        $count = $this->exchangeRateService->updateStoreExchangeRate(
            $user->store,
            (string) $validated['exchange_rate'],
            $user
        );

        return redirect()->route('settings.index')->with('success', "تم تحديث سعر الصرف وإعادة تسعير {$count} منتج فوراً.");
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
            'pin' => ['nullable', 'digits:4'],
            'role' => ['required', 'string', 'in:owner,manager,storekeeper,cashier'],
        ]);

        $actor = $request->user();
        $newUser = User::create([
            'store_id' => $actor->store_id,
            'branch_id' => $actor->branch_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'pin_hash' => ! empty($validated['pin']) ? Hash::make($validated['pin']) : null,
            'is_active' => true,
        ]);

        $newUser->syncRoles([$validated['role']]);

        return redirect()->route('settings.index')->with('success', 'تم إنشاء حساب الموظف وتعيين صلاحياته.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        if ((int) $user->store_id !== (int) $request->user()->store_id) {
            abort(403);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:owner,manager,storekeeper,cashier'],
            'pin' => ['nullable', 'digits:4'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updates = [
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
        if (! empty($validated['pin'])) {
            $updates['pin_hash'] = Hash::make($validated['pin']);
        }

        $user->update($updates);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('settings.index')->with('success', 'تم تحديث صلاحيات الموظف.');
    }

    public function storeTerminal(Request $request): RedirectResponse
    {
        $store = $request->user()->store;
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:191'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $branchId = $validated['branch_id']
            ?? $request->user()->branch_id
            ?? Branch::forStore($store->id)->firstOrFail()->id;

        Terminal::forStore($store->id)->create([
            'store_id' => $store->id,
            'branch_id' => $branchId,
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'last_invoice_seq' => 0,
            'is_active' => true,
        ]);

        return redirect()->route('settings.index')->with('success', 'تم تسجيل جهاز الكاشير الجديد.');
    }

    public function revokeTerminal(Request $request, Terminal $terminal): RedirectResponse
    {
        if ((int) $terminal->store_id !== (int) $request->user()->store_id) {
            abort(403);
        }

        if ($terminal->sanctum_token_id) {
            PersonalAccessToken::query()->where('id', $terminal->sanctum_token_id)->delete();
        }

        PersonalAccessToken::query()
            ->where('name', "terminal:{$terminal->code}")
            ->whereIn('tokenable_id', User::forStore($terminal->store_id)->pluck('id'))
            ->delete();

        $terminal->update([
            'is_active' => false,
            'sanctum_token_id' => null,
        ]);

        return redirect()->route('settings.index')->with('success', "تم إيقاف الجهاز ({$terminal->code}) وإبطال جلسة المزامنة فوراً.");
    }
}
