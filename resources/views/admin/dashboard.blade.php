@php
    use App\Models\Order;
    
    // Additional calculations
    $totalOrders = Order::count();
    $ordersToday = Order::whereDate('created_at', \Carbon\Carbon::today())->count();
@endphp

<x-admin-layout>
    <!-- Page Header -->
    <div class="mb-5 md:mb-7">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-50 tracking-tight">{{ __('admin.dashboard_overview') }}</h1>
        <p class="mt-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 max-w-2xl">{{ __('admin.dashboard_welcome') }}</p>
    </div>

    <!-- Jump to Section - Sticky quick navigation (grouped) -->
    <div id="jump-nav" class="sticky top-0 z-20 -mx-3 px-4 py-4 md:-mx-4 md:px-5 lg:-mx-6 lg:px-6 mb-5 md:mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-xl shadow-lg ring-1 ring-gray-200/50 dark:ring-gray-700/50">
        <p class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-3">Quick navigation</p>
        <div class="flex flex-wrap gap-2.5 sm:gap-3">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-400 dark:text-gray-500">Catalog</span>
            <a href="{{ route('admin.products.index') }}" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors">Products</a>
            <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors">Categories</a>
            <a href="{{ route('admin.services.index') }}" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors">Services</a>
            <span class="w-px h-6 bg-gray-300 dark:bg-gray-600 self-center" aria-hidden="true"></span>
            <a href="#key-metrics" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">Key Metrics</a>
            <a href="#ecommerce" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">E-Commerce</a>
            <a href="#manage-services" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-teal-50 dark:bg-teal-900/30 border border-teal-200 dark:border-teal-700 text-teal-700 dark:text-teal-300 hover:bg-teal-100 dark:hover:bg-teal-900/50 transition-colors">Manage Services</a>
            <a href="#orders-export-supplier" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors">Orders Export</a>
            <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors">Orders</a>
            <a href="{{ route('admin.banners.index') }}" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors">Banners</a>
            <a href="{{ route('admin.packages.index') }}" class="inline-flex items-center px-3.5 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors">Packages</a>
        </div>
    </div>

    <!-- Catalog overview (Products, Categories, Services) -->
    <div id="catalog-overview" class="scroll-mt-24 mb-6 md:mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50 border-l-4 border-indigo-500 pl-3">Catalog</h2>
        <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.products.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    New Product
                </a>
                <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-900/60 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    New Category
                </a>
                <a href="{{ route('admin.services.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-teal-100 dark:bg-teal-900/40 text-teal-700 dark:text-teal-300 hover:bg-teal-200 dark:hover:bg-teal-900/60 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    New Service
                </a>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a href="{{ route('admin.products.index') }}" class="group flex items-center gap-4 p-5 rounded-xl border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-md hover:shadow-lg hover:border-indigo-400 dark:hover:border-indigo-500 transition-all duration-200">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 group-hover:scale-105 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Products</p>
                    <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">{{ number_format($totalProducts ?? 0) }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Add & edit · Link to services</p>
                </div>
                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </a>
            <a href="{{ route('admin.categories.index') }}" class="group flex items-center gap-4 p-5 rounded-xl border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-md hover:shadow-lg hover:border-indigo-400 dark:hover:border-indigo-500 transition-all duration-200">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 group-hover:scale-105 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Categories</p>
                    <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300">{{ number_format($totalCategories ?? 0) }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $activeCategories ?? 0 }} active</p>
                </div>
                <svg class="w-5 h-5 text-gray-500 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </a>
            <a href="{{ route('admin.services.index') }}" class="group flex items-center gap-4 p-5 rounded-xl border-2 border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-md hover:shadow-lg hover:border-teal-400 dark:hover:border-teal-500 transition-all duration-200">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-50 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400 group-hover:scale-105 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Services</p>
                    <p class="text-2xl font-bold text-teal-700 dark:text-teal-300">{{ number_format($totalServices ?? 0) }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Place Service Orders · Optional category</p>
                </div>
                <svg class="w-5 h-5 text-gray-500 group-hover:text-teal-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </a>
        </div>
    </div>

    @php
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $adminUser = auth()->user();
        $adminRole = $adminUser ? (ucwords(str_replace('_', ' ', $adminUser->role ?? 'Admin'))) : 'Admin';
        $adminId = $adminUser ? ('ID: ' . strtoupper(substr($adminUser->role ?? 'ADMIN', 0, 5)) . '-' . $adminUser->id) : '';
    @endphp

    <!-- Welcome + action cards (same style as Key Metrics below) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6 mb-6 md:mb-8">
        <!-- Welcome card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 p-5 md:p-6 shadow-md">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">{{ $greeting }}</p>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $adminUser->name ?? 'Administrator' }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $adminRole }} @if($adminId) · {{ $adminId }} @endif</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-emerald-50">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Reports card -->
        <a href="{{ route('admin.reports.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 p-5 md:p-6 shadow-md hover:shadow-lg transition-all duration-200 hover:border-amber-400 dark:hover:border-amber-500">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">Pending Reports</p>
                    <p class="text-xl font-bold text-amber-700 dark:text-amber-300">{{ number_format($pendingReports ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">View reports</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- New Orders card -->
        <a href="{{ route('admin.orders.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 p-5 md:p-6 shadow-md hover:shadow-lg transition-all duration-200 hover:border-green-400 dark:hover:border-green-500">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">New Orders</p>
                    <p class="text-xl font-bold text-green-700 dark:text-green-300">{{ number_format($ordersToday ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Manage orders</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Tips Management card (dedicated CRUD page) -->
        <a href="{{ route('admin.tips.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200 hover:border-emerald-300 dark:hover:border-emerald-600">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Tips</p>
                    <p class="text-lg font-medium text-emerald-600">{{ number_format($totalTips ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Manage tips</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Home Screen Banners card -->
        <a href="{{ route('admin.banners.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200 hover:border-sky-300 dark:hover:border-sky-600">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Home Screen Banners</p>
                    <p class="text-lg font-medium text-sky-600 dark:text-sky-400">{{ number_format($totalBanners ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $activeBannersCount ?? 0 }} active · Manage</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-sky-50 dark:bg-sky-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Packages card (Customer Home Page: Combined, Fruit, Vegetable) -->
        <a href="{{ route('admin.packages.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200 hover:border-orange-300 dark:hover:border-orange-600">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Packages</p>
                    <p class="text-lg font-medium text-orange-600 dark:text-orange-400">{{ number_format($totalPackages ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Set price & image · View orders</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-orange-50 dark:bg-orange-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Manage Services (Place Service Orders – Categories & Products) card -->
        <a href="#manage-services" class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200 hover:border-teal-300 dark:hover:border-teal-600">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Services (Place Service Orders)</p>
                    <p class="text-lg font-medium text-teal-600 dark:text-teal-400">{{ number_format($totalServices ?? 0) }} services</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Create & manage · {{ number_format($totalProducts ?? 0) }} products</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-teal-50 dark:bg-teal-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Report Management card (generate/schedule reports) -->
        <a href="{{ route('admin.report-management.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200 hover:border-violet-300 dark:hover:border-violet-600">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Report Management</p>
                    <p class="text-lg font-medium text-violet-600">{{ number_format($totalAdminReports ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Generate & schedule</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-violet-50 dark:bg-violet-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <!-- Info Message -->
    @if(session('info'))
        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm">{{ session('info') }}</span>
        </div>
    @endif

    <!-- 1. Key Metrics -->
    <div id="key-metrics" class="scroll-mt-24 mb-6 md:mb-8">
        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50 border-l-4 border-blue-500 pl-3 mb-4">Key Metrics</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <!-- Total Users Card -->
        <a href="{{ route('admin.users.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 p-5 md:p-6 shadow-md hover:shadow-lg transition-all duration-200 hover:border-blue-400 dark:hover:border-blue-500 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">Total Users</p>
                    <p class="text-xl font-bold text-blue-700 dark:text-blue-300">{{ number_format($totalUsers ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">All registered users</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>

        <!-- Active Subscriptions Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 p-5 md:p-6 shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">Active Subscriptions</p>
                    <p class="text-xl font-bold text-green-700 dark:text-green-300">{{ number_format($activeSubscriptions ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Currently active</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-green-50 dark:bg-green-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Revenue Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 p-5 md:p-6 shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">Total Revenue</p>
                    <p class="text-xl font-bold text-amber-700 dark:text-amber-300">AED {{ number_format($totalRevenue ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">All time revenue</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Orders Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 p-5 md:p-6 shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide mb-1">Total Orders</p>
                    <p class="text-xl font-bold text-purple-700 dark:text-purple-300">{{ number_format($totalOrders ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">{{ $ordersToday ?? 0 }} today</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-900/30">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. User Statistics -->
    <div id="user-statistics" class="scroll-mt-24 mb-6 md:mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50 border-l-4 border-purple-500 pl-3">User Statistics</h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Track growth of customers, technicians, and employees</p>
                </div>
                <div class="flex items-center gap-2">
                    <label for="stats_range" class="text-sm font-medium text-gray-700 dark:text-gray-300">Time Range:</label>
                    <select id="stats_range" name="stats_range" onchange="updateStatistics()" 
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="daily" {{ ($timeRange ?? 'monthly') == 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="weekly" {{ ($timeRange ?? 'monthly') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ ($timeRange ?? 'monthly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="yearly" {{ ($timeRange ?? 'monthly') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Customers Card -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/30 rounded-xl border border-blue-200 dark:border-blue-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-blue-500 rounded-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Customers</h3>
                        </div>
                    </div>
                    <div class="mb-2">
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['customers']['current'] ?? 0) }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $stats['period_label'] ?? 'This Month' }}</p>
                    </div>
                    @if(isset($stats['customers']['growth']))
                        <div class="flex items-center gap-2">
                            @if($stats['customers']['growth'] >= 0)
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                <span class="text-sm font-medium text-green-600">+{{ $stats['customers']['growth'] }}%</span>
                            @else
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                </svg>
                                <span class="text-sm font-medium text-red-600">{{ $stats['customers']['growth'] }}%</span>
                            @endif
                            <span class="text-xs text-gray-500">vs previous period</span>
                        </div>
                    @endif
                </div>

                <!-- Technicians Card -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/30 rounded-xl border border-green-200 dark:border-green-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-green-500 rounded-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Technicians</h3>
                        </div>
                    </div>
                    <div class="mb-2">
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ number_format($stats['technicians']['current'] ?? 0) }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $stats['period_label'] ?? 'This Month' }}</p>
                    </div>
                    @if(isset($stats['technicians']['growth']))
                        <div class="flex items-center gap-2">
                            @if($stats['technicians']['growth'] >= 0)
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                <span class="text-sm font-medium text-green-600">+{{ $stats['technicians']['growth'] }}%</span>
                            @else
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                </svg>
                                <span class="text-sm font-medium text-red-600">{{ $stats['technicians']['growth'] }}%</span>
                            @endif
                            <span class="text-xs text-gray-500">vs previous period</span>
                        </div>
                    @endif
                </div>

                <!-- Employees/Staff Card -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/30 rounded-xl border border-purple-200 dark:border-purple-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="p-2 bg-purple-500 rounded-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Employees/Staff</h3>
                        </div>
                    </div>
                    <div class="mb-2">
                        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($stats['employees']['current'] ?? 0) }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $stats['period_label'] ?? 'This Month' }}</p>
                    </div>
                    @if(isset($stats['employees']['growth']))
                        <div class="flex items-center gap-2">
                            @if($stats['employees']['growth'] >= 0)
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                <span class="text-sm font-medium text-green-600">+{{ $stats['employees']['growth'] }}%</span>
                            @else
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                </svg>
                                <span class="text-sm font-medium text-red-600">{{ $stats['employees']['growth'] }}%</span>
                            @endif
                            <span class="text-xs text-gray-500">vs previous period</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateStatistics() {
            const range = document.getElementById('stats_range').value;
            const url = new URL(window.location.href);
            url.searchParams.set('stats_range', range);
            window.location.href = url.toString();
        }
    </script>

    <!-- 3. Visits & Alerts (Secondary Metrics) -->
    <div id="secondary-metrics" class="scroll-mt-24 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        <!-- Total Visits -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Total Visits</p>
                    <p class="text-lg font-medium text-indigo-600 dark:text-indigo-400">{{ number_format($totalVisits ?? 0) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $visitsToday ?? 0 }} today</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Today's Visits -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Today's Visits</p>
                    <p class="text-lg font-medium text-indigo-600 dark:text-indigo-400">{{ $visitsToday ?? 0 }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Scheduled today</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Complaints -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Pending Complaints</p>
                    <p class="text-lg font-medium text-red-600 dark:text-red-400">{{ $pendingComplaints ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/30">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Reports -->
        <a href="{{ route('admin.reports.index') }}" class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-5 shadow-sm hover:shadow-md hover:border-yellow-300 dark:hover:border-yellow-600 transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Pending Reports</p>
                    <p class="text-lg font-medium text-yellow-600 dark:text-yellow-400">{{ $pendingReports ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-50 dark:bg-yellow-900/30">
                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
        </a>

        <!-- Active Regions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 md:p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Active Regions</p>
                    <p class="text-lg font-medium text-teal-600 dark:text-teal-400">{{ $activeRegions ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50 dark:bg-teal-900/30">
                    <svg class="w-5 h-5 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Services (Place Service Orders – Categories & Products) -->
    <div id="manage-services" class="scroll-mt-24 mb-6 md:mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Manage Services (Place Service Orders)</h2>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-teal-100 dark:bg-teal-900/40 text-teal-800 dark:text-teal-200">{{ number_format($totalServices ?? 0) }} services</span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{{ number_format($totalCategories ?? 0) }} categories</span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{{ number_format($totalProducts ?? 0) }} products</span>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-5 md:p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Services appear in the app under <strong>Place Service Orders</strong> (e.g. Tree Care, Fruits, Vegetables). Create a service (optionally link to a category), then link products to it. Disabled services show &quot;Coming Soon&quot; in the app.</p>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.services.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Create Service
                    </a>
                    <a href="{{ route('admin.services.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        View All Services
                    </a>
                    <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-teal-700 dark:text-teal-300 border border-teal-300 dark:border-teal-600 rounded-lg hover:bg-teal-50 dark:hover:bg-teal-900/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                        Categories ({{ $activeCategories ?? 0 }} active)
                    </a>
                    <a href="{{ route('admin.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        Products
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Export & Send to Supplier -->
    <div id="orders-export-supplier" class="scroll-mt-24 mb-6 md:mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Orders Export & Send to Supplier</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">Orders page →</a>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-5 md:p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Export orders to CSV or Excel, or email the export to your supplier. Use filters (date range, package) on the Orders page.</p>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.orders.export', ['format' => 'csv']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Download CSV
                    </a>
                    <a href="{{ route('admin.orders.export', ['format' => 'xlsx']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Download Excel
                    </a>
                    <a href="{{ route('admin.orders.index') }}#send-supplier" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        Send to supplier
                    </a>
                </div>
                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Scheduled daily send runs at 07:00 (last 7 days). Configure MAIL_SUPPLIER_EMAIL in .env.</p>
            </div>
        </div>
    </div>

    <!-- 4. E-Commerce Section -->
    <div id="ecommerce" class="scroll-mt-24 mb-6 md:mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-50 border-l-4 border-emerald-500 pl-3">E-Commerce Overview</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">View All Orders →</a>
        </div>

        <!-- E-Commerce Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
            <!-- Paid Orders -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Paid Orders</p>
                        <p class="text-lg font-medium text-green-600 dark:text-green-400">{{ number_format($paidOrders ?? 0) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format(($paidOrders ?? 0) / max($totalOrders ?? 1, 1) * 100, 1) }}% of total</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Pending Payments</p>
                        <p class="text-lg font-medium text-yellow-600 dark:text-yellow-400">{{ number_format($pendingPayments ?? 0) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Awaiting payment</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-yellow-50 dark:bg-yellow-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Revenue This Month -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Revenue (Month)</p>
                        <p class="text-lg font-medium text-indigo-600 dark:text-indigo-400">AED {{ number_format($revenueThisMonth ?? 0) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This month</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Products -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Products</p>
                        <p class="text-lg font-medium text-blue-600">{{ number_format($totalProducts ?? 0) }}</p>
                        <p class="mt-1 text-xs text-red-600">{{ $lowStockProducts ?? 0 }} low stock</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-blue-50 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Status -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Order Status Breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">Order Status</h3>
                <div class="space-y-3">
                    @foreach($ordersByStatus ?? [] as $status)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    {{ $status->order_status === 'delivered' ? 'bg-green-100 text-green-800' : 
                                       ($status->order_status === 'processing' ? 'bg-blue-100 text-blue-800' : 
                                       ($status->order_status === 'shipped' ? 'bg-purple-100 text-purple-800' : 
                                       ($status->order_status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'))) }}">
                                    {{ ucfirst($status->order_status) }}
                                </span>
                            </div>
                            <span class="text-sm font-medium text-indigo-600">{{ $status->count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Payment Status Breakdown -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">Payment Status</h3>
                <div class="space-y-3">
                    @foreach($ordersByPaymentStatus ?? [] as $payment)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    {{ $payment->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                                       ($payment->payment_status === 'failed' ? 'bg-red-100 text-red-800' : 
                                       ($payment->payment_status === 'refunded' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </div>
                            <span class="text-sm font-medium text-indigo-600">{{ $payment->count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    <!-- 5. Performance Sections -->
    <div id="performance" class="scroll-mt-24 grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Technician Performance Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Technician Performance</h3>
                <p class="text-sm text-gray-500 mt-1">Top 10 technicians by completed visits</p>
            </div>
            <div class="space-y-3">
                @forelse($technicianPerformance ?? [] as $technician)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white text-sm font-medium">
                                {{ strtoupper(substr($technician->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $technician->name }}</p>
                                <p class="text-xs text-gray-500">{{ $technician->email }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-blue-600">{{ $technician->visits_count ?? 0 }}</p>
                            <p class="text-xs text-gray-500">visits</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No technician data available</p>
                @endforelse
            </div>
        </div>

        <!-- Area Performance Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Area Performance</h3>
                <p class="text-sm text-gray-500 mt-1">Visits by area/region</p>
            </div>
            <div class="space-y-3">
                @forelse($areaPerformance ?? [] as $area)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-teal-600 text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $area->name }}</p>
                                <p class="text-xs text-gray-500">{{ $area->city ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-green-600">{{ $area->visits_count ?? 0 }}</p>
                            <p class="text-xs text-gray-500">visits</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No area data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 6. Charts -->
    <div id="charts" class="scroll-mt-24 grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Revenue Growth Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Revenue Growth</h3>
                <p class="text-sm text-gray-500 mt-1">Monthly revenue over the last 6 months</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Visits Activity Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Visits Activity</h3>
                <p class="text-sm text-gray-500 mt-1">Weekly visits over the last 8 weeks</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="visitsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 7. Distribution -->
    <div id="distribution-charts" class="scroll-mt-24 grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Subscription Distribution -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Subscription Distribution</h3>
                <p class="text-sm text-gray-500 mt-1">Active subscriptions by plan type</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="subscriptionsChart"></canvas>
            </div>
        </div>

        <!-- Visit Status Distribution -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Visit Status</h3>
                <p class="text-sm text-gray-500 mt-1">Distribution of visits by status</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="visitStatusChart"></canvas>
            </div>
        </div>
    </div>


    <!-- Chart.js Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue Chart
            fetch('{{ route("admin.analytics.revenue") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.revenue || data.revenue.length === 0) {
                        console.warn('No revenue data available');
                        // Show empty state
                        const ctx = document.getElementById('revenueChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: ['No Data'],
                                    datasets: [{
                                        label: 'Revenue (AED)',
                                        data: [0],
                                        borderColor: 'rgb(99, 102, 241)',
                                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false }
                                    }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('revenueChart'), {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Revenue (AED)',
                                data: data.revenue,
                                borderColor: 'rgb(99, 102, 241)',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'AED ' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading revenue chart:', error);
                });

            // Visits Chart
            fetch('{{ route("admin.analytics.visits") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.counts || data.counts.length === 0) {
                        console.warn('No visits data available');
                        const ctx = document.getElementById('visitsChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: ['No Data'],
                                    datasets: [{
                                        label: 'Visits',
                                        data: [0],
                                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('visitsChart'), {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Visits',
                                data: data.counts,
                                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                                borderColor: 'rgb(16, 185, 129)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading visits chart:', error);
                });

            // Subscriptions Chart
            fetch('{{ route("admin.analytics.subscriptions") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.counts || data.counts.length === 0 || (data.counts.length === 1 && data.counts[0] === 0)) {
                        console.warn('No subscriptions data available');
                        const ctx = document.getElementById('subscriptionsChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: ['No Active Subscriptions'],
                                    datasets: [{
                                        data: [1],
                                        backgroundColor: ['rgba(156, 163, 175, 0.5)']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('subscriptionsChart'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.counts,
                                backgroundColor: [
                                    'rgba(99, 102, 241, 0.8)',
                                    'rgba(16, 185, 129, 0.8)',
                                    'rgba(245, 158, 11, 0.8)',
                                    'rgba(239, 68, 68, 0.8)',
                                    'rgba(139, 92, 246, 0.8)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading subscriptions chart:', error);
                });

            // Visit Status Chart
            fetch('{{ route("admin.analytics.visit-status") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.counts || data.counts.length === 0) {
                        console.warn('No visit status data available');
                        const ctx = document.getElementById('visitStatusChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'pie',
                                data: {
                                    labels: ['No Visits'],
                                    datasets: [{
                                        data: [1],
                                        backgroundColor: ['rgba(156, 163, 175, 0.5)']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('visitStatusChart'), {
                        type: 'pie',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.counts,
                                backgroundColor: [
                                    'rgba(16, 185, 129, 0.8)',
                                    'rgba(99, 102, 241, 0.8)',
                                    'rgba(245, 158, 11, 0.8)',
                                    'rgba(239, 68, 68, 0.8)',
                                    'rgba(139, 92, 246, 0.8)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading visit status chart:', error);
                });
        });
    </script>

    <!-- 8. Roles & Users -->
    <div id="roles-users" class="scroll-mt-24 mt-6 md:mt-8">
        <div class="mb-4 md:mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg md:text-xl font-semibold text-gray-900">Roles & User Assignments</h2>
                    <p class="mt-1 text-sm text-gray-500">View all roles and manage user assignments</p>
                </div>
                <a href="{{ route('admin.roles.index') }}" 
                   class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Manage Roles
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
            @forelse($rolesWithUsers ?? [] as $role)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-shadow duration-200">
                    <!-- Role Header -->
                    <div class="px-4 py-3 md:px-5 md:py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-sm md:text-base font-semibold text-gray-900 truncate">
                                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                    </h3>
                                    <span class="flex-shrink-0 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700 min-w-[1.75rem]">
                                        {{ $role->users_count ?? 0 }}
                                    </span>
                                </div>
                                @if($role->description)
                                    <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ Str::limit($role->description, 60) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users List -->
                    <div class="p-3 md:p-4 flex-1 overflow-y-auto" style="max-height: 400px;">
                        @if($role->users && $role->users->count() > 0)
                            <div class="space-y-2">
                                @foreach($role->users as $user)
                                    <div class="flex items-center gap-2.5 p-2.5 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-gray-100">
                                        <!-- Avatar -->
                                        <div class="flex-shrink-0 h-8 w-8 md:h-9 md:w-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-semibold">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        
                                        <!-- User Info -->
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs md:text-sm font-medium text-gray-900 truncate">{{ $user->name }}</p>
                                            <p class="text-xs text-gray-500 truncate mt-0.5">{{ $user->email }}</p>
                                        </div>
                                        
                                        <!-- Status & Actions -->
                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium 
                                                {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                                {{ ucfirst($user->status) }}
                                            </span>
                                            <button 
                                                onclick="openRoleModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ addslashes($user->email) }}', '{{ $user->role }}')"
                                                class="p-1 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                                                title="Change Role">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6 md:py-8">
                                <div class="w-12 h-12 md:w-16 md:h-16 mx-auto mb-3 rounded-full bg-gray-100 flex items-center justify-center">
                                    <svg class="w-6 h-6 md:w-8 md:h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </div>
                                <p class="text-xs md:text-sm text-gray-500 font-medium">No users assigned</p>
                                <p class="text-xs text-gray-400 mt-1">Assign users to this role</p>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full">
                    <div class="bg-white rounded-xl border border-gray-200 p-8 md:p-12 text-center">
                        <div class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                            <svg class="w-8 h-8 md:w-10 md:h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">No Roles Found</h3>
                        <p class="text-sm text-gray-500 mb-4">Create roles to start managing user assignments</p>
                        <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Create Role
                        </a>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <!-- 9. All Active Users -->
    <div id="all-users" class="scroll-mt-24 mt-6 md:mt-8">
        <div class="mb-4 md:mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg md:text-xl font-semibold text-gray-900">All Active Users</h2>
                    <p class="mt-1 text-sm text-gray-500">Complete list of all active users and their assigned roles</p>
                </div>
                <a href="{{ route('admin.users.index') }}" 
                   class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    View All Users
                </a>
            </div>
        </div>

        <!-- Desktop Table View -->
        <div id="users-table-container" class="hidden lg:block bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div id="users-table-wrapper" class="overflow-x-auto">
                <div id="users-loading" class="hidden absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-xl">
                    <div class="flex flex-col items-center gap-3">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                        <p class="text-sm text-gray-600">Loading users...</p>
                    </div>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 xl:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col" class="px-4 xl:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email
                            </th>
                            <th scope="col" class="px-4 xl:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Phone
                            </th>
                            <th scope="col" class="px-4 xl:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th scope="col" class="px-4 xl:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-4 xl:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body" class="bg-white divide-y divide-gray-200">
                        @forelse($allActiveUsers ?? [] as $user)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 xl:px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                            {{ strtoupper(substr($user['name'], 0, 1)) }}
                                        </div>
                                        <div class="ml-3 xl:ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $user['name'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 xl:px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 truncate max-w-xs">{{ $user['email'] }}</div>
                                </td>
                                <td class="px-4 xl:px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">{{ $user['phone'] ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 xl:px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ ucfirst(str_replace('_', ' ', $user['role'])) }}
                                    </span>
                                </td>
                                <td class="px-4 xl:px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($user['status']) }}
                                    </span>
                                </td>
                                <td class="px-4 xl:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button 
                                        onclick="openRoleModal({{ $user['id'] }}, '{{ addslashes($user['name']) }}', '{{ addslashes($user['email']) }}', '{{ $user['role'] }}')"
                                        class="text-indigo-600 hover:text-indigo-900 transition-colors"
                                        title="Change Role">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <p class="text-sm text-gray-500 font-medium">No active users found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination - Desktop -->
            <div id="users-pagination-desktop">
                @if(isset($allActiveUsersPaginated) && $allActiveUsersPaginated->hasPages())
                    <div class="px-4 xl:px-6 py-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="text-xs sm:text-sm text-gray-600">
                                Showing 
                                <span class="font-medium text-gray-900" id="users-from">{{ $allActiveUsersPaginated->firstItem() ?? 0 }}</span>
                                to 
                                <span class="font-medium text-gray-900" id="users-to">{{ $allActiveUsersPaginated->lastItem() ?? 0 }}</span>
                                of 
                                <span class="font-medium text-gray-900" id="users-total">{{ $allActiveUsersPaginated->total() }}</span>
                                users
                            </div>
                            <div class="flex items-center justify-center" id="users-pagination-links-desktop">
                                {{ $allActiveUsersPaginated->appends(request()->except('users_page'))->links() }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Mobile/Tablet Card View -->
        <div id="users-cards-container" class="lg:hidden space-y-3">
            <div id="users-loading-mobile" class="hidden fixed inset-0 bg-white bg-opacity-90 flex items-center justify-center z-50">
                <div class="flex flex-col items-center gap-3">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
                    <p class="text-sm text-gray-600">Loading users...</p>
                </div>
            </div>
            <div id="users-cards-wrapper">
                @forelse($allActiveUsers ?? [] as $user)
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                        <div class="flex items-start gap-3">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                {{ strtoupper(substr($user['name'], 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $user['name'] }}</h3>
                                        <p class="text-xs text-gray-500 truncate mt-0.5">{{ $user['email'] }}</p>
                                    </div>
                                    <button 
                                        onclick="openRoleModal({{ $user['id'] }}, '{{ addslashes($user['name']) }}', '{{ addslashes($user['email']) }}', '{{ $user['role'] }}')"
                                        class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors flex-shrink-0"
                                        title="Change Role">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ ucfirst(str_replace('_', ' ', $user['role'])) }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium 
                                        {{ $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($user['status']) }}
                                    </span>
                                    @if($user['phone'])
                                        <span class="text-xs text-gray-500">📞 {{ $user['phone'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500 font-medium">No active users found</p>
                    </div>
                @endforelse
            </div>
            
            <!-- Pagination - Mobile/Tablet -->
            <div id="users-pagination-mobile">
                @if(isset($allActiveUsersPaginated) && $allActiveUsersPaginated->hasPages())
                    <div class="mt-4 bg-white rounded-xl border border-gray-200 p-4">
                        <div class="flex flex-col items-center justify-between gap-3">
                            <div class="text-xs text-gray-600 text-center">
                                Showing 
                                <span class="font-medium text-gray-900" id="users-from-mobile">{{ $allActiveUsersPaginated->firstItem() ?? 0 }}</span>
                                to 
                                <span class="font-medium text-gray-900" id="users-to-mobile">{{ $allActiveUsersPaginated->lastItem() ?? 0 }}</span>
                                of 
                                <span class="font-medium text-gray-900" id="users-total-mobile">{{ $allActiveUsersPaginated->total() }}</span>
                                users
                            </div>
                            <div class="flex items-center justify-center w-full" id="users-pagination-links-mobile">
                                {{ $allActiveUsersPaginated->appends(request()->except('users_page'))->links() }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Tips -->
    <div id="recent-tips" class="scroll-mt-24 mb-6 md:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-900">Recent Tips</h2>
                <a href="{{ route('admin.tips.index') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition-colors">
                    View All
                </a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentTips ?? [] as $tip)
                    <a href="{{ route('admin.tips.show', $tip->id) }}" class="block px-5 py-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $tip->title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ Str::limit($tip->content, 100) }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $tip->created_at?->format('M d, Y') }} · {{ ucfirst($tip->status) }}</p>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-500">No tips yet. <a href="{{ route('admin.tips.create') }}" class="text-emerald-600 hover:underline">Create one</a></div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 10. Recent Activities -->
    <div id="recent-activities" class="scroll-mt-24 mb-6 md:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-900">Recent Activities</h2>
                <a href="{{ route('admin.recent-activities.index') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 transition-colors">
                    View All
                </a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentActivities ?? [] as $activity)
                    <div class="px-5 py-4 flex items-start gap-4 hover:bg-gray-50 transition-colors">
                        @if(($activity['icon_type'] ?? '') === 'success' || ($activity['icon_type'] ?? '') === 'check')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'user_add')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-700/10">
                                <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3z" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'warning')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'error')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'order')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            </div>
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $activity['description'] ?? '' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $activity['timestamp'] ?? '' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-500">No recent activities yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Role Update Modal -->
    <div id="roleModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-xl bg-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Change User Role</h3>
                <button onclick="closeRoleModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form id="roleUpdateForm" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect_to" value="dashboard">
                
                <div>
                    <p class="text-sm text-gray-600 mb-1">User</p>
                    <p class="text-sm font-medium text-gray-900" id="modalUserName"></p>
                    <p class="text-xs text-gray-500" id="modalUserEmail"></p>
                </div>

                <div>
                    <label for="newRole" class="block text-sm font-medium text-gray-700 mb-2">
                        Select New Role <span class="text-red-500">*</span>
                    </label>
                    <select id="newRole" name="role" required 
                            class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition">
                        @foreach(\Spatie\Permission\Models\Role::orderBy('name')->get() as $roleOption)
                            <option value="{{ $roleOption->name }}">
                                {{ ucfirst(str_replace('_', ' ', $roleOption->name)) }}
                                @if($roleOption->description)
                                    - {{ Str::limit($roleOption->description, 50) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeRoleModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800 transition-colors">
                        Update Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRoleModal(userId, userName, userEmail, currentRole) {
            document.getElementById('roleModal').classList.remove('hidden');
            document.getElementById('modalUserName').textContent = userName;
            document.getElementById('modalUserEmail').textContent = userEmail;
            document.getElementById('newRole').value = currentRole;
            document.getElementById('roleUpdateForm').action = `/admin/users/${userId}`;
        }

        function closeRoleModal() {
            document.getElementById('roleModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRoleModal();
            }
        });

        // AJAX Pagination for Active Users
        document.addEventListener('DOMContentLoaded', function() {
            const usersTableContainer = document.getElementById('users-table-container');
            const usersCardsContainer = document.getElementById('users-cards-container');
            const usersLoading = document.getElementById('users-loading');
            const usersLoadingMobile = document.getElementById('users-loading-mobile');
            
            // Function to handle pagination clicks
            function handlePaginationClick(e) {
                const link = e.target.closest('a');
                if (!link || !link.href || link.href.includes('#') || link.classList.contains('cursor-not-allowed')) {
                    return;
                }
                
                // Check if it's a pagination link
                if (!link.href.includes('users_page') && !link.getAttribute('rel')) {
                    return;
                }
                
                e.preventDefault();
                e.stopPropagation();
                const url = link.href;
                
                // Show loading state
                if (usersTableContainer && !usersTableContainer.classList.contains('hidden')) {
                    usersTableContainer.style.position = 'relative';
                    if (usersLoading) usersLoading.classList.remove('hidden');
                }
                if (usersCardsContainer && !usersCardsContainer.classList.contains('hidden')) {
                    if (usersLoadingMobile) usersLoadingMobile.classList.remove('hidden');
                }
                
                // Make AJAX request
                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Create a temporary container to parse the HTML
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Extract users table/cards and pagination
                    const newTableBody = tempDiv.querySelector('#users-table-body');
                    const newCardsWrapper = tempDiv.querySelector('#users-cards-wrapper');
                    const newPaginationDesktop = tempDiv.querySelector('#users-pagination-desktop');
                    const newPaginationMobile = tempDiv.querySelector('#users-pagination-mobile');
                    const newUsersFrom = tempDiv.querySelector('#users-from');
                    const newUsersTo = tempDiv.querySelector('#users-to');
                    const newUsersTotal = tempDiv.querySelector('#users-total');
                    const newUsersFromMobile = tempDiv.querySelector('#users-from-mobile');
                    const newUsersToMobile = tempDiv.querySelector('#users-to-mobile');
                    const newUsersTotalMobile = tempDiv.querySelector('#users-total-mobile');
                    
                    // Update desktop table with fade animation
                    if (usersTableContainer && newTableBody) {
                        const currentTableBody = document.getElementById('users-table-body');
                        if (currentTableBody) {
                            currentTableBody.style.opacity = '0';
                            currentTableBody.style.transition = 'opacity 0.3s ease-out';
                            
                            setTimeout(() => {
                                currentTableBody.innerHTML = newTableBody.innerHTML;
                                currentTableBody.style.opacity = '1';
                                
                                // Update pagination
                                const paginationDesktop = document.getElementById('users-pagination-desktop');
                                if (paginationDesktop && newPaginationDesktop) {
                                    paginationDesktop.innerHTML = newPaginationDesktop.innerHTML;
                                }
                                
                                // Update pagination info
                                if (newUsersFrom) document.getElementById('users-from').textContent = newUsersFrom.textContent;
                                if (newUsersTo) document.getElementById('users-to').textContent = newUsersTo.textContent;
                                if (newUsersTotal) document.getElementById('users-total').textContent = newUsersTotal.textContent;
                                
                                // Re-attach event listeners
                                attachPaginationListeners();
                                
                                // Hide loading
                                if (usersLoading) usersLoading.classList.add('hidden');
                            }, 150);
                        }
                    }
                    
                    // Update mobile cards with fade animation
                    if (usersCardsContainer && newCardsWrapper) {
                        const currentCardsWrapper = document.getElementById('users-cards-wrapper');
                        if (currentCardsWrapper) {
                            currentCardsWrapper.style.opacity = '0';
                            currentCardsWrapper.style.transition = 'opacity 0.3s ease-out';
                            
                            setTimeout(() => {
                                currentCardsWrapper.innerHTML = newCardsWrapper.innerHTML;
                                currentCardsWrapper.style.opacity = '1';
                                
                                // Update pagination
                                const paginationMobile = document.getElementById('users-pagination-mobile');
                                if (paginationMobile && newPaginationMobile) {
                                    paginationMobile.innerHTML = newPaginationMobile.innerHTML;
                                }
                                
                                // Update pagination info
                                if (newUsersFromMobile) document.getElementById('users-from-mobile').textContent = newUsersFromMobile.textContent;
                                if (newUsersToMobile) document.getElementById('users-to-mobile').textContent = newUsersToMobile.textContent;
                                if (newUsersTotalMobile) document.getElementById('users-total-mobile').textContent = newUsersTotalMobile.textContent;
                                
                                // Re-attach event listeners
                                attachPaginationListeners();
                                
                                // Hide loading
                                if (usersLoadingMobile) usersLoadingMobile.classList.add('hidden');
                            }, 150);
                        }
                    }
                    
                    // Update URL without page reload
                    window.history.pushState({}, '', url);
                    
                    // Scroll to top of users section smoothly
                    const usersSection = document.querySelector('#users-table-container, #users-cards-container');
                    if (usersSection) {
                        usersSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    // Hide loading on error
                    if (usersLoading) usersLoading.classList.add('hidden');
                    if (usersLoadingMobile) usersLoadingMobile.classList.add('hidden');
                    
                    // Show error message
                    if (window.toast) {
                        window.toast.error('Failed to load users. Please try again.');
                    }
                });
            }
            
            // Function to attach pagination listeners using event delegation
            function attachPaginationListeners() {
                // Remove old listeners to prevent duplicates
                const desktopPagination = document.getElementById('users-pagination-links-desktop');
                const mobilePagination = document.getElementById('users-pagination-links-mobile');
                
                // Use event delegation on the pagination containers
                if (desktopPagination) {
                    // Remove any existing listeners by cloning
                    const newDesktop = desktopPagination.cloneNode(true);
                    desktopPagination.parentNode.replaceChild(newDesktop, desktopPagination);
                    document.getElementById('users-pagination-links-desktop').addEventListener('click', handlePaginationClick);
                }
                
                if (mobilePagination) {
                    // Remove any existing listeners by cloning
                    const newMobile = mobilePagination.cloneNode(true);
                    mobilePagination.parentNode.replaceChild(newMobile, mobilePagination);
                    document.getElementById('users-pagination-links-mobile').addEventListener('click', handlePaginationClick);
                }
            }
            
            // Use event delegation on document for better reliability
            document.addEventListener('click', function(e) {
                // Check if click is on a pagination link within our users section
                const link = e.target.closest('a[href*="users_page"]');
                if (!link) return;
                
                const paginationContainer = link.closest('#users-pagination-links-desktop, #users-pagination-links-mobile');
                if (paginationContainer) {
                    handlePaginationClick(e);
                }
            });
            
            // Initial attachment (backup)
            attachPaginationListeners();
        });
    </script>

    <!-- Back to top button -->
    <button type="button" id="back-to-top" class="fixed bottom-6 right-6 z-40 hidden p-3 rounded-full bg-gray-900 text-white shadow-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-all" aria-label="Back to top">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
    </button>
    <script>
        (function() {
            var backToTop = document.getElementById('back-to-top');
            if (!backToTop) return;
            function toggle() {
                if (window.scrollY > 400) backToTop.classList.remove('hidden');
                else backToTop.classList.add('hidden');
            }
            window.addEventListener('scroll', toggle);
            toggle();
            backToTop.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();
    </script>
</x-admin-layout>

