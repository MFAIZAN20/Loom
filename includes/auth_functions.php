<?php
// All sessions are handled in init.php, so we don't need to start session here
// We will rely on init.php to start the session with proper security settings

// Register new user
function register_user($username, $email, $password) {
    global $conn;
    
    // Check if username/email already exists
    $check_user = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = mysqli_prepare($conn, $check_user);
    mysqli_stmt_bind_param($stmt, "ss", $username, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if ($user['username'] === $username) {
            return ['success' => false, 'message' => 'Username already exists'];
        } else {
            return ['success' => false, 'message' => 'Email already exists'];
        }
    }
    
    // Hash password and insert user
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $default_image = 'default-profile.jpg';
    $current_date = date('Y-m-d H:i:s');
    $sql = "INSERT INTO users (username, email, password_hash, profile_image, created_at) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssss", $username, $email, $password_hash, $default_image, $current_date);
    
    if (mysqli_stmt_execute($stmt)) {
        return ['success' => true, 'user_id' => mysqli_insert_id($conn)];
    } else {
        return ['success' => false, 'message' => 'Registration failed: ' . mysqli_error($conn)];
    }
}

// Login user
function login_user($username, $password) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Check if user is banned
        if ($user['is_banned']) {
            return ['success' => false, 'message' => 'Account suspended: ' . ($user['ban_reason'] ?: 'Please contact administration.')];
        }
        
        if (password_verify($password, $user['password_hash'])) {
            // Check if password needs rehash (if PHP's password_hash settings have changed)
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                // Update the hash
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $update = "UPDATE users SET password_hash = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update);
                mysqli_stmt_bind_param($stmt, "si", $new_hash, $user['user_id']);
                mysqli_stmt_execute($stmt);
            }
            
            // Use output buffering for session_regenerate_id to avoid "headers already sent" issues
            ob_start();
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            ob_end_flush();
            
            // Create session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;
            $_SESSION['karma'] = $user['karma'];
            $_SESSION['is_admin'] = $user['is_admin'] == 1;
            
            // Update last login time
            $update_login = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $update_login);
            mysqli_stmt_bind_param($stmt, "i", $user['user_id']);
            mysqli_stmt_execute($stmt);
            
            return ['success' => true];
        }
    }
    
    return ['success' => false, 'message' => 'Invalid username or password'];
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Get user by ID
function getUserById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Check if the current user is an admin
 * 
 * @return bool True if user is logged in and is an admin, false otherwise
 */
function is_admin() {
    if (!is_logged_in()) {
        return false;
    }
    
    // If we already have admin status in session, use it
    if (isset($_SESSION['is_admin'])) {
        return $_SESSION['is_admin'] === true;
    }
    
    // Otherwise check database
    global $conn;
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT is_admin FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $is_admin);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    // Cache the result in session
    $_SESSION['is_admin'] = $is_admin == 1;
    
    return $is_admin == 1;
}

/**
 * Log out the current user securely
 */
function logout_user() {
    // Remove all session variables
    $_SESSION = array();
    
    // Get session parameters 
    $params = session_get_cookie_params();
    
    // Delete the actual cookie
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
    
    // Destroy session
    session_destroy();
}

/**
 * Redirect non-logged in users to login page
 * 
 * @param string $redirect_url URL to redirect to after login (optional)
 */
function require_login($redirect_url = '') {
    if (!is_logged_in()) {
        if ($redirect_url) {
            $redirect = urlencode($redirect_url);
            safe_redirect("login.php?redirect=$redirect");
        } else {
            safe_redirect("login.php");
        }
        exit();
    }
}

/**
 * Redirect non-admin users to homepage
 */
function require_admin() {
    if (!is_admin()) {
        safe_redirect("index.php?error=unauthorized");
        exit();
    }
}
?>
