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
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-medium text-gray-900">Send Notification</h1>
                    <p class="mt-1 text-sm md:text-base text-gray-600">Send push notifications and announcements to users</p>
                </div>
                <a href="<?php echo e(route('admin.notifications.index')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    ← Back to Notifications
                </a>
            </div>
        </div>

        <?php if(session('success')): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm"><?php echo e(session('success')); ?></span>
            </div>
        <?php endif; ?>

        <!-- Send Notification Form -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <form method="POST" action="<?php echo e(route('admin.notifications.send')); ?>" class="space-y-6">
                <?php echo csrf_field(); ?>

                <!-- Notification Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Send To</label>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="type" value="all" class="text-indigo-600 focus:ring-indigo-500" checked>
                            <div>
                                <p class="text-sm font-medium text-gray-900">All Users</p>
                                <p class="text-xs text-gray-500">Send to all registered users</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="type" value="role" class="text-indigo-600 focus:ring-indigo-500">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Specific Role</p>
                                <p class="text-xs text-gray-500 mb-2">Send to users with a specific role</p>
                                <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" disabled id="role-select">
                                    <option value="">Select Role</option>
                                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($role->name); ?>"><?php echo e(ucfirst($role->name)); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="type" value="users" class="text-indigo-600 focus:ring-indigo-500">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Specific Users</p>
                                <p class="text-xs text-gray-500 mb-2">Send to selected users</p>
                                <select name="user_ids[]" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" disabled id="users-select">
                                    <?php $__currentLoopData = \App\Models\User::all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?> (<?php echo e($user->email); ?>)</option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Notification Title</label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="<?php echo e(old('title')); ?>"
                           placeholder="Enter notification title"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           required>
                    <?php $__errorArgs = ['title'];
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

                <!-- Message -->
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea id="message" 
                              name="message" 
                              rows="5"
                              placeholder="Enter notification message"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                              required><?php echo e(old('message')); ?></textarea>
                    <?php $__errorArgs = ['message'];
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

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="<?php echo e(route('admin.notifications.index')); ?>" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                        Send Notification
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeInputs = document.querySelectorAll('input[name="type"]');
            const roleSelect = document.getElementById('role-select');
            const usersSelect = document.getElementById('users-select');

            typeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value === 'role') {
                        roleSelect.disabled = false;
                        roleSelect.required = true;
                        usersSelect.disabled = true;
                        usersSelect.required = false;
                    } else if (this.value === 'users') {
                        usersSelect.disabled = false;
                        usersSelect.required = true;
                        roleSelect.disabled = true;
                        roleSelect.required = false;
                    } else {
                        roleSelect.disabled = true;
                        roleSelect.required = false;
                        usersSelect.disabled = true;
                        usersSelect.required = false;
                    }
                });
            });
        });
    </script>
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

<?php /**PATH C:\projects\tandil-backend\resources\views\admin\notifications\create.blade.php ENDPATH**/ ?>