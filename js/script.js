document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.querySelector('.notification-bell');
    const notificationDropdown = document.querySelector('.notification-dropdown .dropdown-content');
    
    if (notificationBell && notificationDropdown) {
        notificationBell.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationDropdown.classList.toggle('visible');
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-dropdown')) {
                notificationDropdown.classList.remove('visible');
            }
        });
    }
});