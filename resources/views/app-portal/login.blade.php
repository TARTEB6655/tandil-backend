@extends('layouts.app-portal')

@section('title', __('Log in'))

@section('content')
<div class="portal-page portal-page--login">
    <div class="portal-shell">
        @if ($authUser ?? null)
            <div class="portal-alert" style="background:#e0f2fe;border:1px solid #bae6fd;color:#0c4a6e;">
                <strong>{{ __('Switching account') }}</strong><br>
                {{ __('Currently signed in as :email. Submitting this form will sign you in as the account you enter below.', ['email' => $authUser->email]) }}
            </div>
        @endif

        <header style="text-align:center;margin-bottom:1.25rem;">
            <div class="portal-logo-wrap">
                <img src="{{ asset('images/logo.png') }}" alt="TANDIL" width="80" height="80" decoding="async">
            </div>
            <p class="portal-muted" style="margin-top:1rem;font-weight:600;">{{ __('Signing in as') }}</p>
            <p class="portal-title" style="margin-top:6px;">{{ $portalLabel }}</p>
            <p class="portal-sub" style="max-width:36ch;margin-left:auto;margin-right:auto;">{{ $portalSubtitle }}</p>
            <p class="portal-brandline"><span dir="rtl">تنديل</span> &nbsp;|&nbsp; TANDIL</p>
        </header>

        <div class="portal-card">
            <form method="POST" action="{{ route('app-portal.login.submit') }}">
                @csrf

                <div class="portal-field">
                    <label class="portal-label" for="email">{{ __('Email') }}</label>
                    <input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                    @error('email')
                        <p class="portal-err">{{ $message }}</p>
                    @enderror
                </div>

                <div class="portal-field">
                    <label class="portal-label" for="password">{{ __('Password') }}</label>
                    <div class="portal-input-wrap">
                        <input class="portal-input portal-input--password" id="password" name="password" type="password" required autocomplete="current-password">
                        <button type="button" class="portal-password-toggle" data-password-toggle aria-controls="password" aria-label="{{ __('Show password') }}" aria-pressed="false">
                            <svg data-icon-eye class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg data-icon-eye-off class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="portal-err">{{ $message }}</p>
                    @enderror
                </div>

                <div class="portal-row">
                    <label>
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>{{ __('Remember me') }}</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="portal-link" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                    @endif
                </div>

                <button type="submit" class="portal-btn">{{ __('Log in') }}</button>

                @if (Route::has('register'))
                    <div class="portal-divider portal-muted">
                        {{ __('Not registered?') }}
                        <a class="portal-link" href="{{ route('register') }}">{{ __('Register here') }}</a>
                    </div>
                @endif
            </form>

            <div class="portal-foot">
                <a href="{{ route('app-portal.roles') }}">← {{ __('Change role') }}</a>
            </div>
        </div>
    </div>
</div>
@include('components.password-toggle-script')
@endsection
