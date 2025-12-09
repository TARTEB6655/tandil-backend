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
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <h1 class="text-xl font-medium text-gray-900">Edit Subscription Plan</h1>
            <p class="mt-1 text-sm text-gray-500">Update plan pricing and settings</p>
        </div>

        <?php if(session('success')): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm"><?php echo e(session('success')); ?></span>
            </div>
        <?php endif; ?>

        <?php if(session('error')): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm"><?php echo e(session('error')); ?></span>
            </div>
        <?php endif; ?>

        <!-- Edit Plan Form -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <form method="POST" action="<?php echo e(route('admin.subscription-plans.update', $plan['key'])); ?>" class="space-y-6">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <!-- Plan Name -->
                <div>
                    <label for="label" class="block text-sm font-medium text-gray-700 mb-2">Plan Name</label>
                    <input type="text" 
                           id="label" 
                           name="label" 
                           value="<?php echo e(old('label', $plan['label'])); ?>" 
                           required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <?php $__errorArgs = ['label'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <!-- Price -->
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">Price (AED)</label>
                    <input type="number" 
                           id="price" 
                           name="price" 
                           value="<?php echo e(old('price', $plan['price'])); ?>" 
                           step="0.01" 
                           min="0" 
                           required 
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <?php $__errorArgs = ['price'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <!-- Visit Frequency -->
                <div>
                    <label for="visit_frequency" class="block text-sm font-medium text-gray-700 mb-2">Visit Frequency</label>
                    <select id="visit_frequency" 
                            name="visit_frequency" 
                            required 
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="weekly" <?php echo e(old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'weekly' ? 'selected' : ''); ?>>Weekly</option>
                        <option value="bi-weekly" <?php echo e(old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'bi-weekly' ? 'selected' : ''); ?>>Bi-Weekly</option>
                        <option value="monthly" <?php echo e(old('visit_frequency', $plan['visit_frequency'] ?? 'monthly') == 'monthly' ? 'selected' : ''); ?>>Monthly</option>
                    </select>
                    <?php $__errorArgs = ['visit_frequency'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <!-- Enabled Status -->
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <input type="checkbox" 
                           id="enabled" 
                           name="enabled" 
                           value="1" 
                           <?php echo e(old('enabled', $plan['enabled']) ? 'checked' : ''); ?> 
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="enabled" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Enable this plan
                    </label>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="<?php echo e(route('admin.subscription-plans.index')); ?>" 
                       class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                        Update Plan
                    </button>
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
<?php /**PATH C:\projects\tandil-backend\resources\views\admin\subscription-plans\edit.blade.php ENDPATH**/ ?>