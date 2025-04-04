document.addEventListener('DOMContentLoaded', function() {
    // Animation for user rank card
    const userRankCard = document.querySelector('.user-rank-card');
    if (userRankCard) {
        setTimeout(() => {
            userRankCard.classList.add('animated');
        }, 200);
    }
    
    // Highlight current user in the table
    const currentUserRow = document.querySelector('.current-user');
    if (currentUserRow) {
        currentUserRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});