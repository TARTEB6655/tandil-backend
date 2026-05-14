@extends('layouts.app-portal')

@section('title', __('Log in'))

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 sm:py-12">
    <div class="mb-8 text-center">
        <img src="{{ asset('images/logo.png') }}" alt="TANDIL" class="mx-auto h-20 w-20 rounded-full object-cover shadow-sm ring-1 ring-gray-200/80">
        <p class="mt-3 text-lg font-bold tracking-wide text-[#2d4a3e]">TANDIL</p>
        <p class="text-sm text-[#2d4a3e]/90" dir="rtl">تنديل</p>
        <p class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('Signing in as') }}</p>
        <p class="text-sm font-semibold text-gray-800">{{ $portalLabel }}</p>
        <p class="mx-auto mt-1 max-w-xs text-xs text-gray-500">{{ $portalSubtitle }}</p>
    </div>

    <div class="w-full max-w-md rounded-2xl bg-white px-8 py-8 shadow-lg ring-1 ring-gray-200/60">
        <form method="POST" action="{{ route('app-portal.login.submit') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-[#edf2f7] px-3 py-2.5 text-gray-900 shadow-inner outline-none transition focus:border-[#4a90e2] focus:ring-2 focus:ring-[#4a90e2]/30"
                />
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 block w-full rounded-lg border border-gray-200 bg-[#edf2f7] px-3 py-2.5 text-gray-900 shadow-inner outline-none transition focus:border-[#4a90e2] focus:ring-2 focus:ring-[#4a90e2]/30"
                />
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-[#1a202c] focus:ring-[#1a202c]" {{ old('remember') ? 'checked' : '' }}>
                    <span>{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex flex-col items-stretch gap-3 pt-1 sm:flex-row sm:items-center sm:justify-end">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="order-2 text-right text-sm text-gray-500 underline underline-offset-2 hover:text-gray-800 sm:order-1 sm:mr-auto sm:text-left">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
                <button
                    type="submit"
                    class="order-1 inline-flex items-center justify-center rounded-lg bg-[#1a202c] px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-black focus:outline-none focus:ring-2 focus:ring-[#1a202c] focus:ring-offset-2 sm:order-2"
                >
                    {{ __('Log in') }}
                </button>
            </div>

            @if (Route::has('register'))
                <div class="border-t border-gray-100 pt-6 text-center">
                    <a href="{{ route('register') }}" class="text-sm text-gray-500 underline underline-offset-2 hover:text-gray-800">
                        {{ __('Not registered? Register here') }}
                    </a>
                </div>
            @endif
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('app-portal.roles') }}" class="text-sm text-gray-500 hover:text-gray-800">{{ __('← Change role') }}</a>
        </div>
    </div>
</div>
@endsection
