<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['notification_id'])) {
    $notification_id = (int) $_POST['notification_id'];
    
    $check_query = "SELECT * FROM notifications WHERE notification_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Notification not found or access denied']);
        exit;
    }
    
    if (mark_notification_read($notification_id, $user_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
    }
} elseif (isset($_POST['mark_all'])) {
    $mark_all_query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $mark_all_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark all notifications as read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
function mark_notification_read($notification_id, $user_id) {
    global $conn;
    
    $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
    
    return mysqli_stmt_execute($stmt);
}
if (isset($conn)) {
    mysqli_close($conn);
}
?>