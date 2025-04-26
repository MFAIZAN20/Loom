<?php
session_start();
require_once 'db_connect.php';
require_once 'auth_functions.php';

$current_user = getUserById($_SESSION['user_id'] ?? 0);

if (!$current_user || !$current_user['is_admin']) {
    die("Access denied.");
}

if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);

    if ($user_id === $current_user['user_id']) {
        die("You cannot delete yourself.");
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: ../admin.php?message=User+deleted");
    exit;
}

echo "Invalid request.";
