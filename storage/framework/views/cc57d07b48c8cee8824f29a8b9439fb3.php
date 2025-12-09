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
            Create New Tip
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="<?php echo e(route('admin.tips.store')); ?>">
                <?php echo csrf_field(); ?>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="title" value="<?php echo e(old('title')); ?>" required class="mt-1 block w-full rounded-md border-gray-300">
                    <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Content</label>
                    <textarea name="content" rows="5" required class="mt-1 block w-full rounded-md border-gray-300"><?php echo e(old('content')); ?></textarea>
                    <?php $__errorArgs = ['content'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="general" <?php echo e(old('type') == 'general' ? 'selected' : ''); ?>>General</option>
                            <option value="weekly" <?php echo e(old('type') == 'weekly' ? 'selected' : ''); ?>>Weekly</option>
                            <option value="monthly" <?php echo e(old('type') == 'monthly' ? 'selected' : ''); ?>>Monthly</option>
                            <option value="seasonal" <?php echo e(old('type') == 'seasonal' ? 'selected' : ''); ?>>Seasonal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="draft" <?php echo e(old('status') == 'draft' ? 'selected' : ''); ?>>Draft</option>
                            <option value="published" <?php echo e(old('status') == 'published' ? 'selected' : ''); ?>>Published</option>
                            <option value="archived" <?php echo e(old('status') == 'archived' ? 'selected' : ''); ?>>Archived</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Language</label>
                        <select name="language" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="en" <?php echo e(old('language') == 'en' ? 'selected' : ''); ?>>English</option>
                            <option value="ar" <?php echo e(old('language') == 'ar' ? 'selected' : ''); ?>>Arabic</option>
                            <option value="ur" <?php echo e(old('language') == 'ur' ? 'selected' : ''); ?>>Urdu</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Scheduled At (Optional)</label>
                        <input type="datetime-local" name="scheduled_at" value="<?php echo e(old('scheduled_at')); ?>" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Create Tip</button>
                    <a href="<?php echo e(route('admin.tips.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\tips\create.blade.php ENDPATH**/ ?>