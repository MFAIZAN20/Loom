<?php
header('Content-Type: application/json');

// Use full path to includes for consistency
require_once dirname(__DIR__) . '/includes/init.php';

// Set the correct path for the session cookie
$path = '/';
if (dirname($_SERVER['SCRIPT_NAME']) != '/') {
    $path = dirname(dirname($_SERVER['SCRIPT_NAME']));
}
session_set_cookie_params(0, $path);

// Start session explicitly if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to comment', 'session_status' => session_status(), 'session_id' => session_id()]);
    exit;
}

if (empty($_POST['comment'])) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit;
}

$content = sanitize_input($_POST['comment']);
$user_id = $_SESSION['user_id'];
$parent_id = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$post_id = null;

if ($parent_id) {
    $query = "SELECT post_id FROM comments WHERE comment_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $parent_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $post_id);
    
    if (!mysqli_stmt_fetch($stmt)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parent comment']);
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        exit;
    }
    $post_id = (int)$_POST['post_id'];
}

$insert_query = "INSERT INTO comments (post_id, user_id, parent_id, content, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "iiis", $post_id, $user_id, $parent_id, $content);

if (mysqli_stmt_execute($stmt)) {
    $comment_id = mysqli_insert_id($conn);
    
    $username_query = "SELECT username FROM users WHERE user_id = ?";
    $user_stmt = mysqli_prepare($conn, $username_query);
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    mysqli_stmt_bind_result($user_stmt, $username);
    mysqli_stmt_fetch($user_stmt);
    mysqli_stmt_close($user_stmt);
    
    if ($parent_id) {
        $notify_query = "SELECT u.user_id FROM comments c 
                        JOIN users u ON c.user_id = u.user_id 
                        WHERE c.comment_id = ?";
        $notify_stmt = mysqli_prepare($conn, $notify_query);
        mysqli_stmt_bind_param($notify_stmt, "i", $parent_id);
        mysqli_stmt_execute($notify_stmt);
        mysqli_stmt_bind_result($notify_stmt, $notify_user_id);
        
        if (mysqli_stmt_fetch($notify_stmt) && $notify_user_id != $user_id) {
            $notification_text = "$username replied to your comment";
            $notification_link = "post.php?id=$post_id#comment-$comment_id";
            
            create_notification($notify_user_id, $notification_text, $notification_link, 'reply', $parent_id, $user_id);
        }
        mysqli_stmt_close($notify_stmt);
    } else {
        $post_author_query = "SELECT user_id FROM posts WHERE post_id = ?";
        $post_stmt = mysqli_prepare($conn, $post_author_query);
        mysqli_stmt_bind_param($post_stmt, "i", $post_id);
        mysqli_stmt_execute($post_stmt);
        mysqli_stmt_bind_result($post_stmt, $post_author_id);
        
        if (mysqli_stmt_fetch($post_stmt) && $post_author_id != $user_id) {
            $notification_text = "$username commented on your post";
            $notification_link = "post.php?id=$post_id#comment-$comment_id";
            
            create_notification($post_author_id, $notification_text, $notification_link, 'comment', $post_id, $user_id);
        }
        mysqli_stmt_close($post_stmt);
    }
    
    echo json_encode(['success' => true, 'comment_id' => $comment_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
}

if (!function_exists('create_notification')) {
    function create_notification($user_id, $content, $link, $type, $ref_id, $from_user_id) {
        global $conn;
        $query = "INSERT INTO notifications (user_id, content, link, type, ref_id, from_user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isssii", $user_id, $content, $link, $type, $ref_id, $from_user_id);
        mysqli_stmt_execute($stmt);
    }
}
?>
