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
            Tip Details
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <div>
                <h2 class="text-lg font-medium text-gray-900 mb-2"><?php echo e($tip->title); ?></h2>
                <div class="flex gap-4 mb-4">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                        <?php echo e(ucfirst($tip->type)); ?>

                    </span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                        <?php echo e($tip->status == 'published' ? 'bg-green-100 text-green-800' : 
                           ($tip->status == 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                        <?php echo e(ucfirst($tip->status)); ?>

                    </span>
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                        <?php echo e(strtoupper($tip->language)); ?>

                    </span>
                </div>
                <div class="prose max-w-none">
                    <p class="text-gray-700 whitespace-pre-wrap"><?php echo e($tip->content); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-4 border-t">
                <div>
                    <p class="text-sm text-gray-500">Created By</p>
                    <p class="text-sm font-medium text-gray-900"><?php echo e($tip->creator->name ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Scheduled At</p>
                    <p class="text-sm font-medium text-gray-900"><?php echo e($tip->scheduled_at ? $tip->scheduled_at->format('M d, Y H:i') : 'Not scheduled'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Created At</p>
                    <p class="text-sm font-medium text-gray-900"><?php echo e($tip->created_at->format('M d, Y H:i')); ?></p>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="<?php echo e(route('admin.tips.edit', $tip)); ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="<?php echo e(route('admin.tips.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Tips</a>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\tips\show.blade.php ENDPATH**/ ?>