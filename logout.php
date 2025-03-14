<?php
require_once 'includes/auth_functions.php';

// Log the user out
logout_user();

// Redirect to home page
header("Location: index.php");
exit;
?>