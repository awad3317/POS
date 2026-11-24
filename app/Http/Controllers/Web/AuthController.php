<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAuthenticatedUser(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($validated['login']);
        $user = User::query()
            ->with(['store', 'roles.permissions', 'permissions'])
            ->where(function ($q) use ($identifier): void {
                $q->where('email', $identifier)->orWhere('phone', $identifier);
            })
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['login' => 'بيانات الدخول غير صحيحة.'])->withInput($request->only('login'));
        }

        if (! $user->is_active || ! $user->store || $user->store->subscription_status === 'suspended') {
            return back()->withErrors(['login' => 'هذا الحساب أو المتجر غير نشط حالياً.'])->withInput($request->only('login'));
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectAuthenticatedUser($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function redirectAuthenticatedUser(User $user): RedirectResponse
    {
        $user->loadMissing(['store', 'roles']);

        if ($user->store && $user->store->onboarding_completed_at === null) {
            return redirect()->route('onboarding.show');
        }

        if ($user->hasRole('cashier') && ! $user->hasAnyRole(['owner', 'manager'])) {
            return redirect()->route('pos.index');
        }

        return redirect()->route('dashboard');
    }
}
