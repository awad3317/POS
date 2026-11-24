<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\EmployeeAccountService;
use App\Services\ExpenseService;
use App\Services\OrderService;
use App\Services\ReturnService;
use App\Services\ShiftService;
use App\Services\SupplierAccountService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Throwable;

class SyncController extends Controller
{
    public function __construct(
        protected ShiftService $shiftService,
        protected OrderService $orderService,
        protected ReturnService $returnService,
        protected CustomerAccountService $customerAccountService,
        protected ExpenseService $expenseService,
        protected EmployeeAccountService $employeeAccountService,
        protected SupplierAccountService $supplierAccountService,
    ) {}

    public function bootstrap(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles.permissions', 'permissions']);
        $store = Store::query()->findOrFail($user->store_id);
        $canViewCost = $user->can('products.view_cost');

        $products = Product::forStore($store->id)
            ->where('is_active', true)
            ->get()
            ->map(fn (Product $p) => $this->formatProduct($p, $canViewCost))
            ->values();

        $variants = ProductVariant::forStore($store->id)
            ->where('is_active', true)
            ->get()
            ->map(fn (ProductVariant $v) => $this->formatVariant($v, $canViewCost))
            ->values();

        $units = ProductUnit::forStore($store->id)
            ->get()
            ->map(fn (ProductUnit $u) => $this->formatUnit($u, $canViewCost))
            ->values();

        $categories = Category::forStore($store->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order']);

        $customers = Customer::forStore($store->id)
            ->get(['id', 'client_uuid', 'name', 'phone', 'whatsapp', 'credit_limit', 'current_balance']);

        $quickPayees = $this->buildQuickPayees($store->id);

        return response()->json([
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
            'categories' => $categories,
            'products' => $products,
            'variants' => $variants,
            'units' => $units,
            'customers' => $customers,
            'quick_payees' => $quickPayees,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function pull(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles.permissions', 'permissions']);
        $store = Store::query()->findOrFail($user->store_id);
        $canViewCost = $user->can('products.view_cost');

        $sinceInput = $request->query('since');
        $since = $sinceInput ? Carbon::parse((string) $sinceInput) : now()->subYears(5);

        $updatedProducts = Product::forStore($store->id)
            ->where('updated_at', '>=', $since)
            ->get()
            ->map(fn (Product $p) => $this->formatProduct($p, $canViewCost))
            ->values();

        $deletedProductIds = Product::forStore($store->id)
            ->onlyTrashed()
            ->where('deleted_at', '>=', $since)
            ->pluck('id')
            ->values();

        $updatedVariants = ProductVariant::forStore($store->id)
            ->where('updated_at', '>=', $since)
            ->get()
            ->map(fn (ProductVariant $v) => $this->formatVariant($v, $canViewCost))
            ->values();

        $deletedVariantIds = ProductVariant::forStore($store->id)
            ->onlyTrashed()
            ->where('deleted_at', '>=', $since)
            ->pluck('id')
            ->values();

        $updatedUnits = ProductUnit::forStore($store->id)
            ->where('updated_at', '>=', $since)
            ->get()
            ->map(fn (ProductUnit $u) => $this->formatUnit($u, $canViewCost))
            ->values();

        $updatedCustomers = Customer::forStore($store->id)
            ->where('updated_at', '>=', $since)
            ->get(['id', 'client_uuid', 'name', 'phone', 'whatsapp', 'credit_limit', 'current_balance']);

        $deletedCustomerIds = Customer::forStore($store->id)
            ->onlyTrashed()
            ->where('deleted_at', '>=', $since)
            ->pluck('id')
            ->values();

        return response()->json([
            'products' => $updatedProducts,
            'deleted_product_ids' => $deletedProductIds,
            'variants' => $updatedVariants,
            'deleted_variant_ids' => $deletedVariantIds,
            'units' => $updatedUnits,
            'customers' => $updatedCustomers,
            'deleted_customer_ids' => $deletedCustomerIds,
            'quick_payees' => $this->buildQuickPayees($store->id),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function push(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = Store::query()->findOrFail($user->store_id);

        $results = [
            'customers' => [],
            'shifts' => [],
            'orders' => [],
            'payments' => [],
            'expenses' => [],
            'employee_advances' => [],
            'supplier_payments' => [],
            'audit_events' => [],
        ];

        $customerUuidMap = [];
        $shiftUuidMap = [];
        $deferredShiftClosures = [];
        $affectedProductIds = [];
        $affectedVariantIds = [];

        // 1. Offline Customers
        foreach ((array) $request->input('customers', []) as $cData) {
            $uuid = (string) ($cData['client_uuid'] ?? Str::uuid());
            try {
                $existing = Customer::withoutStoreScope()->where('client_uuid', $uuid)->first();
                if ($existing) {
                    if ((int) $existing->store_id !== (int) $store->id) {
                        $results['customers'][$uuid] = ['status' => 'error', 'message' => 'Cross-tenant customer UUID conflict.'];

                        continue;
                    }
                    $customerUuidMap[$uuid] = $existing->id;
                    $results['customers'][$uuid] = ['status' => 'duplicate', 'server_id' => $existing->id];

                    continue;
                }

                $created = Customer::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'client_uuid' => $uuid,
                    'name' => (string) ($cData['name'] ?? 'عميل'),
                    'phone' => $cData['phone'] ?? null,
                    'whatsapp' => $cData['whatsapp'] ?? null,
                    'address' => $cData['address'] ?? null,
                    'opening_balance' => '0.0000',
                    'credit_limit' => (string) ($cData['credit_limit'] ?? '0.0000'),
                    'current_balance' => '0.0000',
                    'notes' => $cData['notes'] ?? null,
                ]);

                $customerUuidMap[$uuid] = $created->id;
                $results['customers'][$uuid] = ['status' => 'synced', 'server_id' => $created->id];
            } catch (Throwable $e) {
                $results['customers'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 2a. Offline Shifts (Open first so sales/payouts attach cleanly)
        foreach ((array) $request->input('shifts', []) as $sData) {
            $uuid = (string) ($sData['client_uuid'] ?? Str::uuid());
            try {
                if (empty($sData['terminal_id']) && ! empty($sData['terminal_code'])) {
                    $sData['terminal_id'] = Terminal::forStore($store->id)->where('code', $sData['terminal_code'])->value('id');
                }

                $res = $this->shiftService->openShift($sData, $user);
                $shift = $res['shift'];
                $shiftUuidMap[$uuid] = $shift->id;

                $statusLabel = $res['status'] === 'duplicate_ok' ? 'duplicate' : 'synced';
                $results['shifts'][$uuid] = ['status' => $statusLabel, 'server_id' => $shift->id];

                if (($sData['status'] ?? 'open') === 'closed' && $shift->status !== 'closed' && isset($sData['actual_cash'])) {
                    $deferredShiftClosures[] = [
                        'uuid' => $uuid,
                        'shift' => $shift,
                        'actual_cash' => (string) $sData['actual_cash'],
                        'notes' => $sData['notes'] ?? null,
                        'closed_at' => $sData['closed_at'] ?? null,
                    ];
                }
            } catch (Throwable $e) {
                $results['shifts'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 3. Offline Orders & Returns
        foreach ((array) $request->input('orders', []) as $oData) {
            $uuid = (string) ($oData['client_uuid'] ?? Str::uuid());
            try {
                $oData['shift_id'] = $this->resolveShiftId($store->id, $oData, $shiftUuidMap);
                $oData['customer_id'] = $this->resolveCustomerId($store->id, $oData, $customerUuidMap);
                $oData['is_offline_sync'] = true;

                if (($oData['type'] ?? 'sale') === 'return') {
                    $parentOrder = $this->resolveParentOrder($store->id, $oData);
                    if (! $parentOrder) {
                        throw new \RuntimeException('Original sale order not found for return.');
                    }
                    $res = $this->returnService->createReturn($parentOrder, $oData, $user);
                } else {
                    $res = $this->orderService->createSale($oData, $user);
                }

                foreach ($res['order']->items as $it) {
                    $affectedProductIds[] = $it->product_id;
                    if ($it->variant_id) {
                        $affectedVariantIds[] = $it->variant_id;
                    }
                }

                $results['orders'][$uuid] = [
                    'status' => $res['status'] === 'duplicate_ok' ? 'duplicate' : 'synced',
                    'server_id' => $res['order']->id,
                    'invoice_number' => $res['order']->invoice_number,
                ];
            } catch (Throwable $e) {
                $results['orders'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 4. Customer Credit Settlements
        foreach ((array) $request->input('payments', []) as $pData) {
            $uuid = (string) ($pData['client_uuid'] ?? Str::uuid());
            try {
                $pData['shift_id'] = $this->resolveShiftId($store->id, $pData, $shiftUuidMap);
                $customerId = $this->resolveCustomerId($store->id, $pData, $customerUuidMap);
                $customer = $customerId ? Customer::forStore($store->id)->find($customerId) : null;
                if (! $customer) {
                    throw new \RuntimeException('Valid store customer is required for credit settlement.');
                }

                $res = $this->customerAccountService->recordCreditSettlement($customer, $pData, $user);
                $results['payments'][$uuid] = [
                    'status' => $res['status'] === 'duplicate_ok' ? 'duplicate' : 'synced',
                    'server_id' => $res['payment']->id,
                ];
            } catch (Throwable $e) {
                $results['payments'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 5. Expenses
        foreach ((array) $request->input('expenses', []) as $eData) {
            $uuid = (string) ($eData['client_uuid'] ?? Str::uuid());
            try {
                $eData['shift_id'] = $this->resolveShiftId($store->id, $eData, $shiftUuidMap);
                $res = $this->expenseService->recordExpense($eData, $user);
                $results['expenses'][$uuid] = [
                    'status' => $res['status'] === 'duplicate_ok' ? 'duplicate' : 'synced',
                    'server_id' => $res['expense']->id,
                ];
            } catch (Throwable $e) {
                $results['expenses'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 6. Employee Advances / Payouts
        foreach ((array) $request->input('employee_advances', []) as $eaData) {
            $uuid = (string) ($eaData['client_uuid'] ?? Str::uuid());
            try {
                $eaData['shift_id'] = $this->resolveShiftId($store->id, $eaData, $shiftUuidMap);
                $employee = Employee::forStore($store->id)->find($eaData['employee_id'] ?? null);
                if (! $employee) {
                    throw new \RuntimeException('Employee does not belong to this store.');
                }

                $res = $this->employeeAccountService->recordAdvanceOrPayout($employee, $eaData, $user);
                $results['employee_advances'][$uuid] = [
                    'status' => $res['status'] === 'duplicate_ok' ? 'duplicate' : 'synced',
                    'server_id' => $res['transaction']->id,
                ];
            } catch (Throwable $e) {
                $results['employee_advances'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 7. Supplier Payments
        foreach ((array) $request->input('supplier_payments', []) as $spData) {
            $uuid = (string) ($spData['client_uuid'] ?? Str::uuid());
            try {
                $spData['shift_id'] = $this->resolveShiftId($store->id, $spData, $shiftUuidMap);
                $supplier = Supplier::forStore($store->id)->find($spData['supplier_id'] ?? null);
                if (! $supplier) {
                    throw new \RuntimeException('Supplier does not belong to this store.');
                }

                $res = $this->supplierAccountService->recordSupplierPayment($supplier, $spData, $user);
                $results['supplier_payments'][$uuid] = [
                    'status' => $res['status'] === 'duplicate_ok' ? 'duplicate' : 'synced',
                    'server_id' => $res['supplier_payment']->id,
                ];
            } catch (Throwable $e) {
                $results['supplier_payments'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 2b. Finalize deferred offline shift closures AFTER all shift cash transactions are posted
        foreach ($deferredShiftClosures as $closure) {
            try {
                $closed = $this->shiftService->closeShift(
                    $closure['shift'],
                    $closure['actual_cash'],
                    $user,
                    $closure['notes'],
                    $closure['closed_at']
                );
                $results['shifts'][$closure['uuid']] = [
                    'status' => 'synced',
                    'server_id' => $closed->id,
                    'expected_cash' => $closed->expected_cash,
                    'difference' => $closed->difference,
                ];
            } catch (Throwable $e) {
                $results['shifts'][$closure['uuid']] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // 8. Audit Events (cart item removals, PIN attempts, etc.)
        foreach ((array) $request->input('audit_events', []) as $aData) {
            $uuid = (string) ($aData['client_uuid'] ?? Str::uuid());
            try {
                if (Activity::query()->where('batch_uuid', $uuid)->exists()) {
                    $results['audit_events'][$uuid] = ['status' => 'duplicate'];

                    continue;
                }

                activity()
                    ->causedBy($user)
                    ->performedOn($store)
                    ->tap(fn (Activity $act) => $act->batch_uuid = $uuid)
                    ->withProperties(array_merge(['store_id' => $store->id], (array) ($aData['properties'] ?? [])))
                    ->event((string) ($aData['event'] ?? 'pos_audit'))
                    ->log((string) ($aData['description'] ?? 'Offline POS audit event'));

                $results['audit_events'][$uuid] = ['status' => 'synced'];
            } catch (Throwable $e) {
                $results['audit_events'][$uuid] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        $stockSnapshot = [
            'products' => count($affectedProductIds) > 0
                ? Product::forStore($store->id)->whereIn('id', array_unique($affectedProductIds))->pluck('quantity', 'id')
                : [],
            'variants' => count($affectedVariantIds) > 0
                ? ProductVariant::forStore($store->id)->whereIn('id', array_unique($affectedVariantIds))->pluck('quantity', 'id')
                : [],
        ];

        return response()->json([
            'results' => $results,
            'stock_balances' => $stockSnapshot,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatProduct(Product $product, bool $canViewCost): array
    {
        $payload = [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'type' => $product->type,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'scale_plu_code' => $product->scale_plu_code,
            'sale_price' => $product->sale_price,
            'wholesale_price' => $product->wholesale_price,
            'min_allowed_sale_price' => $product->purchase_price,
            'quantity' => $product->quantity,
            'tax_rate' => $product->tax_rate,
            'image_path' => $product->image_path,
            'is_quick_pick' => $product->is_quick_pick,
            'is_active' => $product->is_active,
        ];

        if ($canViewCost) {
            $payload['purchase_price'] = $product->purchase_price;
            $payload['cost_currency'] = $product->cost_currency;
            $payload['foreign_cost_price'] = $product->foreign_cost_price;
            $payload['profit_margin_percent'] = $product->profit_margin_percent;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatVariant(ProductVariant $variant, bool $canViewCost): array
    {
        $payload = [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'size' => $variant->size,
            'color' => $variant->color,
            'attribute_label' => $variant->attribute_label,
            'barcode' => $variant->barcode,
            'sale_price' => $variant->sale_price,
            'min_allowed_sale_price' => $variant->purchase_price,
            'quantity' => $variant->quantity,
            'is_active' => $variant->is_active,
        ];

        if ($canViewCost) {
            $payload['purchase_price'] = $variant->purchase_price;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatUnit(ProductUnit $unit, bool $canViewCost): array
    {
        $payload = [
            'id' => $unit->id,
            'product_id' => $unit->product_id,
            'unit_name' => $unit->unit_name,
            'conversion_factor' => $unit->conversion_factor,
            'barcode' => $unit->barcode,
            'sale_price' => $unit->sale_price,
            'min_allowed_sale_price' => $unit->purchase_price,
            'is_default' => $unit->is_default,
        ];

        if ($canViewCost) {
            $payload['purchase_price'] = $unit->purchase_price;
        }

        return $payload;
    }

    /**
     * Expose strictly {id, type, name} for quick drawer payouts without leaking salaries or supplier balances.
     *
     * @return array<int, array{id: int, type: string, name: string}>
     */
    protected function buildQuickPayees(int $storeId): array
    {
        $employees = Employee::forStore($storeId)
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->map(fn (Employee $e) => ['id' => $e->id, 'type' => 'employee', 'name' => $e->name]);

        $suppliers = Supplier::forStore($storeId)
            ->get(['id', 'name'])
            ->map(fn (Supplier $s) => ['id' => $s->id, 'type' => 'supplier', 'name' => $s->name]);

        return $employees->concat($suppliers)->values()->all();
    }

    protected function resolveShiftId(int $storeId, array $data, array $shiftUuidMap): ?int
    {
        if (! empty($data['shift_id'])) {
            return (int) $data['shift_id'];
        }

        if (! empty($data['shift_client_uuid'])) {
            $sUuid = (string) $data['shift_client_uuid'];
            if (isset($shiftUuidMap[$sUuid])) {
                return (int) $shiftUuidMap[$sUuid];
            }

            return Shift::forStore($storeId)->where('client_uuid', $sUuid)->value('id');
        }

        return null;
    }

    protected function resolveCustomerId(int $storeId, array $data, array $customerUuidMap): ?int
    {
        if (! empty($data['customer_id'])) {
            return (int) $data['customer_id'];
        }

        if (! empty($data['customer_client_uuid'])) {
            $cUuid = (string) $data['customer_client_uuid'];
            if (isset($customerUuidMap[$cUuid])) {
                return (int) $customerUuidMap[$cUuid];
            }

            return Customer::forStore($storeId)->where('client_uuid', $cUuid)->value('id');
        }

        return null;
    }

    protected function resolveParentOrder(int $storeId, array $data): ?Order
    {
        if (! empty($data['parent_order_id'])) {
            return Order::forStore($storeId)->find($data['parent_order_id']);
        }

        if (! empty($data['parent_order_client_uuid'])) {
            return Order::forStore($storeId)->where('client_uuid', $data['parent_order_client_uuid'])->first();
        }

        if (! empty($data['parent_invoice_number'])) {
            return Order::forStore($storeId)->where('invoice_number', $data['parent_invoice_number'])->first();
        }

        return null;
    }
}
