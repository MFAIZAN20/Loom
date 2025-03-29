<?php
// Prevent PHP errors from breaking JSON output
error_reporting(0);
// Start output buffering to capture any unexpected output
ob_start();

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

// Capture any unexpected output
$unexpected_output = ob_get_clean();
// Start fresh buffer
ob_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in']);
    exit;
}

if (!isset($_POST['comment_id']) || !is_numeric($_POST['comment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
    exit;
}

$comment_id = (int)$_POST['comment_id'];
$user_id = $_SESSION['user_id'];

$check_query = "SELECT c.*, u.is_admin FROM comments c 
                LEFT JOIN users u ON u.user_id = ? 
                WHERE c.comment_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $comment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row || ($row['user_id'] != $user_id && !$row['is_admin'])) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this comment']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // First, handle reports that reference this comment
    $update_reports = "UPDATE reports SET resolved = 1 WHERE comment_id = ?";
    $stmt = mysqli_prepare($conn, $update_reports);
    mysqli_stmt_bind_param($stmt, "i", $comment_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update reports");
    }
    
    // Delete votes related to this comment
    $delete_votes = "DELETE FROM votes WHERE comment_id = ?";
    $stmt = mysqli_prepare($conn, $delete_votes);
    mysqli_stmt_bind_param($stmt, "i", $comment_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to delete comment votes");
    }
    
    // Finally delete the comment itself
    $delete_query = "DELETE FROM comments WHERE comment_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $comment_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to delete comment");
    }
    
    mysqli_commit($conn);
    
    // Discard any unexpected output
    ob_end_clean();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    
    // Discard any unexpected output
    ob_end_clean();
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>