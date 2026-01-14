<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        // Role-based dashboard redirects - check both Spatie role and direct role property
        if ($user->hasRole('admin') || $user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('supervisor') || $user->role === 'supervisor') {
            return redirect()->route('supervisor.dashboard');
        }

        if ($user->hasRole('technician') || $user->role === 'technician') {
            return redirect()->route('technician.dashboard');
        }

        if ($user->hasRole('client') || $user->role === 'client') {
            return redirect()->route('client.dashboard');
        }

        if ($user->hasRole('area_manager') || $user->role === 'area_manager') {
            return redirect()->route('areamanager.dashboard');
        }

        if ($user->hasRole('hr') || $user->role === 'hr') {
            return redirect()->route('hr.dashboard');
        }

        // Default fallback - redirect to dashboard redirect route
        return redirect()->route('dashboard.redirect');
    }

    /**
     * Logout user.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
?>
