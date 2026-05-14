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
                <img src="{{ asset('images/logo.png') }}" alt="TANDIL" width="70" height="70" decoding="async">
            </div>
            <p class="portal-muted" style="margin-top:14px;letter-spacing:0.12em;text-transform:uppercase;font-weight:600;">{{ __('Signing in as') }}</p>
            <p class="portal-title" style="margin-top:6px;">{{ $portalLabel }}</p>
            <p class="portal-sub" style="max-width:32ch;margin-left:auto;margin-right:auto;">{{ $portalSubtitle }}</p>
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
                    <input class="portal-input" id="password" name="password" type="password" required autocomplete="current-password">
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
@endsection
