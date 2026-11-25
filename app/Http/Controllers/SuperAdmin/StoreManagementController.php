<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreSettingsService;
use App\Support\WhatsAppLink;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class StoreManagementController extends Controller
{
    public function __construct(
        protected StoreSettingsService $settingsService,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $businessType = trim((string) $request->query('business_type', ''));
        $subscriptionStatus = trim((string) $request->query('subscription_status', ''));

        $baseStores = Store::withoutGlobalScopes()->whereNull('deleted_at');

        $kpis = [
            'total_stores' => (clone $baseStores)->count(),
            'active_stores' => (clone $baseStores)
                ->where('subscription_status', 'active')
                ->where(fn ($q) => $q->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '>', now()))
                ->count(),
            'trial_stores' => (clone $baseStores)
                ->where('subscription_status', 'trial')
                ->where(fn ($q) => $q->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '>', now()))
                ->count(),
            'suspended_stores' => (clone $baseStores)
                ->where(function ($q): void {
                    $q->where('subscription_status', 'suspended')
                        ->orWhere(function ($sub): void {
                            $sub->whereNotNull('subscription_ends_at')
                                ->where('subscription_ends_at', '<=', now());
                        });
                })
                ->count(),
            'today_orders' => Order::withoutGlobalScopes()
                ->where('type', 'sale')
                ->whereDate('sold_at', today())
                ->count(),
        ];

        $stores = Store::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->withCount([
                'terminals as terminals_count' => fn ($q) => $q->withoutGlobalScopes(),
                'orders as orders_count' => fn ($q) => $q->withoutGlobalScopes()->where('type', 'sale'),
            ])
            ->with([
                'users' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->with('roles'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereExists(function ($uQuery) use ($search): void {
                            $uQuery->select(DB::raw(1))
                                ->from('users')
                                ->whereColumn('users.store_id', 'stores.id')
                                ->whereNull('users.deleted_at')
                                ->where(function ($inner) use ($search): void {
                                    $inner->where('users.name', 'like', "%{$search}%")
                                        ->orWhere('users.email', 'like', "%{$search}%")
                                        ->orWhere('users.phone', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when(
                in_array($businessType, ['grocery', 'clothing', 'perfumes', 'general'], true),
                fn ($query) => $query->where('business_type', $businessType)
            )
            ->when($subscriptionStatus !== '', function ($query) use ($subscriptionStatus): void {
                if ($subscriptionStatus === 'expired') {
                    $query->whereNotNull('subscription_ends_at')
                        ->where('subscription_ends_at', '<=', now());
                } elseif (in_array($subscriptionStatus, ['active', 'trial', 'suspended'], true)) {
                    $query->where('subscription_status', $subscriptionStatus);
                }
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.index', compact(
            'kpis',
            'stores',
            'search',
            'businessType',
            'subscriptionStatus'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:191'],
            'business_type' => ['required', 'string', 'in:grocery,clothing,perfumes,general'],
            'currency_code' => ['nullable', 'string', 'in:YER,SAR,USD'],
            'currency_symbol' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['nullable', 'integer', 'in:0,2'],
            'subscription_status' => ['required', 'string', 'in:active,trial'],
            'subscription_ends_at' => ['nullable', 'date'],
            'owner_name' => ['required', 'string', 'max:191'],
            'owner_email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'owner_password' => ['required', 'string', 'min:8'],
            'owner_pin' => ['nullable', 'digits:4'],
        ]);

        $currencyCode = $validated['currency_code'] ?? 'YER';
        $defaultSymbols = [
            'YER' => 'ر.ي',
            'SAR' => 'ر.س',
            'USD' => '$',
        ];
        $currencySymbol = trim((string) ($validated['currency_symbol'] ?? '')) ?: ($defaultSymbols[$currencyCode] ?? 'ر.ي');
        $decimalPlaces = array_key_exists('decimal_places', $validated) && $validated['decimal_places'] !== null
            ? (int) $validated['decimal_places']
            : ($currencyCode === 'YER' ? 0 : 2);
        $ownerPin = $validated['owner_pin'] ?? '1234';
        $endsAt = ! empty($validated['subscription_ends_at'])
            ? Carbon::parse($validated['subscription_ends_at'])->endOfDay()
            : null;

        $featurePresets = [
            'grocery' => [
                'variants' => false,
                'multi_units' => true,
                'scale_plu' => true,
                'expiry' => true,
                'quick_pick' => true,
                'exchange_rate' => false,
                'tax' => false,
            ],
            'clothing' => [
                'variants' => true,
                'multi_units' => false,
                'scale_plu' => false,
                'expiry' => false,
                'quick_pick' => false,
                'exchange_rate' => true,
                'tax' => false,
            ],
            'perfumes' => [
                'variants' => true,
                'multi_units' => false,
                'scale_plu' => false,
                'expiry' => false,
                'quick_pick' => true,
                'exchange_rate' => true,
                'tax' => false,
            ],
            'general' => [
                'variants' => false,
                'multi_units' => true,
                'scale_plu' => false,
                'expiry' => false,
                'quick_pick' => true,
                'exchange_rate' => false,
                'tax' => false,
            ],
        ];

        [$newStore, $owner] = DB::transaction(function () use (
            $validated,
            $currencyCode,
            $currencySymbol,
            $decimalPlaces,
            $ownerPin,
            $endsAt,
            $featurePresets,
            $request
        ): array {
            if (Role::query()->where('name', 'owner')->doesntExist()) {
                app(RolesAndPermissionsSeeder::class)->run();
            }

            $baseSlug = Str::slug($validated['store_name']) ?: 'store';
            $slug = $baseSlug . '-' . Str::lower(Str::random(6));
            while (Store::withoutGlobalScopes()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . Str::lower(Str::random(6));
            }

            $store = Store::withoutGlobalScopes()->create([
                'name' => $validated['store_name'],
                'slug' => $slug,
                'phone' => $validated['owner_phone'] ?? null,
                'business_type' => $validated['business_type'],
                'currency_code' => $currencyCode,
                'currency_symbol' => $currencySymbol,
                'decimal_places' => $decimalPlaces,
                'feature_flags' => $featurePresets[$validated['business_type']],
                'receipt_settings' => [
                    'paper_width' => '80mm',
                    'header_note' => "مرحباً بكم في {$validated['store_name']}",
                    'footer_note' => 'شكراً لتسوقكم معنا',
                    'show_qr' => true,
                ],
                'subscription_status' => $validated['subscription_status'],
                'subscription_ends_at' => $endsAt,
                'onboarding_completed_at' => null,
            ]);

            $owner = User::withoutGlobalScopes()->create([
                'store_id' => $store->id,
                'branch_id' => null,
                'name' => $validated['owner_name'],
                'email' => $validated['owner_email'],
                'phone' => $validated['owner_phone'] ?? null,
                'password' => Hash::make($validated['owner_password']),
                'pin_hash' => Hash::make($ownerPin),
                'is_active' => true,
            ]);

            $owner->assignRole('owner');

            activity()
                ->causedBy($request->user())
                ->performedOn($store)
                ->withProperties([
                    'store_id' => $store->id,
                    'business_type' => $store->business_type,
                    'subscription_status' => $store->subscription_status,
                    'subscription_ends_at' => $store->subscription_ends_at?->toIso8601String(),
                    'owner_id' => $owner->id,
                    'owner_email' => $owner->email,
                ])
                ->event('super_admin_store_created')
                ->log("إنشاء متجر جديد: {$store->name}");

            return [$store, $owner];
        });

        $loginUrl = route('login');
        $copyMessage = implode("\n", [
            "🎉 مرحباً بكم في نظام Yemen POS — تم تفعيل متجركم: {$newStore->name}",
            "رابط الدخول: {$loginUrl}",
            "البريد الإلكتروني: {$owner->email}",
            "كلمة المرور: {$validated['owner_password']}",
            "رمز PIN للمالك: {$ownerPin}",
            'عند أول تسجيل دخول ستظهر لك شاشة التهيئة السريعة خلال 60 ثانية.',
        ]);

        $credentialsCard = [
            'store_name' => $newStore->name,
            'owner_name' => $owner->name,
            'login_url' => $loginUrl,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'password' => $validated['owner_password'],
            'pin' => $ownerPin,
            'copy_text' => $copyMessage,
            'whatsapp_url' => WhatsAppLink::build($owner->phone, $copyMessage),
        ];

        return redirect()
            ->route('super-admin.index')
            ->with('success', "تم إنشاء متجر «{$newStore->name}» وحساب المالك بنجاح.")
            ->with('created_credentials', $credentialsCard);
    }

    public function toggleStatus(Request $request, int $store): RedirectResponse
    {
        $storeModel = Store::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($store);

        $previousStatus = $storeModel->subscription_status;
        $newStatus = $previousStatus === 'suspended' ? 'active' : 'suspended';

        $storeModel->update([
            'subscription_status' => $newStatus,
        ]);

        $this->settingsService->clearCache($storeModel->id);

        activity()
            ->causedBy($request->user())
            ->performedOn($storeModel)
            ->withProperties([
                'store_id' => $storeModel->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ])
            ->event('super_admin_store_status_toggled')
            ->log("تغيير حالة اشتراك المتجر ({$storeModel->name}) إلى {$newStatus}");

        $statusLabel = $newStatus === 'active' ? 'تفعيل' : 'إيقاف';

        return redirect()
            ->route('super-admin.index')
            ->with('success', "تم {$statusLabel} المتجر «{$storeModel->name}» بنجاح.");
    }

    public function extendSubscription(Request $request, int $store): RedirectResponse
    {
        $storeModel = Store::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($store);

        $validated = $request->validate([
            'preset' => ['nullable', 'string', 'in:+1 month,+3 months,+1 year,1_month,3_months,1_year'],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        if (empty($validated['preset']) && empty($validated['subscription_ends_at'])) {
            throw ValidationException::withMessages([
                'subscription_ends_at' => 'يرجى اختيار مدة التمديد السريعة أو تحديد تاريخ انتهاء جديد.',
            ]);
        }

        if (! empty($validated['subscription_ends_at'])) {
            $newEndsAt = Carbon::parse($validated['subscription_ends_at'])->endOfDay();
        } else {
            $base = ($storeModel->subscription_ends_at && $storeModel->subscription_ends_at->isFuture())
                ? $storeModel->subscription_ends_at->copy()
                : now();

            $newEndsAt = match ($validated['preset']) {
                '+1 month', '1_month' => $base->addMonth()->endOfDay(),
                '+3 months', '3_months' => $base->addMonths(3)->endOfDay(),
                '+1 year', '1_year' => $base->addYear()->endOfDay(),
            };
        }

        $storeModel->update([
            'subscription_status' => 'active',
            'subscription_ends_at' => $newEndsAt,
        ]);

        $this->settingsService->clearCache($storeModel->id);

        activity()
            ->causedBy($request->user())
            ->performedOn($storeModel)
            ->withProperties([
                'store_id' => $storeModel->id,
                'preset' => $validated['preset'] ?? null,
                'subscription_ends_at' => $newEndsAt->toIso8601String(),
            ])
            ->event('super_admin_subscription_extended')
            ->log("تمديد اشتراك المتجر ({$storeModel->name}) حتى {$newEndsAt->toDateString()}");

        return redirect()
            ->route('super-admin.index')
            ->with('success', "تم تمديد وتفعيل اشتراك المتجر «{$storeModel->name}» حتى {$newEndsAt->format('Y-m-d')}.");
    }

    public function resetOwnerPassword(Request $request, int $store): RedirectResponse
    {
        $storeModel = Store::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($store);

        if (! $request->filled('password') && $request->filled('new_password')) {
            $request->merge(['password' => $request->input('new_password')]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $storeUsers = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('store_id', $storeModel->id)
            ->with('roles')
            ->get();

        $owner = $storeUsers->first(fn (User $u) => $u->hasRole('owner')) ?? $storeUsers->first();

        if (! $owner) {
            throw ValidationException::withMessages([
                'password' => 'لا يوجد حساب مالك مرتبط بهذا المتجر.',
            ]);
        }

        $owner->update([
            'password' => Hash::make($validated['password']),
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($storeModel)
            ->withProperties([
                'store_id' => $storeModel->id,
                'owner_id' => $owner->id,
                'owner_email' => $owner->email,
            ])
            ->event('super_admin_owner_password_reset')
            ->log("إعادة تعيين كلمة مرور مالك المتجر ({$storeModel->name})");

        $loginUrl = route('login');
        $copyMessage = implode("\n", [
            "🔐 تحديث كلمة المرور — متجر {$storeModel->name}",
            "رابط الدخول: {$loginUrl}",
            "البريد الإلكتروني: {$owner->email}",
            "كلمة المرور الجديدة: {$validated['password']}",
        ]);

        return redirect()
            ->route('super-admin.index')
            ->with('success', "تم تحديث كلمة مرور المالك ({$owner->email}) بنجاح.")
            ->with('created_credentials', [
                'store_name' => $storeModel->name,
                'owner_name' => $owner->name,
                'login_url' => $loginUrl,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'password' => $validated['password'],
                'pin' => null,
                'copy_text' => $copyMessage,
                'whatsapp_url' => WhatsAppLink::build($owner->phone, $copyMessage),
            ]);
    }
}
