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
            Edit Subscription
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="<?php echo e(route('admin.subscriptions.update', $subscription)); ?>">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                    <select name="payment_status" required class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="pending" <?php echo e(old('payment_status', $subscription->payment_status) == 'pending' ? 'selected' : ''); ?>>Pending</option>
                        <option value="paid" <?php echo e(old('payment_status', $subscription->payment_status) == 'paid' ? 'selected' : ''); ?>>Paid</option>
                        <option value="failed" <?php echo e(old('payment_status', $subscription->payment_status) == 'failed' ? 'selected' : ''); ?>>Failed</option>
                        <option value="refunded" <?php echo e(old('payment_status', $subscription->payment_status) == 'refunded' ? 'selected' : ''); ?>>Refunded</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Amount (AED)</label>
                    <input type="number" name="amount" step="0.01" value="<?php echo e(old('amount', $subscription->amount)); ?>" class="mt-1 block w-full rounded-md border-gray-300">
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update Subscription</button>
                    <a href="<?php echo e(route('admin.subscriptions.show', $subscription)); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\subscriptions\edit.blade.php ENDPATH**/ ?>