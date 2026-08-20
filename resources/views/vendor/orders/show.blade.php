@php
    $statusTone = match($mapping->status) {
        'pending' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'confirmed' => 'bg-sky-50 text-sky-800 ring-sky-200',
        'processing' => 'bg-indigo-50 text-indigo-800 ring-indigo-200',
        'shipped' => 'bg-violet-50 text-violet-800 ring-violet-200',
        'delivered' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'cancelled' => 'bg-rose-50 text-rose-800 ring-rose-200',
        default => 'bg-gray-50 text-gray-700 ring-gray-200',
    };
@endphp

<x-vendor-layout>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('vendor.orders.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← Back to orders</a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-gray-900">{{ $orderNumber }}</h1>
            <p class="mt-1 text-sm text-gray-500">Shop order #{{ $mapping->order_id }} · {{ $detail['order_date_label'] ?? $mapping->created_at?->format('d/m/Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('vendor.orders.invoice', $mapping->id) }}" class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50">Download invoice</a>
            <a href="{{ route('vendor.orders.invoice', ['mapping' => $mapping->id, 'print' => 1]) }}" target="_blank" class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50">Print</a>
            <a href="{{ route('vendor.orders.download', $mapping->id) }}" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Download order</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1 {{ $statusTone }}">{{ $detail['status_label'] ?? $mapping->status }}</span>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total</p>
            <p class="mt-2 text-xl font-semibold tabular-nums text-gray-900">AED {{ number_format($mapping->total_amount, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Payment</p>
            <p class="mt-2 text-sm font-medium text-gray-900">{{ $detail['payment_method'] ?? '—' }}</p>
            <p class="text-xs text-gray-500">{{ $detail['payment_status'] ?? '' }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tracking</p>
            <p class="mt-2 text-sm font-medium text-gray-900">{{ $mapping->tracking_number ?: 'Not set' }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Order items</h2>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse($detail['products'] ?? [] as $product)
                        <li class="flex items-center justify-between gap-4 px-5 py-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $product['name'] }}</p>
                                <p class="text-xs text-gray-500">Qty {{ $product['qty'] }} · Unit AED {{ number_format($product['unit_price'] ?? 0, 2) }}</p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums text-gray-900">AED {{ number_format($product['price'], 2) }}</p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">No vendor line items on this order.</li>
                    @endforelse
                </ul>
                <div class="space-y-2 border-t border-gray-100 bg-slate-50/60 px-5 py-4 text-sm">
                    <div class="flex justify-between text-gray-600"><span>Subtotal</span><span class="tabular-nums">AED {{ number_format($mapping->subtotal, 2) }}</span></div>
                    <div class="flex justify-between text-gray-600"><span>Tax</span><span class="tabular-nums">AED {{ number_format($mapping->tax_amount, 2) }}</span></div>
                    <div class="flex justify-between text-gray-600"><span>Shipping</span><span class="tabular-nums">AED {{ number_format($mapping->shipping_amount, 2) }}</span></div>
                    <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900"><span>Total</span><span class="tabular-nums">AED {{ number_format($mapping->total_amount, 2) }}</span></div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Fulfillment timeline</h2>
                <ol class="space-y-4">
                    @foreach($detail['status_timeline'] ?? [] as $step)
                        @php
                            $dot = match($step['status'] ?? '') {
                                'completed' => 'bg-emerald-500',
                                'current' => 'bg-indigo-600 ring-4 ring-indigo-100',
                                'cancelled' => 'bg-rose-500',
                                default => 'bg-gray-300',
                            };
                        @endphp
                        <li class="flex gap-3">
                            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $dot }}"></span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $step['label'] }}</p>
                                <p class="text-xs text-gray-500">{{ $step['date'] ?? '—' }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if(!empty($detail['order_notes']))
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5">
                    <h2 class="text-sm font-semibold text-amber-900">Order notes</h2>
                    <p class="mt-2 text-sm text-amber-900/80">{{ $detail['order_notes'] }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Customer</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Name</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $contact['customer']['name'] ?? 'Customer' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Phone</dt>
                        <dd class="mt-1 text-gray-700">{{ $contact['customer']['phone'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Email</dt>
                        <dd class="mt-1 text-gray-700">{{ $contact['customer']['email'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Address</dt>
                        <dd class="mt-1 text-gray-700">{{ $contact['customer']['address_text'] ?? $contact['customer']['location'] ?? '—' }}</dd>
                    </div>
                </dl>
                @if(!empty($contact['contact_actions']))
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($contact['contact_actions'] as $action)
                            <a href="{{ $action['url'] }}" class="inline-flex rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">{{ $action['label'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">Update status</h2>
                @if(count($allowedStatuses) === 0)
                    <p class="text-sm text-gray-500">No further status changes are available for this order.</p>
                @else
                    <form method="POST" action="{{ route('vendor.orders.update-status', $mapping->id) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">Next status</label>
                            <select name="status" class="w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-indigo-300 focus:ring-indigo-200" required>
                                @foreach($allowedStatuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">Tracking number</label>
                            <input type="text" name="tracking_number" value="{{ old('tracking_number', $mapping->tracking_number) }}"
                                   placeholder="Optional — auto-generated when shipping"
                                   class="w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-indigo-300 focus:ring-indigo-200" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">Note</label>
                            <textarea name="note" rows="2" placeholder="Optional note for timeline" class="w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-indigo-300 focus:ring-indigo-200"></textarea>
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Update status</button>
                    </form>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-gray-900">Status history</h2>
                <ul class="space-y-3 text-sm">
                    @forelse($mapping->statusLogs as $log)
                        <li>
                            <p class="font-medium capitalize text-gray-900">{{ $log->status }}</p>
                            <p class="text-xs text-gray-500">{{ $log->created_at?->format('M d, Y H:i') }}@if($log->note) · {{ $log->note }}@endif</p>
                        </li>
                    @empty
                        <li class="text-gray-500">No status changes yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-vendor-layout>
