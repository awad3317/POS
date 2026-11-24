<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeAccountService
{
    public function __construct(
        protected ShiftService $shiftService,
    ) {}

    /**
     * @return array{status: string, transaction: EmployeeTransaction}
     */
    public function recordAdvanceOrPayout(Employee $employee, array $data, User $actor): array
    {
        if ((int) $employee->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Employee belongs to another store.');
        }

        $clientUuid = isset($data['client_uuid']) ? (string) $data['client_uuid'] : (string) Str::uuid();
        $existing = EmployeeTransaction::withoutStoreScope()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            if ((int) $existing->store_id !== (int) $actor->store_id) {
                throw new AuthorizationException('Cross-tenant employee transaction UUID conflict.');
            }

            return ['status' => 'duplicate_ok', 'transaction' => $existing];
        }

        return DB::transaction(function () use ($employee, $data, $actor, $clientUuid): array {
            $type = (string) ($data['type'] ?? 'advance');
            if (! in_array($type, ['advance', 'salary_payout'], true)) {
                throw ValidationException::withMessages(['type' => 'Type must be advance or salary_payout.']);
            }

            $amount = MoneyMath::money((string) ($data['amount'] ?? '0'));
            if (! MoneyMath::isPositive($amount)) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            $lockedEmployee = Employee::withoutStoreScope()
                ->where('store_id', $actor->store_id)
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $signedAmount = MoneyMath::negate(MoneyMath::abs($amount));
            $balanceBefore = MoneyMath::money($lockedEmployee->current_balance);
            $balanceAfter = MoneyMath::add($balanceBefore, $signedAmount);

            $lockedEmployee->current_balance = $balanceAfter;
            $lockedEmployee->save();

            $paymentSource = (string) ($data['payment_source'] ?? 'owner_pocket');
            $shiftId = $data['shift_id'] ?? null;
            $branchId = $data['branch_id'] ?? $lockedEmployee->branch_id ?? $actor->branch_id;

            if ($paymentSource === 'cash_drawer') {
                if (! $shiftId) {
                    throw ValidationException::withMessages(['shift_id' => 'Shift is required when paying employee from cash_drawer.']);
                }

                $lockedShift = Shift::withoutStoreScope()
                    ->where('store_id', $actor->store_id)
                    ->lockForUpdate()
                    ->findOrFail($shiftId);

                $branchId = $lockedShift->branch_id;
                $lockedShift->cash_out_total = MoneyMath::add($lockedShift->cash_out_total, $amount);
                $this->shiftService->recalculateExpectedCash($lockedShift);
                $lockedShift->save();
            }

            $tx = EmployeeTransaction::withoutStoreScope()->create([
                'store_id' => $actor->store_id,
                'branch_id' => $branchId,
                'employee_id' => $lockedEmployee->id,
                'shift_id' => $shiftId,
                'user_id' => $actor->id,
                'client_uuid' => $clientUuid,
                'type' => $type,
                'payment_source' => $paymentSource,
                'amount' => $amount,
                'signed_amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['status' => 'created', 'transaction' => $tx];
        });
    }

    /**
     * @return array{accrued_count: int, skipped_count: int, transactions: array<int, EmployeeTransaction>}
     */
    public function accrueSalaries(Store $store, string $periodLabel, User $actor): array
    {
        if ((int) $store->id !== (int) $actor->store_id) {
            throw new AuthorizationException('Cannot accrue salaries for another store.');
        }

        $periodTag = 'period:' . trim($periodLabel);

        return DB::transaction(function () use ($store, $periodLabel, $periodTag, $actor): array {
            $employees = Employee::withoutStoreScope()
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            $accruedCount = 0;
            $skippedCount = 0;
            $transactions = [];

            foreach ($employees as $emp) {
                if (! MoneyMath::isPositive($emp->base_salary)) {
                    $skippedCount++;

                    continue;
                }

                $alreadyAccrued = EmployeeTransaction::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->where('employee_id', $emp->id)
                    ->where('type', 'salary_accrual')
                    ->where('reference_type', $periodTag)
                    ->exists();

                if ($alreadyAccrued) {
                    $skippedCount++;

                    continue;
                }

                $amount = MoneyMath::money($emp->base_salary);
                $balanceBefore = MoneyMath::money($emp->current_balance);
                $balanceAfter = MoneyMath::add($balanceBefore, $amount);

                $emp->current_balance = $balanceAfter;
                $emp->save();

                $transactions[] = EmployeeTransaction::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'branch_id' => $emp->branch_id,
                    'employee_id' => $emp->id,
                    'shift_id' => null,
                    'user_id' => $actor->id,
                    'client_uuid' => (string) Str::uuid(),
                    'type' => 'salary_accrual',
                    'payment_source' => null,
                    'amount' => $amount,
                    'signed_amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'reference_type' => $periodTag,
                    'reference_id' => null,
                    'transaction_date' => now(),
                    'notes' => "استحقاق راتب ({$periodLabel})",
                ]);

                $accruedCount++;
            }

            return [
                'accrued_count' => $accruedCount,
                'skipped_count' => $skippedCount,
                'transactions' => $transactions,
            ];
        });
    }

    public function recordDeductionOrBonus(
        Employee $employee,
        string $type,
        string $amount,
        User $actor,
        ?string $notes = null
    ): EmployeeTransaction {
        if ((int) $employee->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Employee belongs to another store.');
        }

        if (! in_array($type, ['bonus', 'deduction'], true)) {
            throw ValidationException::withMessages(['type' => 'Type must be bonus or deduction.']);
        }

        return DB::transaction(function () use ($employee, $type, $amount, $actor, $notes): EmployeeTransaction {
            $absAmount = MoneyMath::abs($amount);
            if (! MoneyMath::isPositive($absAmount)) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            $lockedEmployee = Employee::withoutStoreScope()
                ->where('store_id', $actor->store_id)
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $signedAmount = $type === 'bonus' ? $absAmount : MoneyMath::negate($absAmount);
            $balanceBefore = MoneyMath::money($lockedEmployee->current_balance);
            $balanceAfter = MoneyMath::add($balanceBefore, $signedAmount);

            $lockedEmployee->current_balance = $balanceAfter;
            $lockedEmployee->save();

            return EmployeeTransaction::withoutStoreScope()->create([
                'store_id' => $actor->store_id,
                'branch_id' => $lockedEmployee->branch_id,
                'employee_id' => $lockedEmployee->id,
                'shift_id' => null,
                'user_id' => $actor->id,
                'client_uuid' => (string) Str::uuid(),
                'type' => $type,
                'payment_source' => null,
                'amount' => $absAmount,
                'signed_amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transaction_date' => now(),
                'notes' => $notes,
            ]);
        });
    }

    public function transferShiftShortageToCashier(Shift $shift, User $actor, ?string $notes = null): EmployeeTransaction
    {
        if ((int) $shift->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Shift belongs to another store.');
        }

        return DB::transaction(function () use ($shift, $actor, $notes): EmployeeTransaction {
            $lockedShift = Shift::withoutStoreScope()
                ->where('store_id', $actor->store_id)
                ->lockForUpdate()
                ->findOrFail($shift->id);

            if ($lockedShift->status !== 'closed') {
                throw ValidationException::withMessages(['shift' => 'Shift must be closed before transferring shortage.']);
            }

            if ($lockedShift->difference === null || ! MoneyMath::isNegative($lockedShift->difference)) {
                throw ValidationException::withMessages(['shift' => 'Shift does not have a negative cash shortage.']);
            }

            $alreadyTransferred = EmployeeTransaction::withoutStoreScope()
                ->where('store_id', $actor->store_id)
                ->where('type', 'shift_shortage')
                ->where('reference_type', Shift::class)
                ->where('reference_id', $lockedShift->id)
                ->exists();

            if ($alreadyTransferred) {
                throw ValidationException::withMessages(['shift' => 'Shortage for this shift has already been transferred.']);
            }

            $lockedEmployee = Employee::withoutStoreScope()
                ->where('store_id', $actor->store_id)
                ->where('user_id', $lockedShift->user_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedEmployee) {
                throw ValidationException::withMessages(['employee' => 'No employee profile is linked to this shift cashier.']);
            }

            $shortageAbs = MoneyMath::abs($lockedShift->difference);
            $signedAmount = MoneyMath::negate($shortageAbs);
            $balanceBefore = MoneyMath::money($lockedEmployee->current_balance);
            $balanceAfter = MoneyMath::add($balanceBefore, $signedAmount);

            $lockedEmployee->current_balance = $balanceAfter;
            $lockedEmployee->save();

            return EmployeeTransaction::withoutStoreScope()->create([
                'store_id' => $actor->store_id,
                'branch_id' => $lockedShift->branch_id,
                'employee_id' => $lockedEmployee->id,
                'shift_id' => $lockedShift->id,
                'user_id' => $actor->id,
                'client_uuid' => (string) Str::uuid(),
                'type' => 'shift_shortage',
                'payment_source' => null,
                'amount' => $shortageAbs,
                'signed_amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => Shift::class,
                'reference_id' => $lockedShift->id,
                'transaction_date' => now(),
                'notes' => $notes ?? "ترحيل عجز الوردية #{$lockedShift->id}",
            ]);
        });
    }

    /**
     * @return array{employee_id: int, opening_balance: string, entries: array<int, array<string, mixed>>, closing_balance: string}
     */
    public function getEmployeeStatement(Employee $employee, ?string $from = null, ?string $to = null): array
    {
        $txs = EmployeeTransaction::withoutStoreScope()
            ->where('store_id', $employee->store_id)
            ->where('employee_id', $employee->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $fromTs = $from ? strtotime($from) : null;
        $toTs = $to ? strtotime($to) : null;

        $openingBalance = MoneyMath::money($employee->opening_balance);
        $runningBalance = $openingBalance;
        $entries = [];

        foreach ($txs as $tx) {
            $ts = $tx->transaction_date?->getTimestamp() ?? 0;

            if ($fromTs !== null && $ts < $fromTs) {
                $openingBalance = MoneyMath::add($openingBalance, $tx->signed_amount);
                $runningBalance = $openingBalance;

                continue;
            }

            if ($toTs !== null && $ts > $toTs) {
                continue;
            }

            $runningBalance = MoneyMath::add($runningBalance, $tx->signed_amount);
            $entries[] = [
                'id' => $tx->id,
                'date' => $tx->transaction_date?->toIso8601String(),
                'type' => $tx->type,
                'payment_source' => $tx->payment_source,
                'amount' => MoneyMath::money($tx->amount),
                'signed_amount' => MoneyMath::money($tx->signed_amount),
                'running_balance' => $runningBalance,
                'notes' => $tx->notes,
            ];
        }

        return [
            'employee_id' => $employee->id,
            'opening_balance' => $openingBalance,
            'entries' => $entries,
            'closing_balance' => $runningBalance,
        ];
    }
}
