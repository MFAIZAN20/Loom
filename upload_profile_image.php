<?php

require_once __DIR__ . '/includes/init.php';

if (!is_logged_in()) {
    safe_redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect('settings.php');
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['settings_error'] = 'Please select an image to upload.';
    safe_redirect('settings.php');
}

$file = $_FILES['profile_image'];

if ($file['size'] > 2 * 1024 * 1024) {
    $_SESSION['settings_error'] = 'File too large. Max size is 2MB.';
    safe_redirect('settings.php');
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($extension, $allowed_extensions, true)) {
    $_SESSION['settings_error'] = 'Invalid file type. Use JPG, PNG, GIF, or WEBP.';
    safe_redirect('settings.php');
}

$image_info = @getimagesize($file['tmp_name']);
if ($image_info === false) {
    $_SESSION['settings_error'] = 'Invalid image file.';
    safe_redirect('settings.php');
}

$upload_dir = __DIR__ . '/uploads/profile_pictures';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$user_id = (int)$_SESSION['user_id'];
$new_file_name = $user_id . '_' . time() . '.' . $extension;
$destination = $upload_dir . '/' . $new_file_name;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    $_SESSION['settings_error'] = 'Upload failed. Please try again.';
    safe_redirect('settings.php');
}

$update_query = "UPDATE users SET profile_image = ? WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "si", $new_file_name, $user_id);
mysqli_stmt_execute($stmt);

$_SESSION['profile_image'] = $new_file_name;
$_SESSION['settings_message'] = 'Profile photo updated successfully.';

safe_redirect('settings.php');

