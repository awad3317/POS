<?php

namespace App\Http\Middleware;

use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'غير مصرح لك بالوصول.'], 403);
            }
            abort(403, 'غير مصرح لك بالوصول.');
        }

        $user->loadMissing('roles');

        if (! $user->hasRole('super_admin')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'غير مصرح لك بالوصول إلى لوحة إدارة المنصة.'], 403);
            }
            abort(403, 'غير مصرح لك بالوصول إلى لوحة إدارة المنصة.');
        }

        return StoreContext::withoutScope(fn () => $next($request));
    }
}
