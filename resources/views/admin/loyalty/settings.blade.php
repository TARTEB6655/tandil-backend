<x-admin-layout>
    @include('admin.loyalty._theme')
    @php $a = $settings['eligible_activities']; @endphp

    <div class="mx-auto max-w-3xl space-y-6"
         x-data="{ targeting: @js(old('customer_targeting', $settings['customer_targeting'])) }">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Loyalty settings</h1>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Same payload as <code class="text-xs">GET/PUT /api/admin/loyalty/settings</code></p>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Pts/AED</p>
                <p class="mt-1 text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ $settings['points_per_aed'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Activities</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ $settings['activities_selected'] }}</p>
            </div>
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4 dark:border-teal-800 dark:bg-teal-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Status</p>
                <p class="mt-1 text-2xl font-bold text-teal-900 dark:text-teal-100">{{ $settings['status'] }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.loyalty.settings.save') }}" class="space-y-5">
            @csrf

            <div class="flex items-center justify-between rounded-xl border-2 border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Loyalty system enabled</p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">When off, customers cannot earn or redeem points.</p>
                </div>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="hidden" name="loyalty_system_enabled" value="0">
                    <input type="checkbox" name="loyalty_system_enabled" value="1" class="peer sr-only" {{ old('loyalty_system_enabled', $settings['loyalty_system_enabled']) ? 'checked' : '' }}>
                    <span class="h-7 w-12 rounded-full bg-gray-300 peer-checked:ly-toggle-on after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                </label>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Earning</h2>
                <label class="mt-3 block text-sm font-medium text-gray-700 dark:text-gray-300">Points per AED</label>
                <input type="number" name="points_per_aed" min="0" value="{{ old('points_per_aed', $settings['points_per_aed']) }}"
                       class="mt-1.5 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                @error('points_per_aed')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Eligible activities</h2>
                <div class="mt-3 space-y-2">
                    @foreach(['shop_orders' => 'Shop orders', 'service_orders' => 'Service orders', 'memberships' => 'Memberships'] as $key => $label)
                        <label class="flex items-center gap-3 text-sm text-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="eligible_activities[{{ $key }}]" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   {{ old('eligible_activities.'.$key, $a[$key] ?? false) ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Expiry</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Points expiry (months)</label>
                    <p class="text-xs text-gray-500">Leave empty for never.</p>
                    <input type="number" name="points_expiry_months" min="1" value="{{ old('points_expiry_months', $settings['points_expiry_months']) }}"
                           class="mt-1.5 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Rewards expiry (months)</label>
                    <input type="number" name="rewards_expiry_months" min="1" value="{{ old('rewards_expiry_months', $settings['rewards_expiry_months']) }}"
                           class="mt-1.5 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                </div>
            </div>

            <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Restrictions</h2>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cities (comma-separated)</label>
                    <p class="text-xs text-gray-500">Empty = all cities. Example: Abu Dhabi, Dubai</p>
                    <input type="text" name="cities" value="{{ old('cities', $settings['cities']) }}"
                           class="mt-1.5 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer targeting</p>
                    <p class="text-xs text-gray-500">{{ $settings['customer_targeting_label'] ?? '' }}</p>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button type="button" @click="targeting='all'"
                                :class="targeting==='all' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                class="rounded-xl px-3 py-2 text-sm font-medium">All customers</button>
                        <button type="button" @click="targeting='specific'"
                                :class="targeting==='specific' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                class="rounded-xl px-3 py-2 text-sm font-medium">Specific customer</button>
                    </div>
                    <input type="hidden" name="customer_targeting" :value="targeting">
                    <div x-show="targeting==='specific'" x-cloak class="mt-3">
                        @if(!empty($settings['specific_customers']))
                            <div class="mb-2 flex flex-wrap gap-1.5">
                                @foreach($settings['specific_customers'] as $name)
                                    <span class="rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-800">{{ $name }}</span>
                                @endforeach
                            </div>
                        @endif
                        <p class="mb-2 text-xs text-gray-500">Choose one or more customers.</p>
                        <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/40">
                            @php $selectedIds = old('specific_customer_ids', $settings['specific_customer_ids'] ?? []); @endphp
                            @forelse(($clients ?? []) as $client)
                                <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                    <input type="checkbox" name="specific_customer_ids[]" value="{{ $client->id }}"
                                           class="rounded text-indigo-600 focus:ring-indigo-500"
                                           {{ in_array($client->id, (array) $selectedIds, false) || in_array((string) $client->id, array_map('strval', (array) $selectedIds), true) ? 'checked' : '' }}>
                                    {{ $client->name }}
                                </label>
                            @empty
                                <p class="text-xs text-gray-500">No clients found.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-700">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Campaign periods only</p>
                        <p class="text-xs text-gray-500">Earn points only during active campaigns.</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="hidden" name="campaign_periods_only" value="0">
                        <input type="checkbox" name="campaign_periods_only" value="1" class="peer sr-only" {{ old('campaign_periods_only', $settings['campaign_periods_only']) ? 'checked' : '' }}>
                        <span class="h-7 w-12 rounded-full bg-gray-300 peer-checked:ly-toggle-on after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="ly-btn w-full rounded-lg px-4 py-3 text-sm font-semibold shadow-sm">Save settings</button>
        </form>
    </div>
</x-admin-layout>
