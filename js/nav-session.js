/**
 * nav-session.js
 * Included on EVERY page (except login.html).
 * Checks session and updates the nav bar:
 *   - Logged out : shows Login button
 *   - Logged in  : shows user first-name + Dashboard link + Logout button
 */

(function () {
    'use strict';

    async function updateNav() {
        try {
            const res  = await fetch('api/auth.php?action=check-session');
            const data = await res.json();

            // Selector covers both desktop and mobile nav login buttons
            const loginLinks = document.querySelectorAll(
                'a[href="login.html"], a[href="./login.html"]'
            );

            if (data.success && data.data) {
                const user      = data.data;
                const firstName = (user.user_name || 'Account').split(' ')[0];
                const dashboard = user.user_type === 'volunteer'
                    ? 'volunteer-dashboard.html'
                    : 'donor-dashboard.html';

                loginLinks.forEach(link => {
                    // Replace the login link with a dashboard link
                    link.textContent = firstName;
                    link.href        = dashboard;
                    link.title       = 'Go to your dashboard';
                    link.classList.remove('btn-outline');
                    link.classList.add('btn-user');

                    // Insert a logout button right after
                    if (!link.nextElementSibling?.classList.contains('btn-logout')) {
                        const logoutBtn = document.createElement('button');
                        logoutBtn.className   = 'btn-logout btn btn-outline';
                        logoutBtn.textContent = 'Logout';
                        logoutBtn.style.cssText = 'margin-left:8px;cursor:pointer;font-size:inherit;';
                        logoutBtn.addEventListener('click', handleLogout);
                        link.insertAdjacentElement('afterend', logoutBtn);
                    }
                });

                // Store session info globally for dashboard pages
                window.currentUser = user;

            } else {
                // Not logged in — restore login links to default text if they were changed
                loginLinks.forEach(link => {
                    if (link.classList.contains('btn-user')) {
                        link.textContent = 'Login';
                        link.href        = 'login.html';
                        link.classList.remove('btn-user');
                        link.classList.add('btn-outline');
                        link.nextElementSibling?.classList.contains('btn-logout') &&
                            link.nextElementSibling.remove();
                    }
                });
                window.currentUser = null;
            }
        } catch (e) {
            console.warn('Session check failed:', e);
        }
    }

    async function handleLogout() {
        try {
            await fetch('api/auth.php?action=logout', { method: 'POST' });
        } catch (e) { /* silent */ }
        window.location.href = 'login.html';
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateNav);
    } else {
        updateNav();
    }

    // Expose for manual refresh if needed
    window.refreshNavSession = updateNav;

})();
