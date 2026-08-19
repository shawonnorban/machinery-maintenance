import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * The websocket transport (SRS 29, ADR-018).
 *
 * REST stays the source of truth. Everything here updates what is already on
 * screen or tells somebody to go and look; nothing is stored from a socket
 * message, because a client that missed a frame while its laptop was asleep
 * would otherwise be quietly wrong.
 *
 * Channels are subscribed from the ids the server put on the page, never from
 * anything the user can type. Authorization still happens server-side on every
 * subscription — this is convenience, not security.
 */
const config = window.App ?? {};

if (!config.reverbKey) {
    // No key configured: the app runs perfectly well without a socket, just
    // without live updates. Failing loudly here would break every page in an
    // environment where Reverb simply is not running.
    console.info('Real-time updates are not configured.');
}

window.Echo = config.reverbKey
    ? new Echo({
        broadcaster: 'reverb',
        key: config.reverbKey,
        wsHost: config.reverbHost,
        wsPort: config.reverbPort ?? 80,
        wssPort: config.reverbPort ?? 443,
        forceTLS: (config.reverbScheme ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    })
    : null;

/**
 * The connection light in the header.
 *
 * Three states rather than two. "Reconnecting" is the one that matters: a
 * technician who thinks they are live, and is not, will trust a stale screen —
 * saying so is the difference between a pause and a wrong decision.
 */
function trackConnection() {
    const indicator = document.querySelector('[data-connection-state]');

    if (!indicator || !window.Echo) {
        return;
    }

    const set = (state, label) => {
        indicator.dataset.connectionState = state;
        indicator.textContent = label;
        indicator.hidden = state === 'live';
    };

    const labels = indicator.dataset;

    window.Echo.connector.pusher.connection.bind('connected', () => set('live', labels.labelLive));
    window.Echo.connector.pusher.connection.bind('connecting', () => set('reconnecting', labels.labelReconnecting));
    window.Echo.connector.pusher.connection.bind('unavailable', () => set('offline', labels.labelOffline));
    window.Echo.connector.pusher.connection.bind('failed', () => set('offline', labels.labelOffline));
    window.Echo.connector.pusher.connection.bind('disconnected', () => set('offline', labels.labelOffline));
}

/**
 * A short-lived message for something that just happened elsewhere.
 *
 * Deliberately not a modal and never blocking. Somebody typing a reading into a
 * work order should not have a breakdown alert steal their keystrokes.
 */
function toast(title, body, tone = 'info', href = null) {
    const region = document.querySelector('[data-toast-region]');

    if (!region) {
        return;
    }

    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${tone} border-0 show mb-2`;
    el.setAttribute('role', 'status');
    el.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong class="d-block"></strong>
                <span class="small"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;

    // Set as text, never as markup: a machine name or a problem description is
    // user input, and it arrived here over a socket.
    el.querySelector('strong').textContent = title;
    el.querySelector('span').textContent = body ?? '';

    if (href) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', (event) => {
            if (!event.target.closest('.btn-close')) {
                window.location.href = href;
            }
        });
    }

    region.append(el);

    window.setTimeout(() => el.remove(), 12000);
}

/**
 * Rows already on screen are updated in place; anything else is announced.
 *
 * Re-rendering a list from a socket message would mean two implementations of
 * every row — one in Blade and one here — and they drift.
 */
function updateStatusPill(selector, status, label) {
    document.querySelectorAll(selector).forEach((cell) => {
        cell.textContent = label ?? status;
        cell.dataset.status = status;
    });
}

function subscribe() {
    if (!window.Echo) {
        return;
    }

    const { companyId, factoryId, userId, t = {} } = config;

    if (userId) {
        window.Echo.private(`user.${userId}`)
            .listen('.notification.created', (event) => {
                bumpNotificationBadge();
                toast(
                    event.title,
                    event.body,
                    event.severity === 'CRITICAL' ? 'danger' : (event.severity === 'WARNING' ? 'warning' : 'info'),
                    event.action_url,
                );
            })
            .listen('.work-order.assigned', (event) => {
                toast(t.assignedToYou ?? 'Assigned to you', `${event.number} — ${event.title}`, 'info');
            });
    }

    if (factoryId) {
        window.Echo.private(`factory.${factoryId}`)
            .listen('.breakdown.reported', (event) => {
                toast(
                    `${t.breakdown ?? 'Breakdown'}: ${event.asset_code ?? ''}`.trim(),
                    event.problem,
                    event.severity === 'CRITICAL' ? 'danger' : 'warning',
                );
            })
            .listen('.asset.status-changed', (event) => {
                updateStatusPill(`[data-asset-status="${event.id}"]`, event.to_status);
            })
            .listen('.work-order.updated', (event) => {
                updateStatusPill(`[data-work-order-status="${event.id}"]`, event.status);
            });
    }

    if (companyId) {
        window.Echo.private(`company.${companyId}`)
            .listen('.stock.changed', (event) => {
                document.querySelectorAll(`[data-part-on-hand="${event.id}"]`).forEach((cell) => {
                    cell.textContent = event.on_hand;
                    cell.classList.toggle('text-danger', event.below_reorder_level);
                });
            });
    }
}

function bumpNotificationBadge() {
    const badge = document.querySelector('[data-notification-count]');

    if (!badge) {
        return;
    }

    const next = Number.parseInt(badge.textContent || '0', 10) + 1;

    badge.textContent = String(next);
    badge.hidden = false;
}

document.addEventListener('DOMContentLoaded', () => {
    trackConnection();
    subscribe();
});
