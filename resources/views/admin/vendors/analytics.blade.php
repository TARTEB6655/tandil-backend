<x-admin-layout>
    <div class="space-y-6 max-w-6xl">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Performance Analytics</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $vendor->profile?->business_name }} — {{ $analytics['period_label'] ?? ucfirst($period) }}</p>
            </div>
            <a href="{{ route('admin.vendors.show', $vendor) }}" class="px-3 py-2 text-sm border rounded-lg">← Vendor profile</a>
        </div>
        <x-admin.marketplace-nav />

        <form method="GET" class="flex flex-wrap gap-2">
            @foreach($analytics['filters'] ?? [] as $filter)
                <a href="{{ route('admin.vendors.analytics', ['vendor' => $vendor, 'period' => $filter['id']]) }}"
                   class="px-4 py-2 text-sm rounded-lg border {{ !empty($filter['selected']) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50' }}">
                    {{ $filter['label'] }}
                </a>
            @endforeach
        </form>

        @php $overview = $analytics['overview'] ?? []; @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                <p class="text-xs text-gray-500 uppercase">Revenue</p>
                <p class="text-2xl font-semibold mt-1">{{ $overview['total_revenue']['display'] ?? 'AED 0' }}</p>
                @if(!empty($overview['total_revenue']['growth_display']))<p class="text-xs text-gray-500 mt-1">{{ $overview['total_revenue']['growth_display'] }}</p>@endif
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                <p class="text-xs text-gray-500 uppercase">Completed orders</p>
                <p class="text-2xl font-semibold mt-1">{{ $overview['total_orders']['value'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $overview['total_orders']['subtitle'] ?? '' }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                <p class="text-xs text-gray-500 uppercase">Active products</p>
                <p class="text-2xl font-semibold mt-1">{{ $overview['total_products']['value'] ?? 0 }}</p>
                @if(!empty($overview['total_products']['growth_display']))<p class="text-xs text-gray-500 mt-1">{{ $overview['total_products']['growth_display'] }}</p>@endif
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-4">
                <p class="text-xs text-gray-500 uppercase">Product views</p>
                <p class="text-2xl font-semibold mt-1">{{ number_format($overview['total_views']['value'] ?? 0) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $overview['total_views']['subtitle'] ?? '' }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
                <h2 class="font-medium mb-4">Performance metrics</h2>
                <dl class="space-y-3 text-sm">
                    @foreach($analytics['performance_metrics'] ?? [] as $key => $metric)
                        <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                            <dt class="text-gray-600 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="font-semibold">{{ $metric['display'] ?? ($metric['value'] ?? '—') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
                <h2 class="font-medium mb-4">Trends</h2>
                @foreach($analytics['trends'] ?? [] as $trend)
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $trend['title'] ?? 'Trend' }}</p>
                        @if(!empty($trend['data_points']))
                            <div class="flex flex-wrap gap-2">
                                @foreach($trend['data_points'] as $point)
                                    <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700">{{ $point['label'] ?? '' }}: {{ $point['value'] ?? 0 }}</span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-500">No data for this period.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @if(!empty($analytics['top_products']))
            <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
                <h2 class="font-medium mb-4">Top products</h2>
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="pb-2">Product</th><th class="pb-2">Units</th><th class="pb-2 text-right">Revenue</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($analytics['top_products'] as $product)
                            <tr>
                                <td class="py-2">{{ $product['name'] ?? '—' }}</td>
                                <td class="py-2">{{ $product['units_sold'] ?? 0 }}</td>
                                <td class="py-2 text-right">AED {{ number_format($product['revenue'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
            <h2 class="font-medium mb-4">Shared analytics links</h2>
            @if($shares->isEmpty())
                <p class="text-sm text-gray-500">No public share links created by this vendor yet.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="pb-2">Period</th><th class="pb-2">Expires</th><th class="pb-2">Created</th><th class="pb-2 text-right">Link</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($shares as $share)
                            <tr>
                                <td class="py-2">{{ ucfirst($share->period) }}</td>
                                <td class="py-2">{{ $share->expires_at?->format('d M Y') ?? '—' }}</td>
                                <td class="py-2">{{ $share->created_at?->format('d M Y') }}</td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('shared.vendor-analytics', $share->token) }}" target="_blank" class="text-indigo-600 hover:underline">View PDF</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin-layout>
