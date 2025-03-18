document.addEventListener('DOMContentLoaded', function() {
    // Initialize vote buttons
    initVoteButtons();
    
    // Initialize report modal
    initReportModal();
    
    // Update active navigation link
    updateActiveNavLink();
    
    // Mark notifications as read when clicked
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            if (notificationId) {
                fetch('ajax/mark_notification_read.php', {
                    method: 'POST',
                    body: new FormData().append('notification_id', notificationId)
                });
            }
        });
    });
    
    // Also call it after any AJAX navigation if you have SPA-like features
    document.addEventListener('ajaxPageLoaded', updateActiveNavLink);
});

function initVoteButtons() {
    const voteButtons = document.querySelectorAll('.vote-btn');
    
    voteButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Check if user is logged in
            if (!document.body.classList.contains('logged-in')) {
                alert('Please log in to vote');
                return;
            }
            
            const voteType = this.dataset.vote;
            const postId = this.closest('.post-card')?.dataset.postId;
            const commentId = this.closest('.comment')?.dataset.commentId;
            
            vote(voteType, postId, commentId, this);
        });
    });
}

function vote(voteType, postId, commentId, buttonElement) {
    const data = new FormData();
    data.append('vote_type', voteType);
    
    if (postId) {
        data.append('post_id', postId);
    } else if (commentId) {
        data.append('comment_id', commentId);
    }
    
    fetch('ajax/vote.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json().catch(error => {
            console.error('Error parsing JSON response:', error);
            throw new Error('Could not parse server response');
        });
    })
    .then(data => {
        console.log('Vote response:', data);
        if (data && data.success) {
            // Get vote count from response
            let voteCount = data.vote_count || data.voteCount || 0;
            
            // Find the vote container through different possible methods
            let container = null;
            let countElement = null;
            let upvoteBtn = null;
            let downvoteBtn = null;
            
            // Try to find the container
            if (postId) {
                container = document.querySelector(`.post-card[data-post-id="${postId}"]`);
            } else if (commentId) {
                container = document.querySelector(`.comment[data-comment-id="${commentId}"]`);
            }
            
            // If container found, locate the elements inside it
            if (container) {
                countElement = container.querySelector('.vote-count');
                upvoteBtn = container.querySelector('.vote-up');
                downvoteBtn = container.querySelector('.vote-down');
                
                // Update the count if element found
                if (countElement) {
                    countElement.textContent = voteCount;
                }
                
                // Update button styling
                if (upvoteBtn && downvoteBtn) {
                    upvoteBtn.classList.remove('upvoted');
                    downvoteBtn.classList.remove('downvoted');
                    
                    if (data.user_vote === 1) {
                        upvoteBtn.classList.add('upvoted');
                    } else if (data.user_vote === -1) {
                        downvoteBtn.classList.add('downvoted');
                    }
                }
                
                // Show success notification if available
                if (typeof showNotification === 'function') {
                    showNotification('Vote recorded successfully', 'success');
                }
            } else {
                console.warn('Could not find container for vote UI update');
                // Fallback: try with the button's parent elements
                try {
                    const voteContainer = buttonElement.closest('.vote-column') || 
                                         buttonElement.closest('.comment-votes') || 
                                         buttonElement.closest('.vote-container') ||
                                         buttonElement.parentElement;
                    
                    if (voteContainer) {
                        const countEl = voteContainer.querySelector('.vote-count');
                        if (countEl) countEl.textContent = voteCount;
                        
                        const upBtn = voteContainer.querySelector('.vote-up');
                        const downBtn = voteContainer.querySelector('.vote-down');
                        
                        if (upBtn) upBtn.classList.remove('upvoted');
                        if (downBtn) downBtn.classList.remove('downvoted');
                        
                        if (data.user_vote === 1 && upBtn) {
                            upBtn.classList.add('upvoted');
                        } else if (data.user_vote === -1 && downBtn) {
                            downBtn.classList.add('downvoted');
                        }
                        
                        if (typeof showNotification === 'function') {
                            showNotification('Vote recorded successfully', 'success');
                        }
                    } else {
                        console.warn('Could not find vote container, reloading page');
                        window.location.reload();
                    }
                } catch (err) {
                    console.error('Error updating UI:', err);
                    window.location.reload();
                }
            }
        } else {
            const errorMsg = data && data.message ? data.message : 'Error processing vote';
            console.error('Vote error:', errorMsg);
            if (typeof showNotification === 'function') {
                showNotification(errorMsg, 'error');
            } else {
                alert(errorMsg);
            }
        }
    })
    .catch(error => {
        console.error('Error during vote operation:', error);
        if (typeof showNotification === 'function') {
            showNotification('Failed to process vote: ' + error.message, 'error');
        } else {
            alert('Failed to process vote');
        }
    });
}

function updateVoteUI(buttonElement, voteType, newCount) {
    try {
        // First, determine whether we're dealing with a post or comment
        const isCommentVote = !!buttonElement.closest('.comment');
        const isPostVote = !!buttonElement.closest('.post-card');
        
        // Get the actual comment or post container
        const container = isCommentVote 
            ? buttonElement.closest('.comment') 
            : (isPostVote ? buttonElement.closest('.post-card') : null);
        
        if (!container) {
            console.warn('Could not find container element for vote');
            return;
        }
        
        const buttonParent = buttonElement.parentElement;
        
        if (!buttonParent) {
            console.warn('Vote button has no parent element');
            return;
        }
        
        // Try to find the count and vote buttons within the same parent
        let upvoteButton = buttonParent.querySelector('.vote-up');
        let downvoteButton = buttonParent.querySelector('.vote-down');
        let countElement = buttonParent.querySelector('.vote-count');
        
        if (!upvoteButton || !downvoteButton || !countElement) {
            upvoteButton = container.querySelector('.vote-up');
            downvoteButton = container.querySelector('.vote-down');
            countElement = container.querySelector('.vote-count');
        }
        
        // Handle cases where we still can't find the elements
        if (!upvoteButton && !downvoteButton && !countElement) {
            console.warn('Could not find vote UI elements');
            return;
        }
        
        // Update only the elements we found
        if (upvoteButton) upvoteButton.classList.remove('upvoted');
        if (downvoteButton) downvoteButton.classList.remove('downvoted');
        
        // Set new active state
        if (voteType === '1' && upvoteButton) {
            upvoteButton.classList.add('upvoted');
        } else if (voteType === '-1' && downvoteButton) {
            downvoteButton.classList.add('downvoted');
        }
        
        // Update count if the element exists
        if (countElement) {
            countElement.textContent = newCount || '0';
        }
        
        // Show success notification if that function exists
        if (typeof showNotification === 'function') {
            showNotification('Vote recorded', 'success');
        }
    } catch (error) {
        console.error('Error updating vote UI:', error);
        // Don't throw the error - gracefully degrade
    }
}

function initReportModal() {
    const reportButtons = document.querySelectorAll('.report-btn');
    const reportModal = document.getElementById('report-modal');
    const closeModal = document.querySelector('.close-modal');
    
    if (!reportModal) return;
    
    reportButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!document.body.classList.contains('logged-in')) {
                alert('Please log in to report content');
                return;
            }
            
            const contentType = this.dataset.type;
            const contentId = this.dataset.id;
            
            document.getElementById('report-type').value = contentType;
            document.getElementById('report-id').value = contentId;
            
            reportModal.style.display = 'block';
        });
    });
    
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            reportModal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === reportModal) {
            reportModal.style.display = 'none';
        }
    });
}

function updateActiveNavLink() {
    // Get current path
    const currentPath = window.location.pathname;
    const currentPage = currentPath.substring(currentPath.lastIndexOf('/') + 1);
    
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href) {
            const linkPage = href.substring(href.lastIndexOf('/') + 1).split('?')[0];
            if (currentPage === linkPage) {
                link.classList.add('active');
            }
        }
    });
}