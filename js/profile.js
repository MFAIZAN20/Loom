document.addEventListener('DOMContentLoaded', function () {
    // Add event listeners for vote buttons
    const voteBtns = document.querySelectorAll('.vote-btn');
    voteBtns.forEach((voteBtn) => {
        voteBtn.addEventListener('click', function (event) {
            const postCard = event.target.closest('.post-card');
            const postId = postCard.getAttribute('data-post-id');
            const action = event.target.classList.contains('upvote') ? 'upvote' : 'downvote';
            handleVote(postId, action, postCard);
        });
    });

    // Function to handle vote
    function handleVote(postId, action, postCard) {
        const url = 'vote-post.php'; 
        const params = new URLSearchParams({
            post_id: postId,
            action: action
        });

        fetch(url, {
            method: 'POST',
            body: params
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    // Update the vote count
                    const voteCount = postCard.querySelector('.vote-count');
                    voteCount.textContent = data.new_vote_count;
                } else {
                    alert('Error: Unable to vote');
                }
            })
            .catch((error) => console.error('Error:', error));
    }

    // Edit post action
    const editPostLinks = document.querySelectorAll('.post-action.edit');
    editPostLinks.forEach((link) => {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const postId = event.target.closest('.post-card').getAttribute('data-post-id');
            window.location.href = `edit-post.php?id=${postId}`;
        });
    });

    // Delete post action
    const deletePostLinks = document.querySelectorAll('.post-action.delete');
    deletePostLinks.forEach((link) => {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const postId = event.target.closest('.post-card').getAttribute('data-post-id');
            const csrfToken = link.getAttribute('data-csrf-token'); // Ensure this attribute is in HTML
            if (confirm('Are you sure you want to delete this post?')) {
                window.location.href = `profile.php?delete=${postId}&csrf_token=${csrfToken}`;
            }
        });
    });
});