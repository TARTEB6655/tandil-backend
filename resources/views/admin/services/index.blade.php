<x-admin-layout>
    <div class="space-y-6">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Dashboard</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">Services</span>
                </nav>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Services</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Click a service card to see its category and the products assigned to it.
                </p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('admin.categories.index') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Manage Categories
                </a>
                <a href="{{ route('admin.products.create') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Add product
                </a>
            </div>
        </div>

        <!-- Service cards grid -->
        @php
            $iconMap = ['water' => '💧', 'leaf' => '🌿', 'broom' => '🧹', 'heart' => '❤️', 'wrench' => '🔧'];
            $gradients = [
                'water' => 'from-cyan-500/10 to-blue-500/10 dark:from-cyan-500/20 dark:to-blue-500/20 border-cyan-200/50 dark:border-cyan-500/30',
                'leaf' => 'from-emerald-500/10 to-green-500/10 dark:from-emerald-500/20 dark:to-green-500/20 border-emerald-200/50 dark:border-emerald-500/30',
                'broom' => 'from-amber-500/10 to-orange-500/10 dark:from-amber-500/20 dark:to-orange-500/20 border-amber-200/50 dark:border-amber-500/30',
                'heart' => 'from-rose-500/10 to-pink-500/10 dark:from-rose-500/20 dark:to-pink-500/20 border-rose-200/50 dark:border-rose-500/30',
                'wrench' => 'from-slate-500/10 to-gray-500/10 dark:from-slate-500/20 dark:to-gray-500/20 border-slate-200/50 dark:border-slate-500/30',
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @forelse($categories as $cat)
                @php
                    $icon = $cat->icon && isset($iconMap[$cat->icon]) ? $iconMap[$cat->icon] : '📋';
                    $gradient = $gradients[$cat->icon ?? ''] ?? 'from-gray-500/10 to-gray-500/10 dark:from-gray-500/20 dark:to-gray-500/20 border-gray-200/50 dark:border-gray-500/30';
                @endphp
                <a href="{{ route('admin.services.category', $cat->id) }}" 
                   class="group block rounded-2xl border bg-white dark:bg-gray-800 shadow-sm hover:shadow-lg transition-all duration-200 overflow-hidden {{ $gradient }} border hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <div class="p-6 flex flex-col h-full min-h-[180px]">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <span class="text-4xl" aria-hidden="true">{{ $icon }}</span>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                {{ $cat->products_count }} {{ Str::plural('product', $cat->products_count) }}
                            </span>
                        </div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                            {{ $cat->name }}
                        </h2>
                        @if($cat->description)
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2 flex-1">
                                {{ Str::limit($cat->description, 80) }}
                            </p>
                        @endif
                        <span class="mt-4 inline-flex items-center text-sm font-medium text-indigo-600 dark:text-indigo-400">
                            View category & products
                            <svg class="w-4 h-4 ml-1 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </div>
                </a>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 p-12 text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-1">No services yet</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Create categories to see service cards here.</p>
                    <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100">
                        Manage Categories
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
