/**
 * Donation Form Handler
 * Step 1: POST to api/donations.php  → saves record, returns transaction_id
 * Step 2: POST to api/initiate-payment.php → returns Paytm params
 * Step 3: Auto-submits a hidden form to Paytm's gateway URL
 */

document.addEventListener('DOMContentLoaded', function () {
    initializeDonationForm();
    loadCSRFToken();
});

// ── CSRF token ────────────────────────────────────────────────────────────────
async function loadCSRFToken() {
    try {
        const res  = await fetch('api/csrf-token.php');
        const data = await res.json();
        if (data.success && data.csrf_token) {
            document.getElementById('csrf_token').value = data.csrf_token;
        }
    } catch (err) {
        console.error('Failed to load CSRF token:', err);
    }
}

// ── Form initialisation ───────────────────────────────────────────────────────
function initializeDonationForm() {
    const form = document.getElementById('donationForm');
    if (!form) return;

    // Cause buttons
    document.querySelectorAll('.cause-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cause-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selected_cause').value = this.getAttribute('data-cause');
            updateSummary();
        });
    });

    // Preset amount buttons
    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('donation_amount').value = this.getAttribute('data-amount');
            document.getElementById('customAmount').value = '';
            updateSummary();
        });
    });

    // Custom amount input
    const customInput = document.getElementById('customAmount');
    if (customInput) {
        customInput.addEventListener('input', function () {
            if (this.value) {
                document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
                document.getElementById('donation_amount').value = this.value;
                updateSummary();
            }
        });
    }

    // Frequency radios
    document.querySelectorAll('input[name="frequency"]').forEach(r =>
        r.addEventListener('change', updateSummary)
    );

    form.addEventListener('submit', handleDonationSubmit);
}

// ── Summary panel ─────────────────────────────────────────────────────────────
function updateSummary() {
    const cause     = document.getElementById('selected_cause').value;
    const amount    = document.getElementById('donation_amount').value;
    const frequency = document.querySelector('input[name="frequency"]:checked')?.value || 'one-time';

    const causeBtn = document.querySelector(`[data-cause="${cause}"]`);
    if (causeBtn) {
        const el = document.getElementById('summary-cause');
        if (el) el.textContent = causeBtn.textContent.trim();
    }

    const fmt = n => Number(n).toLocaleString('en-IN');
    const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };

    el('summary-amount',    '₹' + fmt(amount));
    el('summary-total',     '₹' + fmt(amount));
    el('tax-deduction',     '₹' + fmt(Math.round(Number(amount) * 0.5)));
    el('summary-frequency', { 'one-time': 'One Time', 'monthly': 'Monthly', 'yearly': 'Yearly' }[frequency] || 'One Time');
}

// ── Main submit handler ───────────────────────────────────────────────────────
async function handleDonationSubmit(e) {
    e.preventDefault();
    const form         = e.target;
    const submitButton = form.querySelector('button[type="submit"]');

    if (!validateDonationForm(form)) return;

    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    showLoadingOverlay();

    try {
        // ── Step 1: Save donation record ──────────────────────────────────────
        const formData = new FormData(form);
        const saveRes  = await fetch('api/donations.php', { method: 'POST', body: formData });
        const saveData = await saveRes.json();

        if (!saveData.success) {
            throw new Error(saveData.message || 'Failed to save donation. Please try again.');
        }

        const transactionId = saveData.transaction_id;

        // ── Step 2: Initiate Paytm payment ────────────────────────────────────
        // Reload CSRF token (the session token may have been consumed)
        const csrfRes  = await fetch('api/csrf-token.php');
        const csrfData = await csrfRes.json();
        const freshCsrf = csrfData.csrf_token || '';

        const payParams = new FormData();
        payParams.append('transaction_id', transactionId);
        payParams.append('csrf_token',     freshCsrf);

        const payRes  = await fetch('api/initiate-payment.php', { method: 'POST', body: payParams });
        const payData = await payRes.json();

        if (!payData.success) {
            throw new Error(payData.message || 'Payment initiation failed. Please try again.');
        }

        // ── Step 3: Auto-submit Paytm form ────────────────────────────────────
        const paytmForm = document.createElement('form');
        paytmForm.method  = 'POST';
        paytmForm.action  = payData.paytm_url;
        paytmForm.style.display = 'none';

        Object.entries(payData.paytm_params).forEach(([key, value]) => {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = key;
            input.value = value;
            paytmForm.appendChild(input);
        });

        document.body.appendChild(paytmForm);
        paytmForm.submit(); // Redirects user to Paytm gateway

    } catch (err) {
        console.error('Donation error:', err);
        hideLoadingOverlay();
        showNotification(err.message || 'Network error. Please check your connection and try again.', 'error');
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
}

// ── Validation ────────────────────────────────────────────────────────────────
function validateDonationForm(form) {
    const name   = form.querySelector('#donor_name')?.value.trim();
    const email  = form.querySelector('#donor_email')?.value.trim();
    const amount = parseFloat(form.querySelector('#donation_amount')?.value);
    const terms  = form.querySelector('#terms')?.checked;

    if (!name)                         { showNotification('Please enter your full name', 'error');                          return false; }
    if (!email || !isValidEmail(email)) { showNotification('Please enter a valid email address', 'error');                   return false; }
    if (!amount || amount < 1)          { showNotification('Please select or enter a donation amount', 'error');            return false; }
    if (amount > 1000000)               { showNotification('Maximum donation amount is ₹10,00,000', 'error');               return false; }
    if (!terms)                         { showNotification('Please accept the terms and conditions', 'error');              return false; }
    return true;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// ── UI helpers ────────────────────────────────────────────────────────────────
function showLoadingOverlay() {
    document.getElementById('loadingOverlay')?.classList.remove('hidden');
}

function hideLoadingOverlay() {
    document.getElementById('loadingOverlay')?.classList.add('hidden');
}

function showNotification(message, type = 'info') {
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;';
        document.body.appendChild(container);
    }

    const colours = { success: '#437a22', error: '#a12c2c', info: '#01696f' };
    const n = document.createElement('div');
    n.style.cssText = `background:${colours[type] || colours.info};color:#fff;padding:14px 18px;
        margin-bottom:10px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);
        animation:slideIn 0.25s ease;font-size:14px;font-family:inherit;`;
    n.innerHTML = `<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <span>${message}</span>
        <button onclick="this.closest('div').parentElement.remove()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;">×</button>
    </div>`;

    container.appendChild(n);
    setTimeout(() => n.isConnected && n.remove(), 5500);
}

// Slide-in animation
(function () {
    const s = document.createElement('style');
    s.textContent = '@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}';
    document.head.appendChild(s);
})();
