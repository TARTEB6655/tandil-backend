@extends('layouts.app-portal')

@section('title', __('Choose your role'))

@section('content')
<div class="min-h-screen flex flex-col items-center px-4 py-10 sm:py-14">
    <div class="mb-8 text-center">
        <img src="{{ asset('images/logo.png') }}" alt="TANDIL" class="mx-auto h-20 w-20 rounded-full object-cover shadow-sm ring-1 ring-gray-200/80">
        <p class="mt-3 text-lg font-bold tracking-wide text-[#2d4a3e]">TANDIL</p>
        <p class="text-sm text-[#2d4a3e]/90" dir="rtl">تنديل</p>
        <h1 class="mt-6 text-xl font-semibold text-gray-900">{{ __('Choose your role') }}</h1>
        <p class="mt-1 max-w-md text-sm text-gray-500">{{ __('Select how you use the app. You will be taken to the sign-in page for that role.') }}</p>
    </div>

    @if (session('error'))
        <div class="mb-6 w-full max-w-md rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ session('error') }}
        </div>
    @endif

    <div class="w-full max-w-md space-y-3">
        @foreach ($portals as $key => $meta)
            <a
                href="{{ route('app-portal.login', ['portal' => $key]) }}"
                class="flex w-full flex-col rounded-2xl border border-gray-200/80 bg-[#ebe4d8]/90 px-4 py-4 text-left shadow-sm transition hover:bg-[#e4dccf] hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[#2d4a3e]/40"
            >
                <span class="text-base font-semibold text-gray-900">{{ $meta['title'] }}</span>
                <span class="mt-1 text-sm leading-snug text-gray-600">{{ $meta['subtitle'] }}</span>
            </a>
        @endforeach
    </div>

    <p class="mt-10 text-center text-xs text-gray-400">
        <a href="{{ route('login') }}" class="underline decoration-gray-400 underline-offset-2 hover:text-gray-600">{{ __('Staff web login (classic)') }}</a>
    </p>
</div>
@endsection
