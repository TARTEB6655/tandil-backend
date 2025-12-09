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
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Email Templates</h1>
                <p class="text-sm text-gray-500 mt-1">Manage email templates for notifications and communications</p>
            </div>
            <a href="<?php echo e(route('admin.settings.index')); ?>" 
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                ← Back to Settings
            </a>
        </div>

        <?php if(session('success')): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?>

        <!-- Email Templates List -->
        <div class="space-y-4">
            <?php $__empty_1 = true; $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-base font-medium text-gray-900"><?php echo e($template->name); ?></h3>
                            <p class="text-sm text-gray-500 mt-1">Key: <?php echo e($template->key); ?></p>
                        </div>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo e($template->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                            <?php echo e($template->is_active ? 'Active' : 'Inactive'); ?>

                        </span>
                    </div>
                    
                    <form method="POST" action="<?php echo e(route('admin.settings.email-template.update', $template->id)); ?>" class="space-y-4">
                        <?php echo csrf_field(); ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                            <input type="text" 
                                   name="subject" 
                                   value="<?php echo e(old('subject', $template->subject)); ?>"
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Body</label>
                            <textarea name="body" 
                                      rows="10"
                                      required
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"><?php echo e(old('body', $template->body)); ?></textarea>
                            <?php if($template->variables): ?>
                                <p class="mt-2 text-xs text-gray-500">
                                    Available variables: <?php echo e(implode(', ', json_decode($template->variables, true) ?? [])); ?>

                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                            Update Template
                        </button>
                    </form>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <h3 class="text-sm font-medium text-gray-900 mb-1">No email templates found</h3>
                    <p class="text-sm text-gray-500">Email templates will appear here once created.</p>
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




<?php /**PATH C:\projects\tandil-backend\resources\views\admin\settings\email-templates.blade.php ENDPATH**/ ?>