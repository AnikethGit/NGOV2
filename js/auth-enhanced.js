/**
 * auth-enhanced.js
 * Handles login, register, forgot-password for login.html
 * Calls api/auth.php
 */

(function () {
    'use strict';

    // ── CSRF token ──────────────────────────────────────────────────────────
    async function loadCsrfToken() {
        try {
            const res  = await fetch('api/csrf-token.php');
            const data = await res.json();
            const token = data.csrf_token || data.token;
            if (token) {
                document.querySelectorAll('input[name="csrf_token"]').forEach(el => {
                    el.value = token;
                });
            }
        } catch (e) {
            console.warn('CSRF token fetch failed:', e);
        }
    }

    // ── Tab switching ───────────────────────────────────────────────────────
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                btn.classList.add('active');
                const content = document.getElementById(tab + '-tab');
                if (content) content.classList.add('active');
                clearMessages();
            });
        });
    }

    // ── Password toggle ─────────────────────────────────────────────────────
    function initPasswordToggles() {
        document.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.closest('.input-group').querySelector('input');
                if (!input) return;
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
        });
    }

    // ── Password strength meter ─────────────────────────────────────────────
    function initPasswordStrength() {
        const pwInput = document.getElementById('register_password');
        const fill    = document.querySelector('.strength-fill');
        const label   = document.getElementById('strength-level');
        if (!pwInput || !fill || !label) return;

        pwInput.addEventListener('input', () => {
            const val = pwInput.value;
            let score = 0;
            if (val.length >= 8)             score++;
            if (val.length >= 12)            score++;
            if (/[A-Z]/.test(val))           score++;
            if (/[0-9]/.test(val))           score++;
            if (/[^A-Za-z0-9]/.test(val))   score++;

            const levels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
            const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
            const pct    = (score / 5) * 100;

            fill.style.width      = pct + '%';
            fill.style.background = colors[score] || '#ef4444';
            label.textContent     = levels[score] || 'Weak';
        });
    }

    // ── Confirm password match ──────────────────────────────────────────────
    function initPasswordMatch() {
        const pw  = document.getElementById('register_password');
        const cpw = document.getElementById('confirm_password');
        if (!pw || !cpw) return;

        function checkMatch() {
            const wrap   = cpw.closest('.input-group');
            const checks = wrap?.querySelectorAll('.password-match i');
            if (!checks) return;
            const match = cpw.value && pw.value === cpw.value;
            checks[0].style.display = match ? 'inline'  : 'none';
            checks[1].style.display = match ? 'none'    : (cpw.value ? 'inline' : 'none');
        }

        pw.addEventListener('input', checkMatch);
        cpw.addEventListener('input', checkMatch);
    }

    // ── Feedback helpers ────────────────────────────────────────────────────
    function showMessage(formId, message, type) {
        clearMessages();
        const form = document.getElementById(formId);
        if (!form) return;
        const div       = document.createElement('div');
        div.className   = 'auth-message auth-message--' + type;
        div.textContent = message;
        div.style.cssText = [
            'padding:12px 16px',
            'border-radius:8px',
            'margin-bottom:16px',
            'font-size:14px',
            'font-weight:500',
            type === 'success'
                ? 'background:#dcfce7;color:#15803d;border:1px solid #bbf7d0'
                : 'background:#fee2e2;color:#dc2626;border:1px solid #fecaca'
        ].join(';');
        form.insertAdjacentElement('beforebegin', div);
    }

    function clearMessages() {
        document.querySelectorAll('.auth-message').forEach(el => el.remove());
    }

    function setLoading(btn, loading) {
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            btn.dataset.origText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait...';
        } else {
            btn.innerHTML = btn.dataset.origText || btn.innerHTML;
        }
    }

    // ── Redirect helper ─────────────────────────────────────────────────────
    function redirectForRole(role) {
        switch (role) {
            case 'admin':     return 'admin-dashboard.html';
            case 'volunteer': return 'volunteer-dashboard.html';
            default:          return 'donor-dashboard.html';
        }
    }

    // ── API call ────────────────────────────────────────────────────────────
    // FIX: Do NOT append ?action= to the URL for POST requests.
    // The action is already inside the JSON body which auth.php reads via
    // file_get_contents('php://input'). Appending it to the query string
    // caused Hostinger's PHP to route some requests into the GET handler
    // branch, returning 'Unknown action'.
    async function authRequest(action, payload) {
        const res = await fetch('api/auth.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ ...payload, action })
        });
        return res.json();
    }

    // ── Login form ──────────────────────────────────────────────────────────
    function initLoginForm() {
        const form = document.getElementById('loginForm');
        if (!form) return;

        form.addEventListener('submit', async e => {
            e.preventDefault();
            const btn = form.querySelector('.auth-submit');
            setLoading(btn, true);
            clearMessages();

            const payload = {
                email:       form.email.value.trim(),
                password:    form.password.value,
                user_type:   form.user_type?.value || 'donor',
                remember_me: form.remember_me?.checked ? 1 : 0,
                csrf_token:  document.getElementById('login_csrf_token')?.value || ''
            };

            try {
                const data = await authRequest('login', payload);
                if (data.success) {
                    showMessage('loginForm', data.message, 'success');
                    const dest = data.data?.redirect || redirectForRole(data.data?.user_type || data.data?.user?.role || 'donor');
                    setTimeout(() => { window.location.href = dest; }, 800);
                } else {
                    showMessage('loginForm', data.message || 'Login failed.', 'error');
                    await loadCsrfToken();
                }
            } catch (err) {
                showMessage('loginForm', 'Network error. Please check your connection.', 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    }

    // ── Register form ───────────────────────────────────────────────────────
    function initRegisterForm() {
        const form = document.getElementById('registerForm');
        if (!form) return;

        form.addEventListener('submit', async e => {
            e.preventDefault();

            if (!document.getElementById('terms_agreement')?.checked) {
                showMessage('registerForm', 'Please agree to the Terms of Service to continue.', 'error');
                return;
            }

            const btn = form.querySelector('.auth-submit');
            setLoading(btn, true);
            clearMessages();

            const payload = {
                name:             form.querySelector('[name="name"]').value.trim(),
                email:            form.querySelector('[name="email"]').value.trim(),
                phone:            form.querySelector('[name="phone"]').value.trim(),
                password:         document.getElementById('register_password').value,
                confirm_password: document.getElementById('confirm_password').value,
                user_type:        form.querySelector('[name="user_type"]')?.value || 'donor',
                newsletter:       form.querySelector('[name="newsletter"]')?.checked ? 1 : 0,
                csrf_token:       document.getElementById('register_csrf_token')?.value || ''
            };

            try {
                const data = await authRequest('register', payload);
                if (data.success) {
                    showMessage('registerForm', data.message, 'success');
                    const dest = data.data?.redirect || redirectForRole(data.data?.user_type || 'donor');
                    setTimeout(() => { window.location.href = dest; }, 900);
                } else {
                    showMessage('registerForm', data.message || 'Registration failed.', 'error');
                    await loadCsrfToken();
                }
            } catch (err) {
                showMessage('registerForm', 'Network error. Please check your connection.', 'error');
            } finally {
                setLoading(btn, false);
            }
        });
    }

    // ── Forgot Password Modal ───────────────────────────────────────────────
    function initForgotPassword() {
        const link  = document.querySelector('.forgot-link');
        const modal = document.getElementById('forgotPasswordModal');
        const close = modal?.querySelector('.modal-close');
        const form  = document.getElementById('forgotPasswordForm');

        if (!link || !modal) return;

        link.addEventListener('click', e => {
            e.preventDefault();
            modal.classList.add('active');
            modal.style.display = 'flex';
        });

        close?.addEventListener('click', () => {
            modal.classList.remove('active');
            modal.style.display = 'none';
        });

        modal.addEventListener('click', e => {
            if (e.target === modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
        });

        form?.addEventListener('submit', async e => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            setLoading(btn, true);

            const payload = {
                email:      document.getElementById('forgot_email').value.trim(),
                csrf_token: document.getElementById('forgot_csrf_token')?.value || ''
            };

            try {
                const data = await authRequest('forgot-password', payload);
                const msg  = form.querySelector('.forgot-msg') || document.createElement('p');
                msg.className   = 'forgot-msg';
                msg.textContent = data.message;
                msg.style.cssText = 'margin-top:12px;color:' + (data.success ? '#15803d' : '#dc2626') + ';font-size:14px;';
                form.appendChild(msg);
                if (data.success) form.reset();
            } catch (err) {
                console.error('Forgot password error:', err);
            } finally {
                setLoading(btn, false);
            }
        });
    }

    // ── Init ────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        loadCsrfToken();
        initTabs();
        initPasswordToggles();
        initPasswordStrength();
        initPasswordMatch();
        initLoginForm();
        initRegisterForm();
        initForgotPassword();

        // If already logged in, redirect away from login page immediately
        fetch('api/auth.php?action=check-session')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.logged_in) {
                    const dest = d.data?.redirect || redirectForRole(d.data?.user_type || 'donor');
                    window.location.href = dest;
                }
            })
            .catch(() => {});
    });

})();
