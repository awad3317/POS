<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->store_id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized or inactive user account.'], 403);
            }
            abort(403, 'الحساب غير نشط أو غير مرتبط بمتجر.');
        }

        $user->loadMissing(['store', 'roles.permissions', 'permissions']);
        $store = $user->store ?? Store::query()->find($user->store_id);

        if (! $store || $store->subscription_status === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Store account is suspended or unavailable.'], 403);
            }
            abort(403, 'حساب المتجر موقوف حالياً.');
        }

        StoreContext::set((int) $store->id);

        try {
            return $next($request);
        } finally {
            StoreContext::clear();
        }
    }
}
