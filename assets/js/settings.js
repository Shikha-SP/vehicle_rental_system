// Handle Change Password
document.addEventListener('DOMContentLoaded', function() {
    // Password change form
    const passwordForm = document.querySelector('.settings-page-card form');
    if (passwordForm && window.location.href.includes('section=password')) {
        passwordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            try {
                const response = await fetch('/vehicle_rental_collab_project/ajax/update_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    passwordForm.reset();
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // Name change form
    const nameForm = document.querySelector('.settings-page-card form');
    if (nameForm && window.location.href.includes('section=name')) {
        nameForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            const currentPassword = document.getElementById('current_password').value;
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            try {
                const response = await fetch('/vehicle_rental_collab_project/ajax/update_name.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        first_name: firstName,
                        last_name: lastName,
                        current_password: currentPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update displayed name in the page
                    setTimeout(() => {
                        window.location.href = 'settings.php?section=overview';
                    }, 1500);
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // Delete account form
    const deleteForm = document.querySelector('.settings-page-card form');
    if (deleteForm && window.location.href.includes('section=delete')) {
        deleteForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('delete_password').value;
            const confirmText = document.getElementById('confirm_text').value;
            
            // Extra confirmation
            const userConfirmed = confirm('WARNING: This action is irreversible! Are you absolutely sure you want to delete your account?');
            if (!userConfirmed) {
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            
            try {
                const response = await fetch('../../ajax/update_password.php', { 
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        password: password,
                        confirm_text: confirmText
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Redirect after deletion
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                } else {
                    showNotification(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                showNotification('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
});

// Notification function
function showNotification(message, type) {
    // Remove existing notification
    const existingNotification = document.querySelector('.settings-notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `settings-notification settings-notification-${type}`;
    notification.innerHTML = `
        <div class="settings-notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add to page
    const container = document.querySelector('.settings-page-content');
    if (container) {
        container.insertBefore(notification, container.firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
}