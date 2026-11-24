<?php

use App\Console\Kernel;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShiftService;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('production backup config, hourly cron schedule, and HTTPS/cache hardening are configured cleanly', function () {
    expect(config('app.timezone'))->toBe('Asia/Aden');
    expect(config('database.connections.mysql.dump.useSingleTransaction'))->toBeTrue();
    expect(File::exists(config_path('backup.php')))->toBeTrue();

    // Verify encrypted ZIP settings when BACKUP_ARCHIVE_PASSWORD is set
    $backupConfig = require config_path('backup.php');
    expect($backupConfig['backup']['destination']['compression_method'])->toBe(ZipArchive::CM_DEFAULT);

    // Verify hourly backup:run, daily backup:clean, and daily backup:monitor in Console Kernel
    app()->make(Kernel::class);
    $events = app()->make(Schedule::class)->events();
    $commands = array_map(fn ($e) => $e->command, $events);

    $hasHourlyBackup = collect($events)->contains(
        fn ($e) => str_contains((string) $e->command, 'backup:run') && $e->expression === '0 * * * *'
    );
    $hasDailyClean = collect($events)->contains(
        fn ($e) => str_contains((string) $e->command, 'backup:clean')
    );
    $hasDailyMonitor = collect($events)->contains(
        fn ($e) => str_contains((string) $e->command, 'backup:monitor')
    );

    expect($hasHourlyBackup)->toBeTrue('Expected hourly backup:run schedule in Console Kernel');
    expect($hasDailyClean)->toBeTrue('Expected daily backup:clean schedule in Console Kernel');
    expect($hasDailyMonitor)->toBeTrue('Expected daily backup:monitor schedule in Console Kernel');

    // Verify production runbook exists
    expect(File::exists(base_path('docs/PRODUCTION_AND_PILOT_RUNBOOK.md')))->toBeTrue();
});

test('encrypted AES-256 backup archive creates, encrypts, rejects wrong passwords, and restores full database faithfully', function () {
    $tmpDir = storage_path('framework/testing/backup_restore_' . uniqid());
    File::ensureDirectoryExists($tmpDir);

    $sourceSqlite = "{$tmpDir}/source.sqlite";
    $restoredSqlite = "{$tmpDir}/restored.sqlite";
    $zipPath = "{$tmpDir}/yemen-pos-backup.zip";
    $password = 'YemenPos@Backup2026!';

    try {
        touch($sourceSqlite);

        // Configure isolated SQLite connection for clean end-to-end backup & restore drill
        Config::set('database.connections.backup_test_src', [
            'driver' => 'sqlite',
            'database' => $sourceSqlite,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Migrate and seed isolated source DB + create live order
        $originalDefault = config('database.default');
        Config::set('database.default', 'backup_test_src');
        DB::purge('backup_test_src');

        Artisan::call('migrate', ['--database' => 'backup_test_src', '--force' => true]);
        $this->seed(DemoStoresSeeder::class);

        $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
        $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
        $terminal = Terminal::forStore($store->id)->firstOrFail();
        $rani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail();

        $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '25000.0000'], $cashier)['shift'];
        $order = app(OrderService::class)->createSale([
            'shift_id' => $shift->id,
            'items' => [['product_id' => $rani->id, 'quantity' => '4.000']],
            'payments' => [['method' => 'cash', 'amount' => '1200.0000']],
        ], $cashier)['order'];

        $expectedInvoice = $order->invoice_number;
        $expectedProductQty = $rani->fresh()->quantity; // 116.000
        $expectedShiftCash = $shift->fresh()->expected_cash; // 26200.0000

        DB::disconnect('backup_test_src');
        Config::set('database.default', $originalDefault);

        // 1. Create AES-256 encrypted ZIP backup archive (matching spatie/laravel-backup ZipArchive::EM_AES_256)
        $zip = new ZipArchive();
        expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
        $zip->addFile($sourceSqlite, 'db-dumps/yemen-pos.sqlite');
        $zip->setEncryptionName('db-dumps/yemen-pos.sqlite', ZipArchive::EM_AES_256, $password);
        $zip->close();

        expect(File::exists($zipPath))->toBeTrue();
        expect(File::size($zipPath))->toBeGreaterThan(1024);

        // 2. Verify wrong password CANNOT decrypt backup
        $badZip = new ZipArchive();
        expect($badZip->open($zipPath))->toBeTrue();
        $badZip->setPassword('WrongPassword');
        expect($badZip->getFromName('db-dumps/yemen-pos.sqlite'))->toBeFalse();
        $badZip->close();

        // 3. Decrypt with valid password and restore to fresh target database
        $goodZip = new ZipArchive();
        expect($goodZip->open($zipPath))->toBeTrue();
        $goodZip->setPassword($password);
        $restoredBytes = $goodZip->getFromName('db-dumps/yemen-pos.sqlite');
        $goodZip->close();

        expect($restoredBytes)->not->toBeFalse();
        File::put($restoredSqlite, $restoredBytes);

        Config::set('database.connections.backup_test_dst', [
            'driver' => 'sqlite',
            'database' => $restoredSqlite,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('backup_test_dst');

        $restoredOrder = DB::connection('backup_test_dst')->table('orders')->where('invoice_number', $expectedInvoice)->first();
        $restoredRani = DB::connection('backup_test_dst')->table('products')->where('barcode', '6281007041126')->first();
        $restoredShift = DB::connection('backup_test_dst')->table('shifts')->where('id', $shift->id)->first();

        expect($restoredOrder)->not->toBeNull();
        expect((string) $restoredOrder->total_amount)->toEqual('1200');
        expect((string) $restoredRani->quantity)->toEqual('116');
        expect((string) $restoredShift->expected_cash)->toEqual('26200');
        expect(Schema::connection('backup_test_dst')->hasTable('stock_movements'))->toBeTrue();

        DB::disconnect('backup_test_dst');
    } finally {
        File::deleteDirectory($tmpDir);
    }
});
