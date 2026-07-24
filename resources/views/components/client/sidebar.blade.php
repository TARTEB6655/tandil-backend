@auth
    @if(auth()->user()->role === 'client')
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
                    <a href="{{ route('client.dashboard') }}" class="flex items-center flex-1 justify-center lg:justify-center">
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
                            <a href="{{ route('client.dashboard') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.dashboard') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                {{ __('admin.dashboard') }}
                            </a>
                        </div>

                        <!-- Subscriptions -->
                        <div class="mb-2">
                            <a href="{{ route('client.subscriptions.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.subscriptions.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('admin.my_subscriptions') }}
                            </a>
                        </div>

                        <!-- Visits -->
                        <div class="mb-2">
                            <a href="{{ route('client.visits.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.visits.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                {{ __('admin.my_visits') }}
                            </a>
                        </div>

                        <!-- Reports -->
                        <div class="mb-2">
                            <a href="{{ route('client.reports.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.reports.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ __('admin.my_reports') }}
                            </a>
                        </div>

                        <!-- Orders -->
                        <div class="mb-2">
                            <a href="{{ route('client.orders.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.orders.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                {{ __('admin.my_orders') }}
                            </a>
                        </div>

                        <!-- Shop Products -->
                        <div class="mb-2">
                            <a href="{{ route('client.shop.index') }}"
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.shop.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.293 1.293a1 1 0 00.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Shop Products
                            </a>
                        </div>

                        <!-- Cart -->
                        <div class="mb-2">
                            <a href="{{ route('client.cart.index') }}"
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.cart.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.293 1.293a1 1 0 00.707 1.707H17m-8 2a2 2 0 11-4 0 2 2 0 014 0zm8 0a2 2 0 100-4 2 2 0 000 4z" />
                                </svg>
                                Cart
                            </a>
                        </div>

                        <!-- Services -->
                        <div class="mb-2">
                            <a href="{{ route('client.services.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.services.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                {{ __('admin.services') }}
                            </a>
                        </div>

                        <!-- Complaints -->
                        <div class="mb-2">
                            <a href="{{ route('client.complaints.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.complaints.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                {{ __('admin.my_complaints') }}
                            </a>
                        </div>

                        <!-- Notifications (admin messages, tips, help & support, leave, etc.) -->
                        <div class="mb-2">
                            <a href="{{ route('client.notifications.index') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.notifications.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                                </svg>
                                {{ __('admin.notifications') }}
                            </a>
                        </div>

                        <!-- Account (aligned with API: Memberships, Personal Information, Addresses, Payment Methods, Notifications, Loyalty, Help & Support) -->
                        <div class="pt-2 mt-2 border-t border-gray-200">
                            <p class="px-3 py-1 text-xs font-normal text-gray-500 uppercase tracking-wider">{{ __('admin.account') }}</p>
                            <a href="{{ route('client.memberships.index') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.memberships.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                                {{ __('admin.memberships') }}
                            </a>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('profile.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                {{ __('admin.personal_information') }}
                            </a>
                            <a href="{{ route('client.phone.edit') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.phone.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                Phone number
                                @if(auth()->user()->needsPhone())
                                    <span class="ml-auto inline-flex h-2 w-2 rounded-full bg-amber-500" title="Phone required"></span>
                                @endif
                            </a>
                            <a href="{{ route('client.addresses.index') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.addresses.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                {{ __('admin.addresses') }}
                            </a>
                            <a href="{{ route('client.payment-methods.index') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.payment-methods.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                {{ __('admin.payment_methods') }}
                            </a>
                            <a href="{{ route('client.wallet.index') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.wallet.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0-6h2a2 2 0 110 4h-2m0-4v4" /></svg>
                                {{ __('admin.wallet') }}
                            </a>
                            <a href="{{ route('client.loyalty.index') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.loyalty.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                                {{ __('admin.loyalty') }}
                            </a>
                            <a href="{{ route('client.help-support.index') }}" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors {{ request()->routeIs('client.help-support.*') ? 'bg-gray-100 text-gray-800' : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ __('admin.help_support') }}
                            </a>
                        </div>
                    </nav>

                    <!-- Bottom Section: Settings & Logout -->
                    <div class="px-3 py-3 border-t border-gray-200 mt-auto flex-shrink-0">
                        <!-- Settings -->
                        <div class="mb-2">
                            <a href="{{ route('profile.edit') }}" 
                               class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-gray-700 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ __('admin.settings') }}
                            </a>
                        </div>

                        <!-- Logout -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" 
                                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-normal text-red-600 hover:bg-red-50 transition-colors w-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                {{ __('admin.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>
    @endif
@endauth

