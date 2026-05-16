/**
 * Settings Page Interactions
 * Handles sidebar toggling and mobile responsiveness
 */

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.settings-page-sidebar');
    const contentArea = document.querySelector('.settings-page-content-area');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            
            // Toggle icon between bars and times
            const icon = sidebarToggle.querySelector('i');
            if (sidebar.classList.contains('open')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Close sidebar when clicking on content area (mobile)
        contentArea.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                const icon = sidebarToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }

    // Notification Helper
    function showNotification(message, type = 'success') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        toast.innerHTML = `
            <i class="fas ${icon} toast__icon"></i>
            <span class="toast__msg">${message}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Export to window so inline scripts can use it
    window.showNotification = showNotification;

    // ── Form Submissions ────────────────────────────────────

    // Generic form handler
    async function handleFormSubmit(formId, endpoint, successCallback = null) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(`/vehicle_rental_collab_project/ajax/${endpoint}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    if (successCallback) successCallback(result);
                    form.reset();
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('An unexpected error occurred. Please try again.', 'error');
            }
        });
    }

    // 1. Change Password
    handleFormSubmit('changePasswordForm', 'update_password.php');

    // 2. Change Name
    handleFormSubmit('changeNameForm', 'update_name.php', (result) => {
        // Update display names on page
        const nameDisplay = document.querySelector('.settings-profile-main h3');
        const avatarDisplay = document.querySelector('.settings-profile-avatar');
        if (nameDisplay) nameDisplay.textContent = result.full_name;
        if (avatarDisplay) {
            const initials = result.full_name.split(' ').map(n => n[0]).join('').toUpperCase();
            avatarDisplay.textContent = initials;
        }
    });

    // 3. Delete Account
    const deleteForm = document.getElementById('deleteAccountForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const password = document.getElementById('delete_password').value;
            const confirmText = document.getElementById('confirm_text').value;

            if (confirmText !== 'DELETE MY ACCOUNT') {
                showNotification('Please type DELETE MY ACCOUNT exactly to confirm.', 'error');
                return;
            }

            try {
                const response = await fetch('/vehicle_rental_collab_project/ajax/delete_account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password, confirm_text: confirmText })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 2000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('An error occurred. Please try again.', 'error');
            }
        });
    }
});