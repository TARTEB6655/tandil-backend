@auth
    @if(auth()->user()->role === 'admin')
        @php
            $user = auth()->user();
        @endphp

        <!-- Mobile Overlay -->
        <div x-data="{ sidebarOpen: false }" 
             x-init="
                window.addEventListener('toggle-sidebar', () => { sidebarOpen = !sidebarOpen; });
                $watch('sidebarOpen', value => {
                    if (value && window.innerWidth < 1024) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                });
             "
             class="lg:hidden">
            <div x-show="sidebarOpen" 
                 x-transition:enter="transition-opacity duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="sidebarOpen = false"
                 class="fixed inset-0 bg-black bg-opacity-50 z-40"
                 style="display: none;"></div>
        </div>

        <!-- Sidebar -->
        <aside x-data="{ sidebarOpen: false }" 
               x-init="
                  window.addEventListener('toggle-sidebar', () => { sidebarOpen = !sidebarOpen; });
                  if (window.innerWidth >= 1024) { sidebarOpen = true; }
                  window.addEventListener('resize', () => {
                      if (window.innerWidth >= 1024) { sidebarOpen = true; }
                      else { sidebarOpen = false; }
                  });
               "
               :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed lg:fixed inset-y-0 left-0 z-50 w-64 bg-gray-50 border-r border-gray-200 transform transition-transform duration-300 ease-in-out">
            
            <div class="flex flex-col h-full">
                <!-- Logo Section -->
                <div class="px-6 py-5 border-b border-gray-200 bg-white">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-2">
                            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto" onerror="this.style.display='none'" />
                            <span class="text-xl font-bold text-gray-900">{{ config('app.name', 'Tandil') }}</span>
                        </a>
                        <button @click="sidebarOpen = false" class="lg:hidden text-gray-500 hover:text-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    <!-- Dashboard -->
                    <a href="{{ route('admin.dashboard') }}" 
                       class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="text-sm">Dashboard</span>
                    </a>

                    <!-- Section: User Management -->
                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">User Management</p>
                        <a href="{{ route('admin.users.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.users.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="text-sm">Users</span>
                        </a>
                        <a href="{{ route('admin.roles.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.roles.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="text-sm">Roles & Permissions</span>
                        </a>
                    </div>

                    <!-- Section: Subscriptions -->
                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Subscriptions</p>
                        <a href="{{ route('admin.subscription-plans.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.subscription-plans.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm">Plans</span>
                        </a>
                        <a href="{{ route('admin.subscriptions.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.subscriptions.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm">All Subscriptions</span>
                        </a>
                    </div>

                    <!-- Section: Operations -->
                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Operations</p>
                        <a href="{{ route('admin.visits.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.visits.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-sm">Visits</span>
                        </a>
                        <a href="{{ route('admin.reports.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.reports.*') && !request()->routeIs('admin.report-management.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm">Reports</span>
                        </a>
                        <a href="{{ route('admin.report-management.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.report-management.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm">Report Management</span>
                        </a>
                        <a href="{{ route('admin.areas.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.areas.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm">Operational Areas</span>
                        </a>
                    </div>

                    <!-- Section: E-Commerce -->
                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">E-Commerce</p>
                        <a href="{{ route('admin.products.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.products.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span class="text-sm">Products</span>
                        </a>
                        <a href="{{ route('admin.orders.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.orders.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span class="text-sm">Orders</span>
                        </a>
                        <a href="{{ route('admin.orders.cancelled') }}"
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.orders.cancelled') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-1.414 1.414A9 9 0 106.343 16.657l1.414-1.414a7 7 0 119.9-9.9zM12 8v4l3 3" />
                            </svg>
                            <span class="text-sm">Cancelled Orders</span>
                        </a>
                    </div>

                    <!-- Section: Communication -->
                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Communication</p>
                        <a href="{{ route('admin.tips.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.tips.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                            <span class="text-sm">Tips & Notifications</span>
                        </a>
                        <a href="{{ route('admin.complaints.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.complaints.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="text-sm">Complaints</span>
                        </a>
                    </div>

                    <!-- Section: Management -->
                    <div class="pt-4">
                        <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Management</p>
                        <a href="{{ route('admin.hr.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.hr.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-sm">HR Management</span>
                        </a>
                        <a href="{{ route('admin.settings.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.settings.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-sm">Settings</span>
                        </a>
                        <a href="{{ route('admin.audit-logs.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.audit-logs.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm">Audit Logs</span>
                        </a>
                        <a href="{{ route('admin.banners.index') }}" 
                           class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.banners.*') ? 'bg-blue-50 text-blue-600 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-sm">Banners</span>
                        </a>
                    </div>
                </nav>
            </div>
        </aside>
    @endif
@endauth
