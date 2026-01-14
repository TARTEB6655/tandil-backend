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

        // Helper function to safely check role
        $hasRole = function($roleName) use ($user) {
            // First check the role field (faster and more reliable)
            if ($user->role === $roleName) {
                return true;
            }
            // Then check Spatie Permission (with error handling)
            try {
                return $user->hasRole($roleName);
            } catch (\Exception $e) {
                return false;
            }
        };

        // Role-based dashboard redirects - check both Spatie role and direct role property
        if ($hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($hasRole('supervisor')) {
            return redirect()->route('supervisor.dashboard');
        }

        if ($hasRole('technician')) {
            return redirect()->route('technician.dashboard');
        }

        if ($hasRole('client')) {
            return redirect()->route('client.dashboard');
        }

        if ($hasRole('area_manager')) {
            return redirect()->route('areamanager.dashboard');
        }

        if ($hasRole('hr')) {
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
