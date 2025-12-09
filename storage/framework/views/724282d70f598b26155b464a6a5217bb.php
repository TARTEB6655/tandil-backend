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
    <h1 class="text-xl font-medium text-gray-900 mb-6">
            Subscription Details
        </h2>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <?php if(session('success')): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php echo e(session('success')); ?>

                </div>
            <?php endif; ?>

            <!-- Client Info -->
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Client Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($subscription->client->name ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($subscription->client->email ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Subscription Details -->
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Subscription Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Plan</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e(str_replace('_', ' ', ucfirst($subscription->plan))); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Amount</p>
                        <p class="text-sm font-medium text-gray-900">AED <?php echo e(number_format($subscription->amount, 2)); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Payment Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?php echo e($subscription->payment_status == 'paid' ? 'bg-green-100 text-green-800' : 
                               ($subscription->payment_status == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')); ?>">
                            <?php echo e(ucfirst($subscription->payment_status)); ?>

                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Visits</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($subscription->completed_visits); ?> / <?php echo e($subscription->total_visits); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Start Date</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($subscription->start_date->format('M d, Y')); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">End Date</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($subscription->end_date->format('M d, Y')); ?></p>
                    </div>
                </div>
            </div>

            <!-- Visits List -->
            <?php if($subscription->visits && $subscription->visits->count() > 0): ?>
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Visits</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Technician</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php $__currentLoopData = $subscription->visits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $visit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A'); ?>

                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo e($visit->technician->name ?? 'Unassigned'); ?>

                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo e($visit->status == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                            <?php echo e(ucfirst($visit->status)); ?>

                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="pt-4 flex gap-4">
                <a href="<?php echo e(route('admin.subscriptions.edit', $subscription)); ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="<?php echo e(route('admin.subscriptions.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Subscriptions</a>
            </div>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\subscriptions\show.blade.php ENDPATH**/ ?>