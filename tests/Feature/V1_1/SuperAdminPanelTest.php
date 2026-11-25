<?php

use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('strict RBAC guard blocks store owner and cashier from /super-admin routes with 403', function () {
    $this->seed(DemoStoresSeeder::class);

    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();

    // Unauthenticated redirects to /login
    $this->get('/super-admin')->assertRedirect('/login');

    // Store owner receives 403 on GET and POST
    $this->actingAs($owner)->get('/super-admin')->assertForbidden();
    $this->actingAs($owner)->post('/super-admin/stores', [
        'store_name' => 'اختراق متجر',
        'business_type' => 'grocery',
        'subscription_status' => 'active',
        'owner_name' => 'مهاجم',
        'owner_email' => 'hacker@test.ye',
        'owner_password' => 'Password123!',
    ])->assertForbidden();

    // Cashier receives 403 on GET and POST
    $this->actingAs($cashier)->get('/super-admin')->assertForbidden();
    $this->actingAs($cashier)->post('/super-admin/stores', [])->assertForbidden();
});

test('super_admin user with null store_id logs in cleanly, redirects to /super-admin, and views all tenant stores', function () {
    $this->seed(DemoStoresSeeder::class);

    // Verify Artisan CLI command creates super_admin with store_id = null
    $exitCode = Artisan::call('yemen-pos:create-super-admin', [
        '--name' => 'مدير المنصة',
        '--email' => 'root@yemenpos.ye',
        '--password' => 'SuperSecret@2026!',
    ]);
    expect($exitCode)->toBe(0);

    $superAdmin = User::withoutGlobalScopes()->where('email', 'root@yemenpos.ye')->firstOrFail();
    expect($superAdmin->store_id)->toBeNull();
    expect($superAdmin->hasRole('super_admin'))->toBeTrue();

    // Login via web POST /login -> redirects straight to /super-admin
    $this->post('/login', [
        'login' => 'root@yemenpos.ye',
        'password' => 'SuperSecret@2026!',
    ])->assertRedirect('/super-admin');

    $html = $this->actingAs($superAdmin)->get('/super-admin')->assertOk()->getContent();

    expect($html)
        ->toContain('dir="rtl"')
        ->not->toContain('fonts.googleapis.com')
        ->toContain('بقالة الخير')
        ->toContain('أناقة صنعاء للملابس والأحذية')
        ->toContain('بقالة')
        ->toContain('ملابس');
});

test('POST /super-admin/stores creates grocery store and owner atomically, flashes credentials card, and sends new owner to /onboarding', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = User::create([
        'store_id' => null,
        'name' => 'مدير المنصة',
        'email' => 'admin@platform.ye',
        'password' => Hash::make('AdminPass@2026!'),
        'is_active' => true,
    ]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->post('/super-admin/stores', [
        'store_name' => 'سوبرماركت الروضة الحديث',
        'business_type' => 'grocery',
        'currency_code' => 'YER',
        'currency_symbol' => 'ر.ي',
        'decimal_places' => 0,
        'subscription_status' => 'trial',
        'subscription_ends_at' => now()->addDays(14)->format('Y-m-d'),
        'owner_name' => 'عبدالله الهمداني',
        'owner_email' => 'owner@alrawdah.ye',
        'owner_phone' => '771122334',
        'owner_password' => 'Rawdah@2026!',
        'owner_pin' => '9876',
    ]);

    $response->assertRedirect('/super-admin')
        ->assertSessionHas('created_credentials');

    $createdStore = Store::withoutGlobalScopes()->where('name', 'سوبرماركت الروضة الحديث')->firstOrFail();
    expect($createdStore->business_type)->toBe('grocery');
    expect($createdStore->subscription_status)->toBe('trial');
    expect($createdStore->onboarding_completed_at)->toBeNull();

    $createdOwner = User::withoutGlobalScopes()->where('email', 'owner@alrawdah.ye')->firstOrFail();
    expect($createdOwner->store_id)->toBe($createdStore->id);
    expect($createdOwner->hasRole('owner'))->toBeTrue();
    expect(Hash::check('9876', $createdOwner->pin_hash))->toBeTrue();
    expect(Activity::where('event', 'super_admin_store_created')->exists())->toBeTrue();

    // Verify credentials card renders with wa.me link on /super-admin
    $followHtml = $this->actingAs($superAdmin)->get('/super-admin')->assertOk()->getContent();
    expect($followHtml)->toContain('https://wa.me/967771122334');

    // Logging in as the newly created merchant owner redirects to /onboarding
    Auth::forgetGuards();
    $this->post('/login', [
        'login' => 'owner@alrawdah.ye',
        'password' => 'Rawdah@2026!',
    ])->assertRedirect('/onboarding');
});

test('suspending a store or expiring subscription_ends_at immediately blocks web login, /dashboard, and /api/v1/sync/bootstrap', function () {
    $this->seed(DemoStoresSeeder::class);

    $superAdmin = User::create([
        'store_id' => null,
        'name' => 'مدير المنصة',
        'email' => 'sa@yemenpos.ye',
        'password' => Hash::make('SuperAdmin@2026!'),
        'is_active' => true,
    ]);
    $superAdmin->assignRole('super_admin');

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();

    // 1. Toggle store status -> suspended
    $this->actingAs($superAdmin)
        ->post("/super-admin/stores/{$store->id}/toggle-status")
        ->assertRedirect('/super-admin');

    expect($store->fresh()->subscription_status)->toBe('suspended');

    // Web /dashboard blocked with 403 + clear Arabic message
    $this->actingAs($owner->fresh())
        ->get('/dashboard')
        ->assertForbidden()
        ->assertSee(Store::SUBSCRIPTION_BLOCKED_MESSAGE);

    // Web login blocked with exact Arabic message
    Auth::forgetGuards();
    $this->post('/login', [
        'login' => 'owner@alkhair.ye',
        'password' => 'YemenPos@Demo2026!',
    ])->assertSessionHasErrors([
        'login' => Store::SUBSCRIPTION_BLOCKED_MESSAGE,
    ]);

    // Sync API /api/v1/sync/bootstrap blocked with 403 + exact Arabic message
    Sanctum::actingAs($cashier->fresh());
    $this->getJson('/api/v1/sync/bootstrap')
        ->assertForbidden()
        ->assertJson([
            'message' => Store::SUBSCRIPTION_BLOCKED_MESSAGE,
        ]);

    // 2. Reactivate status, then test automatic expiry when subscription_ends_at is in the past
    Auth::forgetGuards();
    $this->actingAs($superAdmin)
        ->post("/super-admin/stores/{$store->id}/toggle-status")
        ->assertRedirect('/super-admin');

    expect($store->fresh()->subscription_status)->toBe('active');
    $this->actingAs($owner->fresh())->get('/dashboard')->assertOk();

    // Set subscription_ends_at in the past -> automatically blocked even when status === 'active'
    $store->update(['subscription_ends_at' => now()->subDay()]);

    $this->actingAs($owner->fresh())->get('/dashboard')->assertForbidden();

    Sanctum::actingAs($cashier->fresh());
    $this->getJson('/api/v1/sync/bootstrap')
        ->assertForbidden()
        ->assertJson([
            'message' => Store::SUBSCRIPTION_BLOCKED_MESSAGE,
        ]);
});

test('extending subscription reactivates expired/suspended store and resetting owner password allows login with new password', function () {
    $this->seed(DemoStoresSeeder::class);

    $superAdmin = User::create([
        'store_id' => null,
        'name' => 'مدير المنصة',
        'email' => 'sa2@yemenpos.ye',
        'password' => Hash::make('SuperAdmin@2026!'),
        'is_active' => true,
    ]);
    $superAdmin->assignRole('super_admin');

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();

    // Expire and suspend the store first
    $store->update([
        'subscription_status' => 'suspended',
        'subscription_ends_at' => now()->subDays(5),
    ]);
    $this->actingAs($owner->fresh())->get('/dashboard')->assertForbidden();

    // 1. Extend subscription via '+3 months' preset -> reactivates store & sets future date
    $this->actingAs($superAdmin)
        ->post("/super-admin/stores/{$store->id}/extend-subscription", [
            'preset' => '+3 months',
        ])
        ->assertRedirect('/super-admin');

    $freshStore = $store->fresh();
    expect($freshStore->subscription_status)->toBe('active');
    expect($freshStore->subscription_ends_at->isFuture())->toBeTrue();
    expect(Activity::where('event', 'super_admin_subscription_extended')->exists())->toBeTrue();

    // Owner can access /dashboard again immediately
    $this->actingAs($owner->fresh())->get('/dashboard')->assertOk();

    // 2. Reset owner password via Super Admin panel
    $this->actingAs($superAdmin)
        ->post("/super-admin/stores/{$store->id}/reset-owner-password", [
            'password' => 'NewOwnerPass@2026!',
        ])
        ->assertRedirect('/super-admin');

    expect(Activity::where('event', 'super_admin_owner_password_reset')->exists())->toBeTrue();

    // Old password fails, new password succeeds and redirects to /dashboard
    Auth::forgetGuards();
    $this->post('/login', [
        'login' => 'owner@alkhair.ye',
        'password' => 'YemenPos@Demo2026!',
    ])->assertSessionHasErrors('login');

    $this->post('/login', [
        'login' => 'owner@alkhair.ye',
        'password' => 'NewOwnerPass@2026!',
    ])->assertRedirect('/dashboard');
});
