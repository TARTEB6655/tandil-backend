<?php if(auth()->guard()->check()): ?>
    <?php if(auth()->user()->role === 'admin'): ?>
        <nav class="bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex space-x-1 overflow-x-auto">
                    <a href="<?php echo e(route('admin.dashboard')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.dashboard') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Dashboard
                    </a>
                    <a href="<?php echo e(route('admin.users.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.users.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Users
                    </a>
                    <a href="<?php echo e(route('admin.roles.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.roles.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Roles & Permissions
                    </a>
                    <a href="<?php echo e(route('admin.subscription-plans.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.subscription-plans.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Subscription Plans
                    </a>
                    <a href="<?php echo e(route('admin.subscriptions.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.subscriptions.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Subscriptions
                    </a>
                    <a href="<?php echo e(route('admin.visits.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.visits.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Visits
                    </a>
                    <a href="<?php echo e(route('admin.reports.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.reports.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Reports
                    </a>
                    <a href="<?php echo e(route('admin.areas.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.areas.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Areas/Regions
                    </a>
                    <a href="<?php echo e(route('admin.products.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.products.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Products
                    </a>
                    <a href="<?php echo e(route('admin.orders.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.orders.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Orders
                    </a>
                    <a href="<?php echo e(route('admin.tips.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.tips.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Tips & Notifications
                    </a>
                    <a href="<?php echo e(route('admin.complaints.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.complaints.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Complaints
                    </a>
                    <a href="<?php echo e(route('admin.hr.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.hr.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        HR Management
                    </a>
                    <a href="<?php echo e(route('admin.settings.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.settings.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Settings
                    </a>
                    <a href="<?php echo e(route('admin.audit-logs.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.audit-logs.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Audit Logs
                    </a>
                    <a href="<?php echo e(route('admin.banners.index')); ?>" 
                       class="px-4 py-3 text-sm font-medium <?php echo e(request()->routeIs('admin.banners.*') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Banners
                    </a>
                </div>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>





<?php /**PATH C:\projects\tandil-backend\resources\views\components\admin-nav.blade.php ENDPATH**/ ?>