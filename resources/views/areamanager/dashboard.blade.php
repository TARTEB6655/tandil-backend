<x-areamanager-layout>
    {{-- Welcome & quick action --}}
    <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Overview of areas, visits, team performance, and reports.</p>
        </div>
        <a href="{{ route('areamanager.generated-reports.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Generate PDF Report
        </a>
    </div>

    {{-- Alerts strip --}}
    @if(!empty($alerts))
        <div class="mb-6 space-y-2">
            @foreach($alerts as $alert)
                <div class="rounded-xl border px-4 py-3 text-sm flex items-center gap-3
                    {{ $alert['type'] === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-sky-50 border-sky-200 text-sky-800' }}">
                    @if($alert['type'] === 'warning')
                        <svg class="w-5 h-5 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    @else
                        <svg class="w-5 h-5 shrink-0 text-sky-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    @endif
                    <span>{{ $alert['message'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- API-aligned summary: total_farms, active_subscriptions, monthly_revenue, team, active, done --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 sm:gap-5 mb-6 sm:mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Farms</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-600">{{ number_format($totalFarms ?? 0) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Managed regions</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Active Subscriptions</p>
                    <p class="mt-1 text-2xl font-bold text-teal-600">{{ number_format($activeSubscriptions ?? 0) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Paid & current</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-teal-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Monthly Revenue</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($monthlyRevenue ?? 0, 2) }}</p>
                    <p class="mt-1 text-xs text-gray-500">This month (orders)</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Team</p>
                    <p class="mt-1 text-2xl font-bold text-violet-600">{{ number_format($teamCount ?? 0) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Supervisors in regions</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Active Visits</p>
                    <p class="mt-1 text-2xl font-bold text-blue-600">{{ number_format($activeVisits ?? 0) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Pending, scheduled, in progress</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Done Visits</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">{{ number_format($doneVisits ?? 0) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Completed & approved</p>
                </div>
                <div class="h-12 w-12 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Visit status mini cards (API statuses) --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-6 sm:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase">Pending</p>
            <p class="text-xl font-bold text-amber-600">{{ number_format($pendingVisits ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase">Scheduled</p>
            <p class="text-xl font-bold text-sky-600">{{ number_format($scheduledVisits ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase">In Progress</p>
            <p class="text-xl font-bold text-blue-600">{{ number_format($inProgressVisits ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase">Completed / Approved</p>
            <p class="text-xl font-bold text-emerald-600">{{ number_format(($completedVisits ?? 0) + ($approvedVisits ?? 0)) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase">Pending Reports</p>
            <p class="text-xl font-bold text-orange-600">{{ number_format($pendingReports ?? 0) }}</p>
        </div>
    </div>

    {{-- Team leaders (by performance) --}}
    @if(isset($teamLeaders) && $teamLeaders->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-6 sm:mb-8 overflow-hidden">
            <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Team leaders (by performance)</h2>
                <a href="{{ route('areamanager.areas.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View areas</a>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($teamLeaders as $tl)
                    <div class="px-5 py-4 sm:px-6 sm:py-4 flex items-center justify-between hover:bg-gray-50/50 transition-colors">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-indigo-700 font-semibold text-sm">{{ strtoupper(mb_substr($tl->name, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $tl->name }}</p>
                                <p class="text-xs text-gray-500">{{ $tl->location ?? '–' }} · Team {{ $tl->team }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 shrink-0">
                            <span class="text-xs text-gray-500">{{ $tl->active }} active / {{ $tl->done }} done</span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                @if($tl->performance_percent >= 70) bg-emerald-100 text-emerald-800
                                @elseif($tl->performance_percent >= 40) bg-amber-100 text-amber-800
                                @else bg-gray-100 text-gray-700
                                @endif">
                                {{ $tl->performance_percent }}%
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Visits by status</h3>
            <canvas id="visitsByStatusChart" class="w-full" style="max-height: 280px;"></canvas>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Monthly visits (last 6 months)</h3>
            <canvas id="monthlyVisitsChart" class="w-full" style="max-height: 280px;"></canvas>
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 sm:px-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">Recent visits</h3>
                <a href="{{ route('areamanager.visits.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentVisits ?? [] as $visit)
                    <div class="px-5 py-3 sm:py-4 hover:bg-gray-50/50 transition-colors flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $visit->subscription && $visit->subscription->client ? $visit->subscription->client->name : 'N/A' }}</p>
                            <p class="text-xs text-gray-500">{{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}</p>
                        </div>
                        <span class="shrink-0 px-2.5 py-1 text-xs font-medium rounded-full
                            @if($visit->status === 'completed' || $visit->status === 'approved') bg-emerald-100 text-emerald-800
                            @elseif($visit->status === 'in_progress' || $visit->status === 'started') bg-blue-100 text-blue-800
                            @elseif($visit->status === 'scheduled') bg-sky-100 text-sky-800
                            @else bg-amber-100 text-amber-800
                            @endif">{{ ucfirst($visit->status) }}</span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-500">No recent visits</div>
                @endforelse
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 sm:px-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">Recent reports</h3>
                <a href="{{ route('areamanager.reports.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentReports ?? [] as $report)
                    <div class="px-5 py-3 sm:py-4 hover:bg-gray-50/50 transition-colors flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $report->visit && $report->visit->subscription && $report->visit->subscription->client ? $report->visit->subscription->client->name : 'N/A' }}</p>
                            <p class="text-xs text-gray-500">{{ $report->created_at->format('M d, Y') }}</p>
                        </div>
                        <span class="shrink-0 px-2.5 py-1 text-xs font-medium rounded-full {{ ($report->status ?? '') === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($report->status ?? 'pending') }}</span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-500">No recent reports</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var visitsByStatusCtx = document.getElementById('visitsByStatusChart');
            if (visitsByStatusCtx) {
                new Chart(visitsByStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: {!! json_encode($visitsByStatusLabels ?? array_keys($visitsByStatus ?? [])) !!},
                        datasets: [{
                            data: {!! json_encode($visitsByStatusData ?? array_values($visitsByStatus ?? [])) !!},
                            backgroundColor: ['#f59e0b', '#8b5cf6', '#3b82f6', '#10b981', '#059669']
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
                });
            }
            var monthlyCtx = document.getElementById('monthlyVisitsChart');
            if (monthlyCtx) {
                var monthlyData = {!! json_encode($monthlyVisits ?? []) !!};
                new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyData.map(function(item) { return item.month; }),
                        datasets: [{ label: 'Visits', data: monthlyData.map(function(item) { return item.count; }), borderColor: '#6366f1', backgroundColor: 'rgba(99, 102, 241, 0.1)', tension: 0.4, fill: true }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
            }
        });
    </script>
</x-areamanager-layout>
