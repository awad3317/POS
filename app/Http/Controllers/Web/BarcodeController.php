<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarcodeController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $initialItems = $this->searchLabelItems($store->id, '', 12);

        return view('admin.barcodes.index', compact('store', 'initialItems'));
    }

    public function search(Request $request): JsonResponse
    {
        $store = $request->user()->store;
        $query = trim((string) $request->query('q', ''));

        $items = $this->searchLabelItems($store->id, $query, 20);

        return response()->json([
            'items' => $items,
            'count' => count($items),
        ]);
    }

    /**
     * Searches active products, variants, and multi-units for the current store only (max 20 results).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function searchLabelItems(int $storeId, string $query, int $limit = 20): array
    {
        $limit = min(20, max(1, $limit));
        $results = [];

        $products = Product::forStore($storeId)
            ->where('is_active', true)
            ->with([
                'variants' => fn ($q) => $q->where('is_active', true),
                'units',
            ])
            ->when($query !== '', function ($q) use ($query): void {
                $q->where(function ($sub) use ($query): void {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('barcode', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhere('scale_plu_code', 'like', "%{$query}%")
                        ->orWhereHas('variants', fn ($vq) => $vq->where('barcode', 'like', "%{$query}%")
                            ->orWhere('attribute_label', 'like', "%{$query}%"))
                        ->orWhereHas('units', fn ($uq) => $uq->where('barcode', 'like', "%{$query}%"));
                });
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        $lowerQuery = mb_strtolower($query);

        foreach ($products as $product) {
            $parentMatches = $lowerQuery === ''
                || str_contains(mb_strtolower($product->name), $lowerQuery)
                || str_contains(mb_strtolower((string) $product->barcode), $lowerQuery)
                || str_contains(mb_strtolower((string) $product->sku), $lowerQuery)
                || str_contains(mb_strtolower((string) $product->scale_plu_code), $lowerQuery);

            if ($product->type === 'variant' && $product->variants->isNotEmpty()) {
                /** @var ProductVariant $variant */
                foreach ($product->variants as $variant) {
                    $code = $variant->barcode ?: $product->barcode;
                    if (! $code) {
                        continue;
                    }
                    $subtitle = $variant->attribute_label ?: trim(($variant->size ?? '') . ' - ' . ($variant->color ?? ''), ' -');
                    if (! $parentMatches) {
                        $variantMatches = str_contains(mb_strtolower((string) $variant->barcode), $lowerQuery)
                            || str_contains(mb_strtolower($subtitle), $lowerQuery);
                        if (! $variantMatches) {
                            continue;
                        }
                    }

                    $results[] = [
                        'key' => "variant-{$variant->id}",
                        'type' => 'variant',
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'name' => $product->name,
                        'subtitle' => $subtitle,
                        'barcode' => $code,
                        'price' => (string) ($variant->sale_price ?? $product->sale_price),
                        'sku' => $product->sku,
                    ];
                    if (count($results) >= $limit) {
                        return $results;
                    }
                }

                continue;
            }

            if ($product->barcode && $parentMatches) {
                $results[] = [
                    'key' => "product-{$product->id}",
                    'type' => 'product',
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'name' => $product->name,
                    'subtitle' => $product->type === 'weighted' && $product->scale_plu_code
                        ? "PLU: {$product->scale_plu_code}"
                        : ($product->sku ?? ''),
                    'barcode' => $product->barcode,
                    'price' => (string) $product->sale_price,
                    'sku' => $product->sku,
                ];
                if (count($results) >= $limit) {
                    return $results;
                }
            }

            /** @var ProductUnit $unit */
            foreach ($product->units as $unit) {
                if (! $unit->barcode || $unit->barcode === $product->barcode) {
                    continue;
                }
                if (! $parentMatches) {
                    $unitMatches = str_contains(mb_strtolower((string) $unit->barcode), $lowerQuery)
                        || str_contains(mb_strtolower($unit->unit_name), $lowerQuery);
                    if (! $unitMatches) {
                        continue;
                    }
                }
                $results[] = [
                    'key' => "unit-{$unit->id}",
                    'type' => 'unit',
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'name' => $product->name,
                    'subtitle' => $unit->unit_name,
                    'barcode' => $unit->barcode,
                    'price' => (string) ($unit->sale_price ?? $product->sale_price),
                    'sku' => $product->sku,
                ];
                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }
}
