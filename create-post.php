<?php
$page_title = "Create Post";
include 'includes/header.php';

// Check if user is logged in
if (!is_logged_in()) {
    safe_redirect('login.php');
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = sanitize_input($_POST['title']);
    $content = sanitize_input($_POST['content']);
    $category_id = (int)$_POST['category'];
    $user_id = $_SESSION['user_id'];
    
    // Get AI analysis results if available
    $karma_adjustment = isset($_POST['karma_adjustment']) ? (int)$_POST['karma_adjustment'] : 0;
    $analysis_data = isset($_POST['analysis_data']) ? $_POST['analysis_data'] : '';
    
    // Validate inputs
    if (empty($title) || empty($content) || empty($category_id)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert post into database
        $insert_query = "INSERT INTO posts (user_id, title, content, category_id, created_at) 
                         VALUES (?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "issi", $user_id, $title, $content, $category_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $post_id = mysqli_insert_id($conn);
            
            // Apply karma adjustment if available
            if (isset($karma_adjustment) && $karma_adjustment != 0) {
                // Update user karma
                $update_karma = "UPDATE users SET karma = karma + ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_karma);
                mysqli_stmt_bind_param($stmt, "ii", $karma_adjustment, $user_id);
                mysqli_stmt_execute($stmt);
                
                // Update session karma value
                if (isset($_SESSION['karma'])) {
                    $_SESSION['karma'] = (int)$_SESSION['karma'] + $karma_adjustment;
                } else {
                    // Fetch the current karma value from the database
                    $karma_query = "SELECT karma FROM users WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $karma_query);
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user_data = mysqli_fetch_assoc($result);
                    $_SESSION['karma'] = $user_data['karma'];
                }
                
                // Log karma change - fix the table columns
                $log_query = "INSERT INTO karma_log (user_id, content_type, content_id, karma_change, reason, details) 
                              VALUES (?, 'post', ?, ?, 'AI content analysis', ?)";
                $stmt = mysqli_prepare($conn, $log_query);
                mysqli_stmt_bind_param($stmt, "iiis", $user_id, $post_id, $karma_adjustment, $analysis_data);
                mysqli_stmt_execute($stmt);
                
                // Set success message with karma notice
                if ($karma_adjustment > 0) {
                    $success = "Post created successfully! You earned +$karma_adjustment karma points for quality content.";
                } elseif ($karma_adjustment < 0) {
                    $success = "Post created. Your content could be improved - $karma_adjustment karma points.";
                } else {
                    $success = "Post created successfully!";
                }
            } else {
                $success = "Post created successfully!";
            }
            
            // Redirect to the new post
            safe_redirect("post.php?id=$post_id");
        } else {
            $error = "Error creating post: " . mysqli_error($conn);
        }
    }
}

// Get categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
?>

<div class="create-post-page">
    <div class="container">
        <div class="card create-post-card">
            <div class="page-header text-center">
                <div class="header-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h1>Create a New Post</h1>
                <p class="lead">Share your thoughts, questions, or insights with the community</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form id="post-form" method="post" action="">
                <!-- Title field with icon -->
                <div class="form-group">
                    <label for="post-title">
                        <i class="fas fa-heading"></i> Title <span class="required">*</span>
                    </label>
                    <input type="text" id="post-title" name="title" class="form-control" required 
                           placeholder="Write a descriptive title">
                    <div class="form-help">Be specific and descriptive</div>
                </div>
                
                <!-- Category selection with icon -->
                <div class="form-group">
                    <label for="category">
                        <i class="fas fa-folder"></i> Category <span class="required">*</span>
                    </label>
                    <div class="select-wrapper">
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                <option value="<?php echo $category['category_id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                    <div class="form-help">Choose the most appropriate category for your post</div>
                </div>
                
                <!-- Content field with markdown toolbar -->
                <div class="form-group">
                    <label for="post-content">
                        <i class="fas fa-paragraph"></i> Content <span class="required">*</span>
                    </label>
                    <div class="markdown-toolbar">
                        <button type="button" class="toolbar-btn" data-format="bold" title="Bold">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-format="italic" title="Italic">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-format="heading" title="Heading">
                            <i class="fas fa-heading"></i>
                        </button>
                        <span class="toolbar-divider"></span>
                        <button type="button" class="toolbar-btn" data-format="link" title="Link">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-format="image" title="Image">
                            <i class="fas fa-image"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-format="code" title="Code">
                            <i class="fas fa-code"></i>
                        </button>
                        <span class="toolbar-divider"></span>
                        <button type="button" class="toolbar-btn" data-format="list-ul" title="Bulleted List">
                            <i class="fas fa-list-ul"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-format="list-ol" title="Numbered List">
                            <i class="fas fa-list-ol"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-format="quote" title="Quote">
                            <i class="fas fa-quote-right"></i>
                        </button>
                    </div>
                    <textarea id="post-content" name="content" class="form-control" rows="12" required
                              placeholder="Share your thoughts, questions, or insights here..."></textarea>
                    <div class="form-help">Provide all the details needed for others to understand your post</div>
                </div>
                
                <!-- AI analysis results will appear here -->
                <div id="post-analysis" class="post-analysis-container">
                    <div class="ai-status-indicator">
                        <div class="ai-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <div class="ai-message">
                            <span class="ai-title">AI Content Analyzer</span>
                            <span class="ai-status">Start typing to get content quality feedback</span>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden fields for AI analysis results -->
                <input type="hidden" id="karma-adjustment" name="karma_adjustment" value="0">
                <input type="hidden" id="analysis-data" name="analysis_data" value="">
                
                <!-- Submit buttons with better styling -->
                <div class="form-actions">
                    <button type="button" class="btn btn-light" onclick="history.back()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" id="post-submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Create Post
                    </button>
                </div>
            </form>
            
            <!-- Posting guidelines section -->
            <div class="posting-guidelines">
                <h3><i class="fas fa-lightbulb"></i> Posting Guidelines</h3>
                <div class="guidelines-content">
                    <div class="guideline-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Be respectful and constructive in your posts</span>
                    </div>
                    <div class="guideline-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Use clear, descriptive titles that summarize your post</span>
                    </div>
                    <div class="guideline-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Format your content for better readability</span>
                    </div>
                    <div class="guideline-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Choose the appropriate category for your post</span>
                    </div>
                    <div class="guideline-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Higher quality posts earn more karma points</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add CSS for modern styling -->
<style>
:root {
    --primary-color: #4f46e5;
    --primary-hover: #4338ca;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --light-bg: #f9fafb;
    --dark-bg: #111827;
    --card-bg: #ffffff;
    --border-color: #e5e7eb;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 0.5rem;
}

/* Main container styling */
.create-post-page {
    padding: 2rem 0;
    background-color: var(--light-bg);
    min-height: calc(100vh - 60px);
}

.create-post-card {
    background-color: var(--card-bg);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

/* Header styling */
.page-header {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.header-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 1rem;
    background-color: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    box-shadow: var(--shadow);
}

.page-header h1 {
    color: var(--text-primary);
    font-weight: 700;
    margin-bottom: 0.5rem;
    font-size: 1.8rem;
}

.page-header .lead {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

/* Form styling */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
}

.form-group label i {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.required {
    color: var(--danger-color);
    margin-left: 0.25rem;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    line-height: 1.5;
    color: var(--text-primary);
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: 0;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
}

.form-help {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* Select dropdown styling */
.select-wrapper {
    position: relative;
}

.select-arrow {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    pointer-events: none;
}

select.form-control {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 2.5rem;
}

/* Markdown toolbar */
.markdown-toolbar {
    display: flex;
    flex-wrap: wrap;
    background-color: var(--light-bg);
    border: 1px solid var(--border-color);
    border-bottom: none;
    border-top-left-radius: var(--radius);
    border-top-right-radius: var(--radius);
    padding: 0.5rem;
}

.toolbar-btn {
    background: transparent;
    border: none;
    color: var(--text-secondary);
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.toolbar-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    color: var(--primary-color);
}

.toolbar-divider {
    width: 1px;
    background-color: var(--border-color);
    margin: 0 0.5rem;
    align-self: stretch;
}

textarea#post-content {
    min-height: 200px;
    resize: vertical;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
}

/* Form actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-weight: 500;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: var(--radius);
    transition: all 0.2s ease;
    border: none;
}

.btn-primary {
    color: #fff;
    background-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.btn-light {
    color: var(--text-primary);
    background-color: var(--light-bg);
}

.btn-light:hover {
    background-color: #e5e7eb;
}

/* Alert styling */
.alert {
    position: relative;
    padding: 1rem 1rem;
    margin-bottom: 1.5rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert i {
    font-size: 1.25rem;
}

.alert-danger {
    color: #842029;
    background-color: #f8d7da;
    border-left: 4px solid var(--danger-color);
}

.alert-success {
    color: #0f5132;
    background-color: #d1e7dd;
    border-left: 4px solid var(--success-color);
}

/* AI analysis container styling */
.post-analysis-container {
    margin: 1.5rem 0;
    transition: all 0.3s ease;
}

.ai-status-indicator {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--radius);
    background-color: var(--light-bg);
    border: 1px dashed var(--border-color);
}

.ai-icon {
    width: 40px;
    height: 40px;
    background-color: rgba(79, 70, 229, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.ai-message {
    display: flex;
    flex-direction: column;
}

.ai-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.ai-status {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Content Analysis Styles */
.content-analysis {
    border-radius: var(--radius);
    padding: 1.25rem;
    background-color: var(--light-bg);
    border-left: 4px solid var(--border-color);
    margin: 1.25rem 0;
}

.content-analysis.positive {
    border-left-color: var(--success-color);
    background-color: rgba(16, 185, 129, 0.05);
}

.content-analysis.negative {
    border-left-color: var(--danger-color);
    background-color: rgba(239, 68, 68, 0.05);
}

.content-analysis.neutral {
    border-left-color: var(--primary-color);
    background-color: rgba(79, 70, 229, 0.05);
}

.analysis-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.analysis-header i {
    font-size: 1.25rem;
}

.positive .analysis-header i {
    color: var(--success-color);
}

.negative .analysis-header i {
    color: var(--danger-color);
}

.neutral .analysis-header i {
    color: var(--primary-color);
}

.analysis-header h4 {
    margin: 0;
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.analysis-summary {
    margin-bottom: 1rem;
    line-height: 1.6;
    color: var(--text-primary);
}

.karma-prediction {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    font-weight: 500;
    color: var(--text-primary);
}

.karma-value {
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.karma-value.positive {
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
}

.karma-value.negative {
    background-color: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
}

.karma-value.neutral {
    background-color: rgba(79, 70, 229, 0.1);
    color: var(--primary-color);
}

/* Metrics section */
.metrics-section {
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.metrics-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}

.metrics-title {
    font-weight: 600;
    margin: 0;
    font-size: 1rem;
    color: var(--text-primary);
}

.metrics-content {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease;
}

.metrics-content.show {
    max-height: 500px;
}

.metric-row {
    margin: 1rem 0;
}

.metric-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.metric-name {
    color: var(--text-secondary);
}

.metric-value {
    font-weight: 600;
    color: var(--text-primary);
}

.metric-bar {
    height: 8px;
    background-color: rgba(0,0,0,0.05);
    border-radius: 4px;
    overflow: hidden;
}

.metric-fill {
    height: 100%;
    border-radius: 4px;
    width: 0;
    transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.quality-fill { background-color: var(--primary-color); }
.readability-fill { background-color: #8b5cf6; }
.sentiment-fill { background-color: #ec4899; }
.originality-fill { background-color: #f97316; }

/* Posting guidelines */
.posting-guidelines {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.posting-guidelines h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-primary);
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.posting-guidelines h3 i {
    color: var(--warning-color);
}

.guidelines-content {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.guideline-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.guideline-item i {
    color: var(--success-color);
}

.guideline-item span {
    color: var(--text-secondary);
    font-size: 0.95rem;
    line-height: 1.5;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --light-bg: #1f2937;
        --card-bg: #111827;
        --border-color: #374151;
        --text-primary: #f9fafb;
        --text-secondary: #d1d5db;
        --text-light: #9ca3af;
    }
    
    .form-control {
        background-color: #1f2937;
        color: #f9fafb;
        border-color: #374151;
    }
    
    .form-control:focus {
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.4);
    }
    
    .btn-light {
        background-color: #374151;
        color: #f9fafb;
    }
    
    .btn-light:hover {
        background-color: #4b5563;
    }
    
    .markdown-toolbar {
        background-color: #1f2937;
    }
    
    .toolbar-btn:hover {
        background-color: #374151;
    }
    
    .ai-status-indicator {
        background-color: #1f2937;
        border-color: #374151;
    }
    
    .content-analysis {
        background-color: #1f2937;
    }
    
    .content-analysis.positive {
        background-color: rgba(16, 185, 129, 0.1);
    }
    
    .content-analysis.negative {
        background-color: rgba(239, 68, 68, 0.1);
    }
    
    .content-analysis.neutral {
        background-color: rgba(79, 70, 229, 0.1);
    }
    
    .metric-bar {
        background-color: rgba(255,255,255,0.1);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .create-post-card {
        padding: 1.5rem;
        border-radius: 0;
        box-shadow: none;
    }
    
    .header-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column-reverse;
        gap: 0.75rem;
    }
    
    .btn {
        width: 100%;
    }
    
    .markdown-toolbar {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    .toolbar-btn {
        width: 32px;
        height: 32px;
    }
    
    .guidelines-content {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JavaScript for text editor functionality and AI analysis -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const titleField = document.getElementById('post-title');
    const contentField = document.getElementById('post-content');
    const analysisContainer = document.getElementById('post-analysis');
    const karmaField = document.getElementById('karma-adjustment');
    const analysisDataField = document.getElementById('analysis-data');
    const toolbarButtons = document.querySelectorAll('.toolbar-btn');
    
    // Initialize Sentiment Analysis if available
    let sentiment = window.Sentiment ? new Sentiment() : null;
    
    // Format text based on toolbar buttons
    toolbarButtons.forEach(button => {
        button.addEventListener('click', function() {
            const formatType = this.getAttribute('data-format');
            
            // Get selection
            const start = contentField.selectionStart;
            const end = contentField.selectionEnd;
            const selectedText = contentField.value.substring(start, end);
            
            let formattedText;
            let cursorPosition;
            
            // Apply formatting based on button type
            switch(formatType) {
                case 'bold':
                    formattedText = `**${selectedText}**`;
                    cursorPosition = selectedText.length ? end + 4 : start + 2;
                    break;
                case 'italic':
                    formattedText = `*${selectedText}*`;
                    cursorPosition = selectedText.length ? end + 2 : start + 1;
                    break;
                case 'heading':
                    formattedText = `\n## ${selectedText}`;
                    cursorPosition = selectedText.length ? end + 4 : start + 4;
                    break;
                case 'link':
                    formattedText = `[${selectedText}](url)`;
                    cursorPosition = selectedText.length ? end + 6 : start + 1;
                    break;
                case 'image':
                    formattedText = `![${selectedText}](image-url)`;
                    cursorPosition = selectedText.length ? end + 12 : start + 2;
                    break;
                case 'code':
                    if (selectedText.includes('\n')) {
                        formattedText = `\`\`\`\n${selectedText}\n\`\`\``;
                        cursorPosition = end + 8;
                    } else {
                        formattedText = `\`${selectedText}\``;
                        cursorPosition = selectedText.length ? end + 2 : start + 1;
                    }
                    break;
                case 'list-ul':
                    if (selectedText.includes('\n')) {
                        formattedText = selectedText.split('\n').map(line => `- ${line}`).join('\n');
                    } else {
                        formattedText = `- ${selectedText}`;
                    }
                    cursorPosition = selectedText.length ? formattedText.length : start + 2;
                    break;
                case 'list-ol':
                    if (selectedText.includes('\n')) {
                        formattedText = selectedText.split('\n').map((line, i) => `${i+1}. ${line}`).join('\n');
                    } else {
                        formattedText = `1. ${selectedText}`;
                    }
                    cursorPosition = selectedText.length ? formattedText.length : start + 3;
                    break;
                case 'quote':
                    if (selectedText.includes('\n')) {
                        formattedText = selectedText.split('\n').map(line => `> ${line}`).join('\n');
                    } else {
                        formattedText = `> ${selectedText}`;
                    }
                    cursorPosition = selectedText.length ? formattedText.length : start + 2;
                    break;
                default:
                    return;
            }
            
            // Insert the formatted text
            contentField.focus();
            document.execCommand('insertText', false, formattedText);
            
            // Set cursor position
            if (!selectedText.length) {
                contentField.selectionStart = cursorPosition;
                contentField.selectionEnd = cursorPosition;
            }
            
            // Trigger content analysis
            analyzeContent();
        });
    });
    
    // AI Content Analysis
    let analysisTimeout = null;
    
    function analyzeContent() {
        // Clear any existing timeout
        if (analysisTimeout) {
            clearTimeout(analysisTimeout);
        }
        
        // Set a timeout to prevent analysis on every keystroke
        analysisTimeout = setTimeout(() => {
            const title = titleField.value.trim();
            const content = contentField.value.trim();
            
            // Skip if content is too short
            if (title.length < 5 || content.length < 20) {
                showInitialState();
                return;
            }
            
            // Show loading state
            showLoadingState();
            
            // Simulate AI analysis (replace with actual sentiment analysis if available)
            setTimeout(() => {
                // Use sentiment library if available, otherwise simulate
                const results = performContentAnalysis(title, content);
                showAnalysisResults(results);
                
                // Update hidden fields with analysis results
                karmaField.value = results.karma_change;
                analysisDataField.value = JSON.stringify(results);
            }, 800);
        }, 1000);
    }
    
    // Perform content analysis with basic metrics
    function performContentAnalysis(title, content) {
        // Combine title and content
        const fullText = `${title} ${content}`;
        
        // Calculate basic metrics
        const wordCount = content.split(/\s+/).filter(w => w.trim().length > 0).length;
        const sentenceCount = content.split(/[.!?]+/).filter(s => s.trim().length > 0).length;
        const avgWordLength = calculateAvgWordLength(content);
        const uniqueWordRatio = calculateUniqueWordRatio(content);
        
        // Calculate sentiment if library is available
        let sentimentScore = 0.5; // Neutral default
        if (sentiment) {
            const result = sentiment.analyze(fullText);
            // Normalize to 0-1 range (from -5 to 5 comparative score)
            sentimentScore = (result.comparative + 5) / 10;
        } else {
            // Simple sentiment estimation based on positive/negative word counts
            const positiveWords = ['good', 'great', 'excellent', 'best', 'amazing', 'wonderful', 'fantastic', 'helpful', 'useful'];
            const negativeWords = ['bad', 'worst', 'terrible', 'poor', 'awful', 'horrible', 'useless', 'disappointing'];
            
            const words = fullText.toLowerCase().split(/\s+/);
            const positiveCount = words.filter(word => positiveWords.includes(word)).length;
            const negativeCount = words.filter(word => negativeWords.includes(word)).length;
            
            if (positiveCount + negativeCount > 0) {
                sentimentScore = 0.5 + ((positiveCount - negativeCount) / (positiveCount + negativeCount)) * 0.5;
            }
        }
        
        // Calculate readability score (0-1)
        const readabilityScore = calculateReadabilityScore(content, wordCount, sentenceCount);
        
        // Calculate originality score based on unique words ratio and complexity
        const originalityScore = uniqueWordRatio * 0.7 + (avgWordLength / 8) * 0.3;
        
        // Calculate overall quality score (weighted average)
        const lengthWeight = 0.3;
        const readabilityWeight = 0.3;
        const sentimentWeight = 0.2;
        const originalityWeight = 0.2;
        
        const lengthScore = Math.min(wordCount / 500, 1); // Cap at 500 words
        
        const qualityScore = (lengthScore * lengthWeight) +
                             (readabilityScore * readabilityWeight) +
                             (sentimentScore * sentimentWeight) +
                             (originalityScore * originalityWeight);
        
        // Determine karma adjustment based on quality score
        let karmaChange = 0;
        let qualityLevel = '';
        let feedbackSummary = '';
        
        if (qualityScore >= 0.8) {
            karmaChange = 10;
            qualityLevel = 'excellent';
            feedbackSummary = "Excellent post! Your content is well-written, informative, and adds significant value to the community.";
        } else if (qualityScore >= 0.65) {
            karmaChange = 5;
            qualityLevel = 'good';
            feedbackSummary = "Good post! Your content is clear, helpful, and contributes positively to the discussion.";
        } else if (qualityScore >= 0.5) {
            karmaChange = 2;
            qualityLevel = 'average';
            feedbackSummary = "Solid post. Your content meets our community standards.";
        } else if (qualityScore >= 0.35) {
            karmaChange = 0;
            qualityLevel = 'below average';
            feedbackSummary = "Your post could be improved. Consider adding more details or clarifying your points.";
        } else {
            karmaChange = -2;
            qualityLevel = 'poor';
            feedbackSummary = "This post needs significant improvement. Try adding more substance and detail to your content.";
        }
        
        // Adjust for very short content
        if (wordCount < 50) {
            karmaChange = Math.min(karmaChange, 1);
            feedbackSummary = "Your post is quite short. Consider adding more details for better engagement.";
        }
        
        // Generate improvement tips
        const improvementTips = generateImprovementTips(
            wordCount,
            readabilityScore,
            sentimentScore,
            originalityScore
        );
        
        return {
            success: true,
            karma_change: karmaChange,
            quality_level: qualityLevel,
            quality_score: qualityScore,
            feedback_summary: feedbackSummary,
            metrics: {
                word_count: wordCount,
                sentence_count: sentenceCount,
                avg_word_length: avgWordLength,
                readability: readabilityScore,
                sentiment: sentimentScore,
                originality: originalityScore
            },
            improvement_tips: improvementTips
        };
    }
    
    // Helper functions for content analysis
    function calculateAvgWordLength(text) {
        const words = text.match(/\b[a-z0-9]+\b/gi) || [];
        if (words.length === 0) return 0;
        
        const totalLength = words.reduce((sum, word) => sum + word.length, 0);
        return totalLength / words.length;
    }
    
    function calculateUniqueWordRatio(text) {
        const words = text.toLowerCase().match(/\b[a-z0-9]+\b/gi) || [];
        if (words.length === 0) return 0;
        
        const uniqueWords = new Set(words);
        return uniqueWords.size / words.length;
    }
    
    function calculateReadabilityScore(text, wordCount, sentenceCount) {
        if (wordCount === 0 || sentenceCount === 0) return 0.5;
        
        // Simplified readability calculation
        const wordsPerSentence = wordCount / sentenceCount;
        
        // Higher number of words per sentence can indicate complexity
        // We normalize to a 0-1 scale where moderate complexity (10-20 words per sentence) is ideal
        if (wordsPerSentence < 5) {
            return 0.4; // Too simple
        } else if (wordsPerSentence < 10) {
            return 0.7; // Good simplicity
        } else if (wordsPerSentence < 20) {
            return 0.9; // Ideal complexity
        } else if (wordsPerSentence < 25) {
            return 0.7; // Getting complex
        } else {
            return 0.5; // Too complex
        }
    }
    
    function generateImprovementTips(wordCount, readabilityScore, sentimentScore, originalityScore) {
        const tips = [];
        
        if (wordCount < 100) {
            tips.push("Add more details to make your post more comprehensive");
        }
        
        if (readabilityScore < 0.4) {
            tips.push("Consider using shorter sentences for better readability");
        } else if (readabilityScore > 0.9 && wordCount > 100) {
            tips.push("Your writing is very simple. Adding some complexity might engage readers more");
        }
        
        if (sentimentScore < 0.3) {
            tips.push("Your post has a negative tone. Consider a more balanced approach");
        } else if (sentimentScore > 0.8) {
            tips.push("While positive, ensure your content remains objective and not overly promotional");
        }
        
        if (originalityScore < 0.4) {
            tips.push("Try using more varied vocabulary to make your content more engaging");
        }
        
        // If no specific improvements needed, offer general advice
        if (tips.length === 0) {
            tips.push("Consider adding examples or references to strengthen your points");
        }
        
        return tips;
    }
    
    // Show initial state
    function showInitialState() {
        analysisContainer.innerHTML = `
            <div class="ai-status-indicator">
                <div class="ai-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="ai-message">
                    <span class="ai-title">AI Content Analyzer</span>
                    <span class="ai-status">Start typing to get content quality feedback</span>
                </div>
            </div>
        `;
    }
    
    // Show loading state
    function showLoadingState() {
        analysisContainer.innerHTML = `
            <div class="ai-status-indicator">
                <div class="ai-icon">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <div class="ai-message">
                    <span class="ai-title">AI Content Analyzer</span>
                    <span class="ai-status">Analyzing your content...</span>
                </div>
            </div>
        `;
    }
    
    // Show analysis results
    function showAnalysisResults(analysis) {
        // Determine color class based on karma
        let colorClass = 'neutral';
        let iconClass = 'fa-brain';
        
        if (analysis.karma_change > 0) {
            colorClass = 'positive';
            iconClass = 'fa-check-circle';
        } else if (analysis.karma_change < 0) {
            colorClass = 'negative';
            iconClass = 'fa-exclamation-circle';
        }
        
        // Format metrics for display
        const metrics = analysis.metrics;
        
        // Create analysis HTML
        const analysisHTML = `
            <div class="content-analysis ${colorClass}">
                <div class="analysis-header">
                    <i class="fas ${iconClass}"></i>
                    <h4>AI Content Analysis</h4>
                </div>
                
                <div class="analysis-summary">
                    ${analysis.feedback_summary}
                </div>
                
                <div class="karma-prediction">
                    Estimated karma adjustment: 
                    <span class="karma-value ${colorClass}">
                        ${analysis.karma_change >= 0 ? '+' : ''}${analysis.karma_change}
                    </span>
                </div>
                
                <div class="metrics-section">
                    <div class="metrics-toggle" id="metricsToggle">
                        <h5 class="metrics-title">Content Quality Metrics</h5>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    
                    <div class="metrics-content" id="metricsContent">
                        <div class="metric-row">
                            <div class="metric-label">
                                <span class="metric-name">Overall Quality</span>
                                <span class="metric-value">${Math.round(analysis.quality_score * 100)}%</span>
                            </div>
                            <div class="metric-bar">
                                <div class="metric-fill quality-fill" data-width="${Math.round(analysis.quality_score * 100)}%"></div>
                            </div>
                        </div>
                        
                        <div class="metric-row">
                            <div class="metric-label">
                                <span class="metric-name">Readability</span>
                                <span class="metric-value">${Math.round(metrics.readability * 100)}%</span>
                            </div>
                            <div class="metric-bar">
                                <div class="metric-fill readability-fill" data-width="${Math.round(metrics.readability * 100)}%"></div>
                            </div>
                        </div>
                        
                        <div class="metric-row">
                            <div class="metric-label">
                                <span class="metric-name">Positivity</span>
                                <span class="metric-value">${Math.round(metrics.sentiment * 100)}%</span>
                            </div>
                            <div class="metric-bar">
                                <div class="metric-fill sentiment-fill" data-width="${Math.round(metrics.sentiment * 100)}%"></div>
                            </div>
                        </div>
                        
                        <div class="metric-row">
                            <div class="metric-label">
                                <span class="metric-name">Originality</span>
                                <span class="metric-value">${Math.round(metrics.originality * 100)}%</span>
                            </div>
                            <div class="metric-bar">
                                <div class="metric-fill originality-fill" data-width="${Math.round(metrics.originality * 100)}%"></div>
                            </div>
                        </div>
                        
                        <div class="metrics-stats">
                            <p>Word count: ${metrics.word_count} • Sentences: ${metrics.sentence_count}</p>
                        </div>
                        
                        <div class="metrics-tips">
                            <h6>Tips for Improvement:</h6>
                            <ul>
                                ${analysis.improvement_tips.map(tip => `<li>${tip}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Update the analysis container
        analysisContainer.innerHTML = analysisHTML;
        
        // Animate metric bars
        setTimeout(() => {
            document.querySelectorAll('.metric-fill').forEach(bar => {
                bar.style.width = bar.getAttribute('data-width');
            });
        }, 100);
        
        // Add toggle functionality for metrics
        const metricsToggle = document.getElementById('metricsToggle');
        const metricsContent = document.getElementById('metricsContent');
        
        if (metricsToggle && metricsContent) {
            metricsToggle.addEventListener('click', () => {
                metricsContent.classList.toggle('show');
                metricsToggle.querySelector('i').classList.toggle('fa-chevron-down');
                metricsToggle.querySelector('i').classList.toggle('fa-chevron-up');
            });
        }
    }
    
    // Listen for changes in content and analyze
    titleField.addEventListener('input', analyzeContent);
    contentField.addEventListener('input', analyzeContent);
    
    // Form validation before submission
    document.getElementById('post-form').addEventListener('submit', function(e) {
        const title = titleField.value.trim();
        const content = contentField.value.trim();
        const category = document.getElementById('category').value;
        
        let isValid = true;
        
        if (!title || title.length < 5) {
            alert('Please enter a title with at least 5 characters.');
            isValid = false;
        } else if (!content || content.length < 20) {
            alert('Please enter content with at least 20 characters.');
            isValid = false;
        } else if (!category) {
            alert('Please select a category for your post.');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>