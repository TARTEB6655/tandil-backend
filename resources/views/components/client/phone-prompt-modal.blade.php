@auth
    @if(auth()->user()->role === 'client' && auth()->user()->needsPhone())
        <div x-data="{ open: true }"
             x-show="open"
             x-cloak
             class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4 sm:p-6"
             aria-modal="true"
             role="dialog">
            <div x-show="open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute inset-0 bg-gray-900/40"
                 @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
                <div class="border-b border-indigo-100 bg-indigo-50 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-indigo-900">Add your phone number</h2>
                            <p class="mt-1 text-sm text-indigo-700">So we don’t ask again at checkout. Google / Apple sign-in often skips this step.</p>
                        </div>
                        <button type="button" @click="open = false" class="rounded-lg p-1 text-indigo-400 hover:bg-indigo-100 hover:text-indigo-700" aria-label="Close">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <div class="px-5 py-5 space-y-4">
                    <p class="text-sm text-gray-600">Save your mobile once on your account. It syncs with the app and order delivery contact.</p>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <a href="{{ route('client.phone.edit') }}"
                           class="inline-flex flex-1 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                            Add phone number
                        </a>
                        <button type="button" @click="open = false"
                                class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Later
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endauth
