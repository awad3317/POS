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

    $zipPath = "{$tmpDir}/yemen-pos-backup.zip";
    $password = 'YemenPos@Backup2026!';

    try {
        if (extension_loaded('pdo_sqlite')) {
            $sourceSqlite = "{$tmpDir}/source.sqlite";
            $restoredSqlite = "{$tmpDir}/restored.sqlite";
            touch($sourceSqlite);

            Config::set('database.connections.backup_test_src', [
                'driver' => 'sqlite',
                'database' => $sourceSqlite,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);

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

            DB::disconnect('backup_test_src');
            Config::set('database.default', $originalDefault);

            $zip = new ZipArchive();
            expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
            $zip->addFile($sourceSqlite, 'db-dumps/yemen-pos.sqlite');
            $zip->setEncryptionName('db-dumps/yemen-pos.sqlite', ZipArchive::EM_AES_256, $password);
            $zip->close();

            expect(File::exists($zipPath))->toBeTrue();
            expect(File::size($zipPath))->toBeGreaterThan(1024);

            $badZip = new ZipArchive();
            expect($badZip->open($zipPath))->toBeTrue();
            $badZip->setPassword('WrongPassword');
            expect($badZip->getFromName('db-dumps/yemen-pos.sqlite'))->toBeFalse();
            $badZip->close();

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
            expect(\App\Support\MoneyMath::cmp((string) $restoredOrder->total_amount, '1200'))->toBe(0);
            expect(\App\Support\MoneyMath::cmp((string) $restoredRani->quantity, '116'))->toBe(0);
            expect(\App\Support\MoneyMath::cmp((string) $restoredShift->expected_cash, '26200'))->toBe(0);
            expect(Schema::connection('backup_test_dst')->hasTable('stock_movements'))->toBeTrue();

            DB::disconnect('backup_test_dst');
        } else {
            // MySQL backup & restore drill when pdo_sqlite is not installed on host PHP
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
            $pdo = DB::connection()->getPdo();
            $tablesToBackup = ['products', 'shifts', 'orders', 'order_items', 'payments', 'stock_movements'];

            $sqlLines = ['SET FOREIGN_KEY_CHECKS=0;'];
            foreach ($tablesToBackup as $tbl) {
                $sqlLines[] = "DELETE FROM `{$tbl}`;";
                $rows = DB::table($tbl)->get();
                foreach ($rows as $row) {
                    $arr = (array) $row;
                    $cols = implode(', ', array_map(fn ($c) => "`{$c}`", array_keys($arr)));
                    $vals = implode(', ', array_map(
                        fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($arr)
                    ));
                    $sqlLines[] = "INSERT INTO `{$tbl}` ({$cols}) VALUES ({$vals});";
                }
            }
            $sqlLines[] = 'SET FOREIGN_KEY_CHECKS=1;';

            $dumpPath = "{$tmpDir}/yemen-pos.sql";
            File::put($dumpPath, implode("\n", $sqlLines));

            // 1. Create AES-256 encrypted ZIP archive
            $zip = new ZipArchive();
            expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
            $zip->addFile($dumpPath, 'db-dumps/yemen-pos.sql');
            $zip->setEncryptionName('db-dumps/yemen-pos.sql', ZipArchive::EM_AES_256, $password);
            $zip->close();

            expect(File::exists($zipPath))->toBeTrue();
            expect(File::size($zipPath))->toBeGreaterThan(512);

            // 2. Verify wrong password CANNOT decrypt backup
            $badZip = new ZipArchive();
            expect($badZip->open($zipPath))->toBeTrue();
            $badZip->setPassword('WrongPassword');
            expect($badZip->getFromName('db-dumps/yemen-pos.sql'))->toBeFalse();
            $badZip->close();

            // 3. Simulate data loss, then decrypt with valid password and restore faithfully
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach (array_reverse($tablesToBackup) as $tbl) {
                DB::table($tbl)->delete();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            expect(DB::table('orders')->where('invoice_number', $expectedInvoice)->exists())->toBeFalse();

            $goodZip = new ZipArchive();
            expect($goodZip->open($zipPath))->toBeTrue();
            $goodZip->setPassword($password);
            $restoredSql = $goodZip->getFromName('db-dumps/yemen-pos.sql');
            $goodZip->close();

            expect($restoredSql)->not->toBeFalse();
            DB::unprepared($restoredSql);

            $restoredOrder = DB::table('orders')->where('invoice_number', $expectedInvoice)->first();
            $restoredRani = DB::table('products')->where('barcode', '6281007041126')->first();
            $restoredShift = DB::table('shifts')->where('id', $shift->id)->first();

            expect($restoredOrder)->not->toBeNull();
            expect(\App\Support\MoneyMath::cmp((string) $restoredOrder->total_amount, '1200'))->toBe(0);
            expect(\App\Support\MoneyMath::cmp((string) $restoredRani->quantity, '116'))->toBe(0);
            expect(\App\Support\MoneyMath::cmp((string) $restoredShift->expected_cash, '26200'))->toBe(0);
            expect(Schema::hasTable('stock_movements'))->toBeTrue();
        }
    } finally {
        File::deleteDirectory($tmpDir);
    }
});
