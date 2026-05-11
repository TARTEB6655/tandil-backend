@php
    $user = auth()->user();
    $searchValue = request()->get('search', '');
    $unreadCount = \App\Support\GlobalNotificationFilter::unreadForUser($user)->count();
    // Recent notifications (read + unread): unread stand out with bold; clicking one marks it read and goes to target
    $recentNotifications = \App\Support\GlobalNotificationFilter::forUser($user)->latest()->take(8)->get();
    $headerProfilePic = $user->profile_picture_url ?? null;
    $headerInitial = $user->name ? mb_substr(trim($user->name), 0, 1) : 'A';
@endphp

<header class="sticky top-0 z-40 bg-white dark:bg-gray-900 border-b-2 border-gray-200 dark:border-gray-700 dark:shadow-[0_4px_24px_rgba(0,0,0,0.2)] shadow-md py-4">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex w-full flex-wrap items-center gap-3 lg:flex-nowrap lg:gap-4">
            
            <!-- LEFT: Mobile Menu Button + Logo -->
            <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">
                <!-- Mobile Menu Button (visible only on < 992px) -->
                <button
                    @click="$store.sidebar.toggle()"
                    class="max-[991px]:block min-[992px]:hidden p-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100 transition-colors duration-200"
                    aria-label="{{ __('admin.toggle_sidebar') }}"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

            </div>

            <!-- CENTER: Global search (desktop) — wide, centered, debounced → Users list -->
            <div class="order-3 hidden min-w-0 w-full flex-1 justify-center px-2 lg:order-2 lg:flex lg:px-6">
                <div
                    class="relative w-full max-w-3xl"
                    x-data="{
                        q: @js($searchValue),
                        usersUrl: @js(route('admin.users.index')),
                        go() {
                            const u = new URL(this.usersUrl, window.location.origin);
                            const t = String(this.q ?? '').trim();
                            if (t) u.searchParams.set('search', t); else u.searchParams.delete('search');
                            u.searchParams.delete('page');
                            const next = u.pathname + u.search;
                            const cur = window.location.pathname + window.location.search;
                            if (next === cur) return;
                            window.location.href = next;
                        },
                        clear() { this.q = ''; this.go(); },
                    }"
                >
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        type="text"
                        name="search"
                        x-model="q"
                        autocomplete="off"
                        placeholder="{{ __('admin.search_placeholder') }}"
                        class="w-full h-11 rounded-xl border border-gray-200 bg-gray-50 pl-11 pr-10 text-sm text-gray-900 shadow-inner transition-all placeholder:text-xs placeholder:text-gray-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-indigo-500 dark:focus:bg-gray-900 dark:focus:ring-indigo-500/30"
                        @input.debounce.400ms="go()"
                        @keydown.enter.prevent="go()"
                    />
                    <button
                        type="button"
                        x-show="String(q || '').trim().length > 0"
                        x-cloak
                        @click="clear()"
                        class="absolute inset-y-0 right-0 flex items-center justify-center pr-3 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        aria-label="{{ __('admin.clear_search') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- RIGHT: Language + Notifications + Profile -->
            <div class="order-2 ml-auto flex items-center gap-3 sm:gap-4 lg:order-3 lg:ml-0 lg:gap-5 flex-shrink-0">

                <!-- Language Switcher -->
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="p-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                        aria-label="{{ __('admin.language') }}"
                        title="{{ __('admin.language') }}"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                    </button>
                    <div
                        x-show="open"
                        x-transition
                        @click.away="open = false"
                        class="absolute right-0 mt-2 w-44 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50"
                        style="display: none;"
                    >
                        <form method="POST" action="{{ route('admin.locale') }}" class="block">
                            @csrf
                            <input type="hidden" name="locale" value="en">
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ app()->getLocale() === 'en' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : '' }}">
                                {{ __('admin.english') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.locale') }}" class="block">
                            @csrf
                            <input type="hidden" name="locale" value="ar">
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ app()->getLocale() === 'ar' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : '' }}" dir="rtl">
                                {{ __('admin.arabic') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.locale') }}" class="block">
                            @csrf
                            <input type="hidden" name="locale" value="ur">
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 {{ app()->getLocale() === 'ur' ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium' : '' }}" dir="rtl">
                                {{ __('admin.urdu') }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Notification Dropdown -->
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="relative p-2.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100 transition-colors duration-200 flex-shrink-0"
                        aria-label="{{ __('admin.notifications') }}"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                        </svg>
                        <!-- Red dot only when there are new (unread) notifications -->
                        @if($unreadCount > 0)
                            <span class="absolute top-2 right-2 h-2.5 w-2.5 bg-red-500 rounded-full ring-2 ring-white" aria-label="{{ $unreadCount }} unread"></span>
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
                        class="absolute right-0 mt-3 w-[360px] bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 max-h-[500px] overflow-hidden flex flex-col"
                        style="display: none;"
                    >
                        <!-- Header -->
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.notifications') }}</h3>
                                @if($unreadCount > 0)
                                    <span class="px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full">{{ $unreadCount }} {{ __('admin.new') }}</span>
                                @endif
                            </div>
                            @if($unreadCount > 0)
                                <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" class="inline">
                                    @csrf
                                    <button 
                                        type="submit"
                                        class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors px-2 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
                                        @click.stop
                                    >
                                        {{ __('admin.mark_all_read') }}
                                    </button>
                                </form>
                            @endif
                        </div>

                        <!-- Notifications List: new = bold + red dot; read = normal. Click = mark read and go to target -->
                        <div class="flex-1 overflow-y-auto">
                            @forelse($recentNotifications as $notification)
                                @php
                                    $data = $notification->data;
                                    $type = $notification->type;
                                    $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
                                    $isUnread = is_null($notification->read_at);
                                    $notificationUrl = route('admin.notifications.read-and-redirect', $notification->id);
                                    $iconColor = 'blue';
                                    $iconBg = 'bg-blue-50';
                                    $iconBorder = 'border-blue-100';
                                    if (str_contains($type, 'Order') || str_contains($type, 'order')) {
                                        $iconColor = 'blue';
                                        $iconBg = 'bg-blue-50';
                                        $iconBorder = 'border-blue-100';
                                    } elseif (str_contains($type, 'Visit') || str_contains($type, 'visit')) {
                                        $iconColor = 'green';
                                        $iconBg = 'bg-green-50';
                                        $iconBorder = 'border-green-100';
                                    } elseif (str_contains($type, 'Complaint') || str_contains($type, 'complaint')) {
                                        $iconColor = 'amber';
                                        $iconBg = 'bg-amber-50';
                                        $iconBorder = 'border-amber-100';
                                    } elseif (str_contains($type, 'Report') || str_contains($type, 'report')) {
                                        $iconColor = 'purple';
                                        $iconBg = 'bg-purple-50';
                                        $iconBorder = 'border-purple-100';
                                    }
                                @endphp
                                <a 
                                    href="{{ $notificationUrl }}" 
                                    class="block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors duration-150 group"
                                    @click.stop="open = false"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            @php
                                                $iconColorClass = match($iconColor) {
                                                    'blue' => 'text-blue-600',
                                                    'green' => 'text-green-600',
                                                    'amber' => 'text-amber-600',
                                                    'purple' => 'text-purple-600',
                                                    default => 'text-blue-600'
                                                };
                                            @endphp
                                            <div class="h-9 w-9 rounded-full {{ $iconBg }} {{ $iconBorder }} border flex items-center justify-center">
                                                @if(str_contains($type, 'Order'))
                                                    <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                    </svg>
                                                @elseif(str_contains($type, 'Visit'))
                                                    <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @elseif(str_contains($type, 'Complaint'))
                                                    <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                @else
                                                    <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm leading-snug mb-0.5 {{ $isUnread ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }}">
                                                        {{ $data['message'] ?? class_basename($type) }}
                                                    </p>
                                                    @if(isset($data['visit_id']))
                                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">Visit ID: #{{ $data['visit_id'] }}</p>
                                                    @elseif(isset($data['subscription_id']))
                                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">Subscription ID: #{{ $data['subscription_id'] }}</p>
                                                    @elseif(isset($data['order_id']))
                                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">Order ID: #{{ $data['order_id'] }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1.5">{{ $notification->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <!-- Empty State -->
                                <div class="px-4 py-10 text-center">
                                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">{{ __('admin.no_notifications') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.all_caught_up') }}</p>
                                </div>
                            @endforelse
                        </div>

                        <!-- Footer -->
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-600 flex-shrink-0">
                            <a 
                                href="{{ route('admin.notifications.index') }}" 
                                class="text-xs font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 transition-colors text-center block py-1.5 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600"
                                @click.stop="open = false"
                            >
                                {{ __('admin.view_all_notifications') }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Profile Dropdown -->
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-2 sm:gap-3 px-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200"
                        aria-label="{{ __('admin.user_menu') }}"
                    >
                        <!-- Avatar: profile picture if set, else initial -->
                        @if($headerProfilePic)
                            <img src="{{ $headerProfilePic }}" alt="{{ $user->name ?? 'User' }}" class="h-8 w-8 rounded-full object-cover shadow-sm flex-shrink-0 border border-gray-200 dark:border-gray-600" />
                        @else
                            <div class="h-8 w-8 flex items-center justify-center rounded-full text-white text-sm font-semibold shadow-sm flex-shrink-0" style="background: linear-gradient(to bottom right, #3b82f6, #4f46e5);">
                                {{ mb_strtoupper($headerInitial) }}
                            </div>
                        @endif

                        <!-- User Info (hidden on mobile, visible on desktop) -->
                        <div class="hidden lg:flex flex-col items-start text-left min-w-0">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-tight truncate max-w-[140px]">
                                {{ $user->name ?? 'User' }}
                            </span>
                            <span class="text-xs text-gray-500 leading-tight truncate max-w-[140px]">
                                {{ $user->email ?? '' }}
                            </span>
                        </div>

                        <!-- Dropdown Arrow (hidden on mobile, visible on desktop) -->
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
                        class="absolute right-0 mt-2.5 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-100 dark:border-gray-700 py-2 z-50"
                        style="display: none;"
                    >
                        <!-- User Info Section -->
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-600">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-tight">{{ $user->name ?? 'User' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-tight">{{ $user->email ?? '' }}</p>
                        </div>

                        <!-- My Profile Link -->
                        <a 
                            href="{{ route('profile.edit') }}" 
                            class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150"
                            @click.stop="open = false"
                            onclick="event.stopPropagation();"
                        >
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="font-medium">{{ __('admin.my_profile') }}</span>
                        </a>

                        <!-- Sign Out Button -->
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 mt-1 pt-1">
                            @csrf
                            <button 
                                type="submit" 
                                class="flex items-center gap-3 w-full px-5 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150"
                            >
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                <span class="font-medium">{{ __('admin.sign_out') }}</span>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</header>
