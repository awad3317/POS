<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Shift;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    /**
     * @return array{status: string, expense: Expense}
     */
    public function recordExpense(array $data, User $actor): array
    {
        $clientUuid = isset($data['client_uuid']) ? (string) $data['client_uuid'] : (string) Str::uuid();

        $existing = Expense::withoutStoreScope()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            if ((int) $existing->store_id !== (int) $actor->store_id) {
                throw new AuthorizationException('Cross-tenant expense UUID conflict.');
            }

            return ['status' => 'duplicate_ok', 'expense' => $existing];
        }

        return DB::transaction(function () use ($data, $actor, $clientUuid): array {
            $amount = MoneyMath::money((string) ($data['amount'] ?? '0'));
            if (! MoneyMath::isPositive($amount)) {
                throw ValidationException::withMessages(['amount' => 'Expense amount must be greater than zero.']);
            }

            $paymentSource = (string) ($data['payment_source'] ?? 'owner_pocket');
            $shiftId = $data['shift_id'] ?? null;
            $branchId = (int) ($data['branch_id'] ?? $actor->branch_id);

            if ($paymentSource === 'cash_drawer') {
                if (! $shiftId) {
                    throw ValidationException::withMessages(['shift_id' => 'An active shift is required for cash_drawer expenses.']);
                }

                $lockedShift = Shift::withoutStoreScope()
                    ->where('store_id', $actor->store_id)
                    ->lockForUpdate()
                    ->find($shiftId);

                if (! $lockedShift) {
                    throw ValidationException::withMessages(['shift_id' => 'Invalid shift for this store.']);
                }

                $branchId = (int) $lockedShift->branch_id;
                $lockedShift->cash_out_total = MoneyMath::add($lockedShift->cash_out_total, $amount);
                $this->shiftService->recalculateExpectedCash($lockedShift);
                $lockedShift->save();
            } else {
                $branch = Branch::withoutStoreScope()->where('store_id', $actor->store_id)->find($branchId);
                if (! $branch) {
                    throw ValidationException::withMessages(['branch_id' => 'Invalid branch for this store.']);
                }
            }

            $expense = Expense::withoutStoreScope()->create([
                'store_id' => $actor->store_id,
                'branch_id' => $branchId,
                'shift_id' => $shiftId,
                'user_id' => $actor->id,
                'client_uuid' => $clientUuid,
                'category' => (string) ($data['category'] ?? 'نثريات'),
                'amount' => $amount,
                'payment_source' => $paymentSource,
                'spent_at' => $data['spent_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['status' => 'created', 'expense' => $expense];
        });
    }
}
