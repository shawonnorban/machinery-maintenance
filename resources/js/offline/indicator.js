/**
 * What is waiting to be sent, always visible (Frontend 6.2 rule 5, SRS 38).
 *
 * The rule this exists for: a technician must never wonder whether their work
 * was recorded. A queue nobody can see is indistinguishable from a report that
 * vanished, and the second time that happens people stop using the system and
 * go back to the notebook by the machine.
 *
 * Three states, and they are genuinely different things:
 *   live      connected and nothing waiting
 *   waiting   connected or not, with drafts still to send
 *   failed    the server refused a draft, and only a person can fix it
 */
const SYNC_STATE = 'sync-state';

function text(key, fallback) {
    // Translated server-side and handed to the page, because the bundle is
    // shared by both languages (Handbook 5.2). The fallback is for the rare
    // page that renders before window.App is set.
    return window.App?.t?.[key] ?? fallback;
}

function render({ pending, failed }) {
    const element = document.getElementById(SYNC_STATE);

    if (element !== null) {
        if (failed.length > 0) {
            element.dataset.state = 'failed';
            element.textContent = `⚠ ${text('sync_failed', 'Not sent')} (${failed.length})`;
        } else if (pending.length > 0) {
            element.dataset.state = 'pending';
            element.textContent = `↻ ${text('sync_pending', 'Waiting to send')} (${pending.length})`;
        } else if (navigator.onLine) {
            element.dataset.state = 'synced';
            element.textContent = `✓ ${text('sync_live', 'Saved')}`;
        } else {
            element.dataset.state = 'offline';
            element.textContent = `○ ${text('sync_offline', 'Offline')}`;
        }
    }

    renderList(pending, failed);
}

/**
 * The drafts themselves, where a screen has asked for them.
 *
 * A count alone answers "is anything waiting" but not "is *my* report
 * waiting", which is the question somebody who just tapped Report is asking.
 */
function renderList(pending, failed) {
    const container = document.querySelector('[data-offline-drafts]');

    if (container === null) return;

    const drafts = [...failed, ...pending];

    if (drafts.length === 0) {
        container.innerHTML = '';
        container.hidden = true;
        return;
    }

    container.hidden = false;
    container.innerHTML = '';

    for (const draft of drafts) {
        const row = document.createElement('div');
        row.className = `alert ${draft.state === 'failed' ? 'alert-danger' : 'alert-secondary'} py-2 mb-2`;

        const label = document.createElement('div');
        label.className = 'fw-semibold';
        label.textContent = draft.label;
        row.append(label);

        const detail = document.createElement('div');
        detail.className = 'small';
        detail.textContent = draft.state === 'failed'
            ? `${text('sync_refused', 'The server refused this')}: ${draft.failureReason}`
            : text('sync_will_send', 'Will send when the connection returns.');
        row.append(detail);

        if (draft.state === 'failed') {
            // Only offered for a refusal. A pending draft has done nothing
            // wrong and discarding it would throw away the report.
            const discard = document.createElement('button');
            discard.type = 'button';
            discard.className = 'btn btn-sm btn-outline-danger mt-2';
            discard.textContent = text('sync_discard', 'Discard');
            discard.addEventListener('click', () => window.OfflineQueue?.discardDraft(draft.key));
            row.append(discard);
        }

        container.append(row);
    }
}

// The queue is the only source of this: it announces on save, on flush, and
// when the connection drops. Rendering from a bare `online` event here would
// paint an empty list over drafts that are still waiting.
document.addEventListener('offline-queue:changed', (event) => render(event.detail));
