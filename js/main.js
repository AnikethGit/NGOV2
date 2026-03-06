/**
 * Main JavaScript for Sai Seva Foundation
 * Handles navigation, mobile menu, and common interactions
 */

(function() {
    'use strict';

    // =================================================================
    // MOBILE NAVIGATION
    // =================================================================

    /**
     * Initialize mobile navigation
     */
    function initMobileNav() {
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        const navLinks = document.querySelectorAll('.nav-link');
        const body = document.body;

        if (!hamburger || !navMenu) return;

        // Toggle mobile menu
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        });

        // Close menu when clicking on nav links
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (hamburger.classList.contains('active') && 
                !navMenu.contains(e.target) && 
                !hamburger.contains(e.target)) {
                closeMobileMenu();
            }
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && hamburger.classList.contains('active')) {
                closeMobileMenu();
            }
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 1024 && hamburger.classList.contains('active')) {
                    closeMobileMenu();
                }
            }, 250);
        });

        function toggleMobileMenu() {
            const isActive = hamburger.classList.contains('active');
            
            if (isActive) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        }

        function openMobileMenu() {
            hamburger.classList.add('active');
            navMenu.classList.add('active');
            body.style.overflow = 'hidden';
            
            // Set ARIA attributes
            hamburger.setAttribute('aria-expanded', 'true');
            navMenu.setAttribute('aria-hidden', 'false');
        }

        function closeMobileMenu() {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
            body.style.overflow = '';
            
            // Set ARIA attributes
            hamburger.setAttribute('aria-expanded', 'false');
            navMenu.setAttribute('aria-hidden', 'true');
        }
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
                if (href === '#') {
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

        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            // Add shadow when scrolled
            if (scrollTop > scrollThreshold) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            lastScrollTop = scrollTop;
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
        }

        window.addEventListener('scroll', highlightNav, { passive: true });
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
        initMobileNav();
        initSmoothScroll();
        initHeaderScroll();
        initActiveNav();

        // Add loaded class to body for animations
        document.body.classList.add('loaded');
    }

    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
