<x-admin-layout>
    <div class="space-y-6 max-w-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.settings.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Customer Dashboard Design</h1>
                <p class="mt-1 text-sm text-gray-500">Control what customers see on their dashboard. Title, subtitle, and section visibility.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <form method="POST" action="{{ route('admin.settings.client-dashboard.store') }}">
                @csrf
                <div class="p-6 space-y-6">
                    <div>
                        <label for="client_dashboard_title" class="block text-sm font-medium text-gray-700 mb-1">Dashboard title</label>
                        <input type="text" name="client_dashboard_title" id="client_dashboard_title" value="{{ old('client_dashboard_title', $title) }}"
                            class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm" placeholder="My Dashboard">
                        @error('client_dashboard_title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="client_dashboard_subtitle" class="block text-sm font-medium text-gray-700 mb-1">Dashboard subtitle</label>
                        <textarea name="client_dashboard_subtitle" id="client_dashboard_subtitle" rows="2" placeholder="Welcome back! Here's an overview..."
                            class="w-full rounded-lg border border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500 text-sm">{{ old('client_dashboard_subtitle', $subtitle) }}</textarea>
                        @error('client_dashboard_subtitle')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <h2 class="text-sm font-semibold text-gray-800 mb-3">Sections to show on customer dashboard</h2>
                        <p class="text-xs text-gray-500 mb-4">Uncheck a section to hide it from the customer dashboard. Banners are managed under Banners.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center justify-between">
                                <label for="show_banners" class="text-sm text-gray-700">Banners (carousel)</label>
                                <input type="hidden" name="client_dashboard_show_banners" value="0">
                                <input type="checkbox" name="client_dashboard_show_banners" id="show_banners" value="1" {{ $showBanners ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_metrics" class="text-sm text-gray-700">Key metrics (4 cards: Subscriptions, Visits, Orders, Total Spent)</label>
                                <input type="hidden" name="client_dashboard_show_metrics" value="0">
                                <input type="checkbox" name="client_dashboard_show_metrics" id="show_metrics" value="1" {{ $showMetrics ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_secondary" class="text-sm text-gray-700">Secondary metrics (Pending visits, In progress, Reports, Complaints)</label>
                                <input type="hidden" name="client_dashboard_show_secondary_metrics" value="0">
                                <input type="checkbox" name="client_dashboard_show_secondary_metrics" id="show_secondary" value="1" {{ $showSecondaryMetrics ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_charts" class="text-sm text-gray-700">Charts (Visits by status, Monthly spending)</label>
                                <input type="hidden" name="client_dashboard_show_charts" value="0">
                                <input type="checkbox" name="client_dashboard_show_charts" id="show_charts" value="1" {{ $showCharts ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_recent_subs" class="text-sm text-gray-700">Recent subscriptions table</label>
                                <input type="hidden" name="client_dashboard_show_recent_subscriptions" value="0">
                                <input type="checkbox" name="client_dashboard_show_recent_subscriptions" id="show_recent_subs" value="1" {{ $showRecentSubscriptions ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_recent_visits" class="text-sm text-gray-700">Recent visits table</label>
                                <input type="hidden" name="client_dashboard_show_recent_visits" value="0">
                                <input type="checkbox" name="client_dashboard_show_recent_visits" id="show_recent_visits" value="1" {{ $showRecentVisits ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_recent_reports" class="text-sm text-gray-700">Recent reports table</label>
                                <input type="hidden" name="client_dashboard_show_recent_reports" value="0">
                                <input type="checkbox" name="client_dashboard_show_recent_reports" id="show_recent_reports" value="1" {{ $showRecentReports ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_recent_orders" class="text-sm text-gray-700">Recent orders table</label>
                                <input type="hidden" name="client_dashboard_show_recent_orders" value="0">
                                <input type="checkbox" name="client_dashboard_show_recent_orders" id="show_recent_orders" value="1" {{ $showRecentOrders ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                            <li class="flex items-center justify-between">
                                <label for="show_recent_complaints" class="text-sm text-gray-700">Recent complaints table</label>
                                <input type="hidden" name="client_dashboard_show_recent_complaints" value="0">
                                <input type="checkbox" name="client_dashboard_show_recent_complaints" id="show_recent_complaints" value="1" {{ $showRecentComplaints ? 'checked' : '' }} class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <button type="submit" class="px-4 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">Save customer dashboard design</button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
