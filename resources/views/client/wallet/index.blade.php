<x-client-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">My Wallet</h1>
                <p class="mt-1 text-sm text-gray-500">Track refund credits, expiry, and forfeited amounts.</p>
            </div>
            <a href="{{ route('client.wallet.add-money') }}"
               class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90 transition-opacity"
               style="background-color: #047857;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Money
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-sm text-emerald-900">Need more balance? Top up your wallet with card or Apple Pay.</p>
            <a href="{{ route('client.wallet.add-money') }}"
               class="inline-flex shrink-0 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
               style="background-color: #047857;">
                Add Money
            </a>
        </div>

        @php
            $wt = \App\Support\RefundPolicy::policyForApi()['wallet_terms'] ?? [];
        @endphp
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-800 leading-relaxed">
            <p class="font-semibold text-slate-900 mb-1">How your refund balance works</p>
            <p class="text-slate-700">{{ $wt['forfeiture_summary'] ?? '' }}</p>
            <p class="mt-2 text-xs text-slate-600">{{ $wt['terms_notice'] ?? '' }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Current balance</p>
                <p class="mt-1 text-2xl font-bold text-indigo-900">AED {{ number_format((float) $summary['balance'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Active credits</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900">AED {{ number_format((float) $summary['active_credits'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Wallet validity</p>
                <p class="mt-1 text-2xl font-bold text-amber-900">{{ (int) ($summary['wallet_validity_months'] ?? 6) }} months</p>
                <p class="mt-1 text-xs text-amber-800">
                    Next expiry:
                    {{ !empty($summary['next_active_expiry_at']) ? \Carbon\Carbon::parse($summary['next_active_expiry_at'])->format('M d, Y h:i A') : 'No active expiry' }}
                </p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Forfeited total</p>
                <p class="mt-1 text-2xl font-bold text-rose-900">AED {{ number_format((float) $summary['forfeited_total'], 2) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">Wallet Credits</h2>
                <span class="text-xs text-gray-500">{{ $credits->total() }} entries</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Credited</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($credits as $credit)
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">AED {{ number_format((float) $credit->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @php
                                        $badge = match((string) $credit->status) {
                                            'active' => 'bg-emerald-100 text-emerald-800',
                                            'forfeited' => 'bg-rose-100 text-rose-800',
                                            'used' => 'bg-indigo-100 text-indigo-800',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ ucfirst((string) $credit->status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ ucwords(str_replace('_', ' ', (string) ($credit->reason ?: 'order_refund'))) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ optional($credit->credited_at)->format('M d, Y h:i A') ?: '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ optional($credit->expires_at)->format('M d, Y h:i A') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No wallet credits yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($credits->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $credits->links() }}
                </div>
            @endif
        </div>
    </div>
</x-client-layout>

