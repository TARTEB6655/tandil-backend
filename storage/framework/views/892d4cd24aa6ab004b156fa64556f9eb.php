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
            <div>
                <h1 class="text-xl font-medium text-gray-900">Payments & Transactions</h1>
                <p class="text-sm text-gray-500 mt-1">Manage payment gateways and view transaction logs</p>
            </div>
        </div>

        <?php if(session('success')): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?>

        <!-- Payment Gateways -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Payment Gateway Configuration</h2>
            
            <div class="space-y-6">
                <?php $__currentLoopData = ['stripe', 'paypal', 'razorpay']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-medium text-gray-900 capitalize"><?php echo e($gateway); ?></h3>
                                <p class="text-sm text-gray-500 mt-1">Configure <?php echo e(ucfirst($gateway)); ?> payment gateway</p>
                            </div>
                            <form method="POST" action="<?php echo e(route('admin.payments.update-gateway', $gateway)); ?>" class="inline">
                                <?php echo csrf_field(); ?>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           name="enabled" 
                                           value="1"
                                           <?php echo e($gateways[$gateway]['enabled'] ? 'checked' : ''); ?>

                                           onchange="this.form.submit()"
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </form>
                        </div>
                        
                        <form method="POST" action="<?php echo e(route('admin.payments.update-gateway', $gateway)); ?>" class="space-y-4">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="enabled" value="<?php echo e($gateways[$gateway]['enabled'] ? '1' : '0'); ?>">
                            
                            <?php if($gateway === 'stripe'): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Public Key</label>
                                        <input type="text" 
                                               name="public_key" 
                                               value="<?php echo e($gateways[$gateway]['public_key']); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Secret Key</label>
                                        <input type="password" 
                                               name="secret_key" 
                                               value="<?php echo e($gateways[$gateway]['secret_key']); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Webhook Secret</label>
                                        <input type="text" 
                                               name="webhook_secret" 
                                               value="<?php echo e($gateways[$gateway]['webhook_secret']); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                            <?php elseif($gateway === 'paypal'): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                                        <input type="text" 
                                               name="client_id" 
                                               value="<?php echo e($gateways[$gateway]['client_id']); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Client Secret</label>
                                        <input type="password" 
                                               name="client_secret" 
                                               value="<?php echo e($gateways[$gateway]['client_secret']); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
                                        <select name="mode" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="sandbox" <?php echo e($gateways[$gateway]['mode'] === 'sandbox' ? 'selected' : ''); ?>>Sandbox</option>
                                            <option value="live" <?php echo e($gateways[$gateway]['mode'] === 'live' ? 'selected' : ''); ?>>Live</option>
                                        </select>
                                    </div>
                                </div>
                            <?php elseif($gateway === 'razorpay'): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Key ID</label>
                                        <input type="text" 
                                               name="key_id" 
                                               value="<?php echo e($gateways[$gateway]['key_id']); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Key Secret</label>
                                        <input type="password" 
                                               name="key_secret" 
                                               value="<?php echo e($gateways[$gateway]['key_secret']); ?>"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                                Save <?php echo e(ucfirst($gateway)); ?> Settings
                            </button>
                        </form>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <!-- Transaction Logs -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-6">Transaction Logs</h2>
            
            <!-- Filters -->
            <div class="mb-4 flex flex-wrap gap-4">
                <form method="GET" action="<?php echo e(route('admin.payments.index')); ?>" class="flex gap-2">
                    <input type="text" 
                           name="search" 
                           value="<?php echo e(request('search')); ?>"
                           placeholder="Search by transaction ID..."
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <select name="status" 
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo e(request('status') === 'pending' ? 'selected' : ''); ?>>Pending</option>
                        <option value="completed" <?php echo e(request('status') === 'completed' ? 'selected' : ''); ?>>Completed</option>
                        <option value="failed" <?php echo e(request('status') === 'failed' ? 'selected' : ''); ?>>Failed</option>
                        <option value="refunded" <?php echo e(request('status') === 'refunded' ? 'selected' : ''); ?>>Refunded</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                        Filter
                    </button>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gateway</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo e($transaction->transaction_id); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 capitalize"><?php echo e($transaction->type); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 capitalize"><?php echo e($transaction->gateway ?? 'N/A'); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo e($transaction->currency); ?> <?php echo e(number_format($transaction->amount, 2)); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo e($transaction->status === 'completed' ? 'bg-green-100 text-green-800' : 
                                           ($transaction->status === 'failed' ? 'bg-red-100 text-red-800' : 
                                           ($transaction->status === 'refunded' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800'))); ?>">
                                        <?php echo e(ucfirst($transaction->status)); ?>

                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"><?php echo e($transaction->created_at->format('M d, Y')); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?php echo e(route('admin.payments.transaction', $transaction->id)); ?>" 
                                       class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-sm text-gray-500">No transactions found</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($transactions->hasPages()): ?>
                <div class="mt-4">
                    <?php echo e($transactions->links()); ?>

                </div>
            <?php endif; ?>
        </div>
    </div>
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




<?php /**PATH C:\projects\tandil-backend\resources\views\admin\payments\index.blade.php ENDPATH**/ ?>