@extends('layouts.app-portal')

@section('title', __('Choose your role'))

@section('content')
<div class="min-h-screen bg-gradient-to-b from-[#f4f1eb] via-[#f0ebe3] to-[#e8e2da] px-4 py-8 sm:py-10">
    <div class="mx-auto w-full max-w-[440px]">
        {{-- Compact brand: logo fixed 70×70 --}}
        <header class="mb-6 text-center sm:mb-7">
            <div class="mx-auto flex h-[70px] w-[70px] items-center justify-center rounded-2xl bg-white shadow-md shadow-slate-900/10 ring-1 ring-[#2d4a3e]/10">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="TANDIL"
                    width="70"
                    height="70"
                    class="h-[70px] w-[70px] rounded-2xl object-contain p-1"
                    decoding="async"
                >
            </div>
            <div class="mt-3 flex items-center justify-center gap-2 text-[13px] font-semibold text-[#2d4a3e]">
                <span dir="rtl">تنديل</span>
                <span class="text-stone-300" aria-hidden="true">|</span>
                <span class="tracking-wide">TANDIL</span>
            </div>
            <h1 class="mt-4 text-lg font-bold tracking-tight text-stone-900 sm:text-xl">{{ __('Choose Your Role') }}</h1>
            <p class="mt-1.5 text-[13px] text-stone-600">{{ __('Role Selection Screen Active') }}</p>
        </header>

        @if ($authUser)
            <div class="mb-5 rounded-xl border border-amber-200/90 bg-amber-50/95 px-4 py-3.5 text-[13px] text-amber-950 shadow-sm ring-1 ring-amber-100">
                <p class="font-semibold">{{ __('You are already signed in') }}</p>
                <p class="mt-1 text-amber-900/90">{{ $authUser->email }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('dashboard.redirect') }}" class="inline-flex items-center rounded-lg bg-[#2d4a3e] px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#243d32]">{{ __('Continue to dashboard') }}</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-amber-800/25 bg-white px-3 py-2 text-xs font-semibold text-amber-950 hover:bg-amber-100">{{ __('Sign out to use another account') }}</button>
                    </form>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] font-medium text-amber-900 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <ul class="flex flex-col gap-3 sm:gap-3.5" role="list">
            @foreach ($portals as $key => $meta)
                <li>
                    <a
                        href="{{ route('app-portal.login', ['portal' => $key]) }}"
                        class="group flex w-full items-start gap-4 rounded-2xl border border-stone-200/90 bg-white/85 px-5 py-4 text-left shadow-sm shadow-stone-900/[0.04] ring-1 ring-white/60 transition hover:border-[#2d4a3e]/25 hover:bg-white hover:shadow-md hover:shadow-stone-900/[0.07] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#2d4a3e]/35"
                    >
                        @include('app-portal.partials.role-icon', ['type' => $meta['icon'] ?? 'user'])
                        <span class="min-w-0 flex-1 pt-0.5">
                            <span class="block text-[15px] font-bold leading-snug text-stone-900 group-hover:text-[#1e3d32]">{{ $meta['title'] }}</span>
                            <span class="mt-1.5 block text-[13px] leading-relaxed text-stone-600">{{ $meta['subtitle'] }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="mt-8 pb-4 text-center text-[12px] text-stone-500">
            <a href="{{ route('login') }}" class="font-medium underline decoration-stone-300 underline-offset-2 hover:text-stone-800 hover:decoration-stone-500">{{ __('Classic staff login') }}</a>
        </p>
    </div>
</div>
@endsection
