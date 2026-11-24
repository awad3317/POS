<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Terminal;
use App\Services\StoreSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(
        protected StoreSettingsService $settingsService,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $store = $request->user()->store;
        if ($store->onboarding_completed_at !== null) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.wizard', compact('store'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'in:YER,SAR,USD'],
            'decimal_places' => ['required', 'integer', 'in:0,2'],
            'business_type' => ['required', 'string', 'in:grocery,clothing,perfumes,general'],
            'paper_width' => ['required', 'string', 'in:80mm,58mm'],
            'header_note' => ['nullable', 'string', 'max:255'],
            'footer_note' => ['nullable', 'string', 'max:255'],
            'whatsapp_template' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        $store = $user->store;

        DB::transaction(function () use ($validated, $request, $store, $user): void {
            $currencySymbols = [
                'YER' => 'ر.ي',
                'SAR' => 'ر.س',
                'USD' => '$',
            ];

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

            $defaultCategories = [
                'grocery' => ['مواد غذائية أساسية', 'مشروبات وألبان', 'أجبان وموزونات', 'منظفات وبلاستيكات', 'مخبوزات سريعة'],
                'clothing' => ['ملابس رجالية', 'ملابس نسائية', 'ملابس أطفال', 'أحذية وحقائب'],
                'perfumes' => ['عطور شرقية وعود', 'عطور فرنسية', 'مستحضرات تجميل', 'هدايا وإكسسوارات'],
                'general' => ['أصناف عامة', 'مبيعات سريعة', 'عروض خاصة'],
            ];

            $logoPath = $store->logo_path;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('logos', 'public');
            }

            $businessType = $validated['business_type'];

            $store->update([
                'name' => $validated['name'],
                'logo_path' => $logoPath,
                'phone' => $validated['phone'] ?? $store->phone,
                'address' => $validated['address'] ?? $store->address,
                'business_type' => $businessType,
                'currency_code' => $validated['currency_code'],
                'currency_symbol' => $currencySymbols[$validated['currency_code']] ?? 'ر.ي',
                'decimal_places' => (int) $validated['decimal_places'],
                'feature_flags' => $featurePresets[$businessType],
                'receipt_settings' => [
                    'paper_width' => $validated['paper_width'],
                    'header_note' => $validated['header_note'] ?? "مرحباً بكم في {$validated['name']}",
                    'footer_note' => $validated['footer_note'] ?? 'شكراً لزيارتكم وثقتكم بنا',
                    'whatsapp_template' => $validated['whatsapp_template'] ?? 'فاتورتكم من {store_name} رقم {invoice_number} بمبلغ {total}',
                    'show_qr' => true,
                ],
                'onboarding_completed_at' => now(),
            ]);

            foreach ($defaultCategories[$businessType] as $index => $catName) {
                Category::forStore($store->id)->firstOrCreate(
                    ['store_id' => $store->id, 'name' => $catName],
                    ['sort_order' => $index + 1, 'is_active' => true]
                );
            }

            $branch = Branch::forStore($store->id)->where('is_default', true)->first()
                ?? Branch::forStore($store->id)->first();

            if (! $branch) {
                $branch = Branch::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'name' => 'الفرع الرئيسي',
                    'phone' => $store->phone,
                    'address' => $store->address,
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            if (! $user->branch_id) {
                $user->update(['branch_id' => $branch->id]);
            }

            Terminal::forStore($store->id)->firstOrCreate(
                ['store_id' => $store->id, 'code' => 'T01'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'كاشير المحل الرئيسي (T01)',
                    'last_invoice_seq' => 0,
                    'is_active' => true,
                ]
            );

            $this->settingsService->clearCache($store->id);
        });

        return redirect()->route('dashboard')->with('success', 'تم تهيئة متجرك بنجاح خلال ثوانٍ! يمكنك البدء بالبيع الآن.');
    }
}
