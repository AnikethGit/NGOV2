/**
 * gallery.js — Dynamic Google Drive gallery
 *
 * Fetches folder/image data from api/gallery.php and renders:
 *   • A sticky tab bar for jumping between folder sections
 *   • One labelled section per Drive folder with a responsive image grid
 *   • A built-in lightbox with keyboard navigation and touch swipe
 *
 * Relies on these elements already existing in the DOM:
 *   #gallery-root   — container where sections are injected
 *   #gallery-tabs   — container where the tab bar is injected
 *   #gallery-lightbox — the lightbox overlay element (see gallery.html)
 */

(function () {
    'use strict';

    var API_URL  = 'api/gallery.php';
    var allImages = [];   // flat array used by lightbox for prev/next
    var lb        = null; // lightbox state object (set in initLightbox)

    // ── Entry point ───────────────────────────────────────────────────────────

    function init() {
        var root   = document.getElementById('gallery-root');
        var tabsEl = document.getElementById('gallery-tabs');
        if (!root) return;

        showSkeleton(root);

        fetch(API_URL)
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                clearEl(root);

                if (!data.success) {
                    throw new Error(data.error || 'Could not load gallery.');
                }

                var folders = data.folders || [];

                if (folders.length === 0) {
                    showEmpty(root);
                    return;
                }

                renderTabs(tabsEl, folders);
                renderFolders(root, folders);
                initLightbox();
                initLazyLoad();
            })
            .catch(function (err) {
                clearEl(root);
                showError(root, err.message);
            });
    }

    // ── Skeleton loading ──────────────────────────────────────────────────────

    function showSkeleton(root) {
        var html = '<div class="gallery-skeleton">';
        for (var s = 0; s < 2; s++) {
            html += '<div class="skeleton-section">'
                  + '<div class="skeleton-heading"></div>'
                  + '<div class="gallery-grid">';
            for (var i = 0; i < 8; i++) {
                html += '<div class="skeleton-card"></div>';
            }
            html += '</div></div>';
        }
        html += '</div>';
        root.innerHTML = html;
    }

    function clearEl(el) { el.innerHTML = ''; }

    // ── Empty / Error states ──────────────────────────────────────────────────

    function showEmpty(root) {
        root.innerHTML = '<div class="gallery-state">'
            + '<i class="fas fa-images"></i>'
            + '<h3>No images yet</h3>'
            + '<p>Check back soon — we\'ll be uploading photos here shortly.</p>'
            + '</div>';
    }

    function showError(root, msg) {
        root.innerHTML = '<div class="gallery-state gallery-state--error">'
            + '<i class="fas fa-exclamation-triangle"></i>'
            + '<h3>Could not load gallery</h3>'
            + '<p>' + esc(msg) + '</p>'
            + '<button class="btn btn-outline" onclick="location.reload()">'
            + '<i class="fas fa-redo"></i> Retry</button>'
            + '</div>';
    }

    // ── Tab bar ───────────────────────────────────────────────────────────────

    function renderTabs(tabsEl, folders) {
        if (!tabsEl) return;

        var html = '<div class="gallery-tab-inner">'
            + '<button class="gallery-tab active" onclick="galleryJump(null,this)">All</button>';

        folders.forEach(function (f) {
            html += '<button class="gallery-tab" onclick="galleryJump(\''
                  + esc(sectionId(f.id)) + '\',this)">' + esc(f.name) + '</button>';
        });

        html += '</div>';
        tabsEl.innerHTML = html;
        tabsEl.style.display = 'block';
    }

    // Exposed globally so onclick attributes can call it
    window.galleryJump = function (id, btn) {
        // Highlight active tab
        document.querySelectorAll('.gallery-tab').forEach(function (t) {
            t.classList.remove('active');
        });
        if (btn) btn.classList.add('active');

        if (!id) {
            // "All" — scroll to top of gallery
            var root = document.getElementById('gallery-root');
            if (root) {
                var top = root.getBoundingClientRect().top + window.pageYOffset - 130;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
            return;
        }

        var el = document.getElementById(id);
        if (!el) return;
        var top = el.getBoundingClientRect().top + window.pageYOffset - 130;
        window.scrollTo({ top: top, behavior: 'smooth' });
    };

    // ── Render folders ────────────────────────────────────────────────────────

    function renderFolders(root, folders) {
        // Build flat image index first (needed for lightbox prev/next)
        allImages = [];
        folders.forEach(function (folder) {
            folder.images.forEach(function (img) {
                allImages.push({
                    id:      img.id,
                    name:    img.name,
                    full:    img.full,
                    section: folder.name,
                });
            });
        });

        var html = '<div id="gallery-all">';
        folders.forEach(function (folder) {
            html += buildSection(folder);
        });
        html += '</div>';
        root.innerHTML = html;
    }

    var ICON_MAP = {
        'utensils':      'fa-utensils',
        'hands-praying': 'fa-hands-praying',
        'bowl-rice':     'fa-bowl-rice',
        'heart':         'fa-heart',
        'star':          'fa-star',
        'images':        'fa-images',
        'calendar':      'fa-calendar-alt',
        'users':         'fa-users',
        'seedling':      'fa-seedling',
        'hands-helping': 'fa-hands-helping',
    };

    function buildSection(folder) {
        var iconClass = ICON_MAP[folder.icon] || 'fa-images';
        var sid = sectionId(folder.id);

        var html = '<section class="gallery-section" id="' + sid + '">'
            + '<div class="gallery-section-header">'
            + '<h2><i class="fas ' + iconClass + '" aria-hidden="true"></i> ' + esc(folder.name) + '</h2>'
            + '<span class="gallery-count">'
            + folder.count + ' photo' + (folder.count !== 1 ? 's' : '')
            + '</span>'
            + '</div>'
            + '<div class="gallery-grid">';

        folder.images.forEach(function (img) {
            // Find the index in allImages for lightbox navigation
            var idx = allImages.findIndex(function (a) { return a.id === img.id; });
            html += '<div class="gallery-card" data-lb-idx="' + idx + '"'
                  + ' role="button" tabindex="0"'
                  + ' aria-label="Open photo: ' + esc(img.name) + '">'
                  + '<img'
                  + ' data-src="' + img.thumb + '"'
                  + ' src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\'/%3E"'
                  + ' alt="' + esc(img.name) + '"'
                  + ' class="gallery-img">'
                  + '<div class="gallery-card-overlay" aria-hidden="true">'
                  + '<i class="fas fa-expand-alt"></i>'
                  + '</div>'
                  + '</div>';
        });

        html += '</div></section>';
        return html;
    }

    function sectionId(folderId) {
        return 'gs-' + folderId.replace(/[^a-zA-Z0-9_-]/g, '');
    }

    // ── Lazy loading ──────────────────────────────────────────────────────────

    function initLazyLoad() {
        var imgs = document.querySelectorAll('.gallery-img[data-src]');

        if (!('IntersectionObserver' in window)) {
            // Fallback: load all images immediately
            imgs.forEach(function (img) {
                img.src = img.getAttribute('data-src');
                img.removeAttribute('data-src');
            });
            wireCards();
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var img = entry.target;
                var src = img.getAttribute('data-src');
                if (!src) return;
                img.src = src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            });
        }, { rootMargin: '300px 0px' });

        imgs.forEach(function (img) { observer.observe(img); });
        wireCards();
    }

    // ── Card click → open lightbox ────────────────────────────────────────────

    function wireCards() {
        document.querySelectorAll('.gallery-card[data-lb-idx]').forEach(function (card) {
            var idx = parseInt(card.getAttribute('data-lb-idx'), 10);

            card.addEventListener('click', function () { openLightbox(idx); });

            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openLightbox(idx);
                }
            });
        });
    }

    // ── Lightbox ──────────────────────────────────────────────────────────────

    function initLightbox() {
        var overlay = document.getElementById('gallery-lightbox');
        if (!overlay) return;

        lb = {
            overlay:     overlay,
            img:         overlay.querySelector('.lb-img'),
            caption:     overlay.querySelector('.lb-caption'),
            counter:     overlay.querySelector('.lb-counter'),
            btnPrev:     overlay.querySelector('.lb-prev'),
            btnNext:     overlay.querySelector('.lb-next'),
            btnClose:    overlay.querySelector('.lb-close'),
            current:     0,
            touchStartX: null,
        };

        lb.btnClose.addEventListener('click', closeLightbox);
        lb.btnPrev.addEventListener('click',  function () { moveLightbox(-1); });
        lb.btnNext.addEventListener('click',  function () { moveLightbox(+1); });

        // Click backdrop to close
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('lb-stage')) {
                closeLightbox();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('lb-open')) return;
            if (e.key === 'Escape')     closeLightbox();
            if (e.key === 'ArrowLeft')  moveLightbox(-1);
            if (e.key === 'ArrowRight') moveLightbox(+1);
        });

        // Touch swipe
        overlay.addEventListener('touchstart', function (e) {
            lb.touchStartX = e.touches[0].clientX;
        }, { passive: true });

        overlay.addEventListener('touchend', function (e) {
            if (lb.touchStartX === null) return;
            var dx = e.changedTouches[0].clientX - lb.touchStartX;
            if (Math.abs(dx) > 50) moveLightbox(dx < 0 ? 1 : -1);
            lb.touchStartX = null;
        });
    }

    function openLightbox(idx) {
        if (!lb || idx < 0 || idx >= allImages.length) return;
        lb.current = idx;
        updateLightboxImage();
        lb.overlay.classList.add('lb-open');
        lb.overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lb-body-lock');
        lb.btnClose.focus();
    }

    function closeLightbox() {
        if (!lb) return;
        lb.overlay.classList.remove('lb-open');
        lb.overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lb-body-lock');
    }

    function moveLightbox(dir) {
        if (!lb || allImages.length === 0) return;
        lb.current = (lb.current + dir + allImages.length) % allImages.length;
        updateLightboxImage();
    }

    function updateLightboxImage() {
        var item = allImages[lb.current];

        // Reset image
        lb.img.classList.add('lb-loading');
        lb.img.onload  = function () { lb.img.classList.remove('lb-loading'); };
        lb.img.onerror = function () { lb.img.classList.remove('lb-loading'); };
        lb.img.src  = item.full;
        lb.img.alt  = item.name || '';

        if (lb.caption) lb.caption.textContent = item.name || '';
        if (lb.counter) lb.counter.textContent  =
            (lb.current + 1) + ' / ' + allImages.length;
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    function esc(str) {
        return (str || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
