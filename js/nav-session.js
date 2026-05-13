/**
 * nav-session.js
 *
 * NOTE: Session management is now handled directly inside components.js.
 * This file is kept as a lightweight shim that simply calls
 * window.refreshNavSession() if it has been exposed, so that any page
 * which still includes this script explicitly won't break.
 *
 * You do NOT need to add this file to new pages — components.js covers it.
 */
(function () {
    'use strict';

    /**
     * Refresh the nav session state.
     * components.js exposes this as window.refreshNavSession after boot.
     * If components.js hasn't run yet we wait for DOMContentLoaded.
     */
    function tryRefresh() {
        if (typeof window.refreshNavSession === 'function') {
            window.refreshNavSession();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryRefresh);
    } else {
        tryRefresh();
    }

})();
