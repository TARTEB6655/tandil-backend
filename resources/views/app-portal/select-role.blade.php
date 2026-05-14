@extends('layouts.app-portal')

@section('title', __('Choose your role'))

@section('content')
<div class="portal-page portal-page--roles">
    <div class="portal-shell">
        <header style="text-align:center;margin-bottom:1.25rem;">
            <div class="portal-logo-wrap">
                <img src="{{ asset('images/logo.png') }}" alt="TANDIL" width="70" height="70" decoding="async">
            </div>
            <p class="portal-brandline" style="margin-top:12px;"><span dir="rtl">تنديل</span> &nbsp;|&nbsp; TANDIL</p>
            <h1 class="portal-title" style="margin-top:14px;">{{ __('Choose Your Role') }}</h1>
            <p class="portal-sub">{{ __('Role Selection Screen Active') }}</p>
        </header>

        @if ($authUser)
            <div class="portal-alert" style="background:#fffbeb;border:1px solid #fcd34d;color:#78350f;">
                <strong>{{ __('You are already signed in') }}</strong><br>
                {{ $authUser->email }}
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;">
                    <a href="{{ route('dashboard.redirect') }}" class="portal-btn" style="width:auto;display:inline-block;padding:8px 14px;font-size:12px;">{{ __('Continue to dashboard') }}</a>
                    <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                        @csrf
                        <button type="submit" style="padding:8px 14px;font-size:12px;border-radius:10px;border:1px solid #b45309;background:#fff;color:#78350f;font-weight:600;cursor:pointer;">{{ __('Sign out to use another account') }}</button>
                    </form>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="portal-alert" style="background:#fef3c7;border:1px solid #f59e0b;color:#92400e;">
                {{ session('error') }}
            </div>
        @endif

        <ul class="portal-role-list">
            @foreach ($portals as $key => $meta)
                <li>
                    <a class="portal-role-link" href="{{ route('app-portal.login', ['portal' => $key]) }}">
                        @include('app-portal.partials.role-icon', ['type' => $meta['icon'] ?? 'user'])
                        <span style="min-width:0;">
                            <p class="portal-role-title">{{ $meta['title'] }}</p>
                            <p class="portal-role-desc">{{ $meta['subtitle'] }}</p>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="portal-foot portal-muted" style="margin-top:1.5rem;">
            <a class="portal-link" href="{{ route('login') }}">{{ __('Classic staff login') }}</a>
        </p>
    </div>
</div>
@endsection
