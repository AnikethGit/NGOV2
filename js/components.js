/**
 * Global site components — header and footer injection
 *
 * Include this script BEFORE main.js so that header/footer elements exist
 * in the DOM when main.js initialises event listeners.
 *
 * Usage in any page:
 *   <div id="global-header"></div>   ← first element inside <body>
 *   <div id="global-footer"></div>   ← last element before </body>
 *   <script src="js/components.js"></script>
 *   <script src="js/main.js"></script>
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
        '        <span class="logo-text">Sri Dutta Sai Manga Bharadwaja Trust</span>',
        '      </div>',
        '',
        '      <div class="nav-menu" id="navMenu" aria-hidden="true">',
        '        <a href="index.html"    class="nav-link" data-nav="index">Home</a>',
        '        <a href="about.html"   class="nav-link" data-nav="about">About</a>',
        '        <a href="services.html" class="nav-link" data-nav="services">Services</a>',
        '        <a href="projects.html" class="nav-link" data-nav="projects">Projects</a>',
        '        <a href="events.html"  class="nav-link" data-nav="events">Events</a>',
        '        <a href="contact.html" class="nav-link" data-nav="contact">Contact</a>',
        '        <div class="nav-actions">',
        '          <a href="donate.html" class="btn btn-primary">Donate Now</a>',
        '          <a href="login.html"  class="btn btn-outline">Login</a>',
        '        </div>',
        '      </div>',
        '',
        '      <div class="mobile-overlay" id="mobileOverlay"></div>',
        '',
        '      <button class="hamburger" id="hamburger"',
        '              aria-label="Toggle navigation menu"',
        '              aria-expanded="false"',
        '              aria-controls="navMenu">',
        '        <span></span>',
        '        <span></span>',
        '        <span></span>',
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
        '',
        '      <!-- Brand & social -->',
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
        '',
        '      <!-- Quick links -->',
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
        '',
        '      <!-- Services -->',
        '      <div class="footer-section">',
        '        <h3>Our Services</h3>',
        '        <ul class="footer-links">',
        '          <li><a href="services.html#feeding">Poor Feeding</a></li>',
        '          <li><a href="services.html#education">Education Support</a></li>',
        '          <li><a href="services.html#medical">Medical Camps</a></li>',
        '          <li><a href="services.html#disaster">Disaster Relief</a></li>',
        '        </ul>',
        '      </div>',
        '',
        '      <!-- Get involved -->',
        '      <div class="footer-section">',
        '        <h3>Get Involved</h3>',
        '        <ul class="footer-links">',
        '          <li><a href="donate.html">Make a Donation</a></li>',
        '          <li><a href="volunteer.html">Volunteer</a></li>',
        '          <li><a href="events.html">Join Events</a></li>',
        '          <li><a href="contact.html#partnership">Partnership</a></li>',
        '        </ul>',
        '      </div>',
        '',
        '      <!-- Contact info -->',
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
        '',
        '    </div><!-- /.footer-content -->',
        '',
        '    <div class="footer-bottom">',
        '      <div class="footer-bottom-content">',
        '        <p>&copy; <span id="footer-year">2025</span> Sri Dutta Sai Manga Bharadwaja Spiritual and Social Service Trust. All rights reserved.</p>',
        '        <div class="footer-bottom-links">',
        '          <a href="policy.html">Privacy Policy</a>',
        '          <a href="terms.html">Terms of Service</a>',
        '          <a href="about.html#transparency">Transparency</a>',
        '        </div>',
        '      </div>',
        '    </div>',
        '',
        '  </div><!-- /.container -->',
        '</footer>'
    ].join('\n');

    // ------------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------------

    /** Replace a placeholder element with raw HTML string, return first inserted node. */
    function replacePlaceholder(placeholder, html) {
        placeholder.insertAdjacentHTML('beforebegin', html);
        placeholder.parentNode.removeChild(placeholder);
    }

    /**
     * Derive the bare page name from the current URL.
     * e.g. "/ngov/about.html" → "about"
     *      "/"               → "index"
     */
    function currentPageKey() {
        var path = window.location.pathname;
        var filename = path.split('/').pop();           // "about.html" or ""
        if (!filename || filename === '/') filename = 'index.html';
        return filename.replace(/\.html?$/i, '') || 'index';
    }

    /** Mark the matching nav link as active; clear any previously active ones. */
    function highlightActiveLink() {
        var key = currentPageKey();
        var links = document.querySelectorAll('.nav-menu .nav-link[data-nav]');
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            if (link.getAttribute('data-nav') === key) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        }
    }

    /** Update the copyright year dynamically. */
    function updateYear() {
        var el = document.getElementById('footer-year');
        if (el) el.textContent = new Date().getFullYear();
    }

    // ------------------------------------------------------------------
    // INJECTION
    // ------------------------------------------------------------------

    function injectComponents() {
        var headerPlaceholder = document.getElementById('global-header');
        if (headerPlaceholder) {
            replacePlaceholder(headerPlaceholder, HEADER_HTML);
            highlightActiveLink();
        }

        var footerPlaceholder = document.getElementById('global-footer');
        if (footerPlaceholder) {
            replacePlaceholder(footerPlaceholder, FOOTER_HTML);
            updateYear();
        }
    }

    // Run immediately — script is loaded at end of <body> so the
    // placeholder divs already exist in the DOM at this point.
    injectComponents();

})();
