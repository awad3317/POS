<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalBarcodeCatalog;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\MoneyMath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $search = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category_id');
        $filter = $request->query('filter');

        $products = Product::forStore($store->id)
            ->with(['category', 'brand', 'units', 'variants'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($filter === 'low_stock', fn ($q) => $q->whereColumn('quantity', '<=', 'min_stock_alert'))
            ->when($filter === 'negative', fn ($q) => $q->where('quantity', '<', 0))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $categories = Category::forStore($store->id)->orderBy('sort_order')->get();

        return view('admin.products.index', compact('store', 'products', 'categories', 'search', 'categoryId', 'filter'));
    }

    public function create(Request $request): View
    {
        $store = $request->user()->store;
        $categories = Category::forStore($store->id)->orderBy('sort_order')->get();
        $brands = Brand::forStore($store->id)->orderBy('sort_order')->get();
        $product = new Product([
            'type' => 'simple',
            'purchase_price' => '0.0000',
            'sale_price' => '0.0000',
            'quantity' => '0.000',
            'min_stock_alert' => '5.000',
            'tax_rate' => '0.00',
            'is_active' => true,
        ]);

        return view('admin.products.form', compact('store', 'product', 'categories', 'brands'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $store = $user->store;
        $validated = $this->validateProductRequest($request, $store->id, null);

        DB::transaction(function () use ($validated, $store, $user): void {
            $branch = $this->resolveDefaultBranch($store->id, $user->branch_id);
            $initialQty = MoneyMath::qty((string) ($validated['quantity'] ?? '0'));

            $product = Product::forStore($store->id)->create([
                'store_id' => $store->id,
                'category_id' => $validated['category_id'] ?? null,
                'brand_id' => $validated['brand_id'] ?? null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'sku' => $validated['sku'] ?? null,
                'barcode' => $validated['barcode'] ?? null,
                'scale_plu_code' => $validated['scale_plu_code'] ?? null,
                'purchase_price' => MoneyMath::money((string) $validated['purchase_price']),
                'sale_price' => MoneyMath::money((string) $validated['sale_price']),
                'wholesale_price' => isset($validated['wholesale_price']) && $validated['wholesale_price'] !== '' ? MoneyMath::money((string) $validated['wholesale_price']) : null,
                'cost_currency' => $validated['cost_currency'] ?? null,
                'foreign_cost_price' => isset($validated['foreign_cost_price']) && $validated['foreign_cost_price'] !== '' ? MoneyMath::money((string) $validated['foreign_cost_price']) : null,
                'profit_margin_percent' => $validated['profit_margin_percent'] ?? null,
                'quantity' => '0.000',
                'min_stock_alert' => MoneyMath::qty((string) ($validated['min_stock_alert'] ?? '0')),
                'tax_rate' => $validated['tax_rate'] ?? '0.00',
                'is_quick_pick' => (bool) ($validated['is_quick_pick'] ?? false),
                'has_expiry' => (bool) ($validated['has_expiry'] ?? false),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            if ($validated['type'] === 'variant' && ! empty($validated['variants'])) {
                foreach ($validated['variants'] as $vData) {
                    $vQty = MoneyMath::qty((string) ($vData['quantity'] ?? '0'));
                    $variant = ProductVariant::forStore($store->id)->create([
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                        'size' => $vData['size'] ?? null,
                        'color' => $vData['color'] ?? null,
                        'attribute_label' => $vData['attribute_label'] ?? trim(($vData['size'] ?? '') . ' - ' . ($vData['color'] ?? ''), ' -'),
                        'barcode' => $vData['barcode'] ?? null,
                        'purchase_price' => MoneyMath::money((string) ($vData['purchase_price'] ?? $product->purchase_price)),
                        'sale_price' => MoneyMath::money((string) ($vData['sale_price'] ?? $product->sale_price)),
                        'quantity' => '0.000',
                        'is_active' => true,
                    ]);

                    if (MoneyMath::isPositive($vQty)) {
                        $this->inventoryService->applyMovement(
                            $store,
                            $branch,
                            $product,
                            $variant,
                            $user,
                            'adjustment_in',
                            $vQty,
                            $variant->purchase_price,
                            Product::class,
                            $product->id,
                            'رصيد افتتاحي للمتغير'
                        );
                    }
                }
            } elseif (MoneyMath::isPositive($initialQty)) {
                $this->inventoryService->applyMovement(
                    $store,
                    $branch,
                    $product,
                    null,
                    $user,
                    'adjustment_in',
                    $initialQty,
                    $product->purchase_price,
                    Product::class,
                    $product->id,
                    'رصيد افتتاحي للصنف'
                );
            }

            if (! empty($validated['units'])) {
                foreach ($validated['units'] as $uData) {
                    if (empty($uData['unit_name'])) {
                        continue;
                    }
                    ProductUnit::forStore($store->id)->create([
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                        'unit_name' => $uData['unit_name'],
                        'conversion_factor' => MoneyMath::qty((string) ($uData['conversion_factor'] ?? '1')),
                        'barcode' => $uData['barcode'] ?? null,
                        'purchase_price' => MoneyMath::money((string) ($uData['purchase_price'] ?? '0')),
                        'sale_price' => MoneyMath::money((string) ($uData['sale_price'] ?? $product->sale_price)),
                        'is_default' => (bool) ($uData['is_default'] ?? false),
                    ]);
                }
            }
        });

        return redirect()->route('products.index')->with('success', 'تمت إضافة المنتج بنجاح.');
    }

    public function edit(Request $request, Product $product): View
    {
        $store = $request->user()->store;
        $product->load(['units', 'variants']);
        $categories = Category::forStore($store->id)->orderBy('sort_order')->get();
        $brands = Brand::forStore($store->id)->orderBy('sort_order')->get();

        return view('admin.products.form', compact('store', 'product', 'categories', 'brands'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $store = $request->user()->store;
        $validated = $this->validateProductRequest($request, $store->id, $product->id);

        DB::transaction(function () use ($validated, $product, $store): void {
            $product->update([
                'category_id' => $validated['category_id'] ?? null,
                'brand_id' => $validated['brand_id'] ?? null,
                'type' => $validated['type'],
                'name' => $validated['name'],
                'sku' => $validated['sku'] ?? null,
                'barcode' => $validated['barcode'] ?? null,
                'scale_plu_code' => $validated['scale_plu_code'] ?? null,
                'purchase_price' => MoneyMath::money((string) $validated['purchase_price']),
                'sale_price' => MoneyMath::money((string) $validated['sale_price']),
                'wholesale_price' => isset($validated['wholesale_price']) && $validated['wholesale_price'] !== '' ? MoneyMath::money((string) $validated['wholesale_price']) : null,
                'cost_currency' => $validated['cost_currency'] ?? null,
                'foreign_cost_price' => isset($validated['foreign_cost_price']) && $validated['foreign_cost_price'] !== '' ? MoneyMath::money((string) $validated['foreign_cost_price']) : null,
                'profit_margin_percent' => $validated['profit_margin_percent'] ?? null,
                'min_stock_alert' => MoneyMath::qty((string) ($validated['min_stock_alert'] ?? '0')),
                'tax_rate' => $validated['tax_rate'] ?? '0.00',
                'is_quick_pick' => (bool) ($validated['is_quick_pick'] ?? false),
                'has_expiry' => (bool) ($validated['has_expiry'] ?? false),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            if (isset($validated['units']) && is_array($validated['units'])) {
                ProductUnit::forStore($store->id)->where('product_id', $product->id)->delete();
                foreach ($validated['units'] as $uData) {
                    if (empty($uData['unit_name'])) {
                        continue;
                    }
                    ProductUnit::forStore($store->id)->create([
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                        'unit_name' => $uData['unit_name'],
                        'conversion_factor' => MoneyMath::qty((string) ($uData['conversion_factor'] ?? '1')),
                        'barcode' => $uData['barcode'] ?? null,
                        'purchase_price' => MoneyMath::money((string) ($uData['purchase_price'] ?? '0')),
                        'sale_price' => MoneyMath::money((string) ($uData['sale_price'] ?? $product->sale_price)),
                        'is_default' => (bool) ($uData['is_default'] ?? false),
                    ]);
                }
            }
        });

        return redirect()->route('products.index')->with('success', 'تم تحديث بيانات المنتج بنجاح.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'تم أرشفة المنتج (Soft Delete) مع الحفاظ على سجله المالي.');
    }

    public function barcodeLookup(Request $request): JsonResponse
    {
        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['found' => false]);
        }

        $item = GlobalBarcodeCatalog::query()->where('barcode', $barcode)->first();
        if (! $item) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'barcode' => $item->barcode,
            'name_ar' => $item->name_ar,
            'name_en' => $item->name_en,
            'category_hint' => $item->category_hint,
            'default_unit' => $item->default_unit,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $store = $request->user()->store;
        $filename = 'products-' . $store->slug . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($store): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            // UTF-8 BOM for Arabic Excel compatibility
            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'barcode',
                'name',
                'sku',
                'type',
                'purchase_price',
                'sale_price',
                'quantity',
                'min_stock_alert',
                'tax_rate',
            ], ',', '"', '');

            Product::forStore($store->id)
                ->orderBy('id')
                ->chunk(200, function ($products) use ($stream): void {
                    foreach ($products as $p) {
                        fputcsv($stream, [
                            $p->barcode,
                            $p->name,
                            $p->sku,
                            $p->type,
                            $p->purchase_price,
                            $p->sale_price,
                            $p->quantity,
                            $p->min_stock_alert,
                            $p->tax_rate,
                        ], ',', '"', '');
                    }
                });

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $file = $request->file('csv_file') ?? $request->file('file');
        if (! $file || ! $file->isValid()) {
            throw ValidationException::withMessages(['csv_file' => 'يرجى اختيار ملف CSV صالح للاستيراد.']);
        }

        $user = $request->user();
        $store = $user->store;
        $branch = $this->resolveDefaultBranch($store->id, $user->branch_id);

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($file, $store, $branch, $user, &$createdCount, &$updatedCount, &$skippedCount): void {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                return;
            }

            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($headers) || count($headers) === 0) {
                fclose($handle);

                return;
            }

            $headers[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $normalizedHeaders = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $assoc = [];
                foreach ($normalizedHeaders as $idx => $key) {
                    $assoc[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
                }

                $name = $assoc['name'] ?? '';
                $barcode = $assoc['barcode'] ?? '';
                $purchasePriceRaw = $assoc['purchase_price'] ?? '';
                $salePriceRaw = $assoc['sale_price'] ?? ($assoc['price'] ?? '');

                if ($name === '' || $purchasePriceRaw === '' || $salePriceRaw === '' || ! is_numeric($purchasePriceRaw) || ! is_numeric($salePriceRaw)) {
                    $skippedCount++;

                    continue;
                }

                $purchasePrice = MoneyMath::money($purchasePriceRaw);
                $salePrice = MoneyMath::money($salePriceRaw);
                $targetQty = isset($assoc['quantity']) && is_numeric($assoc['quantity'])
                    ? MoneyMath::qty($assoc['quantity'])
                    : '0.000';

                $existing = $barcode !== ''
                    ? Product::forStore($store->id)->where('barcode', $barcode)->first()
                    : null;

                if ($existing) {
                    $existing->update([
                        'name' => $name,
                        'sku' => ($assoc['sku'] ?? '') !== '' ? $assoc['sku'] : $existing->sku,
                        'purchase_price' => $purchasePrice,
                        'sale_price' => $salePrice,
                        'min_stock_alert' => isset($assoc['min_stock_alert']) && is_numeric($assoc['min_stock_alert'])
                            ? MoneyMath::qty($assoc['min_stock_alert'])
                            : $existing->min_stock_alert,
                        'tax_rate' => isset($assoc['tax_rate']) && is_numeric($assoc['tax_rate'])
                            ? MoneyMath::add($assoc['tax_rate'], '0', 2)
                            : $existing->tax_rate,
                    ]);

                    $delta = MoneyMath::sub($targetQty, $existing->quantity, 3);
                    if (! MoneyMath::isZero($delta)) {
                        $this->inventoryService->applyMovement(
                            $store,
                            $branch,
                            $existing,
                            null,
                            $user,
                            MoneyMath::isPositive($delta) ? 'adjustment_in' : 'adjustment_out',
                            $delta,
                            $purchasePrice,
                            Product::class,
                            $existing->id,
                            'تحديث جرد عبر استيراد CSV'
                        );
                    }
                    $updatedCount++;
                } else {
                    $created = Product::forStore($store->id)->create([
                        'store_id' => $store->id,
                        'type' => in_array($assoc['type'] ?? 'simple', ['simple', 'variant', 'weighted'], true) ? $assoc['type'] : 'simple',
                        'name' => $name,
                        'barcode' => $barcode !== '' ? $barcode : null,
                        'sku' => ($assoc['sku'] ?? '') !== '' ? $assoc['sku'] : null,
                        'purchase_price' => $purchasePrice,
                        'sale_price' => $salePrice,
                        'quantity' => '0.000',
                        'min_stock_alert' => isset($assoc['min_stock_alert']) && is_numeric($assoc['min_stock_alert']) ? MoneyMath::qty($assoc['min_stock_alert']) : '0.000',
                        'tax_rate' => isset($assoc['tax_rate']) && is_numeric($assoc['tax_rate']) ? MoneyMath::add($assoc['tax_rate'], '0', 2) : '0.00',
                        'is_active' => true,
                    ]);

                    if (MoneyMath::isPositive($targetQty)) {
                        $this->inventoryService->applyMovement(
                            $store,
                            $branch,
                            $created,
                            null,
                            $user,
                            'adjustment_in',
                            $targetQty,
                            $purchasePrice,
                            Product::class,
                            $created->id,
                            'رصيد افتتاحي عبر استيراد CSV'
                        );
                    }
                    $createdCount++;
                }
            }

            fclose($handle);
        });

        return redirect()->route('products.index')->with(
            'success',
            "اكتمل الاستيراد: {$createdCount} جديد، {$updatedCount} محدّث، {$skippedCount} متخطى."
        );
    }

    protected function validateProductRequest(Request $request, int $storeId, ?int $ignoreProductId): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', 'in:simple,variant,weighted'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'scale_plu_code' => ['nullable', 'string', 'max:10'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_currency' => ['nullable', 'string', 'max:10'],
            'foreign_cost_price' => ['nullable', 'numeric', 'min:0'],
            'profit_margin_percent' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'min_stock_alert' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_quick_pick' => ['nullable', 'boolean'],
            'has_expiry' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'units' => ['nullable', 'array'],
        ]);

        // Collect all barcodes submitted on this product + variants + units
        $submittedBarcodes = [];
        if (! empty($validated['barcode'])) {
            $submittedBarcodes[] = trim((string) $validated['barcode']);
        }
        foreach ($validated['variants'] ?? [] as $v) {
            if (! empty($v['barcode'])) {
                $submittedBarcodes[] = trim((string) $v['barcode']);
            }
        }
        foreach ($validated['units'] ?? [] as $u) {
            if (! empty($u['barcode']) && trim((string) $u['barcode']) !== trim((string) ($validated['barcode'] ?? ''))) {
                $submittedBarcodes[] = trim((string) $u['barcode']);
            }
        }

        foreach ($submittedBarcodes as $code) {
            if ($this->barcodeExistsInStore($storeId, $code, $ignoreProductId)) {
                throw ValidationException::withMessages([
                    'barcode' => "الباركود [{$code}] مستخدم مسبقاً لمنتج أو متغير أو وحدة نشطة داخل المتجر.",
                ]);
            }
        }

        return $validated;
    }

    protected function barcodeExistsInStore(int $storeId, string $barcode, ?int $ignoreProductId): bool
    {
        $inProducts = Product::forStore($storeId)
            ->where('barcode', $barcode)
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($inProducts) {
            return true;
        }

        $inVariants = ProductVariant::forStore($storeId)
            ->where('barcode', $barcode)
            ->when($ignoreProductId, fn ($q) => $q->where('product_id', '!=', $ignoreProductId))
            ->exists();

        if ($inVariants) {
            return true;
        }

        return ProductUnit::forStore($storeId)
            ->where('barcode', $barcode)
            ->whereHas('product', fn ($q) => $q->whereNull('deleted_at'))
            ->when($ignoreProductId, fn ($q) => $q->where('product_id', '!=', $ignoreProductId))
            ->exists();
    }

    protected function resolveDefaultBranch(int $storeId, ?int $preferredBranchId): Branch
    {
        if ($preferredBranchId) {
            $b = Branch::forStore($storeId)->find($preferredBranchId);
            if ($b) {
                return $b;
            }
        }

        return Branch::forStore($storeId)->where('is_default', true)->first()
            ?? Branch::forStore($storeId)->firstOrFail();
    }
}
