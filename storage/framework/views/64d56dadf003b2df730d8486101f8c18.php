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
            Report Details
        </h2>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <!-- Visit Info -->
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Visit Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Client</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($report->visit->subscription->client->name ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Technician</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($report->visit->technician->name ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Scheduled Date</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($report->visit->scheduled_date ? \Carbon\Carbon::parse($report->visit->scheduled_date)->format('M d, Y') : 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo e(ucfirst($report->visit->status)); ?>

                        </span>
                    </div>
                </div>
            </div>

            <!-- Technician Notes -->
            <?php if($report->technician_notes): ?>
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-2">Technician Notes</h3>
                <p class="text-sm text-gray-700 bg-gray-50 p-4 rounded"><?php echo e($report->technician_notes); ?></p>
            </div>
            <?php endif; ?>

            <!-- Supervisor Notes -->
            <?php if($report->supervisor_notes): ?>
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-2">Supervisor Notes</h3>
                <p class="text-sm text-gray-700 bg-blue-50 p-4 rounded"><?php echo e($report->supervisor_notes); ?></p>
            </div>
            <?php endif; ?>

            <!-- Recommendations -->
            <?php if($report->recommendations): ?>
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-2">Recommendations</h3>
                <div class="bg-green-50 p-4 rounded">
                    <ul class="list-disc list-inside space-y-1">
                        <?php $__currentLoopData = $report->recommendations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recommendation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="text-sm text-gray-700"><?php echo e($recommendation); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Photos -->
            <?php if($report->visit->photos && $report->visit->photos->count() > 0): ?>
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Visit Photos</h3>
                <div class="grid grid-cols-2 gap-4">
                    <?php $__currentLoopData = $report->visit->photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <img src="<?php echo e(asset('storage/' . $photo->photo_path)); ?>" alt="Visit Photo" class="rounded-lg">
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="pt-4">
                <a href="<?php echo e(route('admin.reports.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Reports</a>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\reports\show.blade.php ENDPATH**/ ?>