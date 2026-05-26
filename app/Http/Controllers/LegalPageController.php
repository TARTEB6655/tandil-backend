<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalPageController extends Controller
{
    /**
     * Public Privacy Policy page (required for App Store / Play Store review).
     */
    public function privacyPolicy(): View
    {
        $user = auth()->user();
        if ($user && $user->hasAppRole('admin')) {
            return view('legal.privacy-policy-admin');
        }

        return view('legal.privacy-policy');
    }
}
