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
            Visit Details
        </h2>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <!-- Visit Info -->
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Visit Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Client</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($visit->subscription->client->name ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Technician</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($visit->technician->name ?? 'Unassigned'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Supervisor</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($visit->supervisor->name ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Area</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($visit->area->name ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Scheduled Date</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo e($visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?php echo e($visit->status == 'completed' ? 'bg-green-100 text-green-800' : 
                               ($visit->status == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800')); ?>">
                            <?php echo e(ucfirst($visit->status)); ?>

                        </span>
                    </div>
                </div>
            </div>

            <!-- Photos -->
            <?php if($visit->photos && $visit->photos->count() > 0): ?>
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Visit Photos</h3>
                <div class="grid grid-cols-2 gap-4">
                    <?php $__currentLoopData = $visit->photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <img src="<?php echo e(asset('storage/' . $photo->photo_path)); ?>" alt="Visit Photo" class="rounded-lg w-full">
                            <p class="text-xs text-gray-500 mt-1"><?php echo e($photo->type ?? 'Photo'); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Report -->
            <?php if($visit->report): ?>
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Report</h3>
                <div class="bg-gray-50 p-4 rounded">
                    <?php if($visit->report->supervisor_notes): ?>
                        <p class="text-sm text-gray-700 mb-2"><strong>Supervisor Notes:</strong> <?php echo e($visit->report->supervisor_notes); ?></p>
                    <?php endif; ?>
                    <?php if($visit->report->recommendations): ?>
                        <p class="text-sm text-gray-700"><strong>Recommendations:</strong></p>
                        <ul class="list-disc list-inside mt-1">
                            <?php $__currentLoopData = $visit->report->recommendations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="text-sm text-gray-700"><?php echo e($rec); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="pt-4">
                <a href="<?php echo e(route('admin.visits.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Visits</a>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\visits\show.blade.php ENDPATH**/ ?>