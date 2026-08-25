import 'bootstrap';
import '@coreui/coreui';
import http from './http';

window.http = http;

/**
 * Shared behaviour for every authenticated page.
 * Kept small on purpose: this bundle loads on the technician's phone too.
 */

/**
 * The service worker (SRS 38).
 *
 * Registered late and failing silently: it caches build assets so the shell
 * starts on a slow factory connection, and nothing on the page depends on it
 * being there. A browser that refuses it — an insecure origin in development,
 * a private window — gets the application exactly as before.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => undefined);
    });
}

// Sidebar collapse, persisted so a user's choice survives navigation.
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    const toggler = document.querySelector('[data-sidebar-toggle]');

    if (sidebar && toggler) {
        if (!window.matchMedia('(max-width: 991.98px)').matches
            && localStorage.getItem('sidebar:narrow') === '1') {
            sidebar.classList.add('sidebar-narrow');
        }

        toggler.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 991.98px)').matches) {
                sidebar.classList.toggle('show');
                return;
            }

            const narrow = sidebar.classList.toggle('sidebar-narrow');
            localStorage.setItem('sidebar:narrow', narrow ? '1' : '0');
        });

        sidebar.querySelectorAll('a[href]:not([href="#"])').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 991.98px)').matches) {
                    sidebar.classList.remove('show');
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                sidebar.classList.remove('show');
            }
        });
    }

    // Confirmation naming the record, not a bare "Are you sure?"
    // (Frontend 3.3 rule 6).
    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('submit', (event) => {
            if (!window.confirm(el.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});

// Live updates (SRS 29). Every screen works without it: the socket updates what
// is already rendered and announces what happened elsewhere, and nothing on the
// page depends on a frame arriving.
import './echo';
