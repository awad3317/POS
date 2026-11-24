<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAccountService
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    /**
     * @return array{status: string, payment: Payment}
     */
    public function recordCreditSettlement(Customer $customer, array $data, User $actor): array
    {
        if ((int) $customer->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Customer belongs to another store.');
        }

        $clientUuid = (string) ($data['client_uuid'] ?? Str::uuid());
        $existing = Payment::withoutStoreScope()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            if ((int) $existing->store_id !== (int) $actor->store_id) {
                throw new AuthorizationException('Cross-tenant payment UUID conflict.');
            }

            return ['status' => 'duplicate_ok', 'payment' => $existing];
        }

        return DB::transaction(function () use ($customer, $data, $actor, $clientUuid): array {
            $amount = MoneyMath::money((string) ($data['amount'] ?? '0'));
            if (! MoneyMath::isPositive($amount)) {
                throw ValidationException::withMessages(['amount' => 'Settlement amount must be greater than zero.']);
            }

            $lockedCustomer = Customer::withoutStoreScope()
                ->where('store_id', $actor->store_id)
                ->lockForUpdate()
                ->findOrFail($customer->id);

            $lockedCustomer->current_balance = MoneyMath::sub($lockedCustomer->current_balance, $amount);
            $lockedCustomer->save();

            $method = (string) ($data['method'] ?? 'cash');
            $shiftId = $data['shift_id'] ?? null;
            $branchId = (int) ($data['branch_id'] ?? $actor->branch_id);

            if ($shiftId !== null) {
                $lockedShift = Shift::withoutStoreScope()
                    ->where('store_id', $actor->store_id)
                    ->lockForUpdate()
                    ->findOrFail($shiftId);
                $branchId = (int) $lockedShift->branch_id;

                if ($method === 'cash') {
                    $lockedShift->cash_in_total = MoneyMath::add($lockedShift->cash_in_total, $amount);
                    $this->shiftService->recalculateExpectedCash($lockedShift);
                    $lockedShift->save();
                }
            }

            $payment = Payment::withoutStoreScope()->create([
                'store_id' => $actor->store_id,
                'branch_id' => $branchId,
                'shift_id' => $shiftId,
                'order_id' => null,
                'customer_id' => $lockedCustomer->id,
                'user_id' => $actor->id,
                'client_uuid' => $clientUuid,
                'payment_type' => 'credit_settlement',
                'method' => $method,
                'reference_number' => $data['reference_number'] ?? null,
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['status' => 'created', 'payment' => $payment];
        });
    }
}
