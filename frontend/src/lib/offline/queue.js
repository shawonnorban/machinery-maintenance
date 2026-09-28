"use client";

import { tx } from "@/lib/offline/db";

/**
 * Offline draft queue for technician screens (docs/12-Stack-Migration-
 * Implementation-Plan.md Phase E, ADR-034, SRS 38) — ported from
 * resources/js/offline/queue.js with its behaviour preserved exactly,
 * since this is the one area a subtly wrong port causes real-world harm
 * (a duplicated or lost breakdown report on the factory floor):
 *
 * - The idempotency key is generated when the draft is saved, not when
 *   it's sent — three taps of submit on a dead connection must produce
 *   one record, not three.
 * - A 409 with `idempotent-replay: true` means the first attempt already
 *   finished — treated as success, the draft is discarded. The same 409
 *   *without* that header means the first attempt is still in flight —
 *   the draft is kept and retried, since deleting it now could lose the
 *   report if that in-flight attempt still fails.
 * - Any other 4xx will never succeed on retry, so it's surfaced as
 *   `failed` rather than looped against forever.
 * - Full offline sync is explicitly out of scope: this queues writes, it
 *   doesn't let a technician read the machine list with no signal.
 *
 * One difference from the Blade version, forced by this stack's own
 * architecture (docs/12-Stack-Migration-Implementation-Plan.md Phase C
 * §5): the old queue minted its own short-lived bearer token client-side
 * (resources/js/offline/token.js) because the web app's session cookie
 * could do that safely. This stack's bearer token lives only in an
 * httpOnly cookie, unreachable from this file on purpose — so a draft is
 * sent through `/api/offline-relay` (a Next.js Route Handler) instead of
 * calling Laravel directly; that route reads the cookie server-side and
 * attaches the token there. This file never sees the token, same as
 * every other client-side code path in this app.
 */
const STORE = "drafts";
const RELAY_ENDPOINT = "/api/offline-relay";

function saveDraft({ endpoint, payload, label }) {
  const draft = {
    key: crypto.randomUUID(),
    endpoint,
    payload,
    // What to call this in a list of things waiting to send — "1 item
    // pending" tells a technician nothing about whether their report
    // actually went in.
    label: label ?? endpoint,
    createdAt: Date.now(),
    attempts: 0,
    state: "pending",
  };

  return tx("readwrite", (store) => store.put(draft)).then(() => {
    announce();
    return draft;
  });
}

function pendingDrafts() {
  return tx("readonly", (store) => store.getAll());
}

function removeDraft(key) {
  return tx("readwrite", (store) => store.delete(key));
}

function markFailed(draft, reason) {
  draft.state = "failed";
  draft.failureReason = reason;
  return tx("readwrite", (store) => store.put(draft));
}

async function discardDraft(key) {
  await removeDraft(key);
  announce();
}

let flushing = false;

async function flush() {
  // Two flushes at once would send every draft twice. The idempotency key
  // would deduplicate them at the API, but the second copy would read
  // "already in flight" (a 409) and the draft would look like a failure.
  if (flushing) {
    return;
  }

  flushing = true;

  try {
    await send();
  } finally {
    flushing = false;
    announce();
  }
}

async function send() {
  const drafts = (await pendingDrafts()).filter((draft) => draft.state !== "failed");

  if (drafts.length === 0) {
    return;
  }

  for (const draft of drafts) {
    let response;

    try {
      response = await fetch(RELAY_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ endpoint: draft.endpoint, payload: draft.payload, idempotencyKey: draft.key }),
      });
    } catch {
      // Network failure: still offline, or the relay itself is unreachable.
      // The draft stays exactly where it is for the next flush.
      return;
    }

    if (response.ok) {
      await removeDraft(draft.key);
      continue;
    }

    const status = response.status;
    const body = await response.json().catch(() => null);
    const code = body?.code;

    // The session cookie is gone or expired. Nothing here can fix that —
    // stop for this cycle rather than working through every draft against
    // a guaranteed-401.
    if (status === 401) {
      return;
    }

    if (status === 409 && code === "IDEMPOTENCY_CONFLICT") {
      if (response.headers.get("idempotent-replay") === "true") {
        await removeDraft(draft.key);
      } else {
        draft.attempts += 1;
        await tx("readwrite", (store) => store.put(draft));
      }
      continue;
    }

    if (status >= 400 && status < 500) {
      await markFailed(draft, code ?? `HTTP_${status}`);
      continue;
    }

    draft.attempts += 1;
    await tx("readwrite", (store) => store.put(draft));
  }
}

/**
 * Tell the page what is waiting. A technician must never have to wonder
 * whether their work was recorded — a queue nobody can see is
 * indistinguishable from a report that vanished.
 */
async function announce() {
  const drafts = await pendingDrafts();

  window.dispatchEvent(
    new CustomEvent("offline-queue:changed", {
      detail: {
        pending: drafts.filter((draft) => draft.state !== "failed"),
        failed: drafts.filter((draft) => draft.state === "failed"),
      },
    }),
  );
}

let started = false;

/** Idempotent — safe to call from every component that wants the queue running; only the first call wires anything up. */
function startOfflineQueue() {
  if (started || typeof window === "undefined") {
    return;
  }
  started = true;

  window.addEventListener("online", flush);
  window.addEventListener("offline", announce);
  announce();
  flush();

  setInterval(async () => {
    if (navigator.onLine && (await pendingDrafts()).length > 0) {
      flush();
    }
  }, 30_000);
}

export { saveDraft, pendingDrafts, discardDraft, flush, startOfflineQueue };
