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
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-medium text-gray-900">
                Tips & Messages Management
            </h1>
            <a href="<?php echo e(route('admin.tips.create')); ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                Create New Tip
            </a>
        </div>
        <!-- Success Message -->
        <?php if(session('success')): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo e(session('success')); ?>

            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" action="<?php echo e(route('admin.tips.index')); ?>" class="flex gap-4">
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Search tips..." class="flex-1 rounded-md border-gray-300">
                <select name="status" class="rounded-md border-gray-300">
                    <option value="">All Statuses</option>
                    <option value="draft" <?php echo e(request('status') == 'draft' ? 'selected' : ''); ?>>Draft</option>
                    <option value="published" <?php echo e(request('status') == 'published' ? 'selected' : ''); ?>>Published</option>
                    <option value="archived" <?php echo e(request('status') == 'archived' ? 'selected' : ''); ?>>Archived</option>
                </select>
                <select name="type" class="rounded-md border-gray-300">
                    <option value="">All Types</option>
                    <option value="weekly" <?php echo e(request('type') == 'weekly' ? 'selected' : ''); ?>>Weekly</option>
                    <option value="monthly" <?php echo e(request('type') == 'monthly' ? 'selected' : ''); ?>>Monthly</option>
                    <option value="seasonal" <?php echo e(request('type') == 'seasonal' ? 'selected' : ''); ?>>Seasonal</option>
                    <option value="general" <?php echo e(request('type') == 'general' ? 'selected' : ''); ?>>General</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filter</button>
                <a href="<?php echo e(route('admin.tips.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Clear</a>
            </form>
        </div>

        <!-- Tips Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Language</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $__empty_1 = true; $__currentLoopData = $tips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo e($tip->title); ?></div>
                                <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo e(Str::limit($tip->content, 50)); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo e(ucfirst($tip->type)); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo e(strtoupper($tip->language)); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo e($tip->status == 'published' ? 'bg-green-100 text-green-800' : 
                                       ($tip->status == 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                    <?php echo e(ucfirst($tip->status)); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo e($tip->scheduled_at ? $tip->scheduled_at->format('M d, Y') : 'Not scheduled'); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <a href="<?php echo e(route('admin.tips.show', $tip)); ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    <a href="<?php echo e(route('admin.tips.edit', $tip)); ?>" class="text-yellow-600 hover:text-yellow-900">Edit</a>
                                    <form action="<?php echo e(route('admin.tips.toggle-status', $tip)); ?>" method="POST" class="inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" 
                                                class="text-xs px-2 py-1 rounded <?php echo e($tip->status === 'published' ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200'); ?> transition-colors">
                                            <?php echo e($tip->status === 'published' ? 'Unpublish' : 'Publish'); ?>

                                        </button>
                                    </form>
                                    <form action="<?php echo e(route('admin.tips.destroy', $tip)); ?>" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No tips found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            <?php echo e($tips->links()); ?>

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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\tips\index.blade.php ENDPATH**/ ?>