/**
 * Contact Form Handler - Fixed CSRF Token Loading
 * Handles form submission with validation
 */

document.addEventListener('DOMContentLoaded', function() {
    // Load CSRF token immediately
    loadCSRFToken();
    initializeContactForm();
});

/**
 * Load CSRF token with retry logic
 */
async function loadCSRFToken() {
    try {
        const response = await fetch('api/csrf-token.php');
        const data = await response.json();
        
        if (data.success && data.csrf_token) {
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                csrfInput.value = data.csrf_token;
                console.log('CSRF token loaded successfully');
            } else {
                console.warn('CSRF input field not found in form');
            }
        } else {
            console.error('Failed to get CSRF token from server');
        }
    } catch (error) {
        console.error('Failed to load security token:', error);
        // Retry after 1 second
        setTimeout(loadCSRFToken, 1000);
    }
}

/**
 * Initialize contact form
 */
function initializeContactForm() {
    const form = document.getElementById('contactForm');
    if (!form) {
        console.error('Contact form not found');
        return;
    }
    
    // Add character counter for message
    const messageField = form.querySelector('textarea[name="message"]');
    if (messageField) {
        addCharacterCounter(messageField);
    }
    
    // Handle form submission
    form.addEventListener('submit', handleContactSubmit);
}

/**
 * Add character counter to textarea
 */
function addCharacterCounter(textarea) {
    const maxLength = 5000;
    const counter = document.createElement('div');
    counter.className = 'character-counter';
    counter.style.cssText = 'text-align: right; color: #666; font-size: 14px; margin-top: 5px;';
    
    const updateCounter = () => {
        const length = textarea.value.length;
        counter.textContent = `${length} / ${maxLength} characters`;
        counter.style.color = length > maxLength * 0.9 ? '#dc3545' : '#666';
    };
    
    textarea.parentNode.appendChild(counter);
    textarea.addEventListener('input', updateCounter);
    updateCounter();
}

/**
 * Handle contact form submission
 */
async function handleContactSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    
    // Check if CSRF token is present
    const csrfInput = form.querySelector('input[name="csrf_token"]');
    if (!csrfInput || !csrfInput.value) {
        showNotification('Security token missing. Please refresh the page and try again.', 'error');
        console.error('CSRF token not found or empty');
        return;
    }
    
    // Validate form
    if (!validateContactForm(form)) {
        return;
    }
    
    // Show loading state
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    try {
        const formData = new FormData(form);
        
        // Log form data for debugging
        console.log('Submitting form with CSRF token:', csrfInput.value);
        
        const response = await fetch('api/contact.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success message
            showNotification(result.message || 'Message sent successfully!', 'success');
            
            // Clear form
            form.reset();
            
            // Update character counter
            const counter = form.querySelector('.character-counter');
            if (counter) {
                counter.textContent = '0 / 5000 characters';
            }
            
            // Reload CSRF token for next submission
            setTimeout(loadCSRFToken, 1000);
            
        } else {
            showNotification(result.message || 'Failed to send message. Please try again.', 'error');
        }
        
    } catch (error) {
        console.error('Contact form error:', error);
        showNotification('Network error. Please check your connection and try again.', 'error');
    } finally {
        // Restore button state
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    }
}

/**
 * Validate contact form
 */
function validateContactForm(form) {
    const firstName = form.querySelector('input[name="first_name"]')?.value.trim() || '';
    const lastName = form.querySelector('input[name="last_name"]')?.value.trim() || '';
    const email = form.querySelector('input[name="email"]')?.value.trim() || '';
    const subject = form.querySelector('select[name="subject"]')?.value || '';
    const message = form.querySelector('textarea[name="message"]')?.value.trim() || '';
    
    if (!firstName || firstName.length < 2) {
        showNotification('Please enter your first name (at least 2 characters)', 'error');
        return false;
    }
    
    if (!lastName || lastName.length < 2) {
        showNotification('Please enter your last name (at least 2 characters)', 'error');
        return false;
    }
    
    if (!email || !isValidEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return false;
    }
    
    if (!subject) {
        showNotification('Please select a subject', 'error');
        return false;
    }
    
    if (!message || message.length < 10) {
        showNotification('Please enter a message (at least 10 characters)', 'error');
        return false;
    }
    
    if (message.length > 5000) {
        showNotification('Message is too long. Maximum 5000 characters allowed.', 'error');
        return false;
    }
    
    return true;
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Create notification container if it doesn't exist
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(container);
    }
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
        color: white;
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        animation: slideIn 0.3s ease;
    `;
    
    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span><strong>${icon}</strong> ${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; margin-left: 15px;">&times;</button>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

/**
 * Load CSRF token with retry logic
 */
async function loadCSRFToken() {
    try {
        const response = await fetch('api/csrf-token.php');
        const data = await response.json();
        
        if (data.success && data.csrf_token) {
            const csrfInput = document.querySelector('input[name="csrf_token"]');
            if (csrfInput) {
                csrfInput.value = data.csrf_token;
                console.log('CSRF token loaded successfully:', data.csrf_token.substring(0, 20) + '...');
            } else {
                console.warn('CSRF input field not found in form');
            }
        } else {
            console.error('Failed to get CSRF token from server:', data);
        }
    } catch (error) {
        console.error('Failed to load security token:', error);
        // Retry after 2 seconds
        setTimeout(loadCSRFToken, 2000);
    }
}
