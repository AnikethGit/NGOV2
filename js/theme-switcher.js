/**
 * NGOV2 Theme Switcher
 * ---------------------
 * Strategy: The site is dark-first. On ALL devices (light or dark OS),
 * we force dark mode by default. A toggle button lets users switch to light.
 *
 * This prevents the "unreadable on light OS" bug where no data-color-scheme
 * was being set, causing browsers to use half-baked :root defaults mixed
 * with hardcoded dark values.
 */

(function () {
  'use strict';

  // ---------- 1. Apply theme IMMEDIATELY (before paint) ----------
  // Use sessionStorage so it persists across page navigations in the same visit.
  // Falls back to 'dark' (site default) if nothing is stored.
  var stored = null;
  try { stored = sessionStorage.getItem('ngov2-theme'); } catch (e) {}
  var theme = stored || 'dark';
  document.documentElement.setAttribute('data-color-scheme', theme);

  // ---------- 2. Inject toggle button after DOM is ready ----------
  document.addEventListener('DOMContentLoaded', function () {

    // Create the toggle button
    var btn = document.createElement('button');
    btn.id = 'theme-toggle-btn';
    btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    btn.setAttribute('title', btn.getAttribute('aria-label'));
    btn.innerHTML = theme === 'dark' ? getSunIcon() : getMoonIcon();

    // Inline styles — no extra CSS file needed; matches your existing palette
    btn.style.cssText = [
      'position:fixed',
      'bottom:24px',
      'right:24px',
      'z-index:9999',
      'width:44px',
      'height:44px',
      'border-radius:50%',
      'border:1px solid var(--color-border)',
      'background:var(--color-surface)',
      'color:var(--color-text)',
      'cursor:pointer',
      'display:flex',
      'align-items:center',
      'justify-content:center',
      'box-shadow:0 2px 12px rgba(0,0,0,0.18)',
      'transition:background 200ms ease,box-shadow 200ms ease',
      'padding:0'
    ].join(';');

    btn.addEventListener('mouseenter', function () {
      btn.style.boxShadow = '0 4px 20px rgba(0,0,0,0.28)';
    });
    btn.addEventListener('mouseleave', function () {
      btn.style.boxShadow = '0 2px 12px rgba(0,0,0,0.18)';
    });

    // ---------- 3. Toggle logic ----------
    btn.addEventListener('click', function () {
      var current = document.documentElement.getAttribute('data-color-scheme');
      var next = current === 'dark' ? 'light' : 'dark';

      document.documentElement.setAttribute('data-color-scheme', next);
      btn.innerHTML = next === 'dark' ? getSunIcon() : getMoonIcon();
      btn.setAttribute('aria-label', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      btn.setAttribute('title', btn.getAttribute('aria-label'));

      try { sessionStorage.setItem('ngov2-theme', next); } catch (e) {}
    });

    document.body.appendChild(btn);
  });

  // ---------- SVG Icons ----------
  function getSunIcon() {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
  }

  function getMoonIcon() {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
  }

})();
