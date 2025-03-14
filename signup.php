<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

$page_title = "Sign Up";
// Remove external CSS reference since we're using internal CSS
// $page_css = "login.css";
$page_js = "auth.js";
include 'includes/header.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

// Process signup form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Check if passwords match
    if ($password !== $_POST['confirm_password']) {
        $error = "Passwords do not match";
    } else if (register_user($username, $email, $password)) {
        // Log the user in
        login_user($username, $password);
        
        // Redirect to home page
        header("Location: index.php");
        exit;
    } else {
        $error = "Username or email already exists";
    }
}
?>

<!-- Adding comprehensive internal CSS for the signup page -->
<style>
:root {
    --primary-color: #4c8bf5;
    --accent-color: #6c5ce7;
    --text-primary: #2d3436;
    --text-secondary: #636e72;
    --border-color: #dfe6e9;
}

.auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f7fafd; /* Subtle background */
}

/* Form container styling */
.form-container {
    background-color: white;
    padding: 2.5rem;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 450px;
    animation: fadeInUp 0.5s ease-out;
}

.form-title {
    text-align: center;
    margin-bottom: 2rem;
    color: var(--primary-color);
    font-size: 2rem;
}

/* Form groups */
.form-group {
    margin-bottom: 1.5rem;
}

label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-control {
    width: 100%;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(76, 139, 245, 0.2);
}

/* Password field */
.password-field-container {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 5px;
}

/* Button Styles */
.btn {
    display: block;
    width: 100%;
    padding: 1rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    text-align: center;
    border-radius: 10px;
    color: var(--primary-color); /* Blue text */
    background: #fff; /* White background */
    border: 2px solid var(--primary-color); /* Blue border */
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(76, 139, 245, 0.25);
    margin-top: 0.5rem;
    position: relative;
    overflow: hidden;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(76, 139, 245, 0.35);
}

.btn::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.2), transparent);
    transform: translateX(-100%);
}

.btn:hover::after {
    animation: shine 1.5s infinite;
}

@keyframes shine {
    100% {
        transform: translateX(100%);
    }
}

/* Form Footer */
.form-footer {
    text-align: center;
    margin-top: 2rem;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.form-footer a {
    color: var(--primary-color);
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    margin-left: 5px;
}

.form-footer a:hover {
    color: var(--accent-color);
    text-decoration: none;
}

/* Alert Messages */
.alert {
    padding: 1rem 1.2rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-danger {
    background-color: #fff5f5;
    border-left: 4px solid #ff4d4f;
    color: #cf1322;
}

/* Divider */
.divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 2rem 0;
    color: var(--text-secondary);
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid var(--border-color);
}

.divider::before {
    margin-right: 1rem;
}

.divider::after {
    margin-left: 1rem;
}

/* Social Buttons */
.social-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.5rem;
}

.social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    background-color: white;
    transition: all 0.3s ease;
    color: var(--text-secondary);
    font-size: 1.2rem;
}

.social-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.social-btn.google:hover {
    color: #DB4437;
    border-color: #DB4437;
}

.social-btn.facebook:hover {
    color: #4267B2;
    border-color: #4267B2;
}

.social-btn.twitter:hover {
    color: #1DA1F2;
    border-color: #1DA1F2;
}

/* Form Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 576px) {
    .form-container {
        padding: 2rem 1.5rem;
    }
    
    .form-title {
        font-size: 1.8rem;
    }
}
</style>

<div class="auth-page">
    <div class="form-container">
        <h1 class="form-title">Create Account</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form id="signup-form" method="post" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required autocomplete="username"
                       value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="email"
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field-container">
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm-password">Confirm Password</label>
                <div class="password-field-container">
                    <input type="password" class="form-control" id="confirm-password" name="confirm_password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Create Account</button>
            </div>
            
            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Log in</a></p>
            </div>
            
            <div class="divider">or sign up with</div>
            
            <div class="social-buttons">
                <a href="#" class="social-btn google" aria-label="Sign up with Google">
                    <i class="fab fa-google"></i>
                </a>
                <a href="#" class="social-btn facebook" aria-label="Sign up with Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="social-btn twitter" aria-label="Sign up with Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const toggleButtons = document.querySelectorAll('.password-toggle');
    
    toggleButtons.forEach(function(toggleButton) {
        toggleButton.addEventListener('click', function() {
            const passwordField = this.previousElementSibling;
            
            // Toggle password visibility
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            // Toggle icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
// Flush output buffer at the end
ob_end_flush();
?>