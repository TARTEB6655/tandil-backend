@extends('layouts.app-portal')

@section('title', __('Choose your role'))

@section('content')
<div class="min-h-screen bg-[#f5f0e8] px-4 py-8 sm:py-12">
    <div class="mx-auto flex max-w-md flex-col items-center">
        <div class="mb-6 text-center">
            <img src="{{ asset('images/logo.png') }}" alt="TANDIL" class="mx-auto h-[4.5rem] w-[4.5rem] rounded-2xl object-cover shadow-md ring-1 ring-[#2d4a3e]/15">
            <p class="mt-3 text-base font-bold tracking-wide text-[#2d4a3e]">تنديل</p>
            <p class="text-sm font-bold tracking-[0.2em] text-[#2d4a3e]">TANDIL</p>
            <h1 class="mt-5 text-lg font-semibold text-gray-800">{{ __('Choose Your Role') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Role Selection Screen Active') }}</p>
        </div>

        @if ($authUser)
            <div class="mb-4 w-full rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-sm">
                <p class="font-medium">{{ __('You are already signed in') }}</p>
                <p class="mt-1 text-amber-900/90">{{ $authUser->email }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('dashboard.redirect') }}" class="inline-flex items-center rounded-lg bg-[#2d4a3e] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#243d32]">{{ __('Continue to dashboard') }}</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-amber-800/30 bg-white px-3 py-1.5 text-xs font-semibold text-amber-950 hover:bg-amber-100">{{ __('Sign out to use another account') }}</button>
                    </form>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ session('error') }}
            </div>
        @endif

        <div class="w-full space-y-3">
            @foreach ($portals as $key => $meta)
                <a
                    href="{{ route('app-portal.login', ['portal' => $key]) }}"
                    class="flex w-full items-start gap-3 rounded-2xl border border-gray-300/60 bg-[#e8e2d8] px-4 py-4 text-left shadow-sm transition hover:bg-[#ded6cc] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#2d4a3e]/35"
                >
                    @include('app-portal.partials.role-icon', ['type' => $meta['icon'] ?? 'user'])
                    <span class="min-w-0 flex-1">
                        <span class="block text-base font-semibold text-gray-900">{{ $meta['title'] }}</span>
                        <span class="mt-1 block text-sm leading-snug text-gray-600">{{ $meta['subtitle'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <p class="mt-10 text-center text-xs text-gray-500">
            <a href="{{ route('login') }}" class="underline decoration-gray-400 underline-offset-2 hover:text-gray-700">{{ __('Classic staff login') }}</a>
        </p>
    </div>
</div>
@endsection
