<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/auth_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to prevent "headers already sent" errors
ob_start();

$user_id = $_SESSION['user_id'] ?? null;

// Mark all as read if requested
if (isset($_GET['mark_all_read']) && $user_id) {
    mark_all_notifications_read($user_id);
    header("Location: notifications.php");
    exit;
}

// Get unread notification count for the badge
if ($user_id) {
    $_SESSION['unread_notifications'] = get_unread_notification_count($user_id);
    
    // Get recent notifications for the dropdown (limit to 5)
    $recent_notifications = get_notifications($user_id, 5);
} else {
    $recent_notifications = [];
}

// More sophisticated current page detection that handles parameters
$current_page = basename($_SERVER['PHP_SELF']);

// For profile.php, handle user parameter
$is_profile_page = ($current_page === 'profile.php');
$is_own_profile = $is_profile_page && 
    (!isset($_GET['user']) || 
    (isset($_SESSION['username']) && isset($_GET['user']) && $_GET['user'] === $_SESSION['username']));

// For post.php, we'll always highlight it when active
$is_post_page = ($current_page === 'post.php');

// For category filtering on index
$is_home_page = ($current_page === 'index.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Loom - Connect & Share</title>
    <link rel="stylesheet" href="css/<?php echo $page_css ?? 'style.css'; ?>">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/comment.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/toxicity"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/universal-sentence-encoder"></script>
    <script src="js/notifications.js"></script>
    <style>
        /* Notification Dropdown Styling */
        .notification-dropdown {
            position: relative;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff4444;
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
            background-color: #fff;
            min-width: 300px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            border-radius: 4px;
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .dropdown-content.show {
            display: block;
        }
        
        .dropdown-header, .dropdown-footer {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dropdown-header h3 {
            margin: 0;
            font-size: 1rem;
        }
        
        .notification-item {
            display: block;
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-decoration: none;
            color: #333;
        }
        
        .notification-item:hover {
            background-color: #f5f5f5;
        }
        
        .notification-item.unread {
            background-color: #e8f4fd;
        }
        
        .notification-content {
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #777;
        }
        
        .no-notifications {
            padding: 20px;
            text-align: center;
            color: #777;
        }
    </style>
</head>

<body class="<?php echo is_logged_in() ? 'logged-in' : ''; ?>">
    <header>
        <div class="container header-container">
            <div class="logo">
                <a href="index.php">
                    <i class="fas fa-project-diagram"></i>
                    <span>loom</span>
                </a>
            </div>

            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>

            <nav class="main-nav">
                <ul>
                    <li>
                        <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span class="nav-text">Home</span>
                        </a>
                    </li>
                    
                    <?php if (is_logged_in()): ?>
                    <!-- Only show Create Post when logged in -->
                    <li>
                        <a href="create-post.php" class="nav-link <?php echo $current_page == 'create-post.php' ? 'active' : ''; ?>">
                            <i class="fas fa-pen"></i>
                            <span class="nav-text">Create Post</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="notification-dropdown">
                        <a href="#" class="nav-link notification-bell">
                            <i class="fas fa-bell"></i>
                            <span class="nav-text">Notifications</span>
                            <?php if (isset($_SESSION['unread_notifications']) && $_SESSION['unread_notifications'] > 0): ?>
                            <span class="notification-badge"><?php echo $_SESSION['unread_notifications']; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-content">
                            <div class="dropdown-header">
                                <h3>Notifications</h3>
                                <a href="notifications.php" class="view-all">View all</a>
                            </div>
                            <div class="notification-list">
                                <?php if ($user_id && !empty($recent_notifications)): ?>
                                    <?php foreach ($recent_notifications as $notification): ?>
                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" 
                                           class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>"
                                           data-id="<?php echo $notification['notification_id']; ?>">
                                            <div class="notification-content">
                                                <?php echo htmlspecialchars($notification['content']); ?>
                                            </div>
                                            <div class="notification-time">
                                                <?php echo time_elapsed_string($notification['created_at']); ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-notifications">
                                        <p>No notifications yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-footer">
                                <a href="notification_settings.php">Notification Settings</a>
                                <a href="notifications.php?mark_all_read=1">Mark all as read</a>
                            </div>
                        </div>
                    </li>
                    <li>
                        <a href="leaderboard.php" class="nav-link <?php echo $current_page == 'leaderboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-trophy"></i>
                            <span class="nav-text">Leaderboard</span>
                        </a>
                    </li>
                    <?php if (is_logged_in()): ?>
                    <li>
                        <a href="profile.php" class="nav-link <?php echo $is_own_profile ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            <span class="nav-text">Profile</span>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <li>
                        <a href="admin.php" class="nav-link <?php echo $current_page == 'admin.php' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt"></i>
                            <span class="nav-text">Admin</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="nav-text">Logout</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li>
                        <a href="login.php" class="nav-link <?php echo $current_page == 'login.php' ? 'active' : ''; ?>">
                            <i class="fas fa-sign-in-alt"></i>
                            <span class="nav-text">Login</span>
                        </a>
                    </li>
                    <!-- Register page removed as requested -->
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
<!-- Header ends here, body content begins -->