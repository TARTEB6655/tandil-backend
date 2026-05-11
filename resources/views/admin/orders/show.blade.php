<x-admin-layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Order Details</h1>
                <p class="text-sm text-gray-500 mt-1">Order #{{ $order->publicOrderNumberDigits() }}</p>
            </div>
            <a href="{{ route('admin.orders.index') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span>{{ __('admin.back_to_orders') }}</span>
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Items -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-base font-medium text-gray-900">{{ __('admin.order_items') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.product') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.price') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.quantity') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.subtotal') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($order->items as $item)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-4">
                                                @php
                                                    $product = $item->product;
                                                    $productImageUrl = $product?->image_url;
                                                    if (! $productImageUrl && ! empty($product?->image)) {
                                                        $rawImage = (string) $product->image;
                                                        if (str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://')) {
                                                            $productImageUrl = str_contains($rawImage, '/storage/products/')
                                                                ? str_replace('/storage/products/', '/media/products/', $rawImage)
                                                                : $rawImage;
                                                        } else {
                                                            $normalizedPath = ltrim(str_replace('\\', '/', $rawImage), '/');
                                                            if (! str_starts_with($normalizedPath, 'products/')) {
                                                                $normalizedPath = 'products/' . $normalizedPath;
                                                            }
                                                            $productImageUrl = asset('media/' . $normalizedPath);
                                                        }
                                                    }
                                                @endphp
                                                @if($productImageUrl)
                                                    <img src="{{ $productImageUrl }}" 
                                                         alt="{{ $product?->name ?? 'Product image' }}" 
                                                         class="h-16 w-16 object-cover rounded-lg border border-gray-200">
                                                @else
                                                    <div class="h-16 w-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $product?->name ?? 'Product unavailable' }}</div>
                                                    <div class="text-xs text-gray-500">{{ $product?->category?->name ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">AED {{ number_format($item->price, 2) }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $item->quantity }}</td>
                                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">AED {{ number_format($item->subtotal, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">{{ __('admin.no_items_found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-right text-sm font-medium text-gray-900">{{ __('admin.total') }}:</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">AED {{ number_format($order->total_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Tracking Timeline -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    @php
                        $rawStatus = strtolower((string) ($order->order_status ?? 'pending'));
                        $isCancelled = $rawStatus === 'cancelled';
                        $timelineNow = ($order->updated_at ?? $order->created_at);
                        $timeFmt = fn ($dt) => $dt ? $dt->format('M d, Y h:i A') : null;
                    @endphp

                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Order Tracking Timeline</h3>
                            <p class="text-sm text-gray-500 mt-1">Clear status steps for client + operations workflow.</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Current</p>
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                                {{ $rawStatus === 'delivered' ? 'bg-green-100 text-green-800' :
                                   ($rawStatus === 'cancelled' ? 'bg-red-100 text-red-800' :
                                   ($rawStatus === 'processing' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                {{ ucfirst($order->order_status ?? 'pending') }}
                            </span>
                        </div>
                    </div>

                    @php
                        $normalizedStatus = match ($rawStatus) {
                            'paid' => 'confirmed',
                            'processing' => 'in_progress',
                            'shipped' => 'completed',
                            default => $rawStatus,
                        };
                        $rank = match ($normalizedStatus) {
                            'pending' => 0,
                            'confirmed' => 1,
                            'assigned' => 2,
                            'in_progress' => 3,
                            'completed' => 4,
                            'delivered' => 5,
                            default => 0,
                        };

                        // For cancelled orders we show a dedicated cancel/refund flow.
                        $timeline = $isCancelled
                            ? [
                                ['key' => 'pending', 'label' => 'Pending', 'desc' => 'Order placed successfully', 'done' => true, 'time' => $timeFmt($order->created_at)],
                                ['key' => 'cancel_order', 'label' => 'Cancel order', 'desc' => 'Order cancelled by customer request', 'done' => true, 'time' => $timeFmt($order->updated_at)],
                            ]
                            : [
                                ['key' => 'pending', 'label' => 'Pending', 'desc' => 'Order placed successfully', 'done' => true, 'time' => $timeFmt($order->created_at)],
                                ['key' => 'confirmed', 'label' => 'Confirmed', 'desc' => 'Order confirmed by our team', 'done' => $rank >= 1, 'time' => $timeFmt($order->paid_at ?? $order->updated_at)],
                                ['key' => 'assigned', 'label' => 'Assigned', 'desc' => 'Technician assignment done', 'done' => $rank >= 2, 'time' => $timeFmt($order->updated_at)],
                                ['key' => 'in_progress', 'label' => 'In Progress', 'desc' => 'Work in progress', 'done' => $rank >= 3, 'time' => $timeFmt($order->updated_at)],
                                ['key' => 'completed', 'label' => 'Completed', 'desc' => 'Work completed', 'done' => $rank >= 4, 'time' => $timeFmt($order->updated_at)],
                                ['key' => 'delivered', 'label' => 'Delivered', 'desc' => 'Order closed and delivered', 'done' => $rank >= 5, 'time' => $timeFmt($order->updated_at)],
                            ];
                    @endphp

                    @if($isCancelled)
                        @php
                            $isRefunded = strtolower((string) ($order->payment_status ?? 'pending')) === 'refunded';
                        @endphp
                        @if($isRefunded)
                            @php
                                $timeline[] = ['key' => 'refund_processing', 'label' => 'Refund Processing', 'desc' => 'Refund request is being processed', 'done' => true, 'time' => $timeFmt($order->updated_at)];
                                $timeline[] = [
                                    'key' => 'refund_complete',
                                    'label' => 'Refund complete',
                                    'desc' => 'Refund amount credited back to original payment method',
                                    'done' => (bool) $order->refunded_at,
                                    'time' => $timeFmt($order->refunded_at ?? $order->updated_at),
                                ];
                            @endphp
                        @else
                            @php
                                $timeline[] = ['key' => 'refund_not_required', 'label' => 'Refund Not Required', 'desc' => 'Order was cancelled before payment/refund eligibility.', 'done' => true, 'time' => $timeFmt($order->updated_at)];
                            @endphp
                        @endif
                    @endif

                    <div class="space-y-3">
                        @foreach($timeline as $i => $step)
                            @php
                                $stepDone = (bool) ($step['done'] ?? false);
                                $rowBorder = $stepDone ? 'border-emerald-200/80' : 'border-gray-200';
                                $rowBg = $stepDone ? 'bg-gradient-to-r from-emerald-50/80 via-white to-white' : 'bg-gray-50/60';
                                $titleColor = $stepDone ? 'text-gray-900' : 'text-gray-500';
                                $descColor = $stepDone ? 'text-gray-600' : 'text-gray-400';
                                $timeBadge = $stepDone ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500';
                            @endphp
                            <div class="relative flex gap-3 pb-4">
                                <div class="relative flex w-7 justify-center pt-0.5">
                                    <span class="z-10 mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-semibold shadow-sm
                                        {{ $stepDone ? 'bg-emerald-600 text-white ring-2 ring-emerald-100' : 'bg-white border border-gray-300 text-gray-400' }}">
                                        {{ $stepDone ? '✓' : '' }}
                                    </span>
                                    @if($i !== count($timeline) - 1)
                                        <span class="absolute top-6 h-[calc(100%-0.5rem)] w-px {{ $stepDone ? 'bg-emerald-500/70' : 'bg-gray-200' }}"></span>
                                    @endif
                                </div>
                                <div class="pt-0.5 flex-1">
                                    <div class="rounded-xl border {{ $rowBorder }} {{ $rowBg }} px-3 py-2.5 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-[1rem] font-semibold tracking-tight {{ $titleColor }}">{{ $step['label'] }}</p>
                                                <p class="text-sm {{ $descColor }}">{{ $step['desc'] }}</p>
                                            </div>
                                            @if(!empty($step['time']) && $stepDone)
                                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $timeBadge }}">
                                                    {{ $step['time'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($rawStatus === 'cancelled')
                        <div class="mt-4 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-700">
                            Cancellation processed.
                            @if(!empty($order->refund_amount))
                                Refund: AED {{ number_format($order->refund_amount, 2) }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Order Status -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-base font-medium text-gray-900 mb-4">{{ __('admin.order_status') }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.order_status') }}</label>
                            <form method="POST" action="{{ route('admin.orders.update-status', $order->id) }}" class="mb-3">
                                @csrf
                                <select name="order_status" onchange="this.form.submit()" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="pending" {{ $order->order_status === 'pending' ? 'selected' : '' }}>{{ __('admin.pending') }}</option>
                                    <option value="processing" {{ $order->order_status === 'processing' ? 'selected' : '' }}>{{ __('admin.processing') }}</option>
                                    <option value="shipped" {{ $order->order_status === 'shipped' ? 'selected' : '' }}>{{ __('admin.shipped') }}</option>
                                    <option value="delivered" {{ $order->order_status === 'delivered' ? 'selected' : '' }}>{{ __('admin.delivered') }}</option>
                                    <option value="cancelled" {{ $order->order_status === 'cancelled' ? 'selected' : '' }}>{{ __('admin.cancelled') }}</option>
                                </select>
                            </form>
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full 
                                {{ $order->order_status === 'delivered' ? 'bg-green-100 text-green-800' : 
                                   ($order->order_status === 'processing' ? 'bg-blue-100 text-blue-800' : 
                                   ($order->order_status === 'shipped' ? 'bg-purple-100 text-purple-800' : 
                                   ($order->order_status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'))) }}">
                                {{ __('admin.' . $order->order_status) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.payment_status') }}</label>
                            @if($order->payment_status !== 'paid' && $order->payment_status !== 'refunded')
                                <form method="POST" action="{{ route('admin.orders.mark-paid', $order->id) }}" class="mb-3">
                                    @csrf
                                    <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        {{ __('admin.mark_as_paid') }}
                                    </button>
                                </form>
                            @endif
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full 
                                {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                                   ($order->payment_status === 'failed' ? 'bg-red-100 text-red-800' : 
                                   ($order->payment_status === 'refunded' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                {{ $order->payment_status === 'refunded' ? __('admin.refunded') : __('admin.' . $order->payment_status) }}
                            </span>
                        </div>
                        
                        <!-- Admin Cancel Order (policy: refund tier + wallet; blocked only when delivered or already cancelled) -->
                        @php
                            $rawOrderStatus = strtolower((string) ($order->order_status ?? 'pending'));
                            $cancelForbidden = in_array($rawOrderStatus, ['delivered', 'cancelled'], true);
                        @endphp
                        @if(! $cancelForbidden)
                            <div class="pt-4 border-t border-gray-200">
                                <form method="POST" action="{{ route('admin.orders.cancel', $order->id) }}" 
                                      onsubmit="return confirm(@json(__('admin.cancel_order_confirm_policy')));">
                                    @csrf
                                    <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                        {{ __('admin.cancel_order') }}
                                    </button>
                                </form>
                                <p class="mt-2 text-xs text-gray-500 leading-relaxed">{{ __('admin.cancel_order_policy_hint') }}</p>
                            </div>
                        @else
                            <div class="pt-4 border-t border-gray-200">
                                <div class="text-xs text-gray-500 leading-relaxed">
                                    {{ __('admin.order_cancel_not_allowed_terminal') }}
                                </div>
                            </div>
                        @endif
                        
                        <!-- Refund Order -->
                        @if($order->payment_status === 'paid' && !$order->refunded_at)
                            <div class="pt-4 border-t border-gray-200">
                                <button type="button" 
                                        onclick="document.getElementById('refundModal').classList.remove('hidden')"
                                        class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                                    {{ __('admin.process_refund') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A11.955 11.955 0 0112 15.75c2.53 0 4.877.78 6.879 2.104M15 9a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </span>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('admin.customer_information') }}</h3>
                    </div>
                    <div class="space-y-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                            <p class="text-xs text-gray-500">Name</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $order->user->name ?? 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                            <p class="text-xs text-gray-500">{{ __('admin.email') }}</p>
                            <p class="text-sm font-semibold text-gray-900 break-all">{{ $order->user->email ?? 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                            <p class="text-xs text-gray-500">{{ __('admin.phone') }}</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $order->user->phone ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h8m0 0l-3-3m3 3l-3 3M5 7h11" /></svg>
                        </span>
                        <h3 class="text-base font-semibold text-gray-900">Order Information</h3>
                    </div>
                    <div class="space-y-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                            <p class="text-xs text-gray-500">Order ID</p>
                            <p class="text-sm font-semibold text-gray-900">#{{ $order->publicOrderNumberDigits() }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                            <p class="text-xs text-gray-500">Order Date</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        @if($order->paid_at)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                                <p class="text-xs text-gray-500">Paid At</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $order->paid_at->format('M d, Y h:i A') }}</p>
                            </div>
                        @endif
                        @if($order->payment_reference)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                                <p class="text-xs text-gray-500">Payment Reference</p>
                                <p class="text-sm font-semibold text-gray-900 break-all">{{ $order->payment_reference }}</p>
                            </div>
                        @endif
                        @if($order->payment_method)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                                <p class="text-xs text-gray-500">Payment Method</p>
                                <p class="text-sm font-semibold text-gray-900">{{ ucfirst($order->payment_method) }}</p>
                            </div>
                        @endif
                        @if($order->transaction_id)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                                <p class="text-xs text-gray-500">Transaction ID</p>
                                <p class="text-sm font-semibold text-gray-900 break-all">{{ $order->transaction_id }}</p>
                            </div>
                        @endif
                        @if($order->refunded_at)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5">
                                <p class="text-xs text-gray-500">Refunded At</p>
                                <p class="text-sm font-semibold text-gray-900">{{ $order->refunded_at->format('M d, Y h:i A') }}</p>
                            </div>
                            @if($order->refund_amount)
                                <div class="rounded-xl border border-red-200 bg-red-50/70 px-3 py-2.5">
                                    <p class="text-xs text-gray-500">Refund Amount</p>
                                    <p class="text-sm font-semibold text-red-600">AED {{ number_format($order->refund_amount, 2) }}</p>
                                </div>
                            @endif
                        @endif
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 px-3 py-2.5">
                            <p class="text-xs text-gray-500">Total Amount</p>
                            <p class="text-lg font-semibold text-indigo-700">AED {{ number_format($order->total_amount, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Refund Modal -->
        <div id="refundModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('admin.process_refund') }}</h3>
                <form method="POST" action="{{ route('admin.orders.refund', $order->id) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Refund Amount (Max: AED {{ number_format($order->total_amount, 2) }})</label>
                            <input type="number" 
                                   name="refund_amount" 
                                   step="0.01" 
                                   max="{{ $order->total_amount }}"
                                   value="{{ $order->total_amount }}"
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Refund Reason (Optional)</label>
                            <textarea name="refund_reason" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <button type="submit" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                            {{ __('admin.process_refund') }}
                        </button>
                        <button type="button" 
                                onclick="document.getElementById('refundModal').classList.add('hidden')"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            {{ __('admin.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
