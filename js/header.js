// Enhanced Header Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Shrink header on scroll
    const header = document.querySelector('header');
    const headerContainer = document.querySelector('.header-container');
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 30) {
            header.classList.add('shrink');
            headerContainer.classList.add('shrink');
        } else {
            header.classList.remove('shrink');
            headerContainer.classList.remove('shrink');
        }
    });
    
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mainNav = document.getElementById('mainNav');
    
    if (mobileMenuBtn && mainNav) {
        mobileMenuBtn.addEventListener('click', () => {
            mainNav.classList.toggle('active');
            
            // Change icon between bars and times
            const icon = mobileMenuBtn.querySelector('i');
            if (icon) {
                if (icon.classList.contains('fa-bars')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    }
    
    // Notification dropdown
    const bell = document.querySelector('.notification-bell');
    const dropdown = document.querySelector('.notification-dropdown .dropdown-content');
    
    if (bell && dropdown) {
        bell.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle('visible');
        });
        
        // Close on click outside
        document.addEventListener('click', () => {
            dropdown.classList.remove('visible');
        });
        
        dropdown.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent closing when clicking inside
        });
        
        // Mark notifications as read when clicked
        const notificationItems = document.querySelectorAll('.notification-item[data-id]');
        notificationItems.forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                if (notificationId) {
                    fetch('ajax/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'notification_id=' + notificationId
                    });
                    this.classList.remove('unread');
                    
                    // Update badge count
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        let count = parseInt(badge.textContent) - 1;
                        if (count <= 0) {
                            badge.style.display = 'none';
                        } else {
                            badge.textContent = count;
                        }
                    }
                }
            });
        });
    }
    
    // Add subtle hover effect to navigation items
    const navItems = document.querySelectorAll('nav ul li a');
    navItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});