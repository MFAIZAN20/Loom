<?php
$page_title = "Notifications";
include 'includes/header.php';

// Redirect if not logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    mark_all_notifications_read($user_id);
    header("Location: notifications.php");
    exit;
}

// Clear all notifications if requested
if (isset($_GET['clear_all'])) {
    clear_all_notifications($user_id);
    header("Location: notifications.php");
    exit;
}

// Replace your existing notification query with this:
$notification_query = "
    SELECT n.*, 
           u.username as actor_name,
           p.title as post_title 
    FROM notifications n
    LEFT JOIN users u ON n.actor_id = u.user_id
    LEFT JOIN posts p ON n.reference_id = p.post_id 
           AND n.notification_type IN ('post', 'comment', 'upvote', 'downvote')
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC
    LIMIT 50";

$stmt = mysqli_prepare($conn, $notification_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$notifications_result = mysqli_stmt_get_result($stmt);

// Get all notifications
$notifications = get_notifications($user_id, 50);
?>

<div class="content-area">
    <div class="notifications-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Your Notifications</h1>
        <div>
            <a href="notifications.php?mark_all_read=1" class="btn">Mark All as Read</a>
            <a href="notifications.php?clear_all=1" class="btn" style="background-color: #ff4444; color: white; margin-left: 10px;">Clear All Notifications</a>
        </div>
    </div>
    
    <div class="card">
        <?php while ($notification = mysqli_fetch_assoc($notifications_result)): ?>
            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>"
                 data-id="<?php echo $notification['notification_id']; ?>">
                
                <div class="notification-content">
                    <?php if (isset($notification['actor_name'])): ?>
                        <span class="notification-user"><?php echo htmlspecialchars($notification['actor_name']); ?></span>
                    <?php else: ?>
                        <span class="notification-user">Someone</span>
                    <?php endif; ?>
                    
                    <?php echo htmlspecialchars($notification['message']); ?>
                    
                    <?php if (isset($notification['post_title']) && !empty($notification['post_title'])): ?>
                        <span class="notification-post-title">"<?php echo htmlspecialchars($notification['post_title']); ?>"</span>
                    <?php endif; ?>
                </div>
                
                <div class="notification-time">
                    <?php echo time_elapsed_string($notification['created_at']); ?>
                </div>
                
                <button class="mark-read-btn" title="Mark as read" data-id="<?php echo $notification['notification_id']; ?>">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        <?php endwhile; ?>

        <?php if (mysqli_num_rows($notifications_result) == 0): ?>
            <div class="no-notifications">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .notification-dropdown {
        position: relative;
    }

    .notification-bell {
        position: relative;
        display: inline-block;
    }

    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: var(--secondary-color);
        color: white;
        border-radius: 50%;
        padding: 0 5px;
        font-size: 0.7rem;
        min-width: 15px;
        height: 15px;
        line-height: 15px;
        text-align: center;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: var(--card-bg);
        min-width: 300px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        border-radius: 4px;
        z-index: 101;
    }

    .notification-dropdown:hover .dropdown-content,
    .notification-dropdown:focus .dropdown-content {
        display: block;
    }

    .dropdown-header, .dropdown-footer {
        padding: 10px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dropdown-footer {
        border-top: 1px solid var(--border-color);
        border-bottom: none;
    }

    .dropdown-header h3 {
        margin: 0;
        font-size: 1rem;
    }

    .notification-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .notification-item {
        display: block;
        padding: 10px;
        border-bottom: 1px solid var(--border-color);
        transition: background-color 0.3s;
    }

    .notification-item:hover {
        background-color: #f5f5f5;
        text-decoration: none;
    }

    .notification-item.unread {
        background-color: #e8f4fd;
    }

    .notification-content {
        margin-bottom: 5px;
        color: var(--primary-color); /* Changed to blue */
    }
    
    .notification-item:hover .notification-content {
        color: var(--accent-color); /* Slightly darker blue on hover */
    }

    .notification-time {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .no-notifications {
        padding: 15px;
        text-align: center;
        color: var(--text-secondary);
    }
</style>

<?php include 'includes/footer.php'; ?>
