<?php
    $user = auth()->user();
    $searchValue = request()->get('search', '');
?>

<header class="sticky top-0 z-40 h-16 bg-white border-b border-gray-200 shadow-sm pt-2">
    <div class="h-full max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="h-full flex items-center justify-between">
            
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

                <!-- Logo (visible only on >= 992px) -->
                <a href="<?php echo e(route('admin.dashboard')); ?>" class="hidden min-[992px]:flex items-center gap-3 transition-opacity hover:opacity-80">
                    <img 
                        src="<?php echo e(asset('images/logo.png')); ?>" 
                        alt="Logo" 
                        class="w-28 h-auto object-contain" 
                        onerror="this.style.display='none'"
                    />
                    <span class="text-lg font-semibold text-gray-900 tracking-tight">
                        <?php echo e(config('app.name', 'Tandil')); ?>

                    </span>
                </a>
            </div>

            <!-- RIGHT: Search + Notifications + Profile -->
            <div class="flex items-center gap-3 sm:gap-4 lg:gap-5 flex-shrink-0">
                
                <!-- Search Bar (hidden on mobile/tablet, visible on desktop >= 1024px) -->
                <form
                    action="<?php echo e(request()->url()); ?>"
                    method="GET"
                    class="hidden lg:block flex-shrink-0"
                    x-data="{ searchValue: '<?php echo e($searchValue); ?>' }"
                >
                    <div class="relative w-64 flex-shrink-0">
                        <!-- Search Icon - Perfectly Vertically Centered -->
                        <div class="absolute inset-0 flex items-center justify-end pointer-events-none mr-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>

                        <input
                            type="text"
                            name="search"
                            x-model="searchValue"
                            value="<?php echo e($searchValue); ?>"
                            placeholder="Search..."
                            class="w-full h-11 pl-12 pr-10 text-sm bg-gray-50 border border-gray-200 rounded-lg
                                   focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white
                                   transition-all duration-200"
                            @keydown.enter.prevent="$el.closest('form').submit()"
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

                    
                    <?php $__currentLoopData = request()->except('search'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </form>

                <!-- Notification Bell Icon -->
                <button
                    class="relative p-2.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200 flex-shrink-0"
                    aria-label="Notifications"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                    </svg>
                    <!-- Notification Dot -->
                    <span class="absolute top-2 right-2 h-2.5 w-2.5 bg-red-500 rounded-full ring-2 ring-white"></span>
                </button>

                <!-- Profile Dropdown -->
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-2 sm:gap-3 px-2 py-1.5 rounded-lg hover:bg-gray-100 transition-colors duration-200"
                        aria-label="User menu"
                    >
                        <!-- Avatar - Fixed Size (Never Collapses) -->
                        <div class="h-8 w-8 text-black border border-[#00000045] flex items-center justify-center rounded-full bg-gray-900 text-black text-sm font-semibold shadow-sm ring-2 ring-gray-900 flex-shrink-0">
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
                            @click="open = false"
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
<?php /**PATH C:\projects\tandil-backend\resources\views/components/admin/header.blade.php ENDPATH**/ ?>