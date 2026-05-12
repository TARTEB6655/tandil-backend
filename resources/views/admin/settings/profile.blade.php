@php
    /** @var \App\Models\User $user */
    $pic = $user->profile_picture_url;
    $initial = $user->name ? mb_substr(trim($user->name), 0, 1) : 'A';
@endphp
<x-admin-layout>
    <div
        class="settings-profile-page mx-auto max-w-6xl"
        x-data="{
            photoName: '',
            showCurrent: false,
            showNew: false,
            showConfirm: false,
            pwdLabels: { show: @js(__('admin.show_password')), hide: @js(__('admin.hide_password')) }
        }"
    >
        {{-- Ambient page background (contained) --}}
        <div class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/90 to-emerald-50/25 p-6 shadow-xl shadow-slate-200/30 ring-1 ring-slate-900/5 sm:p-8 lg:p-10 dark:border-slate-700/80 dark:from-slate-900 dark:via-slate-900 dark:to-emerald-950/20 dark:shadow-black/20 dark:ring-white/5">
            <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-emerald-400/10 blur-3xl dark:bg-emerald-500/10"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-indigo-400/10 blur-3xl dark:bg-indigo-500/10"></div>

            <div class="relative">
                {{-- Page header --}}
                <header class="mb-8 flex flex-col gap-4 border-b border-slate-200/80 pb-8 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700/80">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/25">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600/90 dark:text-emerald-400/90">{{ __('admin.settings') }}</p>
                            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ __('admin.profile_account_title') }}</h1>
                            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ __('admin.profile_account_subtitle') }}</p>
                        </div>
                    </div>
                </header>

                @if (session('success'))
                    <div
                        class="mb-8 flex items-start gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3.5 text-sm text-emerald-900 shadow-sm dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-100"
                        role="status"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </span>
                        <span class="pt-0.5 font-medium">{{ session('success') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-8">
                    @csrf

                    <div class="grid gap-8 lg:grid-cols-12">
                        {{-- Photo column --}}
                        <aside class="lg:col-span-4">
                            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white/80 shadow-lg shadow-slate-200/20 backdrop-blur-sm dark:border-slate-700/80 dark:bg-slate-900/60 dark:shadow-black/30">
                                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-emerald-50/40 px-5 py-4 dark:border-slate-800 dark:from-slate-800/50 dark:to-emerald-950/30">
                                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('admin.profile_photo_section') }}</h2>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('admin.profile_photo_hint') }}</p>
                                </div>
                                <div class="flex flex-col items-center px-5 py-8 sm:px-6">
                                    <div class="relative">
                                        <div class="absolute -inset-1 rounded-[1.35rem] bg-gradient-to-br from-emerald-400/40 via-teal-400/30 to-indigo-400/30 opacity-80 blur-sm dark:opacity-50"></div>
                                        <div class="relative">
                                            @if ($pic)
                                                <img src="{{ $pic }}" alt="" class="h-36 w-36 rounded-2xl object-cover shadow-md ring-4 ring-white dark:ring-slate-800" />
                                            @else
                                                <div class="flex h-36 w-36 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-700 text-4xl font-bold tracking-tight text-white shadow-lg ring-4 ring-white dark:ring-slate-800">
                                                    {{ strtoupper($initial) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <label
                                        class="group mt-8 flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 px-4 py-5 text-center transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-slate-600 dark:bg-slate-800/40 dark:hover:border-emerald-600 dark:hover:bg-emerald-950/20"
                                    >
                                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 group-hover:text-emerald-700 dark:text-slate-200 dark:group-hover:text-emerald-300">
                                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            {{ __('admin.choose_photo') }}
                                        </span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('admin.profile_photo_hint') }}</span>
                                        <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="sr-only" @change="photoName = $event.target.files[0]?.name || ''" />
                                    </label>
                                    <p x-show="photoName" x-cloak class="mt-3 text-center text-xs font-semibold text-emerald-700 dark:text-emerald-400" x-text="photoName"></p>
                                    @error('profile_picture')
                                        <p class="mt-2 text-center text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </aside>

                        {{-- Form column --}}
                        <div class="space-y-6 lg:col-span-8">
                            {{-- Profile details --}}
                            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 shadow-lg shadow-slate-200/25 backdrop-blur-sm dark:border-slate-700/80 dark:bg-slate-900/70 dark:shadow-black/30">
                                <div class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-800/40">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-900 dark:text-emerald-400 dark:ring-slate-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    </span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('admin.profile_details_section') }}</h2>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('admin.profile_details_hint') }}</p>
                                    </div>
                                </div>
                                <div class="space-y-6 p-5 sm:p-6">
                                    <div>
                                        <label for="name" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('admin.profile_display_name') }}</label>
                                        <input
                                            type="text"
                                            name="name"
                                            id="name"
                                            value="{{ old('name', $user->name) }}"
                                            required
                                            autocomplete="name"
                                            class="block w-full rounded-xl border border-slate-200 bg-white py-3 ps-4 pe-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 hover:shadow-md focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15 dark:border-slate-600 dark:bg-slate-900/80 dark:text-white dark:placeholder:text-slate-500 dark:hover:border-slate-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/20"
                                        />
                                        @error('name')
                                            <p class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('admin.email') }}</label>
                                        <div class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 start-0 flex w-11 items-center justify-center text-slate-400 dark:text-slate-500" aria-hidden="true">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                            </span>
                                            <input
                                                type="email"
                                                name="email"
                                                id="email"
                                                value="{{ old('email', $user->email) }}"
                                                required
                                                autocomplete="email"
                                                class="block w-full rounded-xl border border-slate-200 bg-white py-3 ps-11 pe-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 hover:shadow-md focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15 dark:border-slate-600 dark:bg-slate-900/80 dark:text-white dark:placeholder:text-slate-500 dark:hover:border-slate-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/20"
                                            />
                                        </div>
                                        @error('email')
                                            <p class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </section>

                            {{-- Security --}}
                            <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 shadow-lg shadow-slate-200/25 backdrop-blur-sm dark:border-slate-700/80 dark:bg-slate-900/70 dark:shadow-black/30">
                                <div class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6 dark:border-slate-800 dark:bg-slate-800/40">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-900 dark:text-indigo-400 dark:ring-slate-600">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                    </span>
                                    <div>
                                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('admin.profile_security_section') }}</h2>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('admin.profile_security_hint') }}</p>
                                    </div>
                                </div>
                                <div class="space-y-6 p-5 sm:p-6">
                                    <div>
                                        <label for="current_password" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('admin.current_password') }}</label>
                                        <div class="relative">
                                            <input
                                                :type="showCurrent ? 'text' : 'password'"
                                                name="current_password"
                                                id="current_password"
                                                autocomplete="current-password"
                                                class="block w-full rounded-xl border border-slate-200 bg-white py-3 ps-4 pe-12 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 hover:shadow-md focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15 dark:border-slate-600 dark:bg-slate-900/80 dark:text-white dark:hover:border-slate-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/20"
                                            />
                                            <button
                                                type="button"
                                                class="absolute inset-y-0 end-0 z-10 flex items-center rounded-e-xl px-3 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                                @click.prevent="showCurrent = !showCurrent"
                                                :aria-label="showCurrent ? pwdLabels.hide : pwdLabels.show"
                                                :aria-pressed="showCurrent"
                                            >
                                                <svg x-show="!showCurrent" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                <svg x-cloak x-show="showCurrent" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.967 9.967 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" /></svg>
                                            </button>
                                        </div>
                                        @error('current_password')
                                            <p class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="grid gap-6 sm:grid-cols-2">
                                        <div>
                                            <label for="password" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('admin.new_password') }}</label>
                                            <div class="relative">
                                                <input
                                                    :type="showNew ? 'text' : 'password'"
                                                    name="password"
                                                    id="password"
                                                    autocomplete="new-password"
                                                    class="block w-full rounded-xl border border-slate-200 bg-white py-3 ps-4 pe-12 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 hover:shadow-md focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15 dark:border-slate-600 dark:bg-slate-900/80 dark:text-white dark:hover:border-slate-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/20"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute inset-y-0 end-0 z-10 flex items-center rounded-e-xl px-3 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                                    @click.prevent="showNew = !showNew"
                                                    :aria-label="showNew ? pwdLabels.hide : pwdLabels.show"
                                                    :aria-pressed="showNew"
                                                >
                                                    <svg x-show="!showNew" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    <svg x-cloak x-show="showNew" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.967 9.967 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" /></svg>
                                                </button>
                                            </div>
                                            @error('password')
                                                <p class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('admin.confirm_password') }}</label>
                                            <div class="relative">
                                                <input
                                                    :type="showConfirm ? 'text' : 'password'"
                                                    name="password_confirmation"
                                                    id="password_confirmation"
                                                    autocomplete="new-password"
                                                    class="block w-full rounded-xl border border-slate-200 bg-white py-3 ps-4 pe-12 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 hover:shadow-md focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15 dark:border-slate-600 dark:bg-slate-900/80 dark:text-white dark:hover:border-slate-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400/20"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute inset-y-0 end-0 z-10 flex items-center rounded-e-xl px-3 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                                    @click.prevent="showConfirm = !showConfirm"
                                                    :aria-label="showConfirm ? pwdLabels.hide : pwdLabels.show"
                                                    :aria-pressed="showConfirm"
                                                >
                                                    <svg x-show="!showConfirm" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    <svg x-cloak x-show="showConfirm" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.967 9.967 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {{-- Actions --}}
                            <div class="flex flex-col-reverse gap-4 rounded-2xl border border-slate-200/80 bg-white/90 px-5 py-4 shadow-md shadow-slate-200/20 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-700/80 dark:bg-slate-900/70 dark:shadow-black/25">
                                <p class="max-w-md text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('admin.profile_save_footer_note') }}</p>
                                <button
                                    type="submit"
                                    class="admin-profile-save-btn inline-flex min-h-[2.875rem] min-w-[11rem] shrink-0 cursor-pointer items-center justify-center gap-2 rounded-xl px-7 py-3 text-sm font-semibold tracking-wide shadow-lg transition focus:outline-none active:scale-[0.98]"
                                >
                                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ __('admin.save_changes') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
