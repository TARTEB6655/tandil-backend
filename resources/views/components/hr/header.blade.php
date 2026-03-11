@php
    $user = auth()->user();
    $searchValue = request()->get('search', '');
    $unreadNotifications = $user->unreadNotifications()->latest()->take(5)->get();
    $unreadCount = $user->unreadNotifications()->count();
    $headerProfilePic = $user->profile_picture_url ?? null;
    $headerInitial = $user->name ? mb_substr(trim($user->name), 0, 1) : 'H';
@endphp

<header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm py-4">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            
            <!-- LEFT: Mobile Menu Button -->
            <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">
                <!-- Mobile Menu Button (visible only on < 992px) -->
                <button
                    @click="$store.sidebar.toggle()"
                    class="max-[991px]:block min-[992px]:hidden p-2.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200"
                    aria-label="Toggle sidebar"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>

            <!-- RIGHT: Search + Notifications + Profile -->
            <div class="flex items-center gap-2 sm:gap-3 lg:gap-5 flex-shrink-0">
                
                <!-- Search Bar (hidden on mobile/tablet, visible on desktop >= 1024px) -->
                <form
                    action="{{ route('hr.dashboard') }}"
                    method="GET"
                    class="hidden lg:block flex-shrink-0"
                    x-data="{ searchValue: '{{ $searchValue }}' }"
                >
                    <div class="relative w-64 flex-shrink-0">
                        <!-- Search Icon -->
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>

                        <input
                            type="text"
                            name="search"
                            x-model="searchValue"
                            value="{{ $searchValue }}"
                            placeholder="Search"
                            class="w-full h-11 pl-12 pr-10 text-sm placeholder:text-xs bg-gray-50 border border-gray-200 rounded-lg
                                   focus:outline-none focus:ring-1 focus:ring-gray-300 focus:border-gray-300 focus:bg-white
                                   transition-all duration-200"
                            @keydown.enter.prevent="if(searchValue.trim()) { $el.closest('form').submit(); }"
                        />

                        @if($searchValue)
                        <button
                            type="button"
                            @click="searchValue=''; $el.closest('form').submit();"
                            class="absolute inset-y-0 right-0 flex items-center justify-center pr-3 text-gray-400 hover:text-gray-600 transition-colors duration-200"
                            aria-label="Clear search"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        @endif
                    </div>
                </form>

                <!-- Notification Dropdown -->
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="relative p-2.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200 flex-shrink-0"
                        aria-label="Notifications"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                        </svg>
                        @if($unreadCount > 0)
                            <span class="absolute top-2 right-2 h-2.5 w-2.5 bg-red-500 rounded-full ring-2 ring-white"></span>
                        @endif
                    </button>

                    <!-- Notification Dropdown Menu -->
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                        @click.away="open = false"
                        class="absolute right-0 mt-3 w-[320px] sm:w-[360px] bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-[500px] overflow-hidden flex flex-col"
                        style="display: none;"
                    >
                        <!-- Header -->
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                                @if($unreadCount > 0)
                                    <span class="px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ $unreadCount }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Notifications List -->
                        <div class="flex-1 overflow-y-auto">
                            @forelse($unreadNotifications as $notification)
                                <a 
                                    href="#" 
                                    class="block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors duration-150"
                                    @click.stop="open = false"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <div class="h-9 w-9 rounded-full bg-blue-50 flex items-center justify-center border border-blue-100">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 leading-snug mb-0.5">
                                                {{ $notification->data['message'] ?? 'New notification' }}
                                            </p>
                                            <p class="text-xs text-gray-400 mt-1.5">{{ $notification->created_at->diffForHumans() }}</p>
                                        </div>
                                        <span class="flex-shrink-0 h-2 w-2 bg-red-500 rounded-full mt-1.5"></span>
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-10 text-center">
                                    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                    <p class="text-sm font-medium text-gray-900 mb-1">No notifications</p>
                                    <p class="text-xs text-gray-500">You're all caught up!</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-2 sm:gap-3 px-2 py-1.5 rounded-lg hover:bg-gray-100 transition-colors duration-200"
                        aria-label="User menu"
                    >
                        <!-- Avatar: profile picture if set, else initial -->
                        @if($headerProfilePic)
                            <img src="{{ $headerProfilePic }}" alt="{{ $user->name ?? 'HR' }}" class="h-8 w-8 rounded-full object-cover shadow-sm flex-shrink-0 border border-gray-200" />
                        @else
                            <div class="h-8 w-8 flex items-center justify-center rounded-full text-white text-sm font-semibold shadow-sm flex-shrink-0" style="background: linear-gradient(to bottom right, #ec4899, #db2777);">
                                {{ mb_strtoupper($headerInitial) }}
                            </div>
                        @endif

                        <!-- User Info (hidden on mobile, visible on desktop) -->
                        <div class="hidden lg:flex flex-col items-start text-left min-w-0">
                            <span class="text-sm font-medium text-gray-900 leading-tight truncate max-w-[140px]">
                                {{ $user->name ?? 'User' }}
                            </span>
                            <span class="text-xs text-gray-500 leading-tight truncate max-w-[140px]">
                                {{ $user->email ?? '' }}
                            </span>
                        </div>

                        <!-- Dropdown Arrow -->
                        <svg 
                            class="hidden lg:block w-4 h-4 text-gray-500 transition-transform duration-200 flex-shrink-0" 
                            :class="{ 'rotate-180': open }" 
                            fill="none" 
                            stroke="currentColor" 
                            stroke-width="2" 
                            stroke-linecap="round" 
                            stroke-linejoin="round" 
                            viewBox="0 0 24 24"
                        >
                            <path d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                        x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                        @click.away="open = false"
                        class="absolute right-0 mt-2.5 w-64 bg-white rounded-xl shadow-2xl border border-gray-100 py-2 z-50"
                        style="display: none;"
                    >
                        <!-- User Info Section -->
                        <div class="px-5 py-3.5 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900 leading-tight">{{ $user->name ?? 'User' }}</p>
                            <p class="text-xs text-gray-500 mt-1 leading-tight">{{ $user->email ?? '' }}</p>
                        </div>

                        <!-- My Profile Link -->
                        <a 
                            href="{{ route('profile.edit') }}" 
                            class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors duration-150"
                            @click.stop="open = false"
                            onclick="event.stopPropagation();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="font-medium">My Profile</span>
                        </a>

                        <!-- Sign Out Button -->
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 mt-1 pt-1">
                            @csrf
                            <button 
                                type="submit" 
                                class="flex items-center gap-3 w-full px-5 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                <span class="font-medium">Sign Out</span>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</header>

