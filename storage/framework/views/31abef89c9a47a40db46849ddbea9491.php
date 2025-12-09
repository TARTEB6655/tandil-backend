<?php
    $user = auth()->user();
    $searchValue = request()->get('search', '');
    $unreadNotifications = $user->unreadNotifications()->latest()->take(5)->get();
    $unreadCount = $user->unreadNotifications()->count();
?>

<header class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm py-4">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            
            <!-- LEFT: Mobile Menu Button + Logo -->
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
            <div class="flex items-center gap-3 sm:gap-4 lg:gap-5 flex-shrink-0">
                
                <!-- Search Bar (hidden on mobile/tablet, visible on desktop >= 1024px) -->
                <form
                    action="<?php echo e(route('admin.dashboard')); ?>"
                    method="GET"
                    class="hidden lg:block flex-shrink-0"
                    x-data="{ searchValue: '<?php echo e($searchValue); ?>' }"
                >
                    <div class="relative w-64 flex-shrink-0">
                        <!-- Search Icon - Perfectly Vertically Centered -->
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>

                        <input
                            type="text"
                            name="search"
                            x-model="searchValue"
                            value="<?php echo e($searchValue); ?>"
                            placeholder="Search users, orders, visits..."
                            class="w-full h-11 pl-12 pr-10 text-sm placeholder:text-xs bg-gray-50 border border-gray-200 rounded-lg
                                   focus:outline-none focus:ring-1 focus:ring-gray-300 focus:border-gray-300 focus:bg-white
                                   transition-all duration-200"
                            @keydown.enter.prevent="if(searchValue.trim()) { $el.closest('form').submit(); } else { alert('Please enter a search term'); }"
                        />

                        <!-- Clear Button (only visible when there is a search value) -->
                        <?php if($searchValue): ?>
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
                        <?php endif; ?>
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
                        <!-- Notification Dot -->
                        <?php if($unreadCount > 0): ?>
                            <span class="absolute top-2 right-2 h-2.5 w-2.5 bg-red-500 rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
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
                        class="absolute right-0 mt-3 w-[360px] bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-[500px] overflow-hidden flex flex-col"
                        style="display: none;"
                    >
                        <!-- Header -->
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                                <?php if($unreadCount > 0): ?>
                                    <span class="px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-200 rounded-full"><?php echo e($unreadCount); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if($unreadCount > 0): ?>
                                <form method="POST" action="<?php echo e(route('admin.notifications.mark-all-read')); ?>" class="inline">
                                    <?php echo csrf_field(); ?>
                                    <button 
                                        type="submit"
                                        class="text-xs text-gray-500 hover:text-gray-700 transition-colors px-2 py-1 rounded hover:bg-gray-200"
                                        @click.stop
                                    >
                                        Mark all read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Notifications List -->
                        <div class="flex-1 overflow-y-auto">
                            <?php $__empty_1 = true; $__currentLoopData = $unreadNotifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php
                                    $data = $notification->data;
                                    $type = $notification->type;
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
                                ?>
                                <a 
                                    href="<?php echo e(route('admin.notifications.index')); ?>" 
                                    class="block px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors duration-150 group"
                                    @click.stop="open = false"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <?php
                                                $iconColorClass = match($iconColor) {
                                                    'blue' => 'text-blue-600',
                                                    'green' => 'text-green-600',
                                                    'amber' => 'text-amber-600',
                                                    'purple' => 'text-purple-600',
                                                    default => 'text-blue-600'
                                                };
                                            ?>
                                            <div class="h-9 w-9 rounded-full <?php echo e($iconBg); ?> flex items-center justify-center <?php echo e($iconBorder); ?> border">
                                                <?php if(str_contains($type, 'Order')): ?>
                                                    <svg class="w-4 h-4 <?php echo e($iconColorClass); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                    </svg>
                                                <?php elseif(str_contains($type, 'Visit')): ?>
                                                    <svg class="w-4 h-4 <?php echo e($iconColorClass); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                <?php elseif(str_contains($type, 'Complaint')): ?>
                                                    <svg class="w-4 h-4 <?php echo e($iconColorClass); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-4 h-4 <?php echo e($iconColorClass); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 leading-snug mb-0.5">
                                                        <?php echo e($data['message'] ?? class_basename($type)); ?>

                                                    </p>
                                                    <?php if(isset($data['visit_id'])): ?>
                                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">Visit ID: #<?php echo e($data['visit_id']); ?></p>
                                                    <?php elseif(isset($data['subscription_id'])): ?>
                                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">Subscription ID: #<?php echo e($data['subscription_id']); ?></p>
                                                    <?php elseif(isset($data['order_id'])): ?>
                                                        <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">Order ID: #<?php echo e($data['order_id']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="flex-shrink-0 h-2 w-2 bg-red-500 rounded-full mt-1.5"></span>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1.5"><?php echo e($notification->created_at->diffForHumans()); ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <!-- Empty State -->
                                <div class="px-4 py-10 text-center">
                                    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                    <p class="text-sm font-medium text-gray-900 mb-1">No notifications</p>
                                    <p class="text-xs text-gray-500">You're all caught up!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer -->
                        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex-shrink-0">
                            <a 
                                href="<?php echo e(route('admin.notifications.index')); ?>" 
                                class="text-xs font-medium text-gray-700 hover:text-gray-900 transition-colors text-center block py-1.5 rounded-md hover:bg-gray-200"
                                @click.stop="open = false"
                            >
                                View all notifications
                            </a>
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
                        <!-- Avatar - Fixed Size (Never Collapses) -->
                        <div class="h-8 w-8 flex items-center justify-center rounded-full text-white text-sm font-semibold shadow-sm flex-shrink-0" style="background: linear-gradient(to bottom right, #3b82f6, #4f46e5);">
                            <?php echo e(strtoupper(substr($user->name ?? 'A', 0, 1))); ?>

                        </div>

                        <!-- User Info (hidden on mobile, visible on desktop) -->
                        <div class="hidden lg:flex flex-col items-start text-left min-w-0">
                            <span class="text-sm font-medium text-gray-900 leading-tight truncate max-w-[140px]">
                                <?php echo e($user->name ?? 'User'); ?>

                            </span>
                            <span class="text-xs text-gray-500 leading-tight truncate max-w-[140px]">
                                <?php echo e($user->email ?? ''); ?>

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
                        class="absolute right-0 mt-2.5 w-64 bg-white rounded-xl shadow-2xl border border-gray-100 py-2 z-50"
                        style="display: none;"
                    >
                        <!-- User Info Section -->
                        <div class="px-5 py-3.5 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900 leading-tight"><?php echo e($user->name ?? 'User'); ?></p>
                            <p class="text-xs text-gray-500 mt-1 leading-tight"><?php echo e($user->email ?? ''); ?></p>
                        </div>

                        <!-- My Profile Link -->
                        <a 
                            href="<?php echo e(route('profile.edit')); ?>" 
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
                        <form method="POST" action="<?php echo e(route('logout')); ?>" class="border-t border-gray-100 mt-1 pt-1">
                            <?php echo csrf_field(); ?>
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
<?php /**PATH C:\projects\tandil-backend\resources\views\components\admin\header.blade.php ENDPATH**/ ?>