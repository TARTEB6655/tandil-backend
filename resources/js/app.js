import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Create a global store for sidebar state
Alpine.store('sidebar', {
    open: false,
    
    init() {
        // Sidebar is always open on desktop (above 991px)
        // On mobile/tablet (991px and below), it starts closed
        const checkWidth = () => {
            if (window.innerWidth > 991) {
                // Always open on desktop, restore body scroll
                this.open = true;
                document.body.style.overflow = '';
            } else {
                // On mobile/tablet, start closed
                if (this.open) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        };
        
        // Initial check
        checkWidth();
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                checkWidth();
            }, 100);
        });
    },
    
    toggle() {
        // Only toggle on mobile/tablet (991px and below)
        if (window.innerWidth <= 991) {
            this.open = !this.open;
            
            // Prevent body scroll when sidebar is open on mobile
            if (this.open) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        } else {
            // On desktop, ensure it's always open
            this.open = true;
        }
    }
});

    // Prevent sidebar scroll from affecting main page
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarNav = document.querySelector('.sidebar-scroll');
        if (sidebarNav) {
            sidebarNav.addEventListener('wheel', function(e) {
                const isScrollingDown = e.deltaY > 0;
                const isScrollingUp = e.deltaY < 0;
                const isAtTop = sidebarNav.scrollTop === 0;
                const isAtBottom = sidebarNav.scrollTop + sidebarNav.clientHeight >= sidebarNav.scrollHeight - 1;
                
                // Prevent scroll propagation if not at boundaries
                if ((isScrollingDown && !isAtBottom) || (isScrollingUp && !isAtTop)) {
                    e.stopPropagation();
                }
            }, { passive: false });
        }
    });

    Alpine.start();
