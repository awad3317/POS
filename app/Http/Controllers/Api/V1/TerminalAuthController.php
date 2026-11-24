<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TerminalAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'terminal_code' => ['required', 'string', 'max:30'],
        ]);

        $identifier = $validated['login'] ?? $validated['email'] ?? $validated['phone'] ?? null;
        if (! $identifier) {
            throw ValidationException::withMessages(['login' => 'Email or phone is required.']);
        }

        $user = User::query()
            ->where(function ($q) use ($identifier): void {
                $q->where('email', $identifier)->orWhere('phone', $identifier);
            })
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        if (! $user->is_active || ! $user->store_id) {
            return response()->json(['message' => 'الحساب معطل أو غير مرتبط بمتجر.'], 403);
        }

        $store = Store::query()->find($user->store_id);
        if (! $store || $store->subscription_status === 'suspended') {
            return response()->json(['message' => 'حساب المتجر موقوف حالياً.'], 403);
        }

        $terminal = Terminal::withoutStoreScope()
            ->where('store_id', $store->id)
            ->where('code', $validated['terminal_code'])
            ->where('is_active', true)
            ->first();

        if (! $terminal) {
            return response()->json([
                'message' => 'رمز جهاز الكاشير غير معتمد لهذا المتجر أو غير نشط.',
            ], 403);
        }

        $newToken = $user->createToken("terminal:{$terminal->code}");
        $terminal->sanctum_token_id = $newToken->accessToken->id;
        $terminal->last_sync_at = now();
        $terminal->save();

        $user->loadMissing(['roles.permissions', 'permissions']);

        return response()->json([
            'token' => $newToken->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id ?? $terminal->branch_id,
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'business_type' => $store->business_type,
                'currency_symbol' => $store->currency_symbol,
                'currency_code' => $store->currency_code,
                'decimal_places' => $store->decimal_places,
                'feature_flags' => $store->feature_flags ?? [],
                'receipt_settings' => $store->receipt_settings ?? [],
            ],
            'terminal' => [
                'id' => $terminal->id,
                'branch_id' => $terminal->branch_id,
                'code' => $terminal->code,
                'name' => $terminal->name,
                'last_invoice_seq' => $terminal->last_invoice_seq,
            ],
        ]);
    }
}
