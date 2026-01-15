/**
 * Toast Notification Handler for Form Submissions and AJAX Requests
 * Automatically shows toast notifications for form submissions and AJAX responses
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle all form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Skip if form has data-no-toast attribute
        if (form.hasAttribute('data-no-toast')) {
            return;
        }
        
        // For regular form submissions, the toast will be shown via session flash messages
        // But we can add a loading state here if needed
        
        // For AJAX form submissions
        if (form.hasAttribute('data-ajax')) {
            e.preventDefault();
            handleAjaxForm(form);
        }
    });
    
    // Handle AJAX form submission
    function handleAjaxForm(form) {
        const formData = new FormData(form);
        const url = form.getAttribute('action') || window.location.href;
        const method = form.getAttribute('method') || 'POST';
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton ? submitButton.textContent : '';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Processing...';
        }
        
        fetch(url, {
            method: method,
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.toast.success(data.message || 'Operation completed successfully');
                
                // Redirect if specified
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else if (form.hasAttribute('data-reset')) {
                    form.reset();
                }
            } else {
                window.toast.error(data.message || 'An error occurred');
                
                // Show validation errors if any
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        const errorElement = form.querySelector(`[name="${field}"]`);
                        if (errorElement) {
                            errorElement.classList.add('border-red-500');
                            const errorMsg = document.createElement('p');
                            errorMsg.className = 'text-red-500 text-xs mt-1';
                            errorMsg.textContent = data.errors[field][0];
                            errorElement.parentElement.appendChild(errorMsg);
                        }
                    });
                }
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            window.toast.error('An error occurred while processing your request');
        })
        .finally(() => {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        });
    }
    
    // Handle delete buttons with confirmation
    document.addEventListener('click', function(e) {
        const deleteButton = e.target.closest('[data-delete]');
        if (deleteButton) {
            e.preventDefault();
            const message = deleteButton.getAttribute('data-message') || 'Are you sure you want to delete this item?';
            const url = deleteButton.getAttribute('data-url') || deleteButton.getAttribute('href');
            
            if (confirm(message)) {
                handleDelete(url, deleteButton);
            }
        }
    });
    
    // Handle delete request
    function handleDelete(url, button) {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Deleting...';
        
        fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.toast.success(data.message || 'Item deleted successfully');
                
                // Remove the element if it has data-remove attribute
                const elementToRemove = button.closest('[data-remove]');
                if (elementToRemove) {
                    elementToRemove.style.transition = 'opacity 0.3s';
                    elementToRemove.style.opacity = '0';
                    setTimeout(() => {
                        elementToRemove.remove();
                    }, 300);
                } else if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                window.toast.error(data.message || 'Failed to delete item');
                button.disabled = false;
                button.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            window.toast.error('An error occurred while deleting');
            button.disabled = false;
            button.textContent = originalText;
        });
    }
    
    // Handle status toggle buttons
    document.addEventListener('click', function(e) {
        const toggleButton = e.target.closest('[data-toggle-status]');
        if (toggleButton) {
            e.preventDefault();
            const url = toggleButton.getAttribute('data-url') || toggleButton.getAttribute('href');
            handleStatusToggle(url, toggleButton);
        }
    });
    
    // Handle status toggle
    function handleStatusToggle(url, button) {
        const originalText = button.textContent;
        button.disabled = true;
        
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.toast.success(data.message || 'Status updated successfully');
                
                // Update button text/icon if needed
                if (data.status) {
                    button.textContent = data.status === 'active' ? 'Deactivate' : 'Activate';
                }
                
                // Reload after 1 second
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                window.toast.error(data.message || 'Failed to update status');
                button.disabled = false;
                button.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Toggle error:', error);
            window.toast.error('An error occurred');
            button.disabled = false;
            button.textContent = originalText;
        });
    }
});

// Export for use in other scripts
window.ToastHandler = {
    showSuccess: (message) => window.toast.success(message),
    showError: (message) => window.toast.error(message),
    showInfo: (message) => window.toast.info(message),
    showWarning: (message) => window.toast.warning(message),
};

