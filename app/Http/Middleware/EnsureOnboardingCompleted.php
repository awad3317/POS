<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->store;

        if ($store && $store->onboarding_completed_at === null && ! $request->routeIs('onboarding.*')) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
