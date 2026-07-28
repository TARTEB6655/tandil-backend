<x-admin-layout>
    @include('admin.loyalty._theme')
    @php
        $isEdit = $reward !== null;
        $targeting = old('customer_targeting', $reward->customer_targeting ?? 'all');
    @endphp

    <div class="mx-auto max-w-2xl space-y-5" x-data="{ targeting: @js($targeting) }">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.rewards') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-semibold text-gray-900">{{ $isEdit ? 'Edit reward' : 'Add reward' }}</h1>
        </div>

        <form method="POST"
              action="{{ $isEdit ? route('admin.loyalty.rewards.update', $reward->id) : route('admin.loyalty.rewards.store') }}"
              class="space-y-4 rounded-2xl ly-bg-beige p-5">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" value="{{ old('title', $reward->title ?? '') }}" placeholder="Reward title" required
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="3" placeholder="What the customer gets"
                          class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">{{ old('description', $reward->description ?? '') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Points required</label>
                <input type="number" name="points_required" min="1" value="{{ old('points_required', $reward->points_required ?? 500) }}" required
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-900">Active</span>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="peer sr-only" {{ old('is_active', $reward->is_active ?? true) ? 'checked' : '' }}>
                    <span class="h-7 w-12 rounded-full bg-gray-300 peer-checked:ly-toggle-on after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Expires at</label>
                <p class="text-xs text-gray-500">Leave empty for no expiry.</p>
                <input type="date" name="expires_at" value="{{ old('expires_at', optional($reward->expires_at ?? null)->format('Y-m-d')) }}"
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Cities</label>
                <input type="text" name="cities" value="{{ old('cities', $reward->cities ?? '') }}" placeholder="Abu Dhabi, Dubai"
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700">Customer targeting</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" @click="targeting='all'" :class="targeting==='all' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600'" class="rounded-xl px-3 py-2 text-sm font-medium">All customers</button>
                    <button type="button" @click="targeting='specific'" :class="targeting==='specific' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600'" class="rounded-xl px-3 py-2 text-sm font-medium">Specific customer</button>
                </div>
                <input type="hidden" name="customer_targeting" :value="targeting">
            </div>

            <button type="submit" class="ly-btn w-full rounded-xl px-4 py-3 text-sm font-semibold">Save reward</button>
        </form>
    </div>
</x-admin-layout>
