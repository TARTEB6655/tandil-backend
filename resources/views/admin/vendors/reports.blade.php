<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav />

        <x-admin.vendor.page-header
            title="Vendor Reports"
            description="Export marketplace data and access key reporting shortcuts." />

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <x-admin.vendor.card title="Vendor export">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Download a CSV of all vendors with performance metrics.</p>
                <x-admin.vendor.btn variant="primary" :href="route('admin.vendors.export')">Export vendors CSV</x-admin.vendor.btn>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Revenue overview">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Platform-wide revenue, commissions, and payout estimates.</p>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.revenue')">Open revenue dashboard</x-admin.vendor.btn>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Analytics">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Growth trends, top vendors, and marketplace insights.</p>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.insights')">Open analytics</x-admin.vendor.btn>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="All products">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Browse and moderate every vendor product listing.</p>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.marketplace.products.index')">View products</x-admin.vendor.btn>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="All orders">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Track orders across all vendors in one place.</p>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.marketplace.orders.index')">View orders</x-admin.vendor.btn>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Overview snapshot">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Return to the vendor ecosystem dashboard.</p>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.overview')">Vendor overview</x-admin.vendor.btn>
            </x-admin.vendor.card>
        </div>

        <x-admin.vendor.card title="Current snapshot">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach(array_slice($data['kpis'], 0, 8) as $kpi)
                    <div class="rounded-md border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                        <p class="text-xs text-gray-500">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-lg font-semibold {{ $kpi['accent'] }}">{{ $kpi['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-admin.vendor.card>
    </x-admin.vendor.shell>
</x-admin-layout>
