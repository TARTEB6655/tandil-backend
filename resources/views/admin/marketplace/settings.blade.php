<x-admin-layout>
    <div class="max-w-lg space-y-6">
        <h1 class="text-xl font-semibold">Pricing &amp; commission</h1>
        <x-admin.marketplace-nav />
        @if(session('success'))<div class="p-3 bg-green-50 text-green-800 text-sm rounded-lg">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('admin.marketplace.settings.update') }}" class="bg-white dark:bg-gray-800 rounded-xl border p-6 space-y-4">
            @csrf
            <div>
                <label class="text-sm font-medium">Platform commission (%)</label>
                <input type="number" name="marketplace_commission_percent" step="0.01" min="0" max="100" value="{{ old('marketplace_commission_percent', $commissionPercent) }}" required class="mt-1 w-full rounded-lg border-gray-300" />
                <p class="text-xs text-gray-500 mt-1">Applied to vendor order totals unless a vendor has a custom rate.</p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="marketplace_product_approval_required" value="1" @checked(old('marketplace_product_approval_required', $productApprovalRequired)) />
                Require admin approval for new vendor products
            </label>
            <button class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Save settings</button>
        </form>
    </div>
</x-admin-layout>
