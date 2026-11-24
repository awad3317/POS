<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    /**
     * @return array{status: string, shift: Shift}
     */
    public function openShift(array $data, User $actor): array
    {
        $clientUuid = (string) ($data['client_uuid'] ?? Str::uuid());

        $existing = Shift::withoutStoreScope()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            if ((int) $existing->store_id !== (int) $actor->store_id) {
                throw new AuthorizationException('Cross-tenant shift UUID conflict.');
            }

            return ['status' => 'duplicate_ok', 'shift' => $existing];
        }

        return DB::transaction(function () use ($data, $actor, $clientUuid): array {
            $storeId = (int) $actor->store_id;

            $terminal = Terminal::withoutStoreScope()
                ->where('store_id', $storeId)
                ->lockForUpdate()
                ->find($data['terminal_id'] ?? null);

            if (! $terminal) {
                throw ValidationException::withMessages(['terminal_id' => 'Invalid terminal for this store.']);
            }

            $branchId = (int) ($data['branch_id'] ?? $terminal->branch_id);
            $branch = Branch::withoutStoreScope()->where('store_id', $storeId)->find($branchId);
            if (! $branch) {
                throw ValidationException::withMessages(['branch_id' => 'Invalid branch for this store.']);
            }

            $openingCash = MoneyMath::money((string) ($data['opening_cash'] ?? '0.0000'));
            if (MoneyMath::isNegative($openingCash)) {
                throw ValidationException::withMessages(['opening_cash' => 'Opening cash cannot be negative.']);
            }

            $shift = Shift::withoutStoreScope()->create([
                'store_id' => $storeId,
                'branch_id' => $branch->id,
                'terminal_id' => $terminal->id,
                'user_id' => $actor->id,
                'client_uuid' => $clientUuid,
                'opened_at' => $data['opened_at'] ?? now(),
                'opening_cash' => $openingCash,
                'cash_sales_total' => '0.0000',
                'cash_returns_total' => '0.0000',
                'cash_in_total' => '0.0000',
                'cash_out_total' => '0.0000',
                'expected_cash' => $openingCash,
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
            ]);

            return ['status' => 'created', 'shift' => $shift];
        });
    }

    public function recalculateExpectedCash(Shift $shift): string
    {
        $expected = MoneyMath::add($shift->opening_cash, $shift->cash_sales_total);
        $expected = MoneyMath::add($expected, $shift->cash_in_total);
        $expected = MoneyMath::sub($expected, $shift->cash_returns_total);
        $expected = MoneyMath::sub($expected, $shift->cash_out_total);

        $shift->expected_cash = $expected;

        if ($shift->status === 'closed' && $shift->actual_cash !== null) {
            $shift->difference = MoneyMath::sub($shift->actual_cash, $expected);
        }

        return $expected;
    }

    public function closeShift(Shift $shift, string $actualCash, User $actor, ?string $notes = null, mixed $closedAt = null): Shift
    {
        if ((int) $shift->store_id !== (int) $actor->store_id) {
            throw new AuthorizationException('Cannot close shift from another store.');
        }

        return DB::transaction(function () use ($shift, $actualCash, $notes, $closedAt): Shift {
            $lockedShift = Shift::withoutStoreScope()->lockForUpdate()->findOrFail($shift->id);

            $normalizedActual = MoneyMath::money($actualCash);
            if (MoneyMath::isNegative($normalizedActual)) {
                throw ValidationException::withMessages(['actual_cash' => 'Actual cash cannot be negative.']);
            }

            $expected = $this->recalculateExpectedCash($lockedShift);
            $lockedShift->actual_cash = $normalizedActual;
            $lockedShift->difference = MoneyMath::sub($normalizedActual, $expected);
            $lockedShift->status = 'closed';
            $lockedShift->closed_at = $closedAt ?? $lockedShift->closed_at ?? now();
            if ($notes !== null) {
                $lockedShift->notes = $notes;
            }
            $lockedShift->save();

            return $lockedShift;
        });
    }
}
