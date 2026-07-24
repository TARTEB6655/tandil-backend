<x-client-layout>
    @php
        $currentPhone = old('phone', $user->phone);
        $needsPhone = $needsPhone ?? $user->needsPhone();
    @endphp

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-start gap-3">
            <a href="{{ route('profile.edit') }}"
               class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50"
               aria-label="Back to profile">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Phone number</h1>
                <p class="mt-1 text-sm text-gray-500">Save your phone once so we can reach you for orders and visits — no need to enter it every time.</p>
            </div>
        </div>

        @if(session('status') === 'phone-updated')
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-medium text-emerald-800">Phone number saved successfully.</p>
                </div>
            </div>
        @endif

        <div class="rounded-xl border {{ $needsPhone ? 'border-amber-200 bg-amber-50' : 'border-indigo-200 bg-indigo-50' }} px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide {{ $needsPhone ? 'text-amber-700' : 'text-indigo-700' }}">
                {{ $needsPhone ? 'Action needed' : 'Saved on your account' }}
            </p>
            <p class="mt-1 text-2xl font-bold {{ $needsPhone ? 'text-amber-900' : 'text-indigo-900' }}">
                {{ $needsPhone ? 'Add your phone number' : ($user->phone ?: '—') }}
            </p>
            <p class="mt-1 text-sm {{ $needsPhone ? 'text-amber-700' : 'text-indigo-700' }}">
                @if($needsPhone)
                    Google and Apple Sign-In don’t always share a phone. Add it here so checkout won’t ask again.
                @else
                    This number is used for order updates and delivery contact.
                @endif
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-900">{{ $needsPhone ? 'Add phone number' : 'Update phone number' }}</h2>
                <p class="mt-1 text-sm text-gray-500">Use your mobile number with country code (e.g. +971501234567).</p>
            </div>

            <form method="POST" action="{{ route('client.phone.update') }}" class="space-y-5 px-5 py-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone number</label>
                    <div class="mt-1.5 flex overflow-hidden rounded-lg border border-gray-300 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 {{ $errors->has('phone') ? 'border-red-400 focus-within:border-red-500 focus-within:ring-red-500' : '' }}">
                        <span class="inline-flex items-center bg-gray-50 px-3 text-gray-500 border-r border-gray-300">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </span>
                        <input id="phone"
                               name="phone"
                               type="tel"
                               value="{{ $currentPhone }}"
                               required
                               autocomplete="tel"
                               placeholder="Enter your phone number"
                               class="w-full border-0 px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                    @error('phone')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 text-xs text-gray-500">Must be unique. 7–20 characters.</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    Saving here updates your account profile. The mobile app uses the same phone via <span class="font-medium text-gray-800">PUT /api/user/phone</span>.
                </div>

                <div class="border-t border-gray-100 bg-gray-50 -mx-5 -mb-5 px-5 py-4">
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        {{ $needsPhone ? 'Save phone number' : 'Update phone number' }}
                    </button>
                    <p class="mt-2 text-center text-xs text-gray-500">You can change this anytime from Personal Information.</p>
                </div>
            </form>
        </div>
    </div>
</x-client-layout>
