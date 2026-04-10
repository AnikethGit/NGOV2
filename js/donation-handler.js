/**
 * Enhanced Donation Form Handler
 * Handles form submission with validation and payment processing
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeDonationForm();
    loadCSRFToken();
});

/**
 * Load CSRF token
 */
async function loadCSRFToken() {
    try {
        const response = await fetch('api/csrf-token.php');
        const data = await response.json();
        
        if (data.success && data.csrf_token) {
            document.getElementById('csrf_token').value = data.csrf_token;
        }
    } catch (error) {
        console.error('Failed to load security token:', error);
    }
}

/**
 * Initialize donation form
 */
function initializeDonationForm() {
    const form = document.getElementById('donationForm');
    if (!form) return;
    
    // Handle cause selection
    document.querySelectorAll('.cause-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.cause-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const cause = this.getAttribute('data-cause');
            document.getElementById('selected_cause').value = cause;
            updateSummary();
        });
    });
    
    // Handle amount selection
    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const amount = this.getAttribute('data-amount');
            document.getElementById('donation_amount').value = amount;
            document.getElementById('customAmount').value = '';
            updateSummary();
        });
    });
    
    // Handle custom amount
    document.getElementById('customAmount').addEventListener('input', function() {
        if (this.value) {
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('donation_amount').value = this.value;
            updateSummary();
        }
    });
    
    // Handle frequency selection
    document.querySelectorAll('input[name="frequency"]').forEach(radio => {
        radio.addEventListener('change', updateSummary);
    });
    
    // Handle form submission
    form.addEventListener('submit', handleDonationSubmit);
}

/**
 * Update donation summary
 */
function updateSummary() {
    const cause = document.getElementById('selected_cause').value;
    const amount = document.getElementById('donation_amount').value;
    const frequency = document.querySelector('input[name="frequency"]:checked').value;
    
    // Update cause display
    const causeBtn = document.querySelector(`[data-cause="${cause}"]`);
    if (causeBtn) {
        document.getElementById('summary-cause').textContent = causeBtn.textContent.trim();
    }
    
    // Update amount display
    document.getElementById('summary-amount').textContent = '₹' + Number(amount).toLocaleString('en-IN');
    document.getElementById('summary-total').textContent = '₹' + Number(amount).toLocaleString('en-IN');
    
    // Update frequency display
    const frequencyText = {
        'one-time': 'One Time',
        'monthly': 'Monthly',
        'yearly': 'Yearly'
    };
    document.getElementById('summary-frequency').textContent = frequencyText[frequency];
    
    // Update tax deduction (50% under 80G)
    const taxDeduction = Math.round(Number(amount) * 0.5);
    document.getElementById('tax-deduction').textContent = '₹' + taxDeduction.toLocaleString('en-IN');
}

/**
 * Handle donation form submission
 */
async function handleDonationSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    
    // Validate form
    if (!validateDonationForm(form)) {
        return;
    }
    
    // Show loading state
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Show loading overlay
    showLoadingOverlay();
    
    try {
        const formData = new FormData(form);
        
        const response = await fetch('api/donations.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        hideLoadingOverlay();
        
        if (result.success) {
            // Show success message
            showNotification('Donation processed successfully! Redirecting...', 'success');
            
            // Store donation details for success page
            sessionStorage.setItem('donation_details', JSON.stringify({
                transaction_id: result.transaction_id,
                amount: result.amount,
                cause: formData.get('cause')
            }));
            
            // Redirect to success page
            setTimeout(() => {
                window.location.href = result.payment_url;
            }, 1500);
        } else {
            showNotification(result.message || 'An error occurred. Please try again.', 'error');
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
        
    } catch (error) {
        console.error('Donation submission error:', error);
        hideLoadingOverlay();
        showNotification('Network error. Please check your connection and try again.', 'error');
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    }
}

/**
 * Validate donation form
 */
function validateDonationForm(form) {
    const name = form.querySelector('#donor_name').value.trim();
    const email = form.querySelector('#donor_email').value.trim();
    const amount = parseFloat(form.querySelector('#donation_amount').value);
    const terms = form.querySelector('#terms').checked;
    
    if (!name) {
        showNotification('Please enter your full name', 'error');
        return false;
    }
    
    if (!email || !isValidEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return false;
    }
    
    if (!amount || amount < 1) {
        showNotification('Please select or enter a donation amount', 'error');
        return false;
    }
    
    if (amount > 1000000) {
        showNotification('Maximum donation amount is ₹10,00,000', 'error');
        return false;
    }
    
    if (!terms) {
        showNotification('Please accept the terms and conditions', 'error');
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
 * Show loading overlay
 */
function showLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('hidden');
    }
}

/**
 * Hide loading overlay
 */
function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
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
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span>${message}</span>
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