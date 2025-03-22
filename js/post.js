document.addEventListener('DOMContentLoaded', function() {
    // Handle post form submission
    const postForm = document.getElementById('post-form');
    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            if (!validatePostForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Handle comment form submission
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            if (!validateCommentForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Initialize tag handling
    initTags();
    
    // Initialize Reddit-style comment functionality
    initCommentFunctionality();
});

// Initialize all comment-related functionality
function initCommentFunctionality() {
    console.log('Initializing comment functionality');
    
    // Set up collapse buttons
    initCollapseButtons();
    
    // Set up voting functionality
    initVoteHandlers();
    
    // Set up reply functionality
    initReplyButtons();
    
    // Set up edit and delete functionality
    initCommentActions();
}

// Handle comment collapse functionality
function initCollapseButtons() {
    const collapseButtons = document.querySelectorAll('.collapse-btn');
    console.log('Found collapse buttons:', collapseButtons.length);
    
    collapseButtons.forEach(button => {
        button.addEventListener('click', function() {
            const comment = this.closest('.comment');
            comment.classList.toggle('comment-collapsed');
            this.textContent = comment.classList.contains('comment-collapsed') ? '+' : '−';
        });
    });
}

// Handle comment voting
function initVoteHandlers() {
    const voteButtons = document.querySelectorAll('.vote-btn');
    console.log('Found vote buttons:', voteButtons.length);
    
    voteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const isLoggedIn = document.body.classList.contains('logged-in');
            if (!isLoggedIn) {
                window.location.href = 'login.php';
                return;
            }
            
            // Find the closest comment or post container
            const comment = this.closest('.comment');
            let commentId = null;
            let postId = null;
            let voteCount, upvoteBtn, downvoteBtn;
            
            if (comment) {
                // This is a comment vote
                commentId = comment.dataset.commentId;
                voteCount = comment.querySelector('.vote-count');
                upvoteBtn = comment.querySelector('.vote-up');
                downvoteBtn = comment.querySelector('.vote-down');
                console.log('Voting on comment:', commentId, 'Vote type:', this.dataset.vote);
            } else {
                // This might be a post vote
                const post = this.closest('.post-card');
                if (post) {
                    postId = post.dataset.postId;
                    voteCount = post.querySelector('.vote-count');
                    upvoteBtn = post.querySelector('.vote-up');
                    downvoteBtn = post.querySelector('.vote-down');
                    console.log('Voting on post:', postId, 'Vote type:', this.dataset.vote);
                }
            }
            
            // If neither comment nor post found, exit
            if (!commentId && !postId) {
                console.error('Could not find comment or post to vote on');
                return;
            }
            
            const voteType = this.dataset.vote;
            
            // Create FormData for more reliable submission
            const formData = new FormData();
            if (commentId) formData.append('comment_id', commentId);
            if (postId) formData.append('post_id', postId);
            formData.append('vote_type', voteType);
            
            // Call AJAX to vote
            fetch('ajax/vote.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // Important for session cookies
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Vote response:', data);
                
                if (data && data.success) {
                    // Update vote count - handle both response formats
                    if (voteCount) {
                        voteCount.textContent = data.vote_count || data.voteCount || "0";
                    }
                    
                    // Always clear both vote button states first
                    if (upvoteBtn) upvoteBtn.classList.remove('upvoted');
                    if (downvoteBtn) downvoteBtn.classList.remove('downvoted');
                    
                    // Only add active class if there is a non-zero user_vote
                    const userVote = data.user_vote !== undefined ? data.user_vote : data.newVoteType;
                    
                    if (userVote === 1) {
                        if (upvoteBtn) upvoteBtn.classList.add('upvoted');
                    } else if (userVote === -1) {
                        if (downvoteBtn) downvoteBtn.classList.add('downvoted');
                    } else {
                        // user_vote is 0, keep both buttons in neutral state
                        console.log('Vote cleared to neutral state');
                    }
                    
                    // Show success notification with appropriate message
                    if (userVote === 0) {
                        showNotification('Vote removed', 'success');
                    } else {
                        showNotification('Vote recorded successfully', 'success');
                    }
                } else {
                    showNotification(data.message || 'An error occurred while voting', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to process vote. Please try again.', 'error');
            });
        });
    });
}

// Handle reply buttons
function initReplyButtons() {
    const replyButtons = document.querySelectorAll('.reply-btn');
    console.log('Found reply buttons:', replyButtons.length);
    
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const isLoggedIn = document.body.classList.contains('logged-in');
            if (!isLoggedIn) {
                window.location.href = 'login.php';
                return;
            }
            
            const comment = this.closest('.comment');
            const commentId = comment.dataset.commentId;
            console.log('Replying to comment:', commentId);
            
            // Check if reply form already exists
            let replyForm = comment.querySelector('.reply-form');
            
            if (replyForm) {
                // Toggle form visibility
                replyForm.remove();
                return;
            }
            
            // Create reply form
            replyForm = document.createElement('div');
            replyForm.className = 'reply-form comment-form-container';
            replyForm.innerHTML = `
                <form method="post" class="reply-comment-form">
                    <input type="hidden" name="parent_id" value="${commentId}">
                    <div class="form-group">
                        <textarea class="comment-textarea" name="comment" placeholder="Write a reply..." required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-reply-btn">Cancel</button>
                        <button type="submit" class="comment-submit-btn">Reply</button>
                    </div>
                </form>
            `;
            
            // Add form to comment
            comment.appendChild(replyForm);
            
            // Focus on textarea
            replyForm.querySelector('textarea').focus();
            
            // Handle cancel button
            replyForm.querySelector('.cancel-reply-btn').addEventListener('click', () => {
                replyForm.remove();
            });
            
            // Handle form submission
            replyForm.querySelector('form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const textarea = this.querySelector('textarea');
                const commentContent = textarea.value.trim();
                
                if (!commentContent) {
                    showNotification('Comment cannot be empty', 'error');
                    return;
                }
                
                // Find the post ID - first try the post-card, then try data attribute on comments-section
                let postId;
                const postCard = document.querySelector('.post-card');
                const commentsSection = document.querySelector('.comments-section');
                
                if (postCard && postCard.dataset.postId) {
                    postId = postCard.dataset.postId;
                } else if (commentsSection && commentsSection.dataset.postId) {
                    postId = commentsSection.dataset.postId;
                } else {
                    // Try to extract from URL if all else fails
                    const urlParams = new URLSearchParams(window.location.search);
                    postId = urlParams.get('id');
                }
                
                if (!postId) {
                    showNotification('Could not determine which post to comment on', 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('parent_id', commentId);
                formData.append('comment', commentContent);
                formData.append('post_id', postId);
                
                // Disable button and show loading
                const submitBtn = this.querySelector('.comment-submit-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
                
                console.log('Submitting reply:', { parentId: commentId, postId: postId, content: commentContent });
                
                fetch('ajax/add_comment.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin' // Important for session cookies
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Reply response:', data);
                    
                    if (data.success) {
                        // Show success message
                        showNotification('Reply added successfully', 'success');
                        
                        // Reload the page to show new comment
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showNotification(data.message || 'Failed to add reply', 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Reply';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while submitting your reply', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Reply';
                });
            });
        });
    });
}

// Handle comment edit and delete
function initCommentActions() {
    console.log('Initializing comment edit/delete actions');
    
    // Edit comments
    const editButtons = document.querySelectorAll('.edit-btn');
    console.log('Found edit buttons:', editButtons.length);
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const comment = this.closest('.comment');
            if (!comment) {
                console.error('Could not find comment element');
                return;
            }
            
            const commentId = comment.dataset.commentId;
            if (!commentId) {
                console.error('Missing comment ID');
                return;
            }
            
            const commentBody = comment.querySelector('.comment-body');
            if (!commentBody) {
                console.error('Could not find comment body element');
                return;
            }
            
            // Store original content before we modify anything
            // Make sure we're getting just the text content
            const originalHTML = commentBody.innerHTML;
            // Strip HTML tags to get clean text for editing
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = originalHTML;
            const originalContent = tempDiv.textContent.trim();
            
            console.log('Editing comment:', commentId);
            
            // Check if we're already in edit mode
            if (comment.classList.contains('editing')) {
                return;
            }
            
            // Create edit form
            const editForm = document.createElement('form');
            editForm.className = 'edit-form';
            editForm.innerHTML = `
                <textarea class="comment-textarea">${originalContent}</textarea>
                <div class="form-actions">
                    <button type="button" class="cancel-edit-btn">Cancel</button>
                    <button type="submit" class="save-edit-btn">Save</button>
                </div>
            `;
            
            // Add form and mark as editing
            commentBody.innerHTML = '';
            commentBody.appendChild(editForm);
            comment.classList.add('editing');
            
            // Focus on textarea
            const textarea = editForm.querySelector('textarea');
            textarea.focus();
            
            // Position cursor at the end
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            
            // Handle cancel button
            editForm.querySelector('.cancel-edit-btn').addEventListener('click', function() {
                commentBody.innerHTML = originalHTML;
                comment.classList.remove('editing');
            });
            
            // Handle form submission
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const newContent = textarea.value.trim();
                if (!newContent) {
                    showNotification('Comment cannot be empty', 'error');
                    return;
                }
                
                // Disable button and show loading
                const saveBtn = this.querySelector('.save-edit-btn');
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
                
                console.log('Saving edited comment:', commentId);
                
                // Create FormData for more reliable submission
                const formData = new FormData();
                formData.append('comment_id', commentId);
                formData.append('content', newContent);
                
                // Send AJAX request to update comment
                fetch('ajax/edit_comment.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin' // Important for session cookies
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json().catch(error => {
                            console.error('Error parsing JSON:', error);
                            throw new Error('Invalid JSON response from server');
                        });
                    } else {
                        console.error('Unexpected content type:', contentType);
                        throw new Error('Server returned an unexpected response format');
                    }
                })
                .then(data => {
                    console.log('Edit response:', data);
                    
                    if (data && data.success) {
                        // Update comment content
                        commentBody.innerHTML = data.content;
                        comment.classList.remove('editing');
                        showNotification('Comment updated successfully', 'success');
                    } else {
                        showNotification(data.message || 'Failed to update comment', 'error');
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while saving your edit: ' + error.message, 'error');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                });
            });
        });
    });
    
    // Delete comments
    const deleteButtons = document.querySelectorAll('.delete-btn');
    console.log('Found delete buttons:', deleteButtons.length);
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const comment = this.closest('.comment');
            if (!comment) {
                console.error('Could not find comment element');
                return;
            }
            
            const commentId = comment.dataset.commentId;
            if (!commentId) {
                console.error('Missing comment ID');
                return;
            }
            
            console.log('Deleting comment:', commentId);
            
            if (confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
                // Create FormData for more reliable submission
                const formData = new FormData();
                formData.append('comment_id', commentId);
                
                // Send AJAX request to delete comment
                fetch('ajax/delete_comment.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Delete response:', data);
                    
                    if (data && data.success) {
                        // Remove comment from DOM with fade effect
                        comment.style.opacity = '0';
                        setTimeout(() => {
                            comment.remove();
                            showNotification('Comment deleted successfully', 'success');
                        }, 300);
                    } else {
                        showNotification(data.message || 'Failed to delete comment', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting the comment', 'error');
                });
            }
        });
    });
    
    // Report comments
    const reportButtons = document.querySelectorAll('.report-btn');
    console.log('Found report buttons:', reportButtons.length);
    
    reportButtons.forEach(button => {
        button.addEventListener('click', function() {
            const isLoggedIn = document.body.classList.contains('logged-in');
            if (!isLoggedIn) {
                window.location.href = 'login.php';
                return;
            }
            
            const type = this.dataset.type;
            const id = this.dataset.id;
            
            console.log('Reporting:', type, id);
            
            // Open report modal or form
            window.location.href = `report.php?type=${type}&id=${id}`;
        });
    });
}

// Show notification
function showNotification(message, type = 'info') {
    console.log('Notification:', type, message);
    
    // Create notification element if it doesn't exist
    let notification = document.getElementById('notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'notification';
        document.body.appendChild(notification);
    }
    
    // Set notification style based on type
    notification.className = `notification ${type}`;
    notification.textContent = message;
    notification.style.display = 'block';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '12px 20px';
    notification.style.borderRadius = '4px';
    notification.style.color = 'white';
    notification.style.fontWeight = '500';
    notification.style.zIndex = '10000';
    notification.style.boxShadow = '0 3px 10px rgba(0, 0, 0, 0.15)';
    
    // Set background color based on type
    if (type === 'success') {
        notification.style.backgroundColor = '#4CAF50';
    } else if (type === 'error') {
        notification.style.backgroundColor = '#F44336';
    } else {
        notification.style.backgroundColor = '#2196F3';
    }
    
    // Add animation
    notification.style.transform = 'translateY(-20px)';
    notification.style.opacity = '0';
    notification.style.transition = 'opacity 0.3s, transform 0.3s';
    
    setTimeout(() => {
        notification.style.transform = 'translateY(0)';
        notification.style.opacity = '1';
    }, 10);
    
    // Auto hide after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateY(-20px)';
        notification.style.opacity = '0';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 300);
    }, 3000);
}

function validatePostForm() {
    const title = document.getElementById('title').value.trim();
    const content = document.getElementById('content').value.trim();
    let isValid = true;
    
    // Clear previous error messages
    clearErrors();
    
    if (title === '') {
        displayError('title', 'Title is required');
        isValid = false;
    } else if (title.length < 5) {
        displayError('title', 'Title must be at least 5 characters');
        isValid = false;
    }
    
    if (content === '') {
        displayError('content', 'Content is required');
        isValid = false;
    }
    
    return isValid;
}

function validateCommentForm() {
    const commentField = document.querySelector('textarea[name="comment"]');
    if (!commentField) return true;
    
    const comment = commentField.value.trim();
    let isValid = true;
    
    if (comment === '') {
        // Add error styling
        commentField.style.borderColor = '#dc3545';
        
        // Show error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = 'Comment cannot be empty';
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.85rem';
        errorDiv.style.marginTop = '5px';
        
        commentField.parentNode.appendChild(errorDiv);
        isValid = false;
    }
    
    return isValid;
}

function initTags() {
    const tagInput = document.getElementById('tag-input');
    const tagList = document.getElementById('tag-list');
    const hiddenTagField = document.getElementById('tags');
    
    if (!tagInput || !tagList || !hiddenTagField) return;
    
    tagInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTag(this.value.trim());
            this.value = '';
        }
    });
    
    // Handle clicking on existing tags
    tagList.addEventListener('click', function(e) {
        if (e.target.classList.contains('tag-remove')) {
            e.target.parentNode.remove();
            updateHiddenTagField();
        }
    });
}

function addTag(tagName) {
    if (!tagName) return;
    
    // Remove commas and trim
    tagName = tagName.replace(/,/g, '').trim();
    
    if (tagName === '') return;
    
    // Check if tag already exists
    const existingTags = document.querySelectorAll('.tag-item');
    for (let tag of existingTags) {
        if (tag.dataset.tag.toLowerCase() === tagName.toLowerCase()) {
            return; // Tag already exists
        }
    }
    
    // Limit to 5 tags
    if (existingTags.length >= 5) {
        alert('Maximum 5 tags allowed');
        return;
    }
    
    const tagList = document.getElementById('tag-list');
    const tagItem = document.createElement('div');
    tagItem.className = 'tag-item';
    tagItem.dataset.tag = tagName;
    tagItem.innerHTML = `
        <span class="tag-name">${tagName}</span>
        <span class="tag-remove">&times;</span>
    `;
    
    tagList.appendChild(tagItem);
    updateHiddenTagField();
}

function updateHiddenTagField() {
    const tagItems = document.querySelectorAll('.tag-item');
    const tagField = document.getElementById('tags');
    
    const tags = Array.from(tagItems).map(item => item.dataset.tag);
    tagField.value = tags.join(',');
}

function displayError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const errorElement = document.createElement('div');
    errorElement.className = 'error-message';
    errorElement.textContent = message;
    errorElement.style.color = '#721c24';
    errorElement.style.fontSize = '0.8rem';
    errorElement.style.marginTop = '5px';
    
    field.style.borderColor = '#dc3545';
    field.parentNode.appendChild(errorElement);
}

function clearErrors() {
    document.querySelectorAll('.error-message').forEach(el => el.remove());
    document.querySelectorAll('.form-control, .comment-textarea').forEach(el => el.style.borderColor = '');
}