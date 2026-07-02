@extends('layouts.app-portal')

@section('title', __('Vendor registration'))

@section('content')
<div class="portal-page portal-page--register">
    <div class="portal-shell" style="max-width: 52rem;">
        <header style="text-align:center;margin-bottom:1.25rem;">
            <div class="portal-logo-wrap">
                <img src="{{ asset('images/logo.png') }}" alt="TANDIL" width="80" height="80" decoding="async">
            </div>
            <p class="portal-muted" style="margin-top:1rem;font-weight:600;">{{ __('Vendor marketplace') }}</p>
            <h1 class="portal-title" style="margin-top:6px;font-size:1.75rem;">{{ __('Create vendor account') }}</h1>
            <p class="portal-sub">{{ __('Submit your business details for admin review. You cannot access the vendor dashboard until approved.') }}</p>
        </header>

        @if ($errors->any())
            <div class="portal-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;margin-bottom:1rem;">
                <strong>{{ __('Please fix the following:') }}</strong>
                <ul class="mt-2 list-disc pl-5 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="portal-card">
            <form method="POST" action="{{ route('vendor.register.store') }}" enctype="multipart/form-data">
                @csrf

                <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Account credentials') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 mb-6">
                    <div class="portal-field sm:col-span-2">
                        <label class="portal-label" for="email">{{ __('Email') }} *</label>
                        <input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username">
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="password">{{ __('Password') }} *</label>
                        <input class="portal-input" id="password" name="password" type="password" required autocomplete="new-password">
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="password_confirmation">{{ __('Confirm password') }} *</label>
                        <input class="portal-input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </div>
                </div>

                <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Business profile') }}</h2>
                @include('vendor.partials.business-profile-fields', [
                    'profile' => null,
                    'vendorTypes' => $vendorTypes,
                    'emirates' => $emirates,
                    'requireLogo' => false,
                    'requireDocuments' => true,
                    'useOpensClosesTimes' => true,
                    'hideAccountFields' => true,
                ])

                <div class="mt-6 border-t border-slate-200 pt-6">
                    <h2 class="text-base font-semibold text-slate-800 mb-3">{{ __('Business categories') }}</h2>
                    <p class="text-sm text-slate-500 mb-3">{{ __('Optional at signup — you can also choose categories later in onboarding before admin review.') }}</p>
                    @php $selectedCategories = old('category_ids', []); @endphp
                    <div class="grid gap-2 sm:grid-cols-2">
                        @forelse($categories as $category)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                                <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories)) class="rounded border-slate-300 text-indigo-600" />
                                <span>{{ $category->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500 sm:col-span-2">{{ __('No categories available. Contact support.') }}</p>
                        @endforelse
                    </div>
                    @error('category_ids')
                        <p class="portal-err mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="terms_accepted" value="1" class="mt-0.5 rounded border-slate-300 text-indigo-600" @checked(old('terms_accepted')) required />
                        <span>{!! __('I accept the :link of the marketplace.', ['link' => '<a href="'.e(route('legal.privacy-policy')).'" target="_blank" class="text-indigo-600 underline">'.__('Terms & Conditions').'</a>']) !!} *</span>
                    </label>
                    @error('terms_accepted')
                        <p class="portal-err mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="portal-btn mt-6">{{ __('Submit registration for review') }}</button>

                <div class="portal-divider portal-muted mt-4">
                    {{ __('Already registered?') }}
                    <a class="portal-link" href="{{ route('app-portal.login', ['portal' => 'vendor']) }}">{{ __('Log in') }}</a>
                </div>
            </form>

            <div class="portal-foot">
                <a href="{{ route('app-portal.roles') }}">← {{ __('Change role') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
