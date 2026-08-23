import { saveDraft, flush } from './queue';

/**
 * Two separate promises a form can make on a bad connection (SRS 38).
 *
 *   data-offline-persist   what you typed survives a reload, a crash, or a
 *                          phone that locked while you were finding the serial
 *                          number. Every form can want this.
 *
 *   data-offline-endpoint  a submit made with no signal is queued and sent
 *                          later. Only forms whose API endpoint is idempotent
 *                          may say this, because a queued write is a write
 *                          that may arrive twice.
 *
 * Progressive enhancement throughout: with JavaScript off, or when the network
 * is fine, these forms post exactly as they always did. Nothing here is on the
 * path of a working connection.
 */

// -- Keeping what was typed ------------------------------------------------

const PREFIX = 'mm-draft:';

function storageKey(form) {
    // Keyed by form and page, so the breakdown form on machine A does not
    // restore what somebody typed about machine B.
    return `${PREFIX}${form.dataset.offlinePersist}:${window.location.pathname}`;
}

function persistedFields(form) {
    return [...form.elements].filter(
        (element) => element.name
            && !element.disabled
            && !['password', 'file', 'hidden'].includes(element.type)
            // A CSRF token restored from an old draft is an invalid one.
            && element.name !== '_token',
    );
}

function saveTyping(form) {
    const values = {};

    for (const field of persistedFields(form)) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            if (field.checked) values[field.name] = field.value;
        } else if (field.value !== '') {
            values[field.name] = field.value;
        }
    }

    try {
        localStorage.setItem(storageKey(form), JSON.stringify(values));
    } catch {
        // A full or disabled localStorage must never stop somebody filling in
        // a form. Losing the safety net is not losing the form.
    }
}

function restoreTyping(form) {
    let values;

    try {
        values = JSON.parse(localStorage.getItem(storageKey(form)) ?? 'null');
    } catch {
        return;
    }

    if (values === null) return;

    let restored = false;

    for (const field of persistedFields(form)) {
        const value = values[field.name];

        if (value === undefined) continue;

        // Never overwrite what the server put there. A validation redisplay
        // carries the user's own last submission, which is newer than this.
        if (field.type === 'checkbox' || field.type === 'radio') {
            if (!field.checked && field.value === value) {
                field.checked = true;
                restored = true;
            }
        } else if (field.value === '') {
            field.value = value;
            restored = true;
        }
    }

    if (restored) {
        form.dispatchEvent(new CustomEvent('offline-form:restored'));
    }
}

function clearTyping(form) {
    try {
        localStorage.removeItem(storageKey(form));
    } catch {
        // Nothing to do. A stale draft is overwritten on the next keystroke.
    }
}

// -- Submitting with no signal ---------------------------------------------

/**
 * Builds the JSON body the API expects from the form's own fields.
 *
 * Only the fields the API names. A web form carries `_token` and whatever else
 * the screen needed, and posting those to the API would be rejected as
 * unexpected input.
 */
function payloadFor(form) {
    const fields = (form.dataset.offlineFields ?? '')
        .split(',')
        .map((name) => name.trim())
        .filter(Boolean);

    const data = new FormData(form);
    const payload = {};

    for (const name of fields) {
        const value = data.get(name);

        if (value !== null && value !== '') {
            payload[name] = value;
        }
    }

    return payload;
}

async function queueSubmit(form, event) {
    event.preventDefault();

    const draft = await saveDraft({
        endpoint: form.dataset.offlineEndpoint,
        payload: payloadFor(form),
        label: form.dataset.offlineLabel ?? form.dataset.offlineEndpoint,
    });

    clearTyping(form);
    form.reset();

    // Said plainly, because the alternative is somebody standing at a stopped
    // machine unsure whether anybody has been told.
    form.dispatchEvent(new CustomEvent('offline-form:queued', { detail: draft }));

    const notice = form.querySelector('[data-offline-notice]');

    if (notice !== null) {
        notice.hidden = false;
    }

    if (navigator.onLine) flush();
}

// -- Wiring ----------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    for (const form of document.querySelectorAll('[data-offline-persist]')) {
        restoreTyping(form);

        form.addEventListener('input', () => saveTyping(form));
        form.addEventListener('change', () => saveTyping(form));
    }

    for (const form of document.querySelectorAll('[data-offline-endpoint]')) {
        form.addEventListener('submit', (event) => {
            // Online, this is an ordinary form post and the browser handles
            // it. Queueing a submit that could simply be sent would trade a
            // synchronous, verified answer for an asynchronous, hopeful one.
            if (navigator.onLine) {
                clearTyping(form);
                return;
            }

            queueSubmit(form, event);
        });
    }
});
