@extends('layouts.app-portal')

@section('title', __('Log in'))

@section('content')
@php
    $inputClass = 'mt-1.5 block w-full rounded-xl border border-slate-200/90 bg-slate-50 px-3.5 py-2.5 text-[15px] text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-[#2d4a3e] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#2d4a3e]/20';
@endphp
<div class="min-h-screen bg-gradient-to-b from-slate-100 to-[#eef1f4] px-4 py-8 sm:py-10">
    <div class="mx-auto w-full max-w-[380px]">
        @if ($authUser ?? null)
            <div class="mb-5 rounded-xl border border-sky-200/80 bg-sky-50/95 px-4 py-3 text-[13px] leading-relaxed text-sky-950 shadow-sm ring-1 ring-sky-100">
                <p class="font-semibold text-sky-950">{{ __('Switching account') }}</p>
                <p class="mt-1 text-sky-900/90">{{ __('Currently signed in as :email. Submitting this form will sign you in as the account you enter below.', ['email' => $authUser->email]) }}</p>
            </div>
        @endif

        {{-- Brand header: compact for desktop --}}
        <header class="mb-6 text-center">
            <div class="mx-auto flex h-[70px] w-[70px] items-center justify-center rounded-2xl bg-white shadow-md shadow-slate-900/8 ring-1 ring-slate-200/80">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="TANDIL"
                    width="70"
                    height="70"
                    class="h-[70px] w-[70px] rounded-2xl object-contain p-1"
                    decoding="async"
                >
            </div>
            <p class="mt-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Signing in as') }}</p>
            <p class="mt-1 text-base font-bold tracking-tight text-[#1e3d32]">{{ $portalLabel }}</p>
            <p class="mx-auto mt-1.5 max-w-[32ch] text-[13px] leading-snug text-slate-600">{{ $portalSubtitle }}</p>
            <div class="mt-3 flex items-center justify-center gap-2 text-[13px] text-[#2d4a3e]">
                <span dir="rtl" class="font-medium">تنديل</span>
                <span class="text-slate-300" aria-hidden="true">|</span>
                <span class="font-semibold tracking-wide">TANDIL</span>
            </div>
        </header>

        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xl shadow-slate-900/[0.06] ring-1 ring-slate-900/[0.03] sm:p-7">
            <form method="POST" action="{{ route('app-portal.login.submit') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-[13px] font-semibold text-slate-700">{{ __('Email') }}</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="{{ $inputClass }}"
                    />
                    @error('email')
                        <p class="mt-2 text-[13px] font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-[13px] font-semibold text-slate-700">{{ __('Password') }}</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="{{ $inputClass }}"
                    />
                    @error('password')
                        <p class="mt-2 text-[13px] font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Single row: remember left, forgot right (no flex order hacks = no overlap glitches) --}}
                <div class="flex items-center justify-between gap-4 pt-0.5">
                    <label class="flex cursor-pointer select-none items-center gap-2.5 text-[13px] text-slate-600">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="h-4 w-4 shrink-0 rounded border-slate-300 text-[#2d4a3e] focus:ring-2 focus:ring-[#2d4a3e]/30"
                            {{ old('remember') ? 'checked' : '' }}
                        >
                        <span>{{ __('Remember me') }}</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a
                            href="{{ route('password.request') }}"
                            class="shrink-0 text-[13px] font-medium text-sky-700 underline decoration-sky-700/30 underline-offset-2 transition hover:text-sky-900 hover:decoration-sky-900/40"
                        >
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>

                <div class="pt-1">
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-xl bg-[#1a2332] px-4 py-3 text-[13px] font-bold uppercase tracking-[0.12em] text-white shadow-md shadow-slate-900/15 transition hover:bg-[#141b26] focus:outline-none focus:ring-2 focus:ring-[#2d4a3e] focus:ring-offset-2 active:scale-[0.99]"
                    >
                        {{ __('Log in') }}
                    </button>
                </div>

                @if (Route::has('register'))
                    <p class="border-t border-slate-100 pt-5 text-center text-[13px] text-slate-600">
                        <span>{{ __('Not registered?') }}</span>
                        <a href="{{ route('register') }}" class="font-semibold text-[#2d4a3e] underline decoration-[#2d4a3e]/25 underline-offset-2 hover:decoration-[#2d4a3e]/60">
                            {{ __('Register here') }}
                        </a>
                    </p>
                @endif
            </form>

            <div class="mt-6 border-t border-slate-100 pt-5 text-center">
                <a
                    href="{{ route('app-portal.roles') }}"
                    class="inline-flex items-center gap-1.5 text-[13px] font-medium text-slate-500 transition hover:text-[#2d4a3e]"
                >
                    <span class="text-slate-400" aria-hidden="true">←</span>
                    {{ __('Change role') }}
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
