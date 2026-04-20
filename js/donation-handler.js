/**
 * Donation Form Handler
 *
 * GATEWAY ROUTING (controlled by ACTIVE_GATEWAY in includes/config.php):
 *   'razorpay' → Step 1: Save record → Step 2: Create Razorpay order → Step 3: Open Razorpay modal → Step 4: Verify
 *   'paytm'    → Step 1: Save record → Step 2: initiateTransaction (cURL) → Step 3: Load Paytm JS SDK → Step 4: Open inline popup
 *
 * The active gateway is injected by donate.html via:
 *   <script>window.ACTIVE_GATEWAY = '<?php echo ACTIVE_GATEWAY; ?>';</script>
 * If that variable is missing we default to 'razorpay' for the test phase.
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

    document.querySelectorAll('.cause-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cause-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selected_cause').value = this.getAttribute('data-cause');
            updateSummary();
        });
    });

    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('donation_amount').value = this.getAttribute('data-amount');
            document.getElementById('customAmount').value = '';
            updateSummary();
        });
    });

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
    const el  = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };

    el('summary-amount',    '\u20B9' + fmt(amount));
    el('summary-total',     '\u20B9' + fmt(amount));
    el('tax-deduction',     '\u20B9' + fmt(Math.round(Number(amount) * 0.5)));
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
        // ── Step 1: Save donation record (shared by both gateways) ────────────
        const formData = new FormData(form);
        const saveRes  = await fetch('api/donations.php', { method: 'POST', body: formData });
        const saveData = await saveRes.json();

        if (!saveData.success) {
            throw new Error(saveData.message || 'Failed to save donation. Please try again.');
        }

        const transactionId = saveData.transaction_id;

        // ── Step 2: Route to correct gateway ─────────────────────────────────
        const gateway = (window.ACTIVE_GATEWAY || 'razorpay').toLowerCase();

        if (gateway === 'razorpay') {
            hideLoadingOverlay();
            await initiateRazorpayPayment(transactionId, submitButton, originalText);
        } else {
            await initiatePaytmPayment(transactionId, submitButton, originalText);
        }

    } catch (err) {
        console.error('Donation error:', err);
        hideLoadingOverlay();
        showNotification(err.message || 'Network error. Please check your connection and try again.', 'error');
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
}

// ── PAYTM FLOW (v3 — JS SDK inline popup) ────────────────────────────────────
async function initiatePaytmPayment(transactionId, submitButton, originalText) {

    // Step A: Get fresh CSRF + call initiate-payment.php to get txnToken
    const csrfRes   = await fetch('api/csrf-token.php');
    const csrfData  = await csrfRes.json();
    const freshCsrf = csrfData.csrf_token || '';

    const payParams = new FormData();
    payParams.append('transaction_id', transactionId);
    payParams.append('csrf_token',     freshCsrf);

    const payRes  = await fetch('api/initiate-payment.php', { method: 'POST', body: payParams });
    const payData = await payRes.json();

    if (!payData.success) {
        throw new Error(payData.message || 'Payment initiation failed. Please try again.');
    }

    const { txnToken, mid, order_id, amount, env } = payData;

    // Step B: Load Paytm JS Checkout SDK dynamically
    const sdkBase = (env === 'PROD')
        ? 'https://secure.paytmpayments.com'
        : 'https://securestage.paytmpayments.com';

    await loadScript(`${sdkBase}/merchantpgpui/checkoutjs/merchants/${mid}.js`);

    // Step C: Open Paytm inline checkout popup
    hideLoadingOverlay();

    const config = {
        root: '',                   // empty string = popup mode (not embedded)
        flow: 'DEFAULT',
        merchant: {
            mid:         mid,
            redirect:    false,     // keep user on page; use handler callbacks
        },
        data: {
            orderId:   order_id,
            token:     txnToken,
            tokenType: 'TXN_TOKEN',
            amount:    amount,
        },
        handler: {
            notifyMerchant: function (eventName, data) {
                console.log('Paytm event:', eventName, data);

                if (eventName === 'APP_CLOSED') {
                    // User closed the popup without paying
                    showNotification('Payment was cancelled. You can try again.', 'info');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            },
            transactionStatus: function (data) {
                // Called after payment attempt (success or failure)
                console.log('Paytm transactionStatus:', data);

                window.Paytm.CheckoutJS.close();

                if (data.STATUS === 'TXN_SUCCESS') {
                    // Redirect to success page — callback.php handles DB update
                    window.location.href = `payment-success.html?txn=${encodeURIComponent(order_id)}&status=success&amount=${encodeURIComponent(amount)}`;
                } else if (data.STATUS === 'PENDING') {
                    window.location.href = `payment-success.html?txn=${encodeURIComponent(order_id)}&status=pending`;
                } else {
                    showNotification(
                        'Payment failed: ' + (data.RESPMSG || 'Unknown error') + '. Please try again.',
                        'error'
                    );
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            },
        },
    };

    if (window.Paytm && window.Paytm.CheckoutJS) {
        window.Paytm.CheckoutJS.init(config)
            .then(() => window.Paytm.CheckoutJS.invoke())
            .catch(err => {
                console.error('Paytm CheckoutJS error:', err);
                showNotification('Could not open payment window. Please try again.', 'error');
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            });
    } else {
        throw new Error('Paytm Checkout SDK failed to load. Please refresh and try again.');
    }
}

// ── RAZORPAY FLOW ──────────────────────────────────────────────────────────────
async function initiateRazorpayPayment(transactionId, submitButton, originalText) {
    const params = new FormData();
    params.append('transaction_id', transactionId);

    const orderRes  = await fetch('api/razorpay-create-order.php', { method: 'POST', body: params });
    const orderData = await orderRes.json();

    if (!orderData.success) {
        throw new Error(orderData.message || 'Could not create payment order. Please try again.');
    }

    const options = {
        key:         orderData.key_id,
        amount:      orderData.amount_paise,
        currency:    'INR',
        name:        'Sri Dutta Sai Manga Bharadwaja Trust',
        description: 'Donation',
        order_id:    orderData.razorpay_order_id,
        prefill: {
            name:    orderData.donor_name,
            email:   orderData.donor_email,
            contact: orderData.donor_phone,
        },
        theme: { color: '#01696f' },
        modal: {
            ondismiss: function () {
                showNotification('Payment was cancelled. You can try again.', 'info');
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        },
        handler: async function (response) {
            showLoadingOverlay();
            const verifyParams = new FormData();
            verifyParams.append('razorpay_order_id',   response.razorpay_order_id);
            verifyParams.append('razorpay_payment_id', response.razorpay_payment_id);
            verifyParams.append('razorpay_signature',  response.razorpay_signature);
            verifyParams.append('transaction_id',      transactionId);

            try {
                const verifyRes  = await fetch('api/razorpay-verify-payment.php', { method: 'POST', body: verifyParams });
                const verifyData = await verifyRes.json();
                hideLoadingOverlay();

                if (verifyData.success) {
                    window.location.href = verifyData.redirect;
                } else {
                    showNotification(verifyData.message || 'Payment verification failed. Please contact support.', 'error');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            } catch (verifyErr) {
                hideLoadingOverlay();
                showNotification('Verification error. Please contact support with your transaction ID: ' + transactionId, 'error');
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        }
    };

    await loadScript('https://checkout.razorpay.com/v1/checkout.js');

    const rzp = new window.Razorpay(options);
    rzp.on('payment.failed', function (response) {
        hideLoadingOverlay();
        showNotification('Payment failed: ' + (response.error.description || 'Unknown error'), 'error');
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
    rzp.open();
}

// ── Shared script loader ──────────────────────────────────────────────────────
function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) { resolve(); return; }
        const script    = document.createElement('script');
        script.src      = src;
        script.onload   = resolve;
        script.onerror  = () => reject(new Error('Failed to load script: ' + src));
        document.head.appendChild(script);
    });
}

// ── Validation ────────────────────────────────────────────────────────────────
function validateDonationForm(form) {
    const name   = form.querySelector('#donor_name')?.value.trim();
    const email  = form.querySelector('#donor_email')?.value.trim();
    const amount = parseFloat(form.querySelector('#donation_amount')?.value);
    const terms  = form.querySelector('#terms')?.checked;

    if (!name)                          { showNotification('Please enter your full name', 'error');                return false; }
    if (!email || !isValidEmail(email)) { showNotification('Please enter a valid email address', 'error');        return false; }
    if (!amount || amount < 1)          { showNotification('Please select or enter a donation amount', 'error'); return false; }
    if (amount > 1000000)               { showNotification('Maximum donation amount is \u20B910,00,000', 'error'); return false; }
    if (!terms)                         { showNotification('Please accept the terms and conditions', 'error');    return false; }
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
        <button onclick="this.closest('div').parentElement.remove()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;">&times;</button>
    </div>`;

    container.appendChild(n);
    setTimeout(() => n.isConnected && n.remove(), 5500);
}

(function () {
    const s = document.createElement('style');
    s.textContent = '@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}';
    document.head.appendChild(s);
})();
