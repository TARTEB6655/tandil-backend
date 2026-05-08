<x-admin-layout>
    <div class="space-y-6" x-data="{ sendModal: false }" x-init="if (window.location.hash === '#send-supplier') sendModal = true">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">{{ $pageTitle ?? __('admin.orders_management') }}</h1>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Export
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak x-transition
                         class="absolute left-0 mt-2 w-52 bg-white dark:bg-gray-800 rounded-lg shadow-lg z-20 border border-gray-200 dark:border-gray-600 py-1">
                        <a href="{{ route('admin.orders.export', ['format' => 'csv']) }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <span>Download CSV</span>
                        </a>
                        <a href="{{ route('admin.orders.export', ['format' => 'xlsx']) }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <span>Download Excel</span>
                        </a>
                    </div>
                </div>
                <button @click="sendModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    Send to supplier
                </button>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        More actions
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg z-10 border border-gray-200 dark:border-gray-600 py-1">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Bulk print</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Archive orders</a>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        @endif

        <!-- Order Statistics Cards -->
        <div class="rounded-2xl border border-gray-200 bg-white px-3 py-3 shadow-sm">
            <div class="overflow-x-auto">
                <div class="mx-auto flex w-max min-w-full flex-nowrap justify-center gap-3">
                    <div class="w-52 shrink-0 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">Total Orders</p>
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-blue-50 text-blue-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 10H4L5 9z" /></svg>
                            </span>
                        </div>
                        <p class="text-2xl font-semibold leading-none text-gray-900">{{ number_format((int) ($stats['total'] ?? 0)) }}</p>
                    </div>
                    <div class="w-52 shrink-0 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">Pending</p>
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-amber-50 text-amber-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                        </div>
                        <p class="text-2xl font-semibold leading-none text-gray-900">{{ number_format((int) ($stats['open'] ?? 0)) }}</p>
                    </div>
                    <div class="w-52 shrink-0 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">Unfulfilled</p>
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-cyan-50 text-cyan-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8m-8 5h8m-5 5h5M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z" /></svg>
                            </span>
                        </div>
                        <p class="text-2xl font-semibold leading-none text-gray-900">{{ number_format((int) ($stats['unfulfilled'] ?? 0)) }}</p>
                    </div>
                    <div class="w-52 shrink-0 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">Completed</p>
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-green-50 text-green-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            </span>
                        </div>
                        <p class="text-2xl font-semibold leading-none text-gray-900">{{ number_format((int) ($stats['fulfilled'] ?? 0)) }}</p>
                    </div>
                    <div class="w-52 shrink-0 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">Cancelled</p>
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-rose-50 text-rose-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18" /></svg>
                            </span>
                        </div>
                        <p class="text-2xl font-semibold leading-none text-gray-900">{{ number_format((int) ($stats['archived'] ?? 0)) }}</p>
                    </div>
                    <div class="w-52 shrink-0 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">Revenue (AED)</p>
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-violet-50 text-violet-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 .895-4 2s1.79 2 4 2 4 .895 4 2-1.79 2-4 2m0-10V6m0 12v-2" /></svg>
                            </span>
                        </div>
                        <p class="text-2xl font-semibold leading-none text-gray-900">{{ number_format((float) ($stats['total_revenue'] ?? 0), 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Send to supplier modal -->
        <div x-show="sendModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-full items-center justify-center p-4">
                <div x-show="sendModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="sendModal = false"></div>
                <div x-show="sendModal" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Send orders to supplier</h3>
                        <button @click="sendModal = false" class="p-1 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Export orders as CSV and email to the supplier. Leave dates empty for all orders.</p>
                    <form action="{{ route('admin.orders.send-to-supplier') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="send_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier email</label>
                            <input type="email" id="send_email" name="email" value="{{ config('mail.supplier_email') }}"
                                   placeholder="supplier@example.com"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty to use default (MAIL_SUPPLIER_EMAIL)</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From date</label>
                                <input type="date" id="date_from" name="date_from" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            </div>
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To date</label>
                                <input type="date" id="date_to" name="date_to" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            </div>
                        </div>
                        <div>
                            <label for="package_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Package (optional)</label>
                            <select id="package_id" name="package_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                                <option value="">All packages</option>
                                @foreach($packages ?? [] as $pkg)
                                    <option value="{{ $pkg->id }}">{{ $pkg->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="sendModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">Cancel</button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">Send email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                <div class="flex items-center gap-1 overflow-x-auto">
                    <a href="{{ route('admin.orders.index') }}" 
                       class="px-4 py-2 text-sm font-medium {{ ($filter ?? 'all') == 'all' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        All
                    </a>
                    <a href="{{ route('admin.orders.index', ['filter' => 'unfulfilled']) }}" 
                       class="px-4 py-2 text-sm font-medium {{ ($filter ?? 'all') == 'unfulfilled' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        Unfulfilled
                    </a>
                    <a href="{{ route('admin.orders.index', ['filter' => 'unpaid']) }}" 
                       class="px-4 py-2 text-sm font-medium {{ ($filter ?? 'all') == 'unpaid' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        Unpaid
                    </a>
                    <a href="{{ route('admin.orders.index', ['filter' => 'open']) }}" 
                       class="px-4 py-2 text-sm font-medium {{ ($filter ?? 'all') == 'open' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        Open
                    </a>
                    <a href="{{ route('admin.orders.index', ['filter' => 'archived']) }}" 
                       class="px-4 py-2 text-sm font-medium {{ ($filter ?? 'all') == 'archived' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        Archived
                    </a>
                    <button class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-600 whitespace-nowrap">
                        +
                    </button>
                </div>
                <div class="flex items-center gap-2 ml-4">
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </button>
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left w-12">
                                    <input type="checkbox" 
                                           id="selectAll" 
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                           onchange="toggleSelectAll()">
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Order</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Date</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[180px]">Customer</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Channel</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Total</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Payment status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Fulfillment status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Items</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Delivery status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12"></th>
                            </tr>
                        </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($orders as $order)
                            <tr class="hover:bg-gray-50 transition-colors cursor-pointer {{ $order->order_status == 'pending' && $order->payment_status != 'paid' ? 'bg-yellow-50' : '' }}" 
                                onclick="window.location='{{ route('admin.orders.show', $order->id) }}'">
                                <td class="px-3 py-4 whitespace-nowrap" onclick="event.stopPropagation()">
                                    <input type="checkbox" 
                                           class="order-checkbox h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                           value="{{ $order->id }}"
                                           onchange="updateBulkActions()">
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">#{{ $order->publicOrderNumberDigits() }}</div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $order->created_at->format('D') }} at {{ $order->created_at->format('g:i a') }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->created_at->format('M d') }}</div>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="text-sm font-medium text-gray-900 truncate max-w-[160px]">{{ $order->user->name ?? 'Guest' }}</div>
                                    <div class="text-xs text-gray-500 truncate max-w-[160px]">{{ $order->user->email ?? '' }}</div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">Online Store</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">AED {{ number_format($order->total_amount, 2) }}</div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        @if($order->payment_status == 'paid')
                                            <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Paid</span>
                                        @elseif($order->payment_status == 'voided')
                                            <div class="w-2 h-2 rounded-full bg-gray-400 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Voided</span>
                                        @else
                                            <div class="w-2 h-2 rounded-full bg-yellow-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">{{ ucfirst($order->payment_status) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        @if($order->order_status == 'delivered')
                                            <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Fulfilled</span>
                                        @elseif($order->order_status == 'cancelled')
                                            <div class="w-2 h-2 rounded-full bg-gray-400 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Cancelled</span>
                                        @else
                                            <div class="w-2 h-2 rounded-full bg-orange-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Unfulfilled</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">{{ $order->items->count() }} {{ $order->items->count() == 1 ? 'item' : 'items' }}</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    @php
                                        $deliveryStatus = strtolower((string) ($order->order_status ?? 'pending'));
                                        $deliveryLabel = match ($deliveryStatus) {
                                            'delivered' => 'Delivered',
                                            'completed', 'shipped' => 'Out for delivery',
                                            'in_progress', 'processing', 'assigned', 'confirmed' => 'In transit',
                                            'cancelled' => 'Cancelled',
                                            default => 'Pending dispatch',
                                        };
                                        $deliveryDot = match ($deliveryStatus) {
                                            'delivered' => 'bg-green-500',
                                            'completed', 'shipped', 'in_progress', 'processing', 'assigned', 'confirmed' => 'bg-blue-500',
                                            'cancelled' => 'bg-gray-400',
                                            default => 'bg-amber-500',
                                        };
                                    @endphp
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full {{ $deliveryDot }} flex-shrink-0"></div>
                                        <span class="text-sm text-gray-900">{{ $deliveryLabel }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                        <h3 class="text-sm font-medium text-gray-900 mb-1">No orders found</h3>
                                        <p class="text-sm text-gray-500">Orders will appear here when customers make purchases.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        @if($orders->hasPages())
            <div class="mt-4">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            // Bulk actions logic here
        }
    </script>
    @endpush
</x-admin-layout>
