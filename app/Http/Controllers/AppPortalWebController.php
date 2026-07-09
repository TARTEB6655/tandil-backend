<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AppLoginRoles;
use App\Support\VendorLoginGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AppPortalWebController extends Controller
{
    /**
     * @return array<string, array{title: string, subtitle: string, icon: string}>
     */
    public static function portalMeta(): array
    {
        return AppLoginRoles::bySlug();
    }

    public function selectRole(Request $request): View
    {
        $meta = self::portalMeta();
        $ordered = [];

        // App portal UX requirement: keep Admin first on role picker.
        foreach (['admin', 'client', 'vendor', 'technician', 'supervisor', 'area_manager', 'hr'] as $slug) {
            if (isset($meta[$slug])) {
                $ordered[$slug] = $meta[$slug];
            }
        }

        foreach ($meta as $slug => $row) {
            if (! isset($ordered[$slug])) {
                $ordered[$slug] = $row;
            }
        }

        return view('app-portal.select-role', [
            'portals' => $ordered,
            'authUser' => $request->user(),
        ]);
    }

    public function loginForm(Request $request): View|RedirectResponse
    {
        $portal = $request->query('portal');
        if (is_string($portal) && in_array($portal, User::LOGIN_PORTALS, true)) {
            $request->session()->put('app_portal', $portal);
        } else {
            $portal = $request->session()->get('app_portal');
        }

        if (! is_string($portal) || ! in_array($portal, User::LOGIN_PORTALS, true)) {
            return redirect()->route('app-portal.roles')
                ->with('error', __('Choose a role on the previous step first.'));
        }

        $meta = self::portalMeta()[$portal] ?? ['title' => $portal, 'subtitle' => ''];

        return view('app-portal.login', [
            'portal' => $portal,
            'portalLabel' => $meta['title'],
            'portalSubtitle' => $meta['subtitle'],
            'authUser' => $request->user(),
        ]);
    }

    public function loginSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $portal = $request->session()->get('app_portal');
        if (! is_string($portal) || ! in_array($portal, User::LOGIN_PORTALS, true)) {
            return redirect()->route('app-portal.roles');
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withErrors(['email' => trans('auth.failed')])
                ->onlyInput('email');
        }

        if (($user->status ?? null) !== 'active') {
            return back()
                ->withErrors(['email' => __('This account is not active. Please contact an administrator.')])
                ->onlyInput('email');
        }

        if (! $user->matchesLoginPortal($portal)) {
            return back()
                ->withErrors([
                    'email' => __('These credentials do not match this sign-in path. Go back and choose the role that matches your account, or contact support.'),
                ])
                ->onlyInput('email');
        }

        if ($portal === 'vendor') {
            $blocked = VendorLoginGate::blockedMessageForUser($user);
            if ($blocked !== null) {
                return back()
                    ->withErrors(['email' => $blocked])
                    ->onlyInput('email');
            }
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Never use redirect()->intended() blindly: only honor admin/vendor paths matching this portal.
        $intended = $request->session()->pull('url.intended');
        if (is_string($intended) && $this->portalCanAccessUrl($portal, $intended)) {
            return redirect($intended);
        }

        return $this->redirectToRoleDashboard($portal);
    }

    private function portalCanAccessUrl(string $portal, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return match ($portal) {
            'admin' => str_starts_with($path, '/admin'),
            'vendor' => str_starts_with($path, '/vendor'),
            'client' => str_starts_with($path, '/client'),
            'technician' => str_starts_with($path, '/technician'),
            'supervisor' => str_starts_with($path, '/supervisor'),
            'area_manager' => str_starts_with($path, '/area-manager') || str_starts_with($path, '/areamanager'),
            'hr' => str_starts_with($path, '/hr'),
            default => false,
        };
    }

    private function redirectToRoleDashboard(string $portal): RedirectResponse
    {
        return match ($portal) {
            'vendor' => auth()->user()?->vendor?->isApproved()
                ? redirect()->route('vendor.dashboard')
                : redirect()->route('vendor.application.status'),
            'admin' => redirect()->route('admin.dashboard'),
            'supervisor' => redirect()->route('supervisor.dashboard'),
            'technician' => redirect()->route('technician.dashboard'),
            'client' => redirect()->route('client.dashboard'),
            'area_manager' => redirect()->route('areamanager.dashboard'),
            'hr' => redirect()->route('hr.dashboard'),
            default => redirect()->route('dashboard.redirect'),
        };
    }
}
