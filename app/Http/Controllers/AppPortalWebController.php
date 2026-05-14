<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AppPortalWebController extends Controller
{
    /**
     * @return array<string, array{title: string, subtitle: string}>
     */
    public static function portalMeta(): array
    {
        return [
            'client' => [
                'title' => 'Client (Customer)',
                'subtitle' => 'Subscribe to plans, receive reports, and purchase agricultural products.',
            ],
            'technician' => [
                'title' => 'Worker (Field Technician)',
                'subtitle' => 'Perform watering, planting, cleaning tasks and submit field reports.',
            ],
            'supervisor' => [
                'title' => 'Supervisor (Team Leader)',
                'subtitle' => 'Manage workers, review reports, and submit final reports to clients.',
            ],
            'area_manager' => [
                'title' => 'Area Manager',
                'subtitle' => 'Oversee supervisors and technicians within a defined region.',
            ],
            'hr' => [
                'title' => 'HR Manager',
                'subtitle' => 'Manage employee profiles, job IDs, schedules, and visit assignments.',
            ],
            'admin' => [
                'title' => 'Admin',
                'subtitle' => 'Full platform administration, users, settings, and support.',
            ],
        ];
    }

    public function selectRole(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard.redirect');
        }

        return view('app-portal.select-role', [
            'portals' => self::portalMeta(),
        ]);
    }

    public function loginForm(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard.redirect');
        }

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
        ]);
    }

    public function loginSubmit(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard.redirect');
        }

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

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard.redirect'));
    }
}
