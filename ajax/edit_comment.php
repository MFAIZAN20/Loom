<?php
// Prevent PHP notices and warnings from interfering with JSON response
ini_set('display_errors', 0);
error_reporting(0);

// Ensure we catch all output to prevent it from corrupting our JSON
ob_start();

try {
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
    
    // Discard any unexpected output from includes or session start
    ob_clean();
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        throw new Exception('You must be logged in');
    }
    
    if (!isset($_POST['comment_id'], $_POST['content']) || !is_numeric($_POST['comment_id'])) {
        throw new Exception('Invalid request parameters');
    }
    
    $comment_id = (int)$_POST['comment_id'];
    $content = sanitize_input($_POST['content']);
    $user_id = $_SESSION['user_id'];
    
    if (empty($content)) {
        throw new Exception('Comment content cannot be empty');
    }
    
    // Verify user owns the comment
    $check_query = "SELECT * FROM comments WHERE comment_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $comment_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        throw new Exception('You do not have permission to edit this comment');
    }
    
    // Update the comment - removed updated_at column since it doesn't exist
    $update_query = "UPDATE comments SET content = ? WHERE comment_id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "si", $content, $comment_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $formatted_content = nl2br(htmlspecialchars($content));
        
        // Discard any buffer content before our JSON output
        ob_end_clean();
        echo json_encode(['success' => true, 'content' => $formatted_content]);
    } else {
        throw new Exception('Failed to update comment: ' . mysqli_error($conn));
    }
} catch (Exception $e) {
    // Discard any buffer content before our JSON output
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>