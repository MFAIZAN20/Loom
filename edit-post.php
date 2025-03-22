<?php
$page_title = "Edit Post";
$page_js = "post.js";
include 'includes/header.php';

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Check if post ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$post_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get post details and check ownership
$post_query = "SELECT * FROM posts WHERE post_id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $post_query);
mysqli_stmt_bind_param($stmt, "ii", $post_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: index.php");
    exit;
}

$post = mysqli_fetch_assoc($result);

// Process post update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... existing POST handling code ...
}

// Get post tags
$tag_query = "SELECT t.name FROM tags t JOIN post_tags pt ON t.tag_id = pt.tag_id WHERE pt.post_id = ?";
$stmt = mysqli_prepare($conn, $tag_query);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$tag_result = mysqli_stmt_get_result($stmt);

$tags = [];
while ($tag = mysqli_fetch_assoc($tag_result)) {
    $tags[] = $tag['name'];
}
$tags_string = implode(',', $tags);
?>

<main class="container">
    <div class="edit-post-container">
        <div class="edit-post-header">
            <h1><i class="fas fa-edit"></i> Edit Post</h1>
            <p>Update your post details below</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form id="post-form" method="post" action="" class="edit-post-form">
            <div class="form-group">
                <label for="title">Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                <div class="char-counter">
                    <span id="titleCount"><?php echo strlen($post['title']); ?></span>/200
                </div>
            </div>

            <div class="form-group">
                <label for="content">Content <span class="required">*</span></label>
                <div class="editor-toolbar">
                    <button type="button" class="toolbar-btn" data-format="bold" title="Bold">
                        <i class="fas fa-bold"></i>
                    </button>
                    <button type="button" class="toolbar-btn" data-format="italic" title="Italic">
                        <i class="fas fa-italic"></i>
                    </button>
                    <button type="button" class="toolbar-btn" data-format="heading" title="Heading">
                        <i class="fas fa-heading"></i>
                    </button>
                    <button type="button" class="toolbar-btn" data-format="link" title="Link">
                        <i class="fas fa-link"></i>
                    </button>
                    <button type="button" class="toolbar-btn" data-format="list" title="List">
                        <i class="fas fa-list-ul"></i>
                    </button>
                    <button type="button" class="toolbar-btn" data-format="quote" title="Quote">
                        <i class="fas fa-quote-right"></i>
                    </button>
                </div>
                <textarea id="content" name="content" rows="10" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                <div class="markdown-hint">
                    <i class="fab fa-markdown"></i> Markdown supported
                    <a href="#" class="markdown-guide-link">View guide</a>
                </div>
            </div>

            <div class="form-group">
                <label for="category">Category <span class="required">*</span></label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <option value="general" <?php if ($post['category'] === 'general') echo 'selected'; ?>>General</option>
                    <option value="technology" <?php if ($post['category'] === 'technology') echo 'selected'; ?>>Technology</option>
                    <option value="programming" <?php if ($post['category'] === 'programming') echo 'selected'; ?>>Programming</option>
                    <option value="science" <?php if ($post['category'] === 'science') echo 'selected'; ?>>Science</option>
                    <option value="gaming" <?php if ($post['category'] === 'gaming') echo 'selected'; ?>>Gaming</option>
                    <option value="art" <?php if ($post['category'] === 'art') echo 'selected'; ?>>Art & Design</option>
                    <option value="music" <?php if ($post['category'] === 'music') echo 'selected'; ?>>Music</option>
                    <option value="movies" <?php if ($post['category'] === 'movies') echo 'selected'; ?>>Movies & TV</option>
                    <option value="books" <?php if ($post['category'] === 'books') echo 'selected'; ?>>Books & Literature</option>
                    <option value="sports" <?php if ($post['category'] === 'sports') echo 'selected'; ?>>Sports</option>
                    <option value="other" <?php if ($post['category'] === 'other') echo 'selected'; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="tag-input">Tags <span class="tag-limit">(Max 5)</span></label>
                <div class="tag-input-container">
                    <input type="text" id="tag-input" placeholder="Type a tag and press Enter or comma">
                    <div class="tag-suggestions">
                        <span class="suggestion-title">Popular Tags:</span>
                        <span class="suggestion-tag" data-tag="webdev">webdev</span>
                        <span class="suggestion-tag" data-tag="discussion">discussion</span>
                        <span class="suggestion-tag" data-tag="help">help</span>
                        <span class="suggestion-tag" data-tag="tutorial">tutorial</span>
                    </div>
                </div>
                <div id="tag-list" class="tag-list">
                    <?php foreach ($tags as $tag): ?>
                        <div class="tag-item" data-tag="<?php echo htmlspecialchars($tag); ?>">
                            <span class="tag-name"><?php echo htmlspecialchars($tag); ?></span>
                            <span class="tag-remove">&times;</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="tags" name="tags" value="<?php echo htmlspecialchars($tags_string); ?>">
                <div class="form-help">
                    <i class="fas fa-info-circle"></i>
                    Tags help others find your post. Add up to 5 relevant tags.
                </div>
            </div>

            <div class="action-buttons">
                <a href="post.php?id=<?php echo $post_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Post
                </button>
            </div>
        </form>
    </div>

    <!-- Preview panel -->
    <div class="post-preview-container">
        <div class="preview-header">
            <h3><i class="fas fa-eye"></i> Preview</h3>
            <button type="button" class="btn-refresh-preview">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        <div id="post-preview" class="post-preview">
            <div class="preview-placeholder">
                <i class="fas fa-file-alt"></i>
                <p>Your post preview will appear here</p>
                <button class="btn btn-sm btn-outline preview-now">Preview Now</button>
            </div>
        </div>
    </div>
</main>

<style>
/* Edit Post Page Styling */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem;
}

.edit-post-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-bottom: 2rem;
}

.edit-post-header {
    margin-bottom: 2rem;
    text-align: center;
}

.edit-post-header h1 {
    font-size: 1.75rem;
    color: #333;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.edit-post-header p {
    color: #666;
    font-size: 0.95rem;
}

/* Form styling */
.edit-post-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.required {
    color: #e74c3c;
    font-weight: normal;
}

.tag-limit {
    color: #777;
    font-weight: normal;
    font-size: 0.85rem;
}

/* Input fields */
.form-group input[type="text"],
.form-group select,
.form-group textarea {
    padding: 0.75rem 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    background: #f9f9f9;
    transition: all 0.3s ease;
}

.form-group input[type="text"]:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    outline: none;
    background: #fff;
}

/* Character counter */
.char-counter {
    font-size: 0.8rem;
    color: #777;
    text-align: right;
    margin-top: 0.25rem;
}

/* Editor toolbar */
.editor-toolbar {
    display: flex;
    gap: 0.25rem;
    padding: 0.5rem;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
}

.toolbar-btn {
    background: transparent;
    border: none;
    width: 2rem;
    height: 2rem;
    border-radius: 4px;
    cursor: pointer;
    color: #555;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.toolbar-btn:hover {
    background: #ddd;
    color: #333;
}

.toolbar-btn.active {
    background: #e2e6ea;
    color: #007bff;
}

.form-group textarea {
    min-height: 250px;
    resize: vertical;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
}

/* Markdown hint */
.markdown-hint {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #666;
}

.markdown-guide-link {
    color: #007bff;
    text-decoration: none;
    margin-left: auto;
}

.markdown-guide-link:hover {
    text-decoration: underline;
}

/* Tag styling */
.tag-input-container {
    position: relative;
}

#tag-input {
    width: 100%;
    padding-right: 2rem;
}

.tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.tag-item {
    background: #e8f4fd;
    color: #0366d6;
    border-radius: 20px;
    padding: 0.3rem 0.75rem;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: tagAppear 0.3s ease;
}

@keyframes tagAppear {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.tag-name {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tag-remove {
    cursor: pointer;
    font-size: 1.2rem;
    line-height: 0.8;
}

.tag-remove:hover {
    color: #0056b3;
}

.tag-suggestions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px dashed #ddd;
}

.suggestion-title {
    font-size: 0.8rem;
    color: #666;
}

.suggestion-tag {
    background: #f1f1f1;
    color: #444;
    border-radius: 20px;
    padding: 0.2rem 0.6rem;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.suggestion-tag:hover {
    background: #e8f4fd;
    color: #0366d6;
}

/* Form help text */
.form-help {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Action buttons */
.action-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #f1f1f1;
    color: #333;
}

.btn-secondary:hover {
    background: #ddd;
}

.btn i {
    font-size: 0.9rem;
}

/* Alert styling */
.alert {
    padding: 0.85rem 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-danger {
    background-color: #fff5f5;
    color: #e74c3c;
    border-left: 4px solid #e74c3c;
}

/* Preview panel */
.post-preview-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #eee;
}

.preview-header h3 {
    font-size: 1.2rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.btn-refresh-preview {
    background: transparent;
    border: none;
    color: #007bff;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.6rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.btn-refresh-preview:hover {
    background: rgba(0, 123, 255, 0.1);
}

.post-preview {
    min-height: 200px;
    border: 1px dashed #ddd;
    border-radius: 8px;
    padding: 1rem;
}

.preview-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 200px;
    color: #999;
    text-align: center;
    gap: 0.75rem;
}

.preview-placeholder i {
    font-size: 2.5rem;
    opacity: 0.4;
}

.btn-sm {
    font-size: 0.85rem;
    padding: 0.4rem 1rem;
}

.btn-outline {
    background: transparent;
    border: 1px solid #007bff;
    color: #007bff;
}

.btn-outline:hover {
    background: #f0f7ff;
}

/* Dark mode support */
body.dark-mode .edit-post-container,
body.dark-mode .post-preview-container {
    background: #222;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
}

body.dark-mode .edit-post-header h1 {
    color: #eee;
}

body.dark-mode .edit-post-header p {
    color: #bbb;
}

body.dark-mode .form-group label {
    color: #ddd;
}

body.dark-mode .form-group input[type="text"],
body.dark-mode .form-group select,
body.dark-mode .form-group textarea {
    background: #333;
    border-color: #444;
    color: #eee;
}

body.dark-mode .form-group input[type="text"]:focus,
body.dark-mode .form-group select:focus,
body.dark-mode .form-group textarea:focus {
    background: #383838;
    border-color: #007bff;
}

body.dark-mode .editor-toolbar {
    background: #333;
    border-color: #444;
}

body.dark-mode .toolbar-btn {
    color: #bbb;
}

body.dark-mode .toolbar-btn:hover {
    background: #444;
    color: #eee;
}

body.dark-mode .toolbar-btn.active {
    background: #2c3e50;
    color: #3498db;
}

body.dark-mode .tag-item {
    background: #2c3e50;
    color: #3498db;
}

body.dark-mode .suggestion-tag {
    background: #333;
    color: #bbb;
}

body.dark-mode .suggestion-tag:hover {
    background: #2c3e50;
    color: #3498db;
}

body.dark-mode .btn-secondary {
    background: #333;
    color: #eee;
}

body.dark-mode .btn-secondary:hover {
    background: #444;
}

body.dark-mode .post-preview {
    border-color: #444;
}

body.dark-mode .preview-header {
    border-color: #333;
}

/* Responsive design */
@media (min-width: 992px) {
    main.container {
        display: grid;
        grid-template-columns: 3fr 2fr;
        gap: 2rem;
    }
    
    .post-preview-container {
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }
}

@media (max-width: 767px) {
    .edit-post-container,
    .post-preview-container {
        padding: 1.5rem;
    }
    
    .action-buttons {
        flex-direction: column-reverse;
        gap: 1rem;
    }
    
    .action-buttons .btn {
        width: 100%;
        justify-content: center;
    }
    
    .editor-toolbar {
        overflow-x: auto;
        padding: 0.5rem;
    }
    
    .toolbar-btn {
        min-width: 2rem;
    }
    
    .post-preview-container {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counter for title
    const titleInput = document.getElementById('title');
    const titleCount = document.getElementById('titleCount');
    
    if (titleInput && titleCount) {
        titleInput.addEventListener('input', function() {
            const count = this.value.length;
            titleCount.textContent = count;
            
            if (count > 180) {
                titleCount.style.color = count > 200 ? '#e74c3c' : '#f39c12';
            } else {
                titleCount.style.color = '';
            }
        });
    }

    // Tag input handling
    const tagInput = document.getElementById('tag-input');
    const tagList = document.getElementById('tag-list');
    const tagsHiddenInput = document.getElementById('tags');
    const suggestionTags = document.querySelectorAll('.suggestion-tag');
    
    if (tagInput && tagList && tagsHiddenInput) {
        // Handle tag input
        tagInput.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ',') && this.value.trim()) {
                e.preventDefault();
                addTag(this.value.trim());
                this.value = '';
            }
        });
        
        // Handle tag removal
        tagList.addEventListener('click', function(e) {
            if (e.target.classList.contains('tag-remove')) {
                const tagItem = e.target.parentElement;
                const tag = tagItem.getAttribute('data-tag');
                tagItem.remove();
                updateHiddenField();
            }
        });
        
        // Handle tag suggestions
        suggestionTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const tagText = this.getAttribute('data-tag');
                addTag(tagText);
            });
        });
        
        function addTag(tag) {
            // Remove any commas
            tag = tag.replace(/,/g, '');
            
            // Check if tag already exists or if we've reached the limit
            const existingTags = tagList.querySelectorAll('.tag-item');
            let tagExists = false;
            
            existingTags.forEach(item => {
                if (item.getAttribute('data-tag').toLowerCase() === tag.toLowerCase()) {
                    tagExists = true;
                    item.classList.add('highlight');
                    setTimeout(() => item.classList.remove('highlight'), 1000);
                }
            });
            
            if (tagExists || existingTags.length >= 5) {
                if (existingTags.length >= 5) {
                    alert('Maximum 5 tags allowed');
                }
                return;
            }
            
            // Create new tag
            const tagElement = document.createElement('div');
            tagElement.className = 'tag-item';
            tagElement.setAttribute('data-tag', tag);
            tagElement.innerHTML = `
                <span class="tag-name">${tag}</span>
                <span class="tag-remove">&times;</span>
            `;
            
            tagList.appendChild(tagElement);
            updateHiddenField();
        }
        
        function updateHiddenField() {
            const tags = [];
            tagList.querySelectorAll('.tag-item').forEach(item => {
                tags.push(item.getAttribute('data-tag'));
            });
            tagsHiddenInput.value = tags.join(',');
        }
    }
    
    // Editor toolbar functionality
    const toolbarBtns = document.querySelectorAll('.toolbar-btn');
    const contentTextarea = document.getElementById('content');
    
    if (toolbarBtns.length && contentTextarea) {
        toolbarBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const format = this.getAttribute('data-format');
                const textarea = contentTextarea;
                
                // Get selection points
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const selectedText = textarea.value.substring(start, end);
                
                let insertion = '';
                
                switch(format) {
                    case 'bold':
                        insertion = `**${selectedText}**`;
                        break;
                    case 'italic':
                        insertion = `*${selectedText}*`;
                        break;
                    case 'heading':
                        insertion = `## ${selectedText}`;
                        break;
                    case 'link':
                        if (selectedText) {
                            insertion = `[${selectedText}](url)`;
                        } else {
                            insertion = '[Link Text](url)';
                        }
                        break;
                    case 'list':
                        insertion = `- ${selectedText}`;
                        break;
                    case 'quote':
                        insertion = `> ${selectedText}`;
                        break;
                }
                
                // Insert formatted text
                textarea.focus();
                const beforeText = textarea.value.substring(0, start);
                const afterText = textarea.value.substring(end);
                textarea.value = beforeText + insertion + afterText;
                
                // Set cursor position after insertion
                const cursorPos = start + insertion.length;
                textarea.setSelectionRange(cursorPos, cursorPos);
            });
        });
    }
    
    // Preview functionality
    const previewNowBtn = document.querySelector('.preview-now');
    const refreshPreviewBtn = document.querySelector('.btn-refresh-preview');
    const postPreview = document.getElementById('post-preview');
    
    if (previewNowBtn && postPreview) {
        previewNowBtn.addEventListener('click', updatePreview);
    }
    
    if (refreshPreviewBtn && postPreview) {
        refreshPreviewBtn.addEventListener('click', updatePreview);
    }
    
    function updatePreview() {
        const title = document.getElementById('title').value || 'Post Title';
        const content = document.getElementById('content').value || 'Post content will appear here...';
        
        // Simple markdown conversion (this is very basic)
        let formattedContent = content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // Bold
            .replace(/\*(.*?)\*/g, '<em>$1</em>') // Italic
            .replace(/## (.*?)$/gm, '<h2>$1</h2>') // H2
            .replace(/# (.*?)$/gm, '<h1>$1</h1>') // H1
            .replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2">$1</a>') // Links
            .replace(/^- (.*?)$/gm, '<li>$1</li>') // List items
            .replace(/^> (.*?)$/gm, '<blockquote>$1</blockquote>') // Quotes
            .replace(/\n\n/g, '<br><br>'); // Line breaks
        
        postPreview.innerHTML = `
            <div class="preview-post">
                <h1 class="preview-title">${title}</h1>
                <div class="preview-content">${formattedContent}</div>
            </div>
        `;
        
        // Add styles to preview
        const style = document.createElement('style');
        style.textContent = `
            .preview-post {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            }
            .preview-title {
                font-size: 1.5rem;
                margin-bottom: 1rem;
                color: #333;
            }
            .preview-content {
                font-size: 0.95rem;
                line-height: 1.6;
                color: #444;
            }
            .preview-content h1 { font-size: 1.4rem; margin: 1rem 0; }
            .preview-content h2 { font-size: 1.2rem; margin: 0.8rem 0; }
            .preview-content a { color: #0366d6; text-decoration: none; }
            .preview-content a:hover { text-decoration: underline; }
            .preview-content blockquote { 
                border-left: 3px solid #ddd; 
                padding-left: 1rem; 
                color: #666; 
                font-style: italic; 
            }
            .preview-content li { margin-left: 1.5rem; }
        `;
        postPreview.appendChild(style);
    }
});
</script>

<?php include 'includes/footer.php'; ?>