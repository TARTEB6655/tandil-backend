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
        <h1 class="text-xl font-medium text-gray-900 mb-6">
            Audit Log Details
        </h2>

        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Log Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Action</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($log->description ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">User</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($log->causer_id ?? 'System'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Date</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e(isset($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('M d, Y H:i') : 'N/A'); ?></p>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="<?php echo e(route('admin.audit-logs.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Logs</a>
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


<?php /**PATH C:\projects\tandil-backend\resources\views\admin\audit-logs\show.blade.php ENDPATH**/ ?>