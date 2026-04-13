/**
 * Contact Form Handler
 * Fixed: removed duplicate loadCSRFToken declaration, aligned with session fix
 */

document.addEventListener('DOMContentLoaded', function () {
    loadCSRFToken();
    initializeContactForm();
});

// ── CSRF token loader ──────────────────────────────────────────────────────
async function loadCSRFToken() {
    try {
        const response = await fetch('api/csrf-token.php', {
            credentials: 'same-origin'   // ensures session cookie is sent
        });
        const data = await response.json();

        if (data.success && data.csrf_token) {
            // Only ONE csrf_token input exists now — set it directly by id
            const csrfInput = document.getElementById('csrf_token');
            if (csrfInput) {
                csrfInput.value = data.csrf_token;
                console.log('CSRF token loaded');
            } else {
                console.warn('csrf_token input not found');
            }
        } else {
            console.error('Server did not return a CSRF token:', data);
        }
    } catch (error) {
        console.error('Failed to load CSRF token:', error);
        // Retry once after 2 s
        setTimeout(loadCSRFToken, 2000);
    }
}

// ── Form initialiser ──────────────────────────────────────────────────────
function initializeContactForm() {
    const form = document.getElementById('contactForm');
    if (!form) {
        console.error('contactForm not found');
        return;
    }

    const messageField = form.querySelector('textarea[name="message"]');
    if (messageField) addCharacterCounter(messageField);

    form.addEventListener('submit', handleContactSubmit);
}

// ── Character counter ─────────────────────────────────────────────────────
function addCharacterCounter(textarea) {
    const maxLength = 5000;
    const counter   = document.createElement('div');
    counter.className = 'character-counter';
    counter.style.cssText = 'text-align:right;color:#666;font-size:14px;margin-top:5px;';

    const update = () => {
        const len = textarea.value.length;
        counter.textContent = `${len} / ${maxLength} characters`;
        counter.style.color = len > maxLength * 0.9 ? '#dc3545' : '#666';
    };

    textarea.parentNode.appendChild(counter);
    textarea.addEventListener('input', update);
    update();
}

// ── Submit handler ────────────────────────────────────────────────────────
async function handleContactSubmit(e) {
    e.preventDefault();

    const form         = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const csrfInput    = document.getElementById('csrf_token');

    if (!csrfInput || !csrfInput.value) {
        showNotification('Security token missing. Please refresh the page and try again.', 'error');
        return;
    }

    if (!validateContactForm(form)) return;

    const originalHTML = submitButton.innerHTML;
    submitButton.disabled  = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    try {
        const response = await fetch('api/contact.php', {
            method: 'POST',
            credentials: 'same-origin',   // send session cookie
            body: new FormData(form)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message || 'Message sent successfully!', 'success');
            form.reset();

            const counter = form.querySelector('.character-counter');
            if (counter) counter.textContent = '0 / 5000 characters';

            // Refresh token for the next submission
            setTimeout(loadCSRFToken, 500);
        } else {
            showNotification(result.message || 'Failed to send message. Please try again.', 'error');
        }

    } catch (error) {
        console.error('Contact form error:', error);
        showNotification('Network error. Please check your connection and try again.', 'error');
    } finally {
        submitButton.disabled  = false;
        submitButton.innerHTML = originalHTML;
    }
}

// ── Validation ────────────────────────────────────────────────────────────
function validateContactForm(form) {
    const firstName = form.querySelector('input[name="first_name"]')?.value.trim() || '';
    const lastName  = form.querySelector('input[name="last_name"]')?.value.trim()  || '';
    const email     = form.querySelector('input[name="email"]')?.value.trim()      || '';
    const subject   = form.querySelector('select[name="subject"]')?.value          || '';
    const message   = form.querySelector('textarea[name="message"]')?.value.trim() || '';

    if (!firstName || firstName.length < 2) {
        showNotification('Please enter your first name (at least 2 characters)', 'error'); return false;
    }
    if (!lastName || lastName.length < 2) {
        showNotification('Please enter your last name (at least 2 characters)', 'error'); return false;
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showNotification('Please enter a valid email address', 'error'); return false;
    }
    if (!subject) {
        showNotification('Please select a subject', 'error'); return false;
    }
    if (!message || message.length < 10) {
        showNotification('Please enter a message (at least 10 characters)', 'error'); return false;
    }
    if (message.length > 5000) {
        showNotification('Message is too long. Maximum 5000 characters allowed.', 'error'); return false;
    }
    return true;
}

// ── Notification toast ────────────────────────────────────────────────────
function showNotification(message, type = 'info') {
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;';
        document.body.appendChild(container);
    }

    const colors = { success: '#28a745', error: '#dc3545', info: '#007bff' };
    const icons  = { success: '✓', error: '✕', info: 'ℹ' };

    const n = document.createElement('div');
    n.style.cssText = `background:${colors[type]||colors.info};color:white;padding:15px 20px;margin-bottom:10px;border-radius:5px;box-shadow:0 4px 6px rgba(0,0,0,.1);animation:slideIn .3s ease;`;
    n.innerHTML = `<div style="display:flex;align-items:center;justify-content:space-between;">
        <span><strong>${icons[type]||icons.info}</strong> ${message}</span>
        <button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;color:white;font-size:20px;cursor:pointer;margin-left:15px;">&times;</button>
    </div>`;

    container.appendChild(n);
    setTimeout(() => { if (n.parentElement) n.remove(); }, 5000);
}

// Slide-in animation
const _style = document.createElement('style');
_style.textContent = `@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}`;
document.head.appendChild(_style);
