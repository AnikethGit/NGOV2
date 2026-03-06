/**
 * HERO CAROUSEL JAVASCRIPT
 * Complete functionality for hero image carousel with auto-play, navigation, and controls
 */

(function() {
    'use strict';

    // ====================================================================
    // CAROUSEL STATE & CONFIGURATION
    // ====================================================================

    const carousel = {
        currentSlide: 0,
        totalSlides: 0,
        isAutoPlaying: true,
        autoPlayInterval: null,
        autoPlayDelay: 5000, // 5 seconds
        isTransitioning: false,
        progressInterval: null,
        progressDuration: 0
    };

    // DOM Elements
    let slides = [];
    let indicators = [];
    let prevBtn = null;
    let nextBtn = null;
    let playPauseBtn = null;
    let progressBar = null;

    // ====================================================================
    // INITIALIZATION
    // ====================================================================

    /**
     * Initialize carousel
     */
    function initCarousel() {
        // Get DOM elements
        const slidesContainer = document.getElementById('carousel-slides');
        if (!slidesContainer) {
            console.warn('Carousel slides container not found');
            return;
        }

        slides = Array.from(slidesContainer.querySelectorAll('.carousel-slide'));
        indicators = Array.from(document.querySelectorAll('.carousel-indicators .indicator'));
        prevBtn = document.getElementById('prevBtn');
        nextBtn = document.getElementById('nextBtn');
        playPauseBtn = document.getElementById('playPauseBtn');
        progressBar = document.getElementById('progressBar');

        if (slides.length === 0) {
            console.warn('No carousel slides found');
            return;
        }

        carousel.totalSlides = slides.length;

        // Set up event listeners
        setupEventListeners();

        // Show first slide
        showSlide(0);

        // Start auto-play
        startAutoPlay();

        // Add keyboard navigation
        setupKeyboardNavigation();

        // Add touch/swipe support
        setupTouchSupport();

        console.log(`Carousel initialized with ${carousel.totalSlides} slides`);
    }

    // ====================================================================
    // EVENT LISTENERS
    // ====================================================================

    /**
     * Set up all event listeners
     */
    function setupEventListeners() {
        // Previous button
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                prevSlide();
                resetAutoPlay();
            });
        }

        // Next button
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                nextSlide();
                resetAutoPlay();
            });
        }

        // Play/Pause button
        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', toggleAutoPlay);
        }

        // Indicators
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', function() {
                goToSlide(index);
                resetAutoPlay();
            });
        });

        // Pause on hover
        const carouselElement = document.querySelector('.hero-carousel');
        if (carouselElement) {
            carouselElement.addEventListener('mouseenter', function() {
                if (carousel.isAutoPlaying) {
                    pauseAutoPlay();
                }
            });

            carouselElement.addEventListener('mouseleave', function() {
                if (carousel.isAutoPlaying) {
                    startAutoPlay();
                }
            });
        }

        // Pause when tab is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                pauseAutoPlay();
            } else if (carousel.isAutoPlaying) {
                startAutoPlay();
            }
        });
    }

    // ====================================================================
    // KEYBOARD NAVIGATION
    // ====================================================================

    /**
     * Set up keyboard navigation
     */
    function setupKeyboardNavigation() {
        document.addEventListener('keydown', function(e) {
            // Only handle if carousel is in viewport
            const carouselElement = document.querySelector('.hero-carousel');
            if (!carouselElement) return;

            const rect = carouselElement.getBoundingClientRect();
            const isInViewport = rect.top < window.innerHeight && rect.bottom > 0;

            if (!isInViewport) return;

            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    prevSlide();
                    resetAutoPlay();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    nextSlide();
                    resetAutoPlay();
                    break;
                case ' ':
                case 'Spacebar':
                    // Only if not focused on a button or link
                    if (!['BUTTON', 'A'].includes(document.activeElement.tagName)) {
                        e.preventDefault();
                        toggleAutoPlay();
                    }
                    break;
            }
        });
    }

    // ====================================================================
    // TOUCH/SWIPE SUPPORT
    // ====================================================================

    /**
     * Set up touch/swipe support for mobile
     */
    function setupTouchSupport() {
        const carouselElement = document.querySelector('.hero-carousel');
        if (!carouselElement) return;

        let touchStartX = 0;
        let touchEndX = 0;
        let touchStartY = 0;
        let touchEndY = 0;

        carouselElement.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        carouselElement.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            const swipeThreshold = 50;
            const xDiff = touchStartX - touchEndX;
            const yDiff = Math.abs(touchStartY - touchEndY);

            // Only process horizontal swipes (not vertical scrolling)
            if (yDiff > 50) return;

            if (Math.abs(xDiff) > swipeThreshold) {
                if (xDiff > 0) {
                    // Swipe left - next slide
                    nextSlide();
                } else {
                    // Swipe right - previous slide
                    prevSlide();
                }
                resetAutoPlay();
            }
        }
    }

    // ====================================================================
    // SLIDE NAVIGATION
    // ====================================================================

    /**
     * Show specific slide
     * @param {number} index - Slide index to show
     */
    function showSlide(index) {
        if (carousel.isTransitioning) return;
        if (index < 0 || index >= carousel.totalSlides) return;

        carousel.isTransitioning = true;

        // Remove active class from current slide
        slides.forEach(slide => {
            slide.classList.remove('active', 'prev');
        });

        // Add prev class to current slide before switching
        if (slides[carousel.currentSlide]) {
            slides[carousel.currentSlide].classList.add('prev');
        }

        // Update current slide index
        carousel.currentSlide = index;

        // Add active class to new slide
        if (slides[carousel.currentSlide]) {
            slides[carousel.currentSlide].classList.add('active');
        }

        // Update indicators
        updateIndicators();

        // Update ARIA attributes
        updateAriaAttributes();

        // Reset progress bar
        resetProgress();

        // Allow transitions again after animation completes
        setTimeout(() => {
            carousel.isTransitioning = false;
            // Remove prev class after transition
            slides.forEach(slide => slide.classList.remove('prev'));
        }, 800);
    }

    /**
     * Go to specific slide
     * @param {number} index - Slide index
     */
    function goToSlide(index) {
        showSlide(index);
    }

    /**
     * Go to next slide
     */
    function nextSlide() {
        const nextIndex = (carousel.currentSlide + 1) % carousel.totalSlides;
        showSlide(nextIndex);
    }

    /**
     * Go to previous slide
     */
    function prevSlide() {
        const prevIndex = (carousel.currentSlide - 1 + carousel.totalSlides) % carousel.totalSlides;
        showSlide(prevIndex);
    }

    // ====================================================================
    // INDICATORS
    // ====================================================================

    /**
     * Update indicator states
     */
    function updateIndicators() {
        indicators.forEach((indicator, index) => {
            if (index === carousel.currentSlide) {
                indicator.classList.add('active');
                indicator.setAttribute('aria-selected', 'true');
            } else {
                indicator.classList.remove('active');
                indicator.setAttribute('aria-selected', 'false');
            }
        });
    }

    /**
     * Update ARIA attributes for accessibility
     */
    function updateAriaAttributes() {
        slides.forEach((slide, index) => {
            if (index === carousel.currentSlide) {
                slide.setAttribute('aria-hidden', 'false');
            } else {
                slide.setAttribute('aria-hidden', 'true');
            }
        });
    }

    // ====================================================================
    // AUTO-PLAY
    // ====================================================================

    /**
     * Start auto-play
     */
    function startAutoPlay() {
        if (carousel.autoPlayInterval) {
            clearInterval(carousel.autoPlayInterval);
        }

        carousel.autoPlayInterval = setInterval(() => {
            nextSlide();
        }, carousel.autoPlayDelay);

        startProgress();
    }

    /**
     * Pause auto-play
     */
    function pauseAutoPlay() {
        if (carousel.autoPlayInterval) {
            clearInterval(carousel.autoPlayInterval);
            carousel.autoPlayInterval = null;
        }
        pauseProgress();
    }

    /**
     * Toggle auto-play on/off
     */
    function toggleAutoPlay() {
        carousel.isAutoPlaying = !carousel.isAutoPlaying;

        if (carousel.isAutoPlaying) {
            startAutoPlay();
            updatePlayPauseButton('pause');
        } else {
            pauseAutoPlay();
            updatePlayPauseButton('play');
        }
    }

    /**
     * Reset auto-play (restart timer)
     */
    function resetAutoPlay() {
        if (carousel.isAutoPlaying) {
            pauseAutoPlay();
            startAutoPlay();
        }
    }

    /**
     * Update play/pause button icon
     * @param {string} state - 'play' or 'pause'
     */
    function updatePlayPauseButton(state) {
        if (!playPauseBtn) return;

        const icon = playPauseBtn.querySelector('i');
        if (!icon) return;

        if (state === 'play') {
            icon.classList.remove('fa-pause');
            icon.classList.add('fa-play');
            playPauseBtn.setAttribute('aria-label', 'Play auto-play');
        } else {
            icon.classList.remove('fa-play');
            icon.classList.add('fa-pause');
            playPauseBtn.setAttribute('aria-label', 'Pause auto-play');
        }
    }

    // ====================================================================
    // PROGRESS BAR
    // ====================================================================

    /**
     * Start progress bar animation
     */
    function startProgress() {
        if (!progressBar) return;

        resetProgress();
        carousel.progressDuration = 0;

        const updateInterval = 50; // Update every 50ms
        const increment = (100 / (carousel.autoPlayDelay / updateInterval));

        carousel.progressInterval = setInterval(() => {
            carousel.progressDuration += increment;

            if (carousel.progressDuration >= 100) {
                carousel.progressDuration = 100;
            }

            progressBar.style.width = carousel.progressDuration + '%';
        }, updateInterval);
    }

    /**
     * Pause progress bar animation
     */
    function pauseProgress() {
        if (carousel.progressInterval) {
            clearInterval(carousel.progressInterval);
            carousel.progressInterval = null;
        }
    }

    /**
     * Reset progress bar
     */
    function resetProgress() {
        if (carousel.progressInterval) {
            clearInterval(carousel.progressInterval);
            carousel.progressInterval = null;
        }

        if (progressBar) {
            progressBar.style.width = '0%';
        }

        carousel.progressDuration = 0;
    }

    // ====================================================================
    // INITIALIZE ON DOM READY
    // ====================================================================

    /**
     * Initialize when DOM is ready
     */
    function init() {
        initCarousel();
    }

    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        pauseAutoPlay();
        pauseProgress();
    });

})();
