
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const toggleButtons = document.querySelectorAll('.password-toggle');
    
    if (toggleButtons) {
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const passwordField = this.closest('.password-field-container').querySelector('input');
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                // Toggle icon
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    }
    
    // Form validation
    const signupForm = document.getElementById('signup-form');
    
    if (signupForm) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm-password');
        
        signupForm.addEventListener('submit', function(event) {
            if (password.value !== confirmPassword.value) {
                event.preventDefault();
                
                // Create or update error message
                let errorDiv = document.querySelector('.alert-danger');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    const formTitle = document.querySelector('.form-title');
                    formTitle.insertAdjacentElement('afterend', errorDiv);
                }
                
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match';
            }
        });
    }
    
    // Loading state for buttons
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<span class="spinner"></span> Processing...';
            button.disabled = true;
        });
    });
    
    // Focus animation for form fields
    const formControls = document.querySelectorAll('.form-control');
    
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.classList.add('active');
        });
        
        control.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('active');
            }
        });
        
        // Check on page load if field has value
        if (control.value) {
            control.parentElement.classList.add('active');
        }
    });
});