<x-admin-layout>
    @include('admin.loyalty._theme')
    @php
        $isEdit = $campaign !== null;
        $targeting = old('customer_targeting', $campaign->customer_targeting ?? 'all');
        $acts = old('eligible_activities', $campaign->eligible_activities ?? ['shop_orders' => true, 'service_orders' => true]);
        $selectedIds = old('specific_customer_ids', $campaign->specific_customer_ids ?? []);
    @endphp

    <div class="mx-auto max-w-2xl space-y-5" x-data="{ targeting: @js($targeting) }">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.campaigns') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-semibold text-gray-900">{{ $isEdit ? 'Edit campaign' : 'Add campaign' }}</h1>
        </div>

        <form method="POST"
              action="{{ $isEdit ? route('admin.loyalty.campaigns.update', $campaign->id) : route('admin.loyalty.campaigns.store') }}"
              class="space-y-4 rounded-2xl ly-bg-beige p-5">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" value="{{ old('title', $campaign->title ?? '') }}" required
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Multiplier</label>
                <input type="number" step="0.1" min="1" max="10" name="multiplier" value="{{ old('multiplier', $campaign->multiplier ?? 2) }}" required
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Start date</label>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($campaign->start_date ?? null)->format('Y-m-d')) }}" required
                           class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">End date</label>
                    <input type="date" name="end_date" value="{{ old('end_date', optional($campaign->end_date ?? null)->format('Y-m-d')) }}" required
                           class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Cities (comma-separated)</label>
                <input type="text" name="cities" value="{{ old('cities', $campaign->cities ?? '') }}" placeholder="Abu Dhabi, Dubai"
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700">Customer targeting</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" @click="targeting='all'" :class="targeting==='all' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600'" class="rounded-xl px-3 py-2 text-sm font-medium">All customers</button>
                    <button type="button" @click="targeting='specific'" :class="targeting==='specific' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600'" class="rounded-xl px-3 py-2 text-sm font-medium">Specific customer</button>
                </div>
                <input type="hidden" name="customer_targeting" :value="targeting">
                <div x-show="targeting==='specific'" x-cloak class="mt-3">
                    <p class="text-xs text-gray-500 mb-2">Choose one or more customers for this campaign.</p>
                    @if(!empty($selectedIds))
                        <div class="mb-2 flex flex-wrap gap-2">
                            @foreach(($clients ?? []) as $client)
                                @if(in_array($client->id, (array) $selectedIds, false) || in_array((string) $client->id, array_map('strval', (array) $selectedIds), true))
                                    <span class="rounded-full border border-[#1B4332]/20 bg-white px-3 py-1 text-xs font-medium text-[#1B4332]">{{ $client->name }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg bg-white p-3">
                        @forelse(($clients ?? []) as $client)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="specific_customer_ids[]" value="{{ $client->id }}"
                                       class="rounded text-[#1B4332] focus:ring-[#1B4332]"
                                       {{ in_array($client->id, (array) $selectedIds, false) || in_array((string) $client->id, array_map('strval', (array) $selectedIds), true) ? 'checked' : '' }}>
                                {{ $client->name }}
                            </label>
                        @empty
                            <p class="text-xs text-gray-500">No clients found.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700">Eligible activities</p>
                <div class="mt-2 space-y-2">
                    @foreach(['shop_orders' => 'Shop orders', 'service_orders' => 'Service orders', 'memberships' => 'Memberships', 'referrals' => 'Referrals', 'reviews' => 'Reviews'] as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="eligible_activities[{{ $key }}]" value="1" class="rounded text-[#1B4332] focus:ring-[#1B4332]"
                                   {{ !empty($acts[$key]) ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" rows="3" placeholder="Optional internal notes"
                          class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">{{ old('notes', $campaign->notes ?? '') }}</textarea>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-900">Enabled</span>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1" class="peer sr-only" {{ old('is_enabled', $campaign->is_enabled ?? true) ? 'checked' : '' }}>
                    <span class="h-7 w-12 rounded-full bg-gray-300 peer-checked:ly-toggle-on after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                </label>
            </div>

            <button type="submit" class="ly-btn w-full rounded-xl px-4 py-3 text-sm font-semibold">Save campaign</button>
        </form>
    </div>
</x-admin-layout>
