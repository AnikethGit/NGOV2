/**
 * Global site components — header, footer, and auth-aware nav
 *
 * Include this script BEFORE main.js so that header/footer elements exist
 * in the DOM when main.js initialises event listeners.
 *
 * Usage in any page:
 *   <div id="global-header"></div>   ← first element inside <body>
 *   <div id="global-footer"></div>   ← last element before </body>
 *   <script src="js/components.js"></script>
 *   <script src="js/main.js"></script>
 *
 * Auth behaviour (no extra script needed):
 *   Logged out → "Login" button shown
 *   Logged in  → "Login" hidden; user first-name button shown with a
 *                dropdown containing Dashboard and Logout links
 */

(function () {
    'use strict';

    // ------------------------------------------------------------------
    // HEADER TEMPLATE
    // ------------------------------------------------------------------
    var HEADER_HTML = [
        '<header class="header" role="banner">',
        '  <nav class="navbar" role="navigation" aria-label="Main navigation">',
        '    <div class="nav-container">',

        '      <div class="nav-logo">',
        '        <a href="index.html" aria-label="Sri Dutta Sai Manga Bharadwaja Trust – Home">',
        '          <img src="images/LogoNGO.png" alt="Sri Dutta Sai Manga Bharadwaja Trust logo" class="logo-img">',
        '        </a>',
        '        <span class="logo-text">Sri Dutta Sai Manga Bharadwaja<br>Spiritual and Social Service Trust</span>',
        '      </div>',

        '      <div class="nav-menu" id="navMenu" aria-hidden="true">',
        '        <a href="index.html"    class="nav-link" data-nav="index">Home</a>',
        '        <a href="about.html"   class="nav-link" data-nav="about">About</a>',
        '        <a href="services.html" class="nav-link" data-nav="services">Services</a>',
        '        <a href="projects.html" class="nav-link" data-nav="projects">Projects</a>',
        '        <a href="events.html"  class="nav-link" data-nav="events">Events</a>',
        '        <a href="contact.html" class="nav-link" data-nav="contact">Contact</a>',

        '        <div class="nav-actions">',
        '          <a href="donate.html" class="btn btn-primary">Donate Now</a>',

        '          <!-- Logged-out state -->',
        '          <a href="login.html" class="btn btn-outline" id="nav-login-btn">Login</a>',

        '          <!-- Logged-in state (hidden until session confirmed) -->',
        '          <div class="nav-user-menu" id="nav-user-menu" aria-hidden="true">',
        '            <button class="nav-user-trigger" id="nav-user-trigger"',
        '                    aria-expanded="false" aria-haspopup="true"',
        '                    aria-label="Account menu">',
        '              <i class="fas fa-user-circle" aria-hidden="true"></i>',
        '              <span id="nav-user-name">Account</span>',
        '              <i class="fas fa-chevron-down nav-chevron" aria-hidden="true"></i>',
        '            </button>',
        '            <div class="nav-user-dropdown" id="nav-user-dropdown"',
        '                 role="menu" aria-hidden="true">',
        '              <a href="#" id="nav-dashboard-link"',
        '                 class="nav-dropdown-item" role="menuitem">',
        '                <i class="fas fa-tachometer-alt" aria-hidden="true"></i>',
        '                Dashboard',
        '              </a>',
        '              <button class="nav-dropdown-item nav-dropdown-logout"',
        '                      id="nav-logout-btn" role="menuitem">',
        '                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>',
        '                Logout',
        '              </button>',
        '            </div>',
        '          </div>',

        '        </div>',
        '      </div>',

        '      <div class="mobile-overlay" id="mobileOverlay"></div>',

        '      <button class="hamburger" id="hamburger"',
        '              aria-label="Toggle navigation menu"',
        '              aria-expanded="false"',
        '              aria-controls="navMenu">',
        '        <span></span><span></span><span></span>',
        '      </button>',

        '    </div>',
        '  </nav>',
        '</header>'
    ].join('\n');

    // ------------------------------------------------------------------
    // FOOTER TEMPLATE
    // ------------------------------------------------------------------
    var FOOTER_HTML = [
        '<footer class="footer" role="contentinfo">',
        '  <div class="container">',
        '    <div class="footer-content">',

        '      <div class="footer-section">',
        '        <div class="footer-logo">',
        '          <img src="images/LogoNGO.png" alt="Sri Dutta Sai Manga Bharadwaja Trust" class="logo-img">',
        '          <span class="logo-text">Sri Dutta Sai Manga Bharadwaja<br>Spiritual and Social Service Trust</span>',
        '        </div>',
        '        <p class="footer-description">',
        '          Dedicated to serving humanity with compassion and commitment.',
        '          Together, we can build a better tomorrow for those who need it most.',
        '        </p>',
        '        <div class="social-links">',
        '          <a href="https://www.facebook.com/profile.php?id=61580505505543" class="social-link" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>',
        '          <a href="#" class="social-link" aria-label="Twitter / X"><i class="fab fa-twitter" aria-hidden="true"></i></a>',
        '          <a href="https://www.instagram.com/sri_dutta_sai_manga_bharadwaja/" class="social-link" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram" aria-hidden="true"></i></a>',
        '          <a href="#" class="social-link" aria-label="YouTube"><i class="fab fa-youtube" aria-hidden="true"></i></a>',
        '        </div>',
        '      </div>',

        '      <div class="footer-section">',
        '        <h3>Quick Links</h3>',
        '        <ul class="footer-links">',
        '          <li><a href="index.html">Home</a></li>',
        '          <li><a href="about.html">About Us</a></li>',
        '          <li><a href="services.html">Our Services</a></li>',
        '          <li><a href="projects.html">Projects</a></li>',
        '          <li><a href="events.html">Events</a></li>',
        '          <li><a href="contact.html">Contact</a></li>',
        '        </ul>',
        '      </div>',

        '      <div class="footer-section">',
        '        <h3>Our Services</h3>',
        '        <ul class="footer-links">',
        '          <li><a href="services.html#feeding">Poor Feeding</a></li>',
        '          <li><a href="services.html#education">Education Support</a></li>',
        '          <li><a href="services.html#medical">Medical Camps</a></li>',
        '          <li><a href="services.html#disaster">Disaster Relief</a></li>',
        '        </ul>',
        '      </div>',

        '      <div class="footer-section">',
        '        <h3>Get Involved</h3>',
        '        <ul class="footer-links">',
        '          <li><a href="donate.html">Make a Donation</a></li>',
        '          <li><a href="volunteer.html">Volunteer</a></li>',
        '          <li><a href="events.html">Join Events</a></li>',
        '          <li><a href="contact.html#partnership">Partnership</a></li>',
        '        </ul>',
        '      </div>',

        '      <div class="footer-section">',
        '        <h3>Contact Info</h3>',
        '        <div class="contact-info">',
        '          <div class="contact-item">',
        '            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>',
        '            <span>Shirdi, Maharashtra, India</span>',
        '          </div>',
        '          <div class="contact-item">',
        '            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>',
        '            <span>Ganagapur, Karnataka, India</span>',
        '          </div>',
        '          <div class="contact-item">',
        '            <i class="fas fa-phone" aria-hidden="true"></i>',
        '            <span>+91 7893601789 &amp; +91 9491062255</span>',
        '          </div>',
        '          <div class="contact-item">',
        '            <i class="fas fa-phone" aria-hidden="true"></i>',
        '            <span>+91 9440916031 &amp; +91 9398557142</span>',
        '          </div>',
        '          <div class="contact-item">',
        '            <i class="fas fa-envelope" aria-hidden="true"></i>',
        '            <span>info@saisevafoundation.org</span>',
        '          </div>',
        '        </div>',
        '      </div>',

        '    </div>',

        '    <div class="footer-bottom">',
        '      <div class="footer-bottom-content">',
        '        <p>&copy; <span id="footer-year">2026</span> Sri Dutta Sai Manga Bharadwaja Spiritual and Social Service Trust. All rights reserved.</p>',
        '        <div class="footer-bottom-links">',
        '          <a href="policy.html">Privacy Policy</a>',
        '          <a href="terms.html">Terms of Service</a>',
        '          <a href="about.html#transparency">Transparency</a>',
        '        </div>',
        '      </div>',
        '    </div>',

        '  </div>',
        '</footer>'
    ].join('\n');

    // ------------------------------------------------------------------
    // USER DROPDOWN CSS  (injected once into <head>)
    // ------------------------------------------------------------------
    var DROPDOWN_CSS = [
        '/* ── nav-user-menu ─────────────────────────────── */',
        '.nav-user-menu {',
        '  position: relative;',
        '  display: none;',        /* hidden until session confirmed */
        '}',
        '.nav-user-menu.is-visible {',
        '  display: flex;',
        '  align-items: center;',
        '}',

        '.nav-user-trigger {',
        '  display: inline-flex;',
        '  align-items: center;',
        '  gap: 7px;',
        '  background: transparent;',
        '  border: 2px solid rgba(255,255,255,0.55);',
        '  color: #fff;',
        '  border-radius: 8px;',
        '  padding: 7px 14px;',
        '  font-size: inherit;',
        '  font-family: inherit;',
        '  font-weight: 500;',
        '  cursor: pointer;',
        '  white-space: nowrap;',
        '  transition: border-color .2s, background .2s;',
        '  line-height: 1.4;',
        '}',
        '.nav-user-trigger:hover,',
        '.nav-user-trigger[aria-expanded="true"] {',
        '  border-color: #fff;',
        '  background: rgba(255,255,255,0.15);',
        '}',
        '.nav-user-trigger:focus-visible {',
        '  outline: 2px solid #fff;',
        '  outline-offset: 2px;',
        '}',
        '.nav-chevron {',
        '  font-size: .7em;',
        '  transition: transform .2s;',
        '}',
        '.nav-user-trigger[aria-expanded="true"] .nav-chevron {',
        '  transform: rotate(180deg);',
        '}',

        '.nav-user-dropdown {',
        '  position: absolute;',
        '  top: calc(100% + 10px);',
        '  right: 0;',
        '  min-width: 185px;',
        '  background: #fff;',
        '  border-radius: 10px;',
        '  box-shadow: 0 8px 28px rgba(0,0,0,0.16);',
        '  padding: 6px 0;',
        '  margin: 0;',
        '  opacity: 0;',
        '  visibility: hidden;',
        '  transform: translateY(-8px);',
        '  transition: opacity .2s ease, transform .2s ease, visibility .2s;',
        '  z-index: 1030;',
        '  list-style: none;',
        '}',
        '.nav-user-dropdown.is-open {',
        '  opacity: 1;',
        '  visibility: visible;',
        '  transform: translateY(0);',
        '}',

        '.nav-dropdown-item {',
        '  display: flex;',
        '  align-items: center;',
        '  gap: 10px;',
        '  width: 100%;',
        '  padding: 11px 18px;',
        '  color: #2C2A27;',
        '  text-decoration: none;',
        '  font-size: .9rem;',
        '  font-family: inherit;',
        '  font-weight: 500;',
        '  background: none;',
        '  border: none;',
        '  cursor: pointer;',
        '  text-align: left;',
        '  line-height: 1.2;',
        '  transition: background .15s, color .15s;',
        '}',
        '.nav-dropdown-item i { width: 16px; text-align: center; }',
        '.nav-dropdown-item:hover {',
        '  background: #F5F0EA;',
        '  color: #C96B0A;',
        '}',
        '.nav-dropdown-logout {',
        '  color: #C0152F;',
        '  border-top: 1px solid #EAE3D8;',
        '  margin-top: 4px;',
        '}',
        '.nav-dropdown-logout:hover {',
        '  background: #FFF5F5;',
        '  color: #C0152F;',
        '}',

        '/* Mobile: dropdown sits inside the slide-out menu */',
        '@media (max-width: 1024px) {',
        '  .nav-user-menu.is-visible {',
        '    flex-direction: column;',
        '    width: 100%;',
        '    align-items: stretch;',
        '  }',
        '  .nav-user-trigger {',
        '    width: 100%;',
        '    justify-content: center;',
        '    padding: 16px;',
        '    border-radius: 8px;',
        '  }',
        '  .nav-user-dropdown {',
        '    position: static;',
        '    opacity: 1;',
        '    visibility: visible;',
        '    transform: none;',
        '    box-shadow: none;',
        '    border-radius: 8px;',
        '    background: rgba(255,255,255,0.08);',
        '    border: 1px solid rgba(255,255,255,0.15);',
        '    margin-top: 8px;',
        '    display: none;',      /* toggled by .is-open on mobile too */
        '  }',
        '  .nav-user-dropdown.is-open { display: block; }',
        '  .nav-dropdown-item {',
        '    color: rgba(255,255,255,0.9);',
        '    padding: 14px 16px;',
        '    font-size: 1rem;',
        '  }',
        '  .nav-dropdown-item:hover {',
        '    background: rgba(255,255,255,0.12);',
        '    color: #fff;',
        '  }',
        '  .nav-dropdown-logout { color: #FF9090; border-top-color: rgba(255,255,255,0.15); }',
        '  .nav-dropdown-logout:hover { background: rgba(255,80,80,0.15); color: #FF9090; }',
        '}'
    ].join('\n');

    // ------------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------------

    function replacePlaceholder(placeholder, html) {
        placeholder.insertAdjacentHTML('beforebegin', html);
        placeholder.parentNode.removeChild(placeholder);
    }

    function currentPageKey() {
        var filename = window.location.pathname.split('/').pop();
        if (!filename || filename === '/') filename = 'index.html';
        return filename.replace(/\.html?$/i, '') || 'index';
    }

    function highlightActiveLink() {
        var key = currentPageKey();
        var links = document.querySelectorAll('.nav-menu .nav-link[data-nav]');
        for (var i = 0; i < links.length; i++) {
            links[i].classList.toggle('active', links[i].getAttribute('data-nav') === key);
        }
    }

    function updateYear() {
        var el = document.getElementById('footer-year');
        if (el) el.textContent = new Date().getFullYear();
    }

    function injectDropdownStyles() {
        if (document.getElementById('nav-user-menu-styles')) return;
        var style = document.createElement('style');
        style.id = 'nav-user-menu-styles';
        style.textContent = DROPDOWN_CSS;
        document.head.appendChild(style);
    }

    // ------------------------------------------------------------------
    // AUTH-AWARE NAV SESSION
    // ------------------------------------------------------------------

    function initSession() {
        var loginBtn   = document.getElementById('nav-login-btn');
        var userMenu   = document.getElementById('nav-user-menu');
        var userNameEl = document.getElementById('nav-user-name');
        var trigger    = document.getElementById('nav-user-trigger');
        var dropdown   = document.getElementById('nav-user-dropdown');
        var dashLink   = document.getElementById('nav-dashboard-link');
        var logoutBtn  = document.getElementById('nav-logout-btn');

        if (!loginBtn || !userMenu) return;

        // ── Dropdown toggle ──────────────────────────────────────────────
        function openDropdown() {
            dropdown.classList.add('is-open');
            dropdown.setAttribute('aria-hidden', 'false');
            trigger.setAttribute('aria-expanded', 'true');
        }
        function closeDropdown() {
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
        }

        if (trigger && dropdown) {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdown.classList.contains('is-open') ? closeDropdown() : openDropdown();
            });
            document.addEventListener('click', closeDropdown);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeDropdown();
            });
            // Keep clicks inside dropdown from closing it
            dropdown.addEventListener('click', function (e) { e.stopPropagation(); });
        }

        // ── Logout ───────────────────────────────────────────────────────
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function () {
                fetch('api/auth.php?action=logout', { method: 'POST' })
                    .catch(function () {})
                    .finally(function () {
                        window.currentUser = null;
                        window.location.href = 'login.html';
                    });
            });
        }

        // ── Session check ────────────────────────────────────────────────
        fetch('api/auth.php?action=check-session')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.data) {
                    var d         = data.data;
                    var user      = d.user || d;
                    var firstName = (user.name || user.full_name || user.user_name || 'Account')
                                        .trim().split(' ')[0];
                    var role      = d.user_type || user.role || 'donor';
                    var dashboard = role === 'admin'     ? 'admin-dashboard.html'
                                  : role === 'volunteer' ? 'volunteer-dashboard.html'
                                  :                        'donor-dashboard.html';

                    // Show user menu, hide login button
                    loginBtn.style.display = 'none';
                    userMenu.classList.add('is-visible');
                    userMenu.setAttribute('aria-hidden', 'false');
                    if (userNameEl) userNameEl.textContent = firstName;
                    if (dashLink)   dashLink.href = dashboard;

                    // Expose for other scripts (dashboards, etc.)
                    window.currentUser = Object.assign({}, user, { role: role, dashboard: dashboard });

                } else {
                    // Not logged in — defaults already correct, but reset just in case
                    loginBtn.style.display = '';
                    userMenu.classList.remove('is-visible');
                    userMenu.setAttribute('aria-hidden', 'true');
                    window.currentUser = null;
                }
            })
            .catch(function () {
                // API unreachable (e.g. static preview) — leave login button visible
            });
    }

    // ------------------------------------------------------------------
    // INJECTION + BOOT
    // ------------------------------------------------------------------

    function injectComponents() {
        var headerPH = document.getElementById('global-header');
        if (headerPH) {
            replacePlaceholder(headerPH, HEADER_HTML);
            highlightActiveLink();
        }

        var footerPH = document.getElementById('global-footer');
        if (footerPH) {
            replacePlaceholder(footerPH, FOOTER_HTML);
            updateYear();
        }

        // Inject dropdown CSS and start session check whenever there's a header
        if (document.getElementById('nav-user-menu')) {
            injectDropdownStyles();
            initSession();
        }
    }

    // Script is at end of <body>, so placeholders already exist in the DOM.
    injectComponents();

    // Expose a refresh hook so nav-session.js shim (and dashboard pages) can re-check.
    window.refreshNavSession = initSession;

})();
