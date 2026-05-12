@php
    /** @var \App\Models\User $user */
    $pic = $user->profile_picture_url;
    $initial = $user->name ? mb_substr(trim($user->name), 0, 1) : 'A';
@endphp
<x-admin-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ __('admin.profile_account_title') }}</h1>
            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.profile_account_subtitle') }}</p>
        </div>

        @if (session('success'))
            <div class="mb-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-8" x-data="{ photoName: '' }">
            @csrf

            <div class="grid gap-8 lg:grid-cols-12">
                <!-- Photo card -->
                <div class="lg:col-span-4">
                    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="border-b border-gray-100 bg-gradient-to-br from-emerald-50 to-teal-50/80 px-5 py-4 dark:border-gray-800 dark:from-emerald-950/40 dark:to-gray-900">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.profile_photo_section') }}</h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.profile_photo_hint') }}</p>
                        </div>
                        <div class="flex flex-col items-center px-5 py-8">
                            <div class="relative">
                                @if ($pic)
                                    <img src="{{ $pic }}" alt="" class="h-32 w-32 rounded-2xl object-cover shadow-md ring-4 ring-white dark:ring-gray-800" />
                                @else
                                    <div class="flex h-32 w-32 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-600 text-4xl font-bold text-white shadow-md ring-4 ring-white dark:ring-gray-800">
                                        {{ strtoupper($initial) }}
                                    </div>
                                @endif
                            </div>
                            <label class="mt-6 flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-emerald-300 hover:bg-emerald-50/50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/30">
                                <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span>{{ __('admin.choose_photo') }}</span>
                                <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="sr-only" @change="photoName = $event.target.files[0]?.name || ''" />
                            </label>
                            <p x-show="photoName" x-cloak class="mt-2 text-center text-xs font-medium text-emerald-700 dark:text-emerald-400" x-text="photoName"></p>
                            @error('profile_picture')
                                <p class="mt-2 text-center text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Main fields -->
                <div class="space-y-6 lg:col-span-8">
                    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.profile_details_section') }}</h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.profile_details_hint') }}</p>
                        </div>
                        <div class="space-y-5 p-5 sm:p-6">
                            <div>
                                <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.profile_display_name') }}</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required autocomplete="name"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 py-2.5 text-sm text-gray-900 shadow-inner transition placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-600 dark:bg-gray-800/50 dark:text-white dark:focus:border-emerald-500 dark:focus:bg-gray-900" />
                                @error('name')
                                    <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.email') }}</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required autocomplete="email"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 py-2.5 text-sm text-gray-900 shadow-inner transition placeholder:text-gray-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-600 dark:bg-gray-800/50 dark:text-white dark:focus:border-emerald-500 dark:focus:bg-gray-900" />
                                @error('email')
                                    <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('admin.profile_security_section') }}</h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('admin.profile_security_hint') }}</p>
                        </div>
                        <div class="space-y-5 p-5 sm:p-6">
                            <div>
                                <label for="current_password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.current_password') }}</label>
                                <input type="password" name="current_password" id="current_password" autocomplete="current-password"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 py-2.5 text-sm text-gray-900 shadow-inner transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-600 dark:bg-gray-800/50 dark:text-white dark:focus:border-emerald-500 dark:focus:bg-gray-900" />
                                @error('current_password')
                                    <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.new_password') }}</label>
                                    <input type="password" name="password" id="password" autocomplete="new-password"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 py-2.5 text-sm text-gray-900 shadow-inner transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-600 dark:bg-gray-800/50 dark:text-white dark:focus:border-emerald-500 dark:focus:bg-gray-900" />
                                    @error('password')
                                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.confirm_password') }}</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50/50 px-4 py-2.5 text-sm text-gray-900 shadow-inner transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-600 dark:bg-gray-800/50 dark:text-white dark:focus:border-emerald-500 dark:focus:bg-gray-900" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <p class="text-xs text-gray-500 dark:text-gray-400 sm:mr-auto">{{ __('admin.profile_save_footer_note') }}</p>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:from-emerald-700 hover:to-teal-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            {{ __('admin.save_changes') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
