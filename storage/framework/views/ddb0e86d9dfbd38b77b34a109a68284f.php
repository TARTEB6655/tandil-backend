<?php if (isset($component)) { $__componentOriginale0f1cdd055772eb1d4a99981c240763e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale0f1cdd055772eb1d4a99981c240763e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-layout','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-xl font-medium text-gray-900">Orders: All locations</h1>
            <div class="flex items-center gap-3 flex-wrap">
                <button class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    Export
                </button>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        More actions
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                        <div class="py-1">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Bulk print</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Export selected</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Archive orders</a>
                        </div>
                    </div>
                </div>
                <button class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-800 transition-colors">
                    Create order
                </button>
            </div>
        </div>

        <?php if(session('success')): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                <div class="flex items-center gap-1 overflow-x-auto">
                    <a href="<?php echo e(route('admin.orders.index')); ?>" 
                       class="px-4 py-2 text-sm font-medium <?php echo e(($filter ?? 'all') == 'all' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        All
                    </a>
                    <a href="<?php echo e(route('admin.orders.index', ['filter' => 'unfulfilled'])); ?>" 
                       class="px-4 py-2 text-sm font-medium <?php echo e(($filter ?? 'all') == 'unfulfilled' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Unfulfilled
                    </a>
                    <a href="<?php echo e(route('admin.orders.index', ['filter' => 'unpaid'])); ?>" 
                       class="px-4 py-2 text-sm font-medium <?php echo e(($filter ?? 'all') == 'unpaid' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Unpaid
                    </a>
                    <a href="<?php echo e(route('admin.orders.index', ['filter' => 'open'])); ?>" 
                       class="px-4 py-2 text-sm font-medium <?php echo e(($filter ?? 'all') == 'open' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Open
                    </a>
                    <a href="<?php echo e(route('admin.orders.index', ['filter' => 'archived'])); ?>" 
                       class="px-4 py-2 text-sm font-medium <?php echo e(($filter ?? 'all') == 'archived' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900'); ?> whitespace-nowrap">
                        Archived
                    </a>
                    <button class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-600 whitespace-nowrap">
                        +
                    </button>
                </div>
                <div class="flex items-center gap-2 ml-4">
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </button>
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left w-12">
                                    <input type="checkbox" 
                                           id="selectAll" 
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                           onchange="toggleSelectAll()">
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Order</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Date</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[180px]">Customer</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Channel</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Total</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Payment status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Fulfillment status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">Items</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Delivery status</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12"></th>
                            </tr>
                        </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-gray-50 transition-colors cursor-pointer <?php echo e($order->order_status == 'pending' && $order->payment_status != 'paid' ? 'bg-yellow-50' : ''); ?>" 
                                onclick="window.location='<?php echo e(route('admin.orders.show', $order->id)); ?>'">
                                <td class="px-3 py-4 whitespace-nowrap" onclick="event.stopPropagation()">
                                    <input type="checkbox" 
                                           class="order-checkbox h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                           value="<?php echo e($order->id); ?>"
                                           onchange="updateBulkActions()">
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">#<?php echo e($order->id); ?></div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo e($order->created_at->format('D')); ?> at <?php echo e($order->created_at->format('g:i a')); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo e($order->created_at->format('M d')); ?></div>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="text-sm font-medium text-gray-900 truncate max-w-[160px]"><?php echo e($order->user->name ?? 'Guest'); ?></div>
                                    <div class="text-xs text-gray-500 truncate max-w-[160px]"><?php echo e($order->user->email ?? ''); ?></div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">Online Store</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">AED <?php echo e(number_format($order->total_amount, 2)); ?></div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <?php if($order->payment_status == 'paid'): ?>
                                            <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Paid</span>
                                        <?php elseif($order->payment_status == 'voided'): ?>
                                            <div class="w-2 h-2 rounded-full bg-gray-400 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Voided</span>
                                        <?php else: ?>
                                            <div class="w-2 h-2 rounded-full bg-yellow-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900"><?php echo e(ucfirst($order->payment_status)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <?php if($order->order_status == 'delivered'): ?>
                                            <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Fulfilled</span>
                                        <?php elseif($order->order_status == 'cancelled'): ?>
                                            <div class="w-2 h-2 rounded-full bg-gray-400 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Cancelled</span>
                                        <?php else: ?>
                                            <div class="w-2 h-2 rounded-full bg-orange-500 flex-shrink-0"></div>
                                            <span class="text-sm text-gray-900">Unfulfilled</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900"><?php echo e($order->items->count()); ?> <?php echo e($order->items->count() == 1 ? 'item' : 'items'); ?></span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500">-</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="11" class="px-3 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                        <h3 class="text-sm font-medium text-gray-900 mb-1">No orders found</h3>
                                        <p class="text-sm text-gray-500">Orders will appear here when customers make purchases.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if($orders->hasPages()): ?>
            <div class="mt-4">
                <?php echo e($orders->links()); ?>

            </div>
        <?php endif; ?>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            // Bulk actions logic here
        }
    </script>
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale0f1cdd055772eb1d4a99981c240763e)): ?>
<?php $attributes = $__attributesOriginale0f1cdd055772eb1d4a99981c240763e; ?>
<?php unset($__attributesOriginale0f1cdd055772eb1d4a99981c240763e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale0f1cdd055772eb1d4a99981c240763e)): ?>
<?php $component = $__componentOriginale0f1cdd055772eb1d4a99981c240763e; ?>
<?php unset($__componentOriginale0f1cdd055772eb1d4a99981c240763e); ?>
<?php endif; ?>
<?php /**PATH C:\projects\tandil-backend\resources\views\admin\orders\index.blade.php ENDPATH**/ ?>