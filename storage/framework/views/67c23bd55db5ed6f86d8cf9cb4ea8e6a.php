<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e(config('app.name', 'Tandil')); ?> - Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="font-sans antialiased bg-gray-50" style="font-family: 'Inter', sans-serif;">
    <?php if(auth()->guard()->check()): ?>
        <?php if(auth()->user()->role === 'admin'): ?>
            <div x-data="{ sidebarOpen: false }"
                 x-init="
                    if (window.innerWidth >= 1024) { sidebarOpen = true; }
                    window.addEventListener('resize', () => {
                        if (window.innerWidth >= 1024) { sidebarOpen = true; }
                        else { sidebarOpen = false; }
                    });
                 ">
                <!-- Topbar -->
                <?php echo $__env->make('partials.topbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <!-- Sidebar -->
                <?php echo $__env->make('partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <!-- Main Content -->
                <main class="ml-0 lg:ml-64 mt-16 p-4 lg:p-6">
                    <div class="max-w-7xl mx-auto px-6">
                        <?php echo e($slot); ?>

                    </div>
                </main>
            </div>
        <?php else: ?>
            <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <main class="p-6">
                <div class="max-w-7xl mx-auto px-6">
                    <?php echo e($slot); ?>

                </div>
            </main>
        <?php endif; ?>
    <?php else: ?>
        <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <main class="p-6">
            <div class="max-w-7xl mx-auto px-6">
                <?php echo e($slot); ?>

            </div>
        </main>
    <?php endif; ?>

    <script>
        function toggleSidebar() {
            window.dispatchEvent(new CustomEvent('toggle-sidebar'));
        }
    </script>
</body>
</html>
<?php /**PATH C:\projects\tandil-backend\resources\views\layouts\app.blade.php ENDPATH**/ ?>