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
            Product Details
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <?php if(session('success')): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php echo e(session('success')); ?>

                </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 gap-6">
                <?php if($product->image): ?>
                <div>
                    <img src="<?php echo e(asset('storage/' . $product->image)); ?>" alt="<?php echo e($product->name); ?>" class="w-full rounded-lg">
                </div>
                <?php endif; ?>

                <div>
                    <h2 class="text-lg font-medium text-gray-900 mb-2"><?php echo e($product->name); ?></h2>
                    <p class="text-lg font-medium text-indigo-600 mb-4">AED <?php echo e(number_format($product->price, 2)); ?></p>
                    <p class="text-sm text-gray-500 mb-2">Category: <?php echo e($product->category->name ?? 'No Category'); ?></p>
                    <?php if($product->description): ?>
                        <p class="text-sm text-gray-700 mt-4"><?php echo e($product->description); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="<?php echo e(route('admin.products.edit', $product)); ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="<?php echo e(route('admin.products.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Products</a>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\products\show.blade.php ENDPATH**/ ?>