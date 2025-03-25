<?php
// Prevent PHP errors from breaking JSON output
error_reporting(0);
// Start output buffering to capture any unexpected output
ob_start();

// Use full path to init.php to ensure proper inclusion
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

// Capture any unexpected output
$unexpected_output = ob_get_clean();
// Start fresh buffer
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to comment', 'session_status' => session_status(), 'session_id' => session_id()]);
    exit;
}

if (!isset($_POST['post_id']) || !isset($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$post_id = (int) $_POST['post_id'];
$content = sanitize_input($_POST['content']);
$user_id = $_SESSION['user_id'];
$parent_id = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit;
}

$post_check = "SELECT * FROM posts WHERE post_id = ?";
$stmt = mysqli_prepare($conn, $post_check);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$post_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($post_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Post not found']);
    exit;
}

if ($parent_id !== null) {
    $parent_check = "SELECT * FROM comments WHERE comment_id = ? AND post_id = ?";
    $stmt = mysqli_prepare($conn, $parent_check);
    mysqli_stmt_bind_param($stmt, "ii", $parent_id, $post_id);
    mysqli_stmt_execute($stmt);
    $parent_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($parent_result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Parent comment not found']);
        exit;
    }
}

$insert_query = $parent_id === null ?
    "INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())" :
    "INSERT INTO comments (post_id, user_id, content, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())";

$stmt = mysqli_prepare($conn, $insert_query);

if ($parent_id === null) {
    mysqli_stmt_bind_param($stmt, "iis", $post_id, $user_id, $content);
} else {
    mysqli_stmt_bind_param($stmt, "iisi", $post_id, $user_id, $content, $parent_id);
}

if (mysqli_stmt_execute($stmt)) {
    $comment_id = mysqli_insert_id($conn);
    
    // Create notification for post owner if not self-commenting
    $post = mysqli_fetch_assoc($post_result);
    if ($post['user_id'] != $user_id) {
        $post_title = htmlspecialchars(substr($post['title'], 0, 50));
        if (strlen($post['title']) > 50) {
            $post_title .= '...';
        }
        $notification_content = $_SESSION['username'] . " commented on your post: \"$post_title\"";
        $notification_link = "post.php?id=$post_id#comment-$comment_id";
        
        // Assuming you have a create_notification function
        if (function_exists('create_notification')) {
            create_notification($post['user_id'], $notification_content, $notification_link, 'comment', $post_id, $user_id);
        }
    }
    
    // Create notification for parent comment owner if this is a reply
    if ($parent_id !== null) {
        $get_parent_user = "SELECT user_id, content FROM comments WHERE comment_id = ?";
        $stmt = mysqli_prepare($conn, $get_parent_user);
        mysqli_stmt_bind_param($stmt, "i", $parent_id);
        mysqli_stmt_execute($stmt);
        $parent_comment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($parent_comment && $parent_comment['user_id'] != $user_id) {
            $reply_preview = htmlspecialchars(substr($content, 0, 50));
            if (strlen($content) > 50) {
                $reply_preview .= '...';
            }
            $notification_content = $_SESSION['username'] . " replied to your comment: \"$reply_preview\"";
            $notification_link = "post.php?id=$post_id#comment-$comment_id";
            
            if (function_exists('create_notification')) {
                create_notification($parent_comment['user_id'], $notification_content, $notification_link, 'reply', $comment_id, $user_id);
            }
        }
    }
    
    // Get final output and discard
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully',
        'comment_id' => $comment_id
    ]);
} else {
    // Get final output and discard
    ob_end_clean();
    
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
?>