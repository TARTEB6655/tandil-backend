@auth
    @if(auth()->user()->role === 'admin')
        <div>
            <!-- Mobile Overlay (991px and below only) -->
            <div x-show="$store.sidebar.open" 
                 x-transition:enter="transition-opacity duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="$store.sidebar.toggle()"
                 class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden max-[991px]:block"
                 style="display: none;"></div>

            <!-- Sidebar -->
            <aside 
                class="fixed left-0 top-0 z-50 flex h-screen w-[250px] sm:w-[250px] flex-col bg-white border-r border-gray-200 transition-transform duration-300 ease-in-out -translate-x-full min-[992px]:translate-x-0 min-[992px]:shadow-none overflow-hidden"
                :class="$store.sidebar.open ? 'translate-x-0 shadow-lg min-[992px]:shadow-none' : ''"
            >
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center justify-center px-3 sm:px-4 py-6 sm:py-8 lg:justify-center">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center">
                        <img src="{{ asset('images/logo.png') }}" alt="Tandil" class="h-20 md:h-24 w-auto" style="max-width: 160px; object-fit: contain;" onerror="this.style.display='none'" />
                    </a>
                </div>

                <!-- Navigation -->
                <div class="sidebar-scroll flex-1 flex flex-col overflow-y-auto overflow-x-hidden min-h-0">
                    <nav class="px-3 py-2" x-data="{ 
                        userManagement: {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') ? 'true' : 'false' }},
                        subscriptions: {{ request()->routeIs('admin.subscription-plans.*') || request()->routeIs('admin.subscriptions.*') ? 'true' : 'false' }},
                        operations: {{ request()->routeIs('admin.visits.*') || request()->routeIs('admin.reports.*') || request()->routeIs('admin.areas.*') ? 'true' : 'false' }},
                        ecommerce: {{ request()->routeIs('admin.products.*') || request()->routeIs('admin.orders.*') ? 'true' : 'false' }},
                        communication: {{ request()->routeIs('admin.tips.*') || request()->routeIs('admin.complaints.*') ? 'true' : 'false' }},
                        management: {{ request()->routeIs('admin.hr.*') || request()->routeIs('admin.audit-logs.*') || request()->routeIs('admin.banners.*') ? 'true' : 'false' }}
                    }">
                        <!-- Dashboard -->
                        <div class="mb-2">
                            <a href="{{ route('admin.dashboard') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-gray-100' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Dashboard
                            </a>
                        </div>

                        <!-- USER MANAGEMENT -->
                        <div class="mb-2">
                            <button @click="userManagement = !userManagement" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <span>User management</span>
                                <svg class="w-3 h-3 text-gray-500 ml-auto transition-transform duration-200" :class="{ 'rotate-90': userManagement }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <ul x-show="userManagement" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="mt-1.5 flex flex-col gap-0.5">
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.users.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        Users
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.roles.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.roles.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        Roles & Permissions
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- SUBSCRIPTIONS -->
                        <div class="mb-2">
                            <button @click="subscriptions = !subscriptions" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Subscriptions</span>
                                <svg class="w-3 h-3 text-gray-500 ml-auto transition-transform duration-200" :class="{ 'rotate-90': subscriptions }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <ul x-show="subscriptions" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="mt-1.5 flex flex-col gap-0.5">
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.subscription-plans.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.subscription-plans.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Plans
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.subscriptions.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.subscriptions.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        All Subscriptions
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- OPERATIONS -->
                        <div class="mb-2">
                            <button @click="operations = !operations" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span>Operations</span>
                                <svg class="w-3 h-3 text-gray-500 ml-auto transition-transform duration-200" :class="{ 'rotate-90': operations }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <ul x-show="operations" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="mt-1.5 flex flex-col gap-0.5">
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.visits.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.visits.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Visits
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.reports.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.reports.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Reports
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.areas.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.areas.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Areas/Regions
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- E-COMMERCE -->
                        <div class="mb-2">
                            <button @click="ecommerce = !ecommerce" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                <span>E-commerce</span>
                                <svg class="w-3 h-3 text-gray-500 ml-auto transition-transform duration-200" :class="{ 'rotate-90': ecommerce }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <ul x-show="ecommerce" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="mt-1.5 flex flex-col gap-0.5">
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.products.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.products.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        Products
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.orders.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.orders.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                        Orders
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- COMMUNICATION -->
                        <div class="mb-2">
                            <button @click="communication = !communication" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <span>Communication</span>
                                <svg class="w-3 h-3 text-gray-500 ml-auto transition-transform duration-200" :class="{ 'rotate-90': communication }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <ul x-show="communication" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="mt-1.5 flex flex-col gap-0.5">
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.tips.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.tips.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                        </svg>
                                        Tips & Notifications
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.complaints.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.complaints.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        Complaints
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- MANAGEMENT -->
                        <div class="mb-2">
                            <button @click="management = !management" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>Management</span>
                                <svg class="w-3 h-3 text-gray-500 ml-auto transition-transform duration-200" :class="{ 'rotate-90': management }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <ul x-show="management" 
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="mt-1.5 flex flex-col gap-0.5">
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.hr.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.hr.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        Technicians
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.audit-logs.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.audit-logs.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Audit Logs
                                    </a>
                                </li>
                                <li style="padding-left: 20px;">
                                    <a href="{{ route('admin.banners.index') }}" 
                                       class="flex items-center gap-2.5 rounded-md pl-7 pr-3 py-1.5 text-sm font-normal text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors {{ request()->routeIs('admin.banners.*') ? 'bg-gray-100 text-gray-900' : '' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Banners
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </div>

                <!-- Settings & Logout Section -->
                <div class="flex-shrink-0 mt-auto border-t border-gray-200 px-3 py-3 space-y-1">
                    <!-- Settings -->
                    <a href="{{ route('admin.settings.index') }}" 
                       class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('admin.settings.*') ? 'bg-gray-100' : '' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Settings
                    </a>

                    <!-- Logout -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 hover:text-red-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </aside>
        </div>
        
        <!-- Auto-close sidebar on mobile when link is clicked -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Wait for Alpine to be ready
                if (window.Alpine) {
                    setupSidebarAutoClose();
                } else {
                    document.addEventListener('alpine:init', setupSidebarAutoClose);
                }
                
                function setupSidebarAutoClose() {
                    const sidebarLinks = document.querySelectorAll('aside a[href]');
                    sidebarLinks.forEach(link => {
                        link.addEventListener('click', function() {
                            if (window.innerWidth < 1024 && window.Alpine && window.Alpine.store('sidebar')) {
                                const store = window.Alpine.store('sidebar');
                                if (store && store.open) {
                                    store.toggle();
                                }
                            }
                        });
                    });
                }
            });
        </script>
    @endif
@endauth
