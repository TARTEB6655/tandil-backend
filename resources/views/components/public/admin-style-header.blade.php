@php
    $locale = app()->getLocale();
    $langUrl = fn (string $code) => request()->fullUrlWithQuery(['lang' => $code]);
@endphp

<header class="sticky top-0 z-40 bg-white dark:bg-gray-900 border-b-2 border-gray-200 dark:border-gray-700 dark:shadow-[0_4px_24px_rgba(0,0,0,0.2)] shadow-md py-4">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex w-full flex-wrap items-center gap-3 lg:flex-nowrap lg:gap-4">
            <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">
                <a href="{{ auth()->check() ? route('dashboard.redirect') : url('/') }}" class="flex items-center gap-2 sm:gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="Tandil" class="h-10 w-10 object-contain" />
                    <span class="hidden text-lg font-semibold text-gray-900 dark:text-gray-100 sm:inline">Tandil</span>
                </a>
            </div>

            <div class="order-2 ml-auto flex items-center gap-3 sm:gap-4 lg:order-3 lg:ml-0 flex-shrink-0">
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="p-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                        aria-label="{{ __('admin.language') }}"
                        title="{{ __('admin.language') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                    </button>
                    <div
                        x-show="open"
                        x-transition
                        @click.away="open = false"
                        class="absolute right-0 mt-2 w-44 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50"
                        style="display: none;"
                    >
                        <a href="{{ $langUrl('en') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $locale === 'en' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : '' }}">
                            {{ __('admin.english') }}
                        </a>
                        <a href="{{ $langUrl('ar') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $locale === 'ar' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : '' }}" dir="rtl">
                            {{ __('admin.arabic') }}
                        </a>
                        <a href="{{ $langUrl('ur') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $locale === 'ur' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : '' }}" dir="rtl">
                            {{ __('admin.urdu') }}
                        </a>
                    </div>
                </div>

                @auth
                    <a href="{{ route('dashboard.redirect') }}" class="hidden text-sm font-medium text-indigo-600 hover:text-indigo-800 sm:inline">
                        {{ __('admin.dashboard') }}
                    </a>
                @endauth
            </div>
        </div>
    </div>
</header>
