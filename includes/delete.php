<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'], $_POST['csrf_token'])) {
    $post_id = intval($_POST['post_id']);
    $user_id = $_SESSION['user_id'];

    if (!verify_csrf_token($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    if (delete_post($post_id, $user_id)) {
        header("Location: ../index.php?message=Post+deleted");
    } else {
        echo "You are not authorized to delete this post.";
    }
} else {
    echo "Invalid request.";
}
