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
        '        <a href="index.html" aria-label="Sri Datta Sai Manga Bharadwaja Trust – Home">',
        '          <img src="images/LogoNGO.png" alt="Sri Datta Sai Manga Bharadwaja Trust logo" class="logo-img">',
        '        </a>',
        '        <span class="logo-text">Sri Datta Sai Manga Bharadwaja<br>Spiritual and Social Service Trust</span>',
        '      </div>',

        '      <div class="nav-menu" id="navMenu" aria-hidden="true">',
        '        <a href="index.html"    class="nav-link" data-nav="index">Home</a>',
        '        <a href="about.html"   class="nav-link" data-nav="about">About</a>',
        '        <a href="services.html" class="nav-link" data-nav="services">Services</a>',
        '        <a href="contact.html" class="nav-link" data-nav="contact">Contact</a>',

        '        <div class="nav-more" id="navMore">',
        '          <button class="nav-more-trigger" id="nav-more-trigger"',
        '                  aria-expanded="false" aria-haspopup="true"',
        '                  aria-label="More pages">',
        '            More',
        '            <i class="fas fa-chevron-down nav-more-chevron" aria-hidden="true"></i>',
        '          </button>',
        '          <div class="nav-more-dropdown" id="nav-more-dropdown" role="menu" aria-hidden="true">',
        '            <a href="projects.html" class="nav-more-item" data-nav="projects" role="menuitem">',
        '              <i class="fas fa-project-diagram" aria-hidden="true"></i> Projects',
        '            </a>',
        '            <a href="events.html" class="nav-more-item" data-nav="events" role="menuitem">',
        '              <i class="fas fa-calendar-alt" aria-hidden="true"></i> Events',
        '            </a>',
        '            <a href="gallery.html" class="nav-more-item" data-nav="gallery" role="menuitem">',
        '              <i class="fas fa-images" aria-hidden="true"></i> Gallery',
        '            </a>',
        '            <a href="volunteer.html" class="nav-more-item" data-nav="volunteer" role="menuitem">',
        '              <i class="fas fa-hands-helping" aria-hidden="true"></i> Volunteer',
        '            </a>',
        '          </div>',
        '        </div>',

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
        '          <img src="images/LogoNGO.png" alt="Sri Datta Sai Manga Bharadwaja Trust" class="logo-img">',
        '          <span class="logo-text">Sri Datta Sai Manga Bharadwaja<br>Spiritual and Social Service Trust</span>',
        '        </div>',
        '        <p class="footer-description">',
        '          Dedicated to serving humanity with compassion and commitment.',
        '          Together, we can build a better tomorrow for those who need it most.',
        '        </p>',
        '        <div class="social-links">',
        '          <a href="https://www.facebook.com/profile.php?id=61580505505543" class="social-link" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>',
        '          <a href="#" class="social-link" aria-label="Twitter / X"><i class="fab fa-twitter" aria-hidden="true"></i></a>',
        '          <a href="https://www.instagram.com/sri_Datta_sai_manga_bharadwaja/" class="social-link" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram" aria-hidden="true"></i></a>',
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
        '          <li><a href="gallery.html">Gallery</a></li>',
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
        '        <p>&copy; <span id="footer-year">2026</span> Sri Datta Sai Manga Bharadwaja Spiritual and Social Service Trust. All rights reserved.</p>',
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
        '}',

        '/* ── nav-more (More dropdown) ─────────────────── */',
        '.nav-more {',
        '  position: relative;',
        '}',

        '.nav-more-trigger {',
        '  display: inline-flex;',
        '  align-items: center;',
        '  gap: 5px;',
        '  background: transparent;',
        '  border: none;',
        '  color: rgba(255,255,255,0.85);',
        '  font-size: inherit;',
        '  font-family: inherit;',
        '  font-weight: 500;',
        '  cursor: pointer;',
        '  padding: 4px 0;',
        '  letter-spacing: .02em;',
        '  white-space: nowrap;',
        '  transition: color .2s;',
        '  text-decoration: none;',
        '  position: relative;',
        '}',
        '.nav-more-trigger::after {',
        '  content: "";',
        '  position: absolute;',
        '  bottom: -2px;',
        '  left: 0;',
        '  right: 0;',
        '  height: 2px;',
        '  background: #B8860B;',
        '  border-radius: 2px;',
        '  transform: scaleX(0);',
        '  transition: transform .25s;',
        '}',
        '.nav-more-trigger:hover,',
        '.nav-more-trigger.active,',
        '.nav-more-trigger[aria-expanded="true"] {',
        '  color: #fff;',
        '}',
        '.nav-more-trigger:hover::after,',
        '.nav-more-trigger.active::after {',
        '  transform: scaleX(1);',
        '}',
        '.nav-more-chevron {',
        '  font-size: .65em;',
        '  transition: transform .2s;',
        '}',
        '.nav-more-trigger[aria-expanded="true"] .nav-more-chevron {',
        '  transform: rotate(180deg);',
        '}',

        '.nav-more-dropdown {',
        '  position: absolute;',
        '  top: calc(100% + 12px);',
        '  left: 50%;',
        '  transform: translateX(-50%) translateY(-8px);',
        '  min-width: 185px;',
        '  background: #fff;',
        '  border-radius: 10px;',
        '  box-shadow: 0 8px 28px rgba(0,0,0,0.16);',
        '  padding: 6px 0;',
        '  opacity: 0;',
        '  visibility: hidden;',
        '  transition: opacity .2s ease, transform .2s ease, visibility .2s;',
        '  z-index: 1030;',
        '}',
        '.nav-more-dropdown.is-open {',
        '  opacity: 1;',
        '  visibility: visible;',
        '  transform: translateX(-50%) translateY(0);',
        '}',

        '.nav-more-item {',
        '  display: flex;',
        '  align-items: center;',
        '  gap: 10px;',
        '  padding: 11px 18px;',
        '  color: #2C2A27;',
        '  text-decoration: none;',
        '  font-size: .9rem;',
        '  font-weight: 500;',
        '  transition: background .15s, color .15s;',
        '  line-height: 1.2;',
        '}',
        '.nav-more-item i { width: 16px; text-align: center; }',
        '.nav-more-item:hover {',
        '  background: #F5F0EA;',
        '  color: #C96B0A;',
        '}',
        '.nav-more-item.active {',
        '  color: #C96B0A;',
        '  background: #FDF6EE;',
        '}',

        '/* Mobile: More dropdown becomes accordion inside slide-out menu */',
        '@media (max-width: 1024px) {',
        '  .nav-more {',
        '    width: 100%;',
        '  }',
        '  .nav-more-trigger {',
        '    width: 100%;',
        '    padding: 16px 24px;',
        '    justify-content: space-between;',
        '    font-size: 1.05rem;',
        '    color: rgba(255,255,255,0.9);',
        '    border-bottom: 1px solid rgba(255,255,255,0.1);',
        '  }',
        '  .nav-more-trigger::after { display: none; }',
        '  .nav-more-trigger:hover,',
        '  .nav-more-trigger.active,',
        '  .nav-more-trigger[aria-expanded="true"] {',
        '    color: #fff;',
        '    background: rgba(255,255,255,0.05);',
        '  }',
        '  .nav-more-dropdown {',
        '    position: static;',
        '    opacity: 1;',
        '    visibility: visible;',
        '    transform: none;',
        '    box-shadow: none;',
        '    border-radius: 0;',
        '    background: rgba(255,255,255,0.05);',
        '    padding: 0;',
        '    display: none;',
        '  }',
        '  .nav-more-dropdown.is-open { display: block; }',
        '  .nav-more-item {',
        '    color: rgba(255,255,255,0.85);',
        '    padding: 14px 24px 14px 40px;',
        '    font-size: 1rem;',
        '    border-bottom: 1px solid rgba(255,255,255,0.06);',
        '  }',
        '  .nav-more-item:hover {',
        '    background: rgba(255,255,255,0.1);',
        '    color: #fff;',
        '  }',
        '  .nav-more-item.active { color: #FFD070; background: rgba(255,208,112,0.1); }',
        '}'
    ].join('\n');

    // ------------------------------------------------------------------
    // POSTER POPUP
    // ------------------------------------------------------------------
    var POSTER_HTML = [
        '<div id="poster-popup" role="dialog" aria-modal="true" aria-label="Welcome" aria-hidden="true">',
        '  <div class="poster-backdrop"></div>',
        '  <div class="poster-box">',
        '    <button class="poster-close" aria-label="Close poster">&times;</button>',
        '    <img src="images/Poster.jpeg" alt="Poster" class="poster-img">',
        '  </div>',
        '</div>'
    ].join('\n');

    var POSTER_CSS = [
        '/* ── Poster popup ───────────────────────────────── */',
        '#poster-popup {',
        '  position: fixed;',
        '  inset: 0;',
        '  z-index: 3000;',
        '  display: flex;',
        '  align-items: center;',
        '  justify-content: center;',
        '  padding: 1rem;',
        '  box-sizing: border-box;',
        '  visibility: hidden;',
        '  pointer-events: none;',
        '}',
        '#poster-popup.poster-open {',
        '  visibility: visible;',
        '  pointer-events: auto;',
        '}',

        '.poster-backdrop {',
        '  position: absolute;',
        '  inset: 0;',
        '  background: rgba(0,0,0,0.78);',
        '  opacity: 0;',
        '  transition: opacity 0.35s ease;',
        '}',
        '#poster-popup.poster-open .poster-backdrop {',
        '  opacity: 1;',
        '}',

        '.poster-box {',
        '  position: relative;',
        '  z-index: 1;',
        '  max-width: min(540px, 94vw);',
        '  max-height: 92vh;',
        '  display: flex;',
        '  align-items: center;',
        '  justify-content: center;',
        '  opacity: 0;',
        '  transform: scale(0.9);',
        '  transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.16,1,0.3,1);',
        '}',
        '#poster-popup.poster-open .poster-box {',
        '  opacity: 1;',
        '  transform: scale(1);',
        '}',

        '.poster-img {',
        '  display: block;',
        '  width: 100%;',
        '  max-height: 88vh;',
        '  object-fit: contain;',
        '  border-radius: 10px;',
        '  box-shadow: 0 24px 64px rgba(0,0,0,0.55);',
        '  cursor: zoom-in;',
        '  transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);',
        '  transform-origin: center center;',
        '}',
        '.poster-img.poster-zoomed {',
        '  transform: scale(1.85);',
        '  cursor: zoom-out;',
        '}',

        '.poster-close {',
        '  position: absolute;',
        '  top: -14px;',
        '  right: -14px;',
        '  width: 36px;',
        '  height: 36px;',
        '  border-radius: 50%;',
        '  background: #fff;',
        '  border: none;',
        '  color: #111;',
        '  font-size: 1.3rem;',
        '  line-height: 1;',
        '  cursor: pointer;',
        '  display: flex;',
        '  align-items: center;',
        '  justify-content: center;',
        '  box-shadow: 0 4px 14px rgba(0,0,0,0.35);',
        '  transition: background 0.18s, transform 0.18s;',
        '  z-index: 2;',
        '}',
        '.poster-close:hover {',
        '  background: #f0f0f0;',
        '  transform: scale(1.1);',
        '}',
        '.poster-close:focus-visible {',
        '  outline: 2px solid #01696f;',
        '  outline-offset: 2px;',
        '}'
    ].join('\n');

    function initPosterPopup() {
        // Homepage only
        var page = window.location.pathname.split('/').pop();
        if (page !== '' && page !== 'index.html') return;

        // Show only once per browser session
        if (sessionStorage.getItem('poster_seen')) return;

        // Inject HTML + CSS
        document.body.insertAdjacentHTML('beforeend', POSTER_HTML);
        var style = document.createElement('style');
        style.id = 'poster-popup-styles';
        style.textContent = POSTER_CSS;
        document.head.appendChild(style);

        var popup   = document.getElementById('poster-popup');
        var backdrop = popup.querySelector('.poster-backdrop');
        var box      = popup.querySelector('.poster-box');
        var closeBtn = popup.querySelector('.poster-close');

        function openPopup() {
            popup.classList.add('poster-open');
            popup.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            closeBtn.focus();
        }

        function closePopup() {
            popup.classList.remove('poster-open');
            popup.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            sessionStorage.setItem('poster_seen', '1');
        }

        // Close on backdrop click (not on the image/box itself)
        backdrop.addEventListener('click', closePopup);

        // Close on X button
        closeBtn.addEventListener('click', closePopup);

        // Close on Escape key
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape' && popup.classList.contains('poster-open')) {
                closePopup();
                document.removeEventListener('keydown', handler);
            }
        });

        // Clicks inside the box should NOT bubble to backdrop
        box.addEventListener('click', function (e) { e.stopPropagation(); });

        // Double-click toggles zoom on the poster image
        var img = popup.querySelector('.poster-img');
        img.addEventListener('dblclick', function (e) {
            e.stopPropagation();
            img.classList.toggle('poster-zoomed');
        });

        // Show after a short delay so the page settles first
        setTimeout(openPopup, 500);
    }

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

        // Main nav links
        var links = document.querySelectorAll('.nav-menu .nav-link[data-nav]');
        for (var i = 0; i < links.length; i++) {
            links[i].classList.toggle('active', links[i].getAttribute('data-nav') === key);
        }

        // More dropdown items + trigger
        var moreItems = document.querySelectorAll('.nav-more-item[data-nav]');
        var moreTrigger = document.getElementById('nav-more-trigger');
        var moreActive = false;
        for (var j = 0; j < moreItems.length; j++) {
            var isActive = moreItems[j].getAttribute('data-nav') === key;
            moreItems[j].classList.toggle('active', isActive);
            if (isActive) moreActive = true;
        }
        if (moreTrigger) moreTrigger.classList.toggle('active', moreActive);
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
    // MORE DROPDOWN
    // ------------------------------------------------------------------

    function initMoreDropdown() {
        var trigger  = document.getElementById('nav-more-trigger');
        var dropdown = document.getElementById('nav-more-dropdown');
        if (!trigger || !dropdown) return;

        function openMore() {
            dropdown.classList.add('is-open');
            dropdown.setAttribute('aria-hidden', 'false');
            trigger.setAttribute('aria-expanded', 'true');
        }
        function closeMore() {
            dropdown.classList.remove('is-open');
            dropdown.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.contains('is-open') ? closeMore() : openMore();
        });
        // Clicks inside the dropdown should not bubble up and close it
        dropdown.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', closeMore);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMore();
        });
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
                // auth.php handles logout via GET (action=logout lives in the GET block)
                fetch('api/auth.php?action=logout', { credentials: 'include' })
                    .then(function () {
                        window.currentUser = null;
                        window.location.href = 'login.html';
                    })
                    .catch(function () {
                        // API unreachable — clear local state and redirect anyway
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
            initMoreDropdown();
            initSession();
        }

        // Poster popup — once per session
        initPosterPopup();
    }

    // Script is at end of <body>, so placeholders already exist in the DOM.
    injectComponents();

    // Expose a refresh hook so nav-session.js shim (and dashboard pages) can re-check.
    window.refreshNavSession = initSession;

})();
