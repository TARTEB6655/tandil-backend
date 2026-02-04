<!-- Toast Notifications Container -->
<div 
    x-data="toastNotifications()" 
    x-init="init()"
    class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2 max-w-sm w-full pointer-events-none"
    style="max-width: 380px;"
>
    <template x-for="(toast, index) in toasts" :key="index">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-y-4"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-4"
            class="pointer-events-auto bg-white rounded-lg shadow-lg border border-gray-200 p-3 flex items-center gap-3"
        >
            <!-- Icon -->
            <div class="flex-shrink-0">
                <template x-if="toast.type === 'success'">
                    <div class="w-5 h-5 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </template>
                <template x-if="toast.type === 'error'">
                    <div class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </template>
                <template x-if="toast.type === 'info'">
                    <div class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </template>
                <template x-if="toast.type === 'warning'">
                    <div class="w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </template>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900 font-medium" x-text="toast.message"></p>
            </div>

            <!-- Close Button -->
            <button
                @click="removeToast(toast.id)"
                class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors p-1 rounded hover:bg-gray-100"
                aria-label="Close"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>

<script>
function toastNotifications() {
    return {
        toasts: [],
        maxToasts: 4,
        
        init() {
            // Session flash messages are shown by each page's inline banner (e.g. @if(session('success'))).
            // We do NOT show a toast for session flash here to avoid duplicate messages (banner + toast).

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
        
        show(message, type = 'info', duration = 4000) {
            const id = Date.now() + Math.random();
            const toast = {
                id,
                message,
                type,
                visible: true,
                duration: duration,
            };
            
            // Limit number of toasts
            if (this.toasts.length >= this.maxToasts) {
                this.toasts.shift();
            }
            
            this.toasts.push(toast);
            
            // Auto-remove after duration
            if (duration > 0) {
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
        
        success(message, duration = 4000) {
            this.show(message, 'success', duration);
        },
        
        error(message, duration = 4000) {
            this.show(message, 'error', duration);
        },
        
        info(message, duration = 4000) {
            this.show(message, 'info', duration);
        },
        
        warning(message, duration = 4000) {
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
/* Responsive positioning */
@media (max-width: 640px) {
    .fixed.bottom-4.right-4 {
        bottom: 1rem;
        right: 1rem;
        left: 1rem;
        max-width: calc(100% - 2rem);
    }
}
</style>
