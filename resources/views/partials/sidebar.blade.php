@auth
    @if(auth()->user()->role === 'admin')
        <div x-data="{ sidebarOpen: false }"
             x-init="
                window.addEventListener('toggle-sidebar', () => { sidebarOpen = !sidebarOpen; });
                if (window.innerWidth >= 1024) { sidebarOpen = true; }
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) { sidebarOpen = true; }
                    else { sidebarOpen = false; }
                });
                $watch('sidebarOpen', value => {
                    if (value && window.innerWidth < 1024) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                });
             ">
            <!-- Mobile Overlay -->
            <div x-show="sidebarOpen" 
                 x-transition:enter="transition-opacity duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="sidebarOpen = false"
                 class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
                 style="display: none;"></div>

            <!-- Sidebar -->
            <aside 
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed top-16 left-0 w-64 h-[calc(100vh-4rem)] bg-white border-r flex flex-col py-6 transform transition-transform duration-300 ease-in-out z-50 lg:z-auto"
            >
                <nav class="flex-1 overflow-y-auto px-3">
                    <!-- Dashboard -->
                    <a href="{{ route('admin.dashboard') }}" 
                       class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.dashboard') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="text-sm text-gray-700">Dashboard</span>
                    </a>

                    <div class="mt-4">
                        <a href="{{ route('admin.users.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.users.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Users</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.roles.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.roles.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="text-sm text-gray-700">Roles & Permissions</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.products.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.products.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span class="text-sm text-gray-700">Products</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.orders.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.orders.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span class="text-sm text-gray-700">Orders</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.visits.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.visits.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Visits</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.complaints.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.complaints.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="text-sm text-gray-700">Complaints</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.subscriptions.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.subscriptions.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Subscriptions</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.hr.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.hr.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Technicians</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('admin.settings.index') }}" 
                           class="flex items-center space-x-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('admin.settings.*') ? 'bg-gray-100 font-medium' : 'hover:bg-gray-100' }}">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Settings</span>
                        </a>
                    </div>
                </nav>
            </aside>
        </div>
    @endif
@endauth
