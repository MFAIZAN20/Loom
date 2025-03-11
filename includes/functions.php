<?php
// Time formatter (e.g. "2 hours ago")
function time_elapsed_string($datetime) {
    // Define timezones
    $db_timezone = new DateTimeZone('UTC');
    $app_timezone = new DateTimeZone('Asia/Karachi'); // Pakistan timezone

    // Get current time in application timezone
    $now = new DateTime('now', $app_timezone);
    
    // Parse the input date from database timezone
    $ago = new DateTime($datetime, $db_timezone); 
    
    // Convert database time to application timezone
    $ago->setTimezone($app_timezone);
    
    // Calculate difference
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// Sanitize user input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Get user vote on a post or comment
function get_user_vote($user_id, $post_id = null, $comment_id = null) {
    global $conn;
    
    if ($post_id) {
        $query = "SELECT vote_type FROM votes WHERE user_id = ? AND post_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $post_id);
    } else if ($comment_id) {
        $query = "SELECT vote_type FROM votes WHERE user_id = ? AND comment_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $comment_id);
    } else {
        return 0;
    }
    
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $vote_type);
    
    if (mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        return $vote_type;
    } else {
        mysqli_stmt_close($stmt);
        return 0;
    }
}

// Add to functions.php
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Create a notification
 * 
 * @param int $user_id User ID to receive notification
 * @param string $content Notification text
 * @param string $link URL to redirect when clicked
 * @param string $type Notification type (comment, upvote, etc)
 * @param int $ref_id Reference ID (post_id, comment_id)
 * @param int $from_user_id User ID of the actor who triggered notification
 * @return bool Success status
 */
function create_notification($user_id, $content, $link, $type, $ref_id, $from_user_id) {
    global $conn;
    
    // Skip notification if user has disabled this notification type in preferences
    $pref_query = "SELECT * FROM notification_preferences WHERE user_id = ?";
    $pref_stmt = mysqli_prepare($conn, $pref_query);
    mysqli_stmt_bind_param($pref_stmt, "i", $user_id);
    mysqli_stmt_execute($pref_stmt);
    $result = mysqli_stmt_get_result($pref_stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Check if this notification type is disabled
        if ($type == 'comment' && !$row['comment_notifications']) return false;
        if ($type == 'upvote' && !$row['upvote_notifications']) return false;
        if ($type == 'downvote' && !$row['downvote_notifications']) return false;
    }
    
    // Don't send notifications to yourself
    if ($user_id == $from_user_id) {
        return false;
    }
    
    // Check for duplicate notifications (same type, ref_id and from_user within last hour)
    $check_query = "SELECT notification_id FROM notifications 
                   WHERE user_id = ? AND notification_type = ? AND reference_id = ? AND actor_id = ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "isii", $user_id, $type, $ref_id, $from_user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Duplicate found, don't create new notification
        return false;
    }
    
    // Insert the notification - using your actual column names
    $query = "INSERT INTO notifications (user_id, content, link, notification_type, reference_id, actor_id, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isssis", $user_id, $content, $link, $type, $ref_id, $from_user_id);
    
    return mysqli_stmt_execute($stmt);
}

// Get unread notification count
function get_unread_notification_count($user_id) {
    global $conn;
    
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'];
}

/**
 * Get user notifications
 * 
 * @param int $user_id User ID
 * @param int $limit Max number of notifications to return
 * @param bool $unread_only Whether to only return unread notifications
 * @return array Array of notification objects
 */
function get_notifications($user_id, $limit = 10, $unread_only = false) {
    global $conn;
    
    // Build query based on your actual database structure
    $query = "SELECT n.*, 
              u.username AS from_username,
              u.profile_image AS from_profile_image
              FROM notifications n
              LEFT JOIN users u ON n.actor_id = u.user_id
              WHERE n.user_id = ?";
    
    // Add unread only condition if specified
    if ($unread_only) {
        $query .= " AND n.is_read = 0";
    }
    
    // Order by newest first and limit results
    $query .= " ORDER BY n.created_at DESC LIMIT ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Format timestamps for display
        $row['time_ago'] = time_elapsed_string($row['created_at']);
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Mark notification as read
 * 
 * @param int $notification_id The notification ID to mark as read
 * @param int $user_id The user ID for security check
 * @return bool Whether the operation was successful
 */
function mark_notification_read($notification_id, $user_id) {
    global $conn;
    
    $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
    
    return mysqli_stmt_execute($stmt);
}

/**
 * Mark all notifications as read
 * 
 * @param int $user_id The user ID to mark all notifications for
 * @return bool Whether the operation was successful
 */
function mark_all_notifications_read($user_id) {
    global $conn;
    
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    return mysqli_stmt_execute($stmt);
}

function clear_all_notifications($user_id) {
    global $conn; // Assuming $conn is your database connection

    $query = "DELETE FROM notifications WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        die("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $user_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function delete_post($post_id, $user_id) {
    global $conn;

    // Check if user has permission to delete this post
    $query = "SELECT * FROM posts WHERE post_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $post = mysqli_fetch_assoc($result);

    if (!$post || ($post['user_id'] != $user_id && !is_admin())) {
        return false; // Not allowed to delete
    }

    // Use transaction to ensure all related data is deleted
    mysqli_begin_transaction($conn);
    
    try {
        // Delete comments
        $delete_comments = "DELETE FROM comments WHERE post_id = ?";
        $stmt = mysqli_prepare($conn, $delete_comments);
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        
        // Delete votes
        $delete_votes = "DELETE FROM votes WHERE post_id = ?";
        $stmt = mysqli_prepare($conn, $delete_votes);
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        
        // Delete notifications related to this post
        $delete_notifications = "DELETE FROM notifications WHERE reference_id = ? AND notification_type IN ('comment', 'upvote', 'downvote')";
        $stmt = mysqli_prepare($conn, $delete_notifications);
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        
        // Delete post tags
        $delete_post_tags = "DELETE FROM post_tags WHERE post_id = ?";
        $stmt = mysqli_prepare($conn, $delete_post_tags);
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        
        // Delete reports
        $delete_reports = "DELETE FROM reports WHERE post_id = ?";
        $stmt = mysqli_prepare($conn, $delete_reports);
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        
        // Delete the post itself
        $delete_post = "DELETE FROM posts WHERE post_id = ?";
        $stmt = mysqli_prepare($conn, $delete_post);
        mysqli_stmt_bind_param($stmt, "i", $post_id);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        error_log("Error deleting post ID $post_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Safely redirect to another page
 * Prevents open redirect vulnerabilities by only allowing internal redirects
 * 
 * @param string $url The URL to redirect to
 * @return void
 */
function safe_redirect($url) {
    // Make sure URL is relative or from the same domain
    $host = $_SERVER['HTTP_HOST'];
    $parsed_url = parse_url($url);
    
    // If URL has a host, make sure it's our host
    if (isset($parsed_url['host']) && $parsed_url['host'] !== $host) {
        // Redirect to homepage if someone tries to redirect off-site
        $url = 'index.php';
    }
    
    // Clean the URL (remove potential harmful characters)
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    // Set appropriate headers and redirect
    header("Location: $url");
    exit();
}

/**
 * Get badge levels array
 * 
 * @return array Array of badge levels with name, min_karma, and color
 */
function get_badge_levels() {
    return [
        ['name' => 'Novice', 'min_karma' => 0, 'color' => '#cd7f32'], // Bronze
        ['name' => 'Contributor', 'min_karma' => 100, 'color' => '#c0c0c0'], // Silver  
        ['name' => 'Expert', 'min_karma' => 500, 'color' => '#ffd700'], // Gold
        ['name' => 'Sage', 'min_karma' => 1000, 'color' => '#b9f2ff'], // Diamond
        ['name' => 'Luminary', 'min_karma' => 5000, 'color' => '#FF4500'], // Special
        ['name' => 'Legend', 'min_karma' => 10000, 'color' => '#5D4AFF'] // Elite
    ];
}

/**
 * Get badge for a user based on karma
 * 
 * @param int $karma The user's karma points
 * @param array|null $badge_levels Optional badge levels array (will use default if not provided)
 * @return array Badge information with name, min_karma, and color
 */
function get_user_badge($karma, $badge_levels = null) {
    // Use provided badge levels or get the default ones
    if ($badge_levels === null) {
        $badge_levels = get_badge_levels();
    }
    
    $badge = $badge_levels[0]; // Default to lowest badge
    
    foreach ($badge_levels as $level) {
        if ($karma >= $level['min_karma']) {
            $badge = $level;
        } else {
            break;
        }
    }
    
    return $badge;
}

/**
 * Get user rank by karma
 */
function get_user_karma_rank($user_id) {
    global $conn;
    
    $rank_query = "
        SELECT count(*) + 1 as rank_num
        FROM users
        WHERE karma > (SELECT karma FROM users WHERE user_id = ?)
    ";
    
    $stmt = mysqli_prepare($conn, $rank_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $rank_result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($rank_result)['rank_num'];
}

/**
 * Get the URL for a user's avatar
 *
 * @param string|null $avatar The avatar filename or path
 * @return string The complete URL to the avatar image
 */
function get_avatar_url($avatar = null) {
    $default_avatar = 'assets/default-avatar.svg';

    if (empty($avatar)) {
        return $default_avatar;
    }

    if (strpos($avatar, 'http://') === 0 || strpos($avatar, 'https://') === 0) {
        return $avatar;
    }

    $root_dir = dirname(__DIR__);
    $normalized = ltrim($avatar, '/');

    $known_prefixes = ['uploads/profile_pictures/', 'images/avatars/', 'assets/'];
    foreach ($known_prefixes as $prefix) {
        if (strpos($normalized, $prefix) === 0) {
            $file_path = $root_dir . '/' . $normalized;
            return is_file($file_path) ? $normalized : $default_avatar;
        }
    }

    $filename = basename($normalized);

    $uploads_path = 'uploads/profile_pictures/' . $filename;
    if (is_file($root_dir . '/' . $uploads_path)) {
        return $uploads_path;
    }

    $avatars_path = 'images/avatars/' . $filename;
    if (is_file($root_dir . '/' . $avatars_path)) {
        return $avatars_path;
    }

    return $default_avatar;
}
?>
