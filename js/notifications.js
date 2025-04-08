/**
 * Notifications management
 * Handles the notification dropdown and marking notifications as read
 */
document.addEventListener('DOMContentLoaded', function() {
    // Toggle notification dropdown
    const notificationBell = document.querySelector('.notification-bell');
    const notificationDropdown = document.querySelector('.notification-dropdown .dropdown-content');
    
    if (notificationBell && notificationDropdown) {
        notificationBell.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            
            // Mark notifications as read when opened
            if (notificationDropdown.classList.contains('show')) {
                markAllNotificationsAsRead();
            }
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-dropdown')) {
                notificationDropdown.classList.remove('show');
            }
        });
    }
    
    // Mark notification as read when clicked
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(function(item) {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            if (notificationId) {
                markNotificationAsRead(notificationId);
                
                // Remove unread styling
                this.classList.remove('unread');
            }
        });
    });
    
    // Function to mark a single notification as read
    function markNotificationAsRead(notificationId) {
        fetch('ajax/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification count badge if needed
                updateNotificationBadge();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }
    
    // Function to mark all notifications as read
    function markAllNotificationsAsRead() {
        fetch('ajax/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'mark_all=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove all unread styling
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                
                // Hide notification badge
                const badge = document.querySelector('.notification-badge');
                if (badge) badge.style.display = 'none';
            }
        })
        .catch(error => console.error('Error marking all notifications as read:', error));
    }
    
    // Function to update notification badge count
    function updateNotificationBadge() {
        const unreadItems = document.querySelectorAll('.notification-item.unread').length;
        const badge = document.querySelector('.notification-badge');
        
        if (badge) {
            if (unreadItems > 0) {
                badge.textContent = unreadItems;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    // "Mark all as read" button functionality
    const markAllReadBtn = document.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllNotificationsAsRead();
        });
    }
});