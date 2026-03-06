/**
 * Main JavaScript for Sai Seva Foundation
 * Handles navigation, mobile menu, and common interactions
 * FIXED: Hamburger menu now works correctly and doesn't freeze page
 */

(function() {
    'use strict';

    // =================================================================
    // MOBILE NAVIGATION - FIXED
    // =================================================================

    /**
     * Initialize mobile navigation
     */
    function initMobileNav() {
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        const navLinks = document.querySelectorAll('.nav-link');
        const body = document.body;
        const header = document.querySelector('.header');

        if (!hamburger || !navMenu) {
            console.warn('Mobile navigation elements not found');
            return;
        }

        // Initialize ARIA attributes
        hamburger.setAttribute('aria-label', 'Toggle navigation menu');
        hamburger.setAttribute('aria-expanded', 'false');
        hamburger.setAttribute('aria-controls', 'nav-menu');
        navMenu.setAttribute('id', 'nav-menu');
        navMenu.setAttribute('aria-hidden', 'true');

        /**
         * Toggle mobile menu
         */
        function toggleMobileMenu(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            const isActive = hamburger.classList.contains('active');
            
            if (isActive) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        }

        /**
         * Open mobile menu
         */
        function openMobileMenu() {
            // Add active classes
            hamburger.classList.add('active');
            navMenu.classList.add('active');
            body.classList.add('nav-open');
            
            // Update ARIA attributes
            hamburger.setAttribute('aria-expanded', 'true');
            hamburger.setAttribute('aria-label', 'Close navigation menu');
            navMenu.setAttribute('aria-hidden', 'false');
            
            // Prevent body scroll on mobile
            const scrollY = window.scrollY;
            body.style.position = 'fixed';
            body.style.top = `-${scrollY}px`;
            body.style.width = '100%';
            
            console.log('Mobile menu opened');
        }

        /**
         * Close mobile menu
         */
        function closeMobileMenu() {
            // Get current scroll position before changing position
            const scrollY = body.style.top;
            
            // Remove active classes
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            body.classList.remove('nav-open');
            
            // Restore body scroll
            body.style.position = '';
            body.style.top = '';
            body.style.width = '';
            
            // Restore scroll position
            if (scrollY) {
                window.scrollTo(0, parseInt(scrollY || '0') * -1);
            }
            
            // Update ARIA attributes
            hamburger.setAttribute('aria-expanded', 'false');
            hamburger.setAttribute('aria-label', 'Open navigation menu');
            navMenu.setAttribute('aria-hidden', 'true');
            
            console.log('Mobile menu closed');
        }

        // Hamburger button click - CRITICAL FIX
        hamburger.addEventListener('click', toggleMobileMenu, false);

        // Prevent hamburger clicks from bubbling
        hamburger.addEventListener('touchstart', function(e) {
            e.stopPropagation();
        }, { passive: true });

        // Close menu when clicking on nav links
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Don't prevent default for anchor links
                closeMobileMenu();
            }, false);
        });

        // Close menu when clicking outside (on the overlay/body)
        document.addEventListener('click', function(e) {
            // Only close if menu is open and click is not on hamburger or inside nav-menu
            if (hamburger.classList.contains('active') && 
                !navMenu.contains(e.target) && 
                !hamburger.contains(e.target)) {
                closeMobileMenu();
            }
        }, false);

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && hamburger.classList.contains('active')) {
                closeMobileMenu();
            }
        });

        // Handle window resize - close menu when switching to desktop
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Close menu if window is resized to desktop width
                if (window.innerWidth > 1024 && hamburger.classList.contains('active')) {
                    closeMobileMenu();
                }
            }, 250);
        });

        // Handle orientation change on mobile
        window.addEventListener('orientationchange', function() {
            if (hamburger.classList.contains('active')) {
                // Small delay to let orientation settle
                setTimeout(closeMobileMenu, 300);
            }
        });

        console.log('Mobile navigation initialized successfully');
    }

    // =================================================================
    // SMOOTH SCROLL
    // =================================================================

    /**
     * Initialize smooth scrolling for anchor links
     */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Skip if it's just #
                if (href === '#' || href === '#!') {
                    e.preventDefault();
                    return;
                }

                const target = document.querySelector(href);
                
                if (target) {
                    e.preventDefault();
                    
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    }

    // =================================================================
    // HEADER SCROLL BEHAVIOR
    // =================================================================

    /**
     * Add shadow to header on scroll
     */
    function initHeaderScroll() {
        const header = document.querySelector('.header');
        if (!header) return;

        let lastScrollTop = 0;
        const scrollThreshold = 10;
        let ticking = false;

        function updateHeader() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            // Add scrolled class when scrolled past threshold
            if (scrollTop > scrollThreshold) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            lastScrollTop = scrollTop;
            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(updateHeader);
                ticking = true;
            }
        }, { passive: true });
    }

    // =================================================================
    // ACTIVE NAVIGATION HIGHLIGHTING
    // =================================================================

    /**
     * Highlight active navigation item based on scroll position
     */
    function initActiveNav() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-link');

        if (sections.length === 0 || navLinks.length === 0) return;

        let ticking = false;

        function highlightNav() {
            const scrollY = window.pageYOffset;

            sections.forEach(section => {
                const sectionHeight = section.offsetHeight;
                const sectionTop = section.offsetTop - 100;
                const sectionId = section.getAttribute('id');

                if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${sectionId}`) {
                            link.classList.add('active');
                        }
                    });
                }
            });

            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(highlightNav);
                ticking = true;
            }
        }, { passive: true });
    }

    // =================================================================
    // FORM VALIDATION HELPERS
    // =================================================================

    /**
     * Basic form validation helper
     */
    window.validateForm = function(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;

        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('error');
            } else {
                field.classList.remove('error');
            }
        });

        return isValid;
    };

    // =================================================================
    // INITIALIZE ON DOM READY
    // =================================================================

    /**
     * Initialize all functionality when DOM is ready
     */
    function init() {
        console.log('Initializing Sai Seva Foundation JS...');
        
        initMobileNav();
        initSmoothScroll();
        initHeaderScroll();
        initActiveNav();

        // Add loaded class to body for animations
        document.body.classList.add('loaded');
        
        console.log('All scripts initialized successfully');
    }

    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        // Remove any stuck body styles
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        document.body.style.overflow = '';
    });

})();
