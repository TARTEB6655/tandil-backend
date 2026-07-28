<x-admin-layout>
    @include('admin.loyalty._theme')
    @php $a = $settings['eligible_activities']; @endphp

    <div class="mx-auto max-w-2xl space-y-5"
         x-data="{ targeting: @js(old('customer_targeting', $settings['customer_targeting'])) }">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-semibold text-gray-900">Loyalty settings</h1>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="rounded-2xl ly-bg-beige p-5">
            <p class="text-base font-semibold text-gray-900">Control your loyalty engine</p>
            <p class="mt-1 text-sm text-gray-600">Tune earning rules, expiry windows, and customer restrictions from one place.</p>
            <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-white px-3 py-3 text-center text-sm">
                <div><p class="font-bold ly-green">{{ $settings['pts_per_aed'] }}</p><p class="text-xs text-gray-500">Pts/AED</p></div>
                <div><p class="font-bold ly-green">{{ $settings['activities_selected'] }}</p><p class="text-xs text-gray-500">Activities</p></div>
                <div><p class="font-bold ly-green">{{ $settings['status'] }}</p><p class="text-xs text-gray-500">Status</p></div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.loyalty.settings.save') }}" class="space-y-5">
            @csrf

            <div class="flex items-center justify-between rounded-2xl ly-bg-beige px-5 py-4">
                <div>
                    <p class="text-sm font-semibold text-gray-900">Loyalty system enabled</p>
                    <p class="mt-0.5 text-sm text-gray-500">When off, customers cannot earn or redeem points.</p>
                </div>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="hidden" name="loyalty_system_enabled" value="0">
                    <input type="checkbox" name="loyalty_system_enabled" value="1" class="peer sr-only" {{ old('loyalty_system_enabled', $settings['loyalty_system_enabled']) ? 'checked' : '' }}>
                    <span class="h-7 w-12 rounded-full bg-gray-300 peer-checked:ly-toggle-on after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                </label>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Earning</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Core</span>
                </div>
                <div class="rounded-2xl ly-bg-beige p-4">
                    <label class="block text-sm font-medium text-gray-700">Points per AED</label>
                    <input type="number" name="points_per_aed" min="0" value="{{ old('points_per_aed', $settings['points_per_aed']) }}"
                           class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                    @error('points_per_aed')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Eligible activities</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ $settings['activities_selected'] }} selected</span>
                </div>
                <div class="space-y-2 rounded-2xl ly-bg-beige p-4">
                    @foreach(['shop_orders' => 'Shop orders', 'service_orders' => 'Service orders', 'memberships' => 'Memberships'] as $key => $label)
                        <label class="flex items-center gap-3 text-sm text-gray-800">
                            <input type="checkbox" name="eligible_activities[{{ $key }}]" value="1" class="rounded border-gray-300 text-[#1B4332] focus:ring-[#1B4332]"
                                   {{ old('eligible_activities.'.$key, $a[$key] ?? false) ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Expiry</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Policy</span>
                </div>
                <div class="space-y-4 rounded-2xl ly-bg-beige p-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Points expiry (months)</label>
                        <p class="text-xs text-gray-500">Leave empty for never.</p>
                        <input type="number" name="points_expiry_months" min="1" value="{{ old('points_expiry_months', $settings['points_expiry_months']) }}"
                               class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rewards expiry (months)</label>
                        <input type="number" name="rewards_expiry_months" min="1" value="{{ old('rewards_expiry_months', $settings['rewards_expiry_months']) }}"
                               class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                    </div>
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Restrictions</h2>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Rules</span>
                </div>
                <div class="space-y-4 rounded-2xl ly-bg-beige p-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cities (comma-separated)</label>
                        <p class="text-xs text-gray-500">Empty = all cities. Example: Abu Dhabi, Dubai</p>
                        <input type="text" name="cities" value="{{ old('cities', $settings['cities']) }}"
                               class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Customer targeting</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button type="button" @click="targeting='all'"
                                    :class="targeting==='all' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600'"
                                    class="rounded-xl px-3 py-2 text-sm font-medium">All customers</button>
                            <button type="button" @click="targeting='specific'"
                                    :class="targeting==='specific' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600'"
                                    class="rounded-xl px-3 py-2 text-sm font-medium">Specific customer</button>
                        </div>
                        <input type="hidden" name="customer_targeting" :value="targeting">
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Campaign periods only</p>
                            <p class="text-xs text-gray-500">Earn points only during active campaigns.</p>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="hidden" name="campaign_periods_only" value="0">
                            <input type="checkbox" name="campaign_periods_only" value="1" class="peer sr-only" {{ old('campaign_periods_only', $settings['campaign_periods_only']) ? 'checked' : '' }}>
                            <span class="h-7 w-12 rounded-full bg-gray-300 peer-checked:ly-toggle-on after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="ly-btn w-full rounded-xl px-4 py-3 text-sm font-semibold shadow-sm">Save settings</button>
        </form>
    </div>
</x-admin-layout>
