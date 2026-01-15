<!-- Toast Notifications Container -->
<div 
    x-data="toastNotifications()" 
    x-init="init()"
    class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 max-w-sm w-full pointer-events-none"
    style="max-width: 420px;"
>
    <template x-for="(toast, index) in toasts" :key="index">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-x-full"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform translate-x-full"
            :class="{
                'bg-green-50 border-green-200 text-green-800': toast.type === 'success',
                'bg-red-50 border-red-200 text-red-800': toast.type === 'error',
                'bg-blue-50 border-blue-200 text-blue-800': toast.type === 'info',
                'bg-yellow-50 border-yellow-200 text-yellow-800': toast.type === 'warning',
            }"
            class="pointer-events-auto rounded-lg border shadow-lg p-4 flex items-start gap-3 animate-slide-in"
            style="animation: slideInRight 0.3s ease-out;"
        >
            <!-- Icon -->
            <div class="flex-shrink-0 mt-0.5">
                <template x-if="toast.type === 'success'">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </template>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium" x-text="toast.message"></p>
                <p x-if="toast.description" class="text-xs mt-1 opacity-90" x-text="toast.description"></p>
            </div>

            <!-- Close Button -->
            <button
                @click="removeToast(toast.id)"
                class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors"
                aria-label="Close"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Progress Bar (optional) -->
            <div 
                x-if="toast.duration > 0"
                class="absolute bottom-0 left-0 right-0 h-1 bg-black bg-opacity-10 rounded-b-lg overflow-hidden"
            >
                <div 
                    :style="`width: ${toast.progress}%; transition: width ${toast.duration}ms linear;`"
                    :class="{
                        'bg-green-500': toast.type === 'success',
                        'bg-red-500': toast.type === 'error',
                        'bg-blue-500': toast.type === 'info',
                        'bg-yellow-500': toast.type === 'warning',
                    }"
                    class="h-full"
                ></div>
            </div>
        </div>
    </template>
</div>

<script>
function toastNotifications() {
    return {
        toasts: [],
        maxToasts: 5,
        
        init() {
            // Listen for Laravel session flash messages
            @if(session('success'))
                this.show('{{ session('success') }}', 'success');
            @endif
            
            @if(session('error'))
                this.show('{{ session('error') }}', 'error');
            @endif
            
            @if(session('info'))
                this.show('{{ session('info') }}', 'info');
            @endif
            
            @if(session('warning'))
                this.show('{{ session('warning') }}', 'warning');
            @endif

            // Listen for custom toast events from JavaScript
            window.addEventListener('toast', (e) => {
                this.show(e.detail.message, e.detail.type || 'info', e.detail.duration);
            });

            // Listen for notification bell clicks to show toast
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-notification-toast]')) {
                    const notification = e.target.closest('[data-notification-toast]');
                    const message = notification.getAttribute('data-message') || 'New notification';
                    const type = notification.getAttribute('data-type') || 'info';
                    this.show(message, type, 5000);
                }
            });
            
            // Listen for new notifications from real-time updates (if using polling or websockets)
            // This can be extended for real-time notification updates
            @auth
            if (window.Echo) {
                const userId = {{ auth()->id() }};
                window.Echo.private(`App.Models.User.${userId}`)
                    .notification((notification) => {
                        const message = notification.data?.message || notification.message || 'New notification';
                        this.show(message, 'info', 5000);
                    });
            }
            @endauth
        },
        
        show(message, type = 'info', duration = 5000) {
            const id = Date.now() + Math.random();
            const toast = {
                id,
                message,
                type,
                visible: true,
                duration: duration,
                progress: 100,
            };
            
            // Limit number of toasts
            if (this.toasts.length >= this.maxToasts) {
                this.toasts.shift();
            }
            
            this.toasts.push(toast);
            
            // Auto-remove after duration
            if (duration > 0) {
                // Animate progress bar
                setTimeout(() => {
                    toast.progress = 0;
                }, 10);
                
                setTimeout(() => {
                    this.removeToast(id);
                }, duration);
            }
        },
        
        removeToast(id) {
            const index = this.toasts.findIndex(t => t.id === id);
            if (index !== -1) {
                this.toasts[index].visible = false;
                setTimeout(() => {
                    this.toasts.splice(index, 1);
                }, 200);
            }
        },
        
        success(message, duration = 5000) {
            this.show(message, 'success', duration);
        },
        
        error(message, duration = 5000) {
            this.show(message, 'error', duration);
        },
        
        info(message, duration = 5000) {
            this.show(message, 'info', duration);
        },
        
        warning(message, duration = 5000) {
            this.show(message, 'warning', duration);
        }
    };
}

// Make toast functions globally available
window.toast = {
    success: (message, duration) => {
        const event = new CustomEvent('toast', { detail: { message, type: 'success', duration } });
        window.dispatchEvent(event);
    },
    error: (message, duration) => {
        const event = new CustomEvent('toast', { detail: { message, type: 'error', duration } });
        window.dispatchEvent(event);
    },
    info: (message, duration) => {
        const event = new CustomEvent('toast', { detail: { message, type: 'info', duration } });
        window.dispatchEvent(event);
    },
    warning: (message, duration) => {
        const event = new CustomEvent('toast', { detail: { message, type: 'warning', duration } });
        window.dispatchEvent(event);
    }
};
</script>

<style>
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.animate-slide-in {
    animation: slideInRight 0.3s ease-out;
}

/* Responsive positioning */
@media (max-width: 640px) {
    .fixed.top-4.right-4 {
        top: 1rem;
        right: 1rem;
        left: 1rem;
        max-width: calc(100% - 2rem);
    }
}
</style>

