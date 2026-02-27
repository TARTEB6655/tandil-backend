@auth
    @if(auth()->user()->role === 'supervisor')
        <div>
            <!-- Mobile Overlay (991px and below only) -->
            <div x-show="$store.sidebar.open" 
                 x-cloak
                 x-transition:enter="transition-opacity duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="$store.sidebar.toggle()"
                 class="fixed inset-0 bg-black bg-opacity-50 z-40 max-[991px]:block min-[992px]:hidden"
                 style="display: none;"></div>

            <!-- Sidebar -->
            <aside 
                class="fixed left-0 top-0 z-50 flex h-screen w-[250px] sm:w-[250px] flex-col bg-white border-r border-gray-200 transition-transform duration-300 ease-in-out overflow-hidden min-[992px]:translate-x-0"
                :class="$store.sidebar.open ? 'translate-x-0 shadow-lg min-[992px]:shadow-none' : '-translate-x-full'"
            >
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center justify-between px-3 sm:px-4 py-6 sm:py-8">
                    <a href="{{ route('supervisor.dashboard') }}" class="flex items-center flex-1 justify-center lg:justify-center">
                        <img src="{{ asset('images/logo.png') }}" alt="Tandil" class="h-20 md:h-24 w-auto" style="max-width: 160px; object-fit: contain;" onerror="this.style.display='none'" />
                    </a>
                    <!-- Close Button (only visible on mobile/tablet) -->
                    <button 
                        @click="$store.sidebar.toggle()"
                        class="min-[992px]:hidden p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors duration-200 flex-shrink-0"
                        aria-label="Close sidebar"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <div class="sidebar-scroll flex-1 flex flex-col overflow-y-auto overflow-x-hidden min-h-0">
                    <nav class="px-3 py-2 flex-1">
                        <!-- Dashboard -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.dashboard') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.dashboard') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Dashboard
                            </a>
                        </div>

                        <!-- Visits -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.visits.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.visits.*') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Visits
                            </a>
                        </div>

                        <!-- Reports -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.reports.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.reports.*') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Reports
                            </a>
                        </div>

                        <!-- Complaints -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.complaints.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.complaints.*') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Complaints
                            </a>
                        </div>

                        <!-- Areas -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.areas.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.areas.*') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                My Areas
                            </a>
                        </div>

                        <!-- Tips -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.tips.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.tips.*') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                                Tips
                            </a>
                        </div>

                        <!-- Help & Support -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.help-support.index') }}"
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.help-support.*') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Help & Support
                            </a>
                        </div>

                        <!-- Notifications -->
                        <div class="mb-2">
                            <a href="{{ route('supervisor.notifications.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 transition-colors {{ request()->routeIs('supervisor.notifications.*') ? 'bg-gray-100 font-semibold' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                                </svg>
                                <span>Notifications</span>
                                @php
                                    $unreadCount = auth()->user()->unreadNotifications()->count();
                                @endphp
                                @if($unreadCount > 0)
                                    <span class="ml-auto px-2 py-0.5 text-xs font-medium text-white bg-red-500 rounded-full">{{ $unreadCount }}</span>
                                @endif
                            </a>
                        </div>
                    </nav>

                    <!-- Bottom Section: Settings & Logout -->
                    <div class="px-3 py-3 border-t border-gray-200 mt-auto flex-shrink-0">
                        <!-- Settings -->
                        <div class="mb-2">
                            <a href="{{ route('profile.edit') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Settings
                            </a>
                        </div>

                        <!-- Logout -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" 
                                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors w-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>
    @endif
@endauth

