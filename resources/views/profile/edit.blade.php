@auth
    @if(auth()->user()->role === 'admin')
        <x-admin-layout>
            <div class="space-y-4 sm:space-y-6">
                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-lg sm:text-xl font-medium text-gray-900">Profile Settings</h1>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Update your account's profile information and email address</p>
                </div>

                <!-- Success Message -->
                @if(session('status') === 'profile-updated')
                    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs sm:text-sm text-green-700">Profile updated successfully.</span>
                        </div>
                    </div>
                @endif

                <!-- Profile Information Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <!-- Update Password Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-password-form')
                </div>

                <!-- Delete Account Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </x-admin-layout>
    @elseif(auth()->user()->role === 'client')
        <!-- For client users, use the client layout -->
        <x-client-layout>
            <div class="mx-auto max-w-2xl space-y-6">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Personal information</h1>
                    <p class="mt-1 text-sm text-gray-500">Update your name, email, and phone. Matches the indigo dashboard style used across the client portal.</p>
                </div>

                @if(session('status') === 'profile-updated')
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm font-medium text-emerald-800">Profile updated successfully.</span>
                        </div>
                    </div>
                @endif

                @if(auth()->user()->needsPhone())
                    <a href="{{ route('client.phone.edit') }}"
                       class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 hover:bg-amber-100/70 transition-colors">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-amber-900">Phone number missing</p>
                            <p class="mt-0.5 text-sm text-amber-700">Add it once so checkout doesn’t ask every time.</p>
                        </div>
                        <span class="text-sm font-semibold text-amber-800 self-center">Add →</span>
                    </a>
                @endif

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-gray-900">Profile details</h2>
                        <p class="mt-1 text-sm text-gray-500">Name, email, and phone on your account.</p>
                    </div>
                    <div class="px-5 py-5">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-gray-900">Password</h2>
                        <p class="mt-1 text-sm text-gray-500">Keep your account secure with a strong password.</p>
                    </div>
                    <div class="px-5 py-5">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-gray-900">Delete account</h2>
                        <p class="mt-1 text-sm text-gray-500">Permanently remove your account and data.</p>
                    </div>
                    <div class="px-5 py-5">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </x-client-layout>
    @elseif(auth()->user()->role === 'technician')
        <!-- For technician users, use the technician layout -->
        <x-technician-layout>
            <div class="space-y-4 sm:space-y-6">
                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-lg sm:text-xl font-medium text-gray-900">Profile Settings</h1>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Update your account's profile information and email address</p>
                </div>

                <!-- Success Message -->
                @if(session('status') === 'profile-updated')
                    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs sm:text-sm text-green-700">Profile updated successfully.</span>
                        </div>
                    </div>
                @endif

                <!-- Profile Information Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <!-- Update Password Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-password-form')
                </div>

                <!-- Delete Account Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </x-technician-layout>
    @elseif(auth()->user()->role === 'supervisor')
        <!-- For supervisor users, use the supervisor layout -->
        <x-supervisor-layout>
            <div class="space-y-4 sm:space-y-6">
                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-lg sm:text-xl font-medium text-gray-900">Profile Settings</h1>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Update your account's profile information and email address</p>
                </div>

                <!-- Success Message -->
                @if(session('status') === 'profile-updated')
                    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs sm:text-sm text-green-700">Profile updated successfully.</span>
                        </div>
                    </div>
                @endif

                <!-- Profile Information Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <!-- Update Password Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-password-form')
                </div>

                <!-- Delete Account Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </x-supervisor-layout>
    @elseif(auth()->user()->role === 'area_manager')
        <x-areamanager-layout>
            <div class="space-y-4 sm:space-y-6">
                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-lg sm:text-xl font-medium text-gray-900">Profile Settings</h1>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Update your account's profile information and email address</p>
                </div>

                <!-- Success Message -->
                @if(session('status') === 'profile-updated')
                    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs sm:text-sm text-green-700">Profile updated successfully.</span>
                        </div>
                    </div>
                @endif

                <!-- Profile Information Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <!-- Update Password Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-password-form')
                </div>

                <!-- Delete Account Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </x-areamanager-layout>
    @elseif(auth()->user()->role === 'hr')
        <x-hr-layout>
            <div class="space-y-4 sm:space-y-6">
                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-lg sm:text-xl font-medium text-gray-900">Profile Settings</h1>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Update your account's profile information and email address</p>
                </div>

                <!-- Success Message -->
                @if(session('status') === 'profile-updated')
                    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs sm:text-sm text-green-700">Profile updated successfully.</span>
                        </div>
                    </div>
                @endif

                <!-- Profile Information Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <!-- Update Password Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-password-form')
                </div>

                <!-- Delete Account Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </x-hr-layout>
    @elseif(auth()->user()->role === 'vendor')
        <x-vendor-layout>
            <div class="space-y-4 sm:space-y-6">
                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <h1 class="text-lg sm:text-xl font-medium text-gray-900">Profile Settings</h1>
                    <p class="mt-1 text-xs sm:text-sm text-gray-500">Update your account's profile information and email address</p>
                </div>

                <!-- Success Message -->
                @if(session('status') === 'profile-updated')
                    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs sm:text-sm text-green-700">Profile updated successfully.</span>
                        </div>
                    </div>
                @endif

                <!-- Profile Information Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <!-- Update Password Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                    @include('profile.partials.update-password-form')
                </div>

                <!-- Delete Account Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </x-vendor-layout>
    @else
        <!-- For other roles, use the default app layout -->
        <x-app-layout>
            <x-slot name="header">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Profile') }}
                </h2>
            </x-slot>

            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>

                    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                        <div class="max-w-xl">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </div>
            </div>
        </x-app-layout>
    @endif
@endauth
