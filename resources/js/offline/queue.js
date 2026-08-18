import http from '../http';

/**
 * Offline draft queue for technician screens (Frontend 6.3, ADR-034).
 *
 * The idempotency key is generated when the draft is CREATED, not when it is
 * sent. A technician who taps submit three times on a dead connection must
 * produce one breakdown, not three (API 32).
 */
const DB_NAME = 'mm-offline';
const STORE = 'drafts';

function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE, { keyPath: 'key' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function tx(mode, fn) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE, mode);
        const result = fn(transaction.objectStore(STORE));
        transaction.oncomplete = () => resolve(result?.result ?? result);
        transaction.onerror = () => reject(transaction.error);
    });
}

export async function saveDraft({ endpoint, payload }) {
    const draft = {
        key: crypto.randomUUID(),
        endpoint,
        payload,
        createdAt: Date.now(),
        attempts: 0,
        state: 'pending',
    };

    await tx('readwrite', (store) => store.put(draft));
    return draft;
}

export async function pendingDrafts() {
    return tx('readonly', (store) => store.getAll());
}

async function removeDraft(key) {
    return tx('readwrite', (store) => store.delete(key));
}

async function markFailed(draft, reason) {
    draft.state = 'failed';
    draft.failureReason = reason;
    return tx('readwrite', (store) => store.put(draft));
}

export async function flush() {
    const drafts = await pendingDrafts();

    for (const draft of drafts) {
        if (draft.state === 'failed') continue;

        try {
            await http.post(draft.endpoint, draft.payload, { idempotencyKey: draft.key });
            await removeDraft(draft.key);
        } catch (error) {
            const status = error.response?.status;
            const code = error.response?.data?.code;

            // The server already has it. Replaying is a success, not a retry.
            if (status === 409 && code === 'IDEMPOTENCY_CONFLICT') {
                await removeDraft(draft.key);
                continue;
            }

            // A 4xx that is not a conflict will never succeed on retry.
            // Surfacing it beats looping forever against a validation error.
            if (status >= 400 && status < 500) {
                await markFailed(draft, code ?? `HTTP_${status}`);
                continue;
            }

            draft.attempts += 1;
            await tx('readwrite', (store) => store.put(draft));
        }
    }

    document.dispatchEvent(new CustomEvent('offline-queue:flushed'));
}

window.addEventListener('online', flush);
document.addEventListener('DOMContentLoaded', flush);
setInterval(async () => {
    if (navigator.onLine && (await pendingDrafts()).length > 0) flush();
}, 30000);

window.OfflineQueue = { saveDraft, pendingDrafts, flush };
