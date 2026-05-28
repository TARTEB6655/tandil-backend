<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    /**
     * Logout user.
     */
    public function destroy(Request $request): RedirectResponse
    {
        try {
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } catch (\Throwable $e) {
            Log::warning('Web logout failed, redirecting to login anyway.', [
                'message' => $e->getMessage(),
            ]);
        }

        return redirect()->route('app-portal.roles');
    }
}
?>
