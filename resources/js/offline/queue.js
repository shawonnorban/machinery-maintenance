import http from '../http';
import { apiToken, forgetToken } from './token';

/**
 * Offline draft queue for technician screens (Frontend 6.3, ADR-034, SRS 38).
 *
 * The idempotency key is generated when the draft is CREATED, not when it is
 * sent. A technician who taps submit three times on a dead connection must
 * produce one breakdown, not three (API 32).
 *
 * Full offline synchronisation is out of scope: this queues writes, it does
 * not let somebody read the machine list on a dead connection. What it has to
 * guarantee is narrower and more important — a report typed into a phone with
 * no signal is not lost, and does not arrive twice.
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

export async function saveDraft({ endpoint, payload, label }) {
    const draft = {
        key: crypto.randomUUID(),
        endpoint,
        payload,
        // What to call this in a list of things waiting to send. "1 item
        // pending" tells a technician nothing about whether their breakdown
        // report went in.
        label: label ?? endpoint,
        createdAt: Date.now(),
        attempts: 0,
        state: 'pending',
    };

    await tx('readwrite', (store) => store.put(draft));
    announce();

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

export async function discardDraft(key) {
    await removeDraft(key);
    announce();
}

let flushing = false;

export async function flush() {
    // Two flushes at once would send every draft twice. They would both be
    // deduplicated by the idempotency key, but the second would read
    // "already in flight" and the draft would look like a failure.
    if (flushing) return;

    flushing = true;

    try {
        await send();
    } finally {
        flushing = false;
        announce();
    }
}

async function send() {
    const drafts = (await pendingDrafts()).filter((draft) => draft.state !== 'failed');

    if (drafts.length === 0) return;

    let token;

    try {
        token = await apiToken();
    } catch {
        // No token, no send. The drafts stay exactly where they are.
        return;
    }

    for (const draft of drafts) {
        try {
            await http.post(draft.endpoint, draft.payload, {
                idempotencyKey: draft.key,
                headers: { Authorization: `Bearer ${token}` },
            });

            await removeDraft(draft.key);
        } catch (error) {
            const status = error.response?.status;
            const code = error.response?.data?.code;

            // The token went stale underneath us. Drop it and stop; the next
            // flush mints a new one and starts again from this draft.
            if (status === 401) {
                forgetToken();
                return;
            }

            if (status === 409 && code === 'IDEMPOTENCY_CONFLICT') {
                // Two different situations behind one code. If the server
                // finished the first attempt, replaying is a success and the
                // draft is done. If the first attempt is still in flight, the
                // draft must be kept: that attempt can still fail and release
                // its claim, and a draft deleted now would be a report nobody
                // ever receives.
                if (error.response?.headers?.['idempotent-replay'] === 'true') {
                    await removeDraft(draft.key);
                } else {
                    draft.attempts += 1;
                    await tx('readwrite', (store) => store.put(draft));
                }

                continue;
            }

            // A 4xx that is not a conflict will never succeed on retry.
            // Surfacing it beats looping for ever against a validation error.
            if (status >= 400 && status < 500) {
                await markFailed(draft, code ?? `HTTP_${status}`);
                continue;
            }

            draft.attempts += 1;
            await tx('readwrite', (store) => store.put(draft));
        }
    }
}

/**
 * Tell the page what is waiting.
 *
 * A technician must never have to wonder whether their work was recorded
 * (Frontend 6.2 rule 5), and a queue nobody can see is indistinguishable from
 * a report that vanished.
 */
async function announce() {
    const drafts = await pendingDrafts();

    document.dispatchEvent(
        new CustomEvent('offline-queue:changed', {
            detail: {
                pending: drafts.filter((draft) => draft.state !== 'failed'),
                failed: drafts.filter((draft) => draft.state === 'failed'),
            },
        }),
    );
}

window.addEventListener('online', flush);
window.addEventListener('offline', announce);
document.addEventListener('DOMContentLoaded', () => {
    announce();
    flush();
});

setInterval(async () => {
    if (navigator.onLine && (await pendingDrafts()).length > 0) flush();
}, 30000);

window.OfflineQueue = { saveDraft, pendingDrafts, discardDraft, flush };
