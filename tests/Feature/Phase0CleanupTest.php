<?php

use App\Services\StoreSettingsService;
use Brick\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('composer.json removes heavy legacy packages and installs lightweight financial & security stack', function () {
    $composer = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    $require = $composer['require'] ?? [];
    $requireDev = $composer['require-dev'] ?? [];
    $scripts = json_encode($composer['scripts'] ?? []);

    // Removed heavy packages
    expect($require)
        ->not->toHaveKey('filament/filament')
        ->not->toHaveKey('livewire/livewire')
        ->not->toHaveKey('pxlrbt/filament-excel')
        ->not->toHaveKey('mpdf/mpdf');

    expect($requireDev)->not->toHaveKey('filament/upgrade');
    expect($scripts)->not->toContain('filament:upgrade');

    // Required lightweight financial & security packages
    expect($require)
        ->toHaveKey('brick/money')
        ->toHaveKey('spatie/laravel-permission')
        ->toHaveKey('spatie/laravel-activitylog')
        ->toHaveKey('spatie/laravel-backup');

    expect($requireDev)
        ->toHaveKey('pestphp/pest')
        ->toHaveKey('pestphp/pest-plugin-laravel');
});

test('package.json removes print-js and includes offline-first PWA & Alpine dependencies', function () {
    $pkg = json_decode(File::get(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);

    $allDeps = array_merge($pkg['dependencies'] ?? [], $pkg['devDependencies'] ?? []);

    expect($allDeps)
        ->not->toHaveKey('print-js')
        ->toHaveKey('tailwindcss')
        ->toHaveKey('@tailwindcss/vite')
        ->toHaveKey('alpinejs')
        ->toHaveKey('@alpinejs/collapse')
        ->toHaveKey('@alpinejs/focus')
        ->toHaveKey('dexie')
        ->toHaveKey('decimal.js')
        ->toHaveKey('jsbarcode')
        ->toHaveKey('qrcode');
});

test('all legacy Filament, Livewire, and mPDF directories and files are completely removed', function () {
    $forbiddenPaths = [
        base_path('app/Filament'),
        base_path('app/Livewire'),
        base_path('app/Providers/Filament'),
        base_path('app/Http/Controllers/InstallController.php'),
        base_path('app/Http/Controllers/UtilityController.php'),
        base_path('resources/views/filament'),
        base_path('resources/views/livewire'),
        base_path('public/js/filament'),
        base_path('public/css/filament'),
        base_path('config/filament.php'),
        base_path('config/livewire.php'),
    ];

    foreach ($forbiddenPaths as $path) {
        expect(File::exists($path))->toBeFalse("Legacy path still exists: {$path}");
    }

    $bladeFiles = collect(File::allFiles(resource_path('views')))
        ->map(fn ($file) => $file->getFilename())
        ->all();

    expect($bladeFiles)->not->toContain('3-invoice.blade.php');

    expect(File::exists(base_path('bootstrap/providers.php')))->toBeTrue();
    expect(File::get(base_path('bootstrap/providers.php')))->not->toContain('AdminPanelProvider');
    expect(File::get(config_path('app.php')))->not->toContain('AdminPanelProvider');
});

test('legacy vulnerable routes /install, /clear, and /print/{id} return 404', function () {
    $this->get('/install')->assertNotFound();
    $this->get('/clear')->assertNotFound();
    $this->get('/print/1')->assertNotFound();
});

test('filesystem, timezone, and seeder security hardening rules are enforced', function () {
    expect(config('filesystems.disks.public_uploads'))->toBeNull();
    expect(config('filesystems.disks.public'))->toBeArray();
    expect(config('filesystems.disks.public.root'))->toBe(storage_path('app/public'));

    if (File::exists(database_path('seeders/UserSeeder.php'))) {
        expect(File::get(database_path('seeders/UserSeeder.php')))
            ->not->toContain('123456789');
    }

    expect(config('app.timezone'))->toBe('Asia/Aden');
    expect(File::get(base_path('.env.example')))
        ->toContain('APP_TIMEZONE=Asia/Aden')
        ->toContain('INITIAL_ADMIN_EMAIL=');
});

test('new financial precision package, cached settings service, and admin CLI command are operational', function () {
    $money = Money::of('12500000.0000', 'YER');

    expect($money->getCurrency()->getCurrencyCode())->toBe('YER');
    expect($money->getAmount()->toScale(2)->__toString())->toBe('12500000.00');

    expect(class_exists(StoreSettingsService::class))->toBeTrue();
    $service = app(StoreSettingsService::class);
    expect($service)->toBeInstanceOf(StoreSettingsService::class);
    expect($service->defaults())->toHaveKey('currency_code', 'YER');

    $commands = Artisan::all();
    expect($commands)->toHaveKey('yemen-pos:create-admin');
});

test('offline Arabic woff2 fonts exist locally and views have zero external Google Fonts or Livewire directives', function () {
    $woff2Fonts = File::glob(public_path('fonts/*.woff2'));
    expect(count($woff2Fonts))->toBeGreaterThanOrEqual(1);

    foreach ($woff2Fonts as $fontFile) {
        expect(File::size($fontFile))->toBeGreaterThan(10000);
    }

    $bladeFiles = File::allFiles(resource_path('views'));
    foreach ($bladeFiles as $file) {
        $contents = File::get($file->getPathname());
        expect($contents)
            ->not->toContain('fonts.googleapis.com')
            ->not->toContain('fonts.gstatic.com')
            ->not->toContain('@livewireStyles')
            ->not->toContain('@livewireScripts')
            ->not->toContain('@filamentStyles')
            ->not->toContain('@filamentScripts');
    }
});
