// Create and append the modal HTML to the document
function createLogoutModal() {
    const modal = document.createElement('div');
    modal.className = 'logout-modal';
    modal.innerHTML = `
        <div class="logout-modal-content">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to logout?</p>
            <div class="logout-modal-buttons">
                <button class="cancel-button">Cancel</button>
                <button class="confirm-button">Logout</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Add event listeners
    const cancelButton = modal.querySelector('.cancel-button');
    const confirmButton = modal.querySelector('.confirm-button');

    cancelButton.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    confirmButton.addEventListener('click', () => {
        window.location.href = 'login.html';
    });

    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    return modal;
}

// Initialize logout functionality
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logoutBtn');
    const modal = createLogoutModal();

    logoutBtn.addEventListener('click', function(e) {
        e.preventDefault();
        modal.style.display = 'flex';
    });
}); 