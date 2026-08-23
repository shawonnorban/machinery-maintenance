/**
 * The bearer token this page uses to reach the API (SRS 38).
 *
 * Held in memory and nowhere else. localStorage would survive the tab, which
 * sounds like a feature until you remember these are shared tablets on a
 * factory floor: a token that outlives the session is a token the next person
 * to pick up the device is holding.
 *
 * Losing it on reload costs one request, and only when there is something to
 * send — which by definition means the network is back.
 */
let token = null;
let expiresAt = 0;
let inFlight = null;

/** Renewed a minute early, so a request never starts with a token that
 *  expires while it is in the air. */
const EARLY_RENEWAL_MS = 60_000;

function fresh() {
    return token !== null && Date.now() < expiresAt - EARLY_RENEWAL_MS;
}

export async function apiToken() {
    if (fresh()) return token;

    // One request, however many callers arrive at once. Four queued drafts
    // flushing together must not mint four tokens and revoke three of them.
    inFlight ??= mint().finally(() => {
        inFlight = null;
    });

    return inFlight;
}

async function mint() {
    const response = await fetch('/app/session-token', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.App?.csrf ?? '',
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        // Almost always an expired session. The caller decides what to do;
        // the queue keeps the draft rather than losing it.
        throw new Error(`session-token: ${response.status}`);
    }

    const body = await response.json();

    token = body.token;
    expiresAt = Date.parse(body.expires_at ?? '') || Date.now() + 3_600_000;

    return token;
}

export function forgetToken() {
    token = null;
    expiresAt = 0;
}
