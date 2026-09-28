import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/**
 * The one behaviour docs/12-Stack-Migration-Implementation-Plan.md Phase E
 * names as a required verification gate before Breakdown/WorkOrder screens
 * migrate: a draft retried under a dead connection must produce exactly
 * one server-side record, never more. Each test re-imports queue.js after
 * `vi.resetModules()` so its module-level `flushing` guard starts fresh,
 * and clears IndexedDB between tests since fake-indexeddb persists data
 * across them otherwise.
 */
async function freshQueue() {
  vi.resetModules();
  return import("@/lib/offline/queue");
}

// Clears the object store's contents rather than deleting the database:
// db.js's tx() opens a fresh connection per call and never closes it, so
// deleteDatabase() would block forever behind those still-open handles.
async function clearDb() {
  const { tx } = await import("@/lib/offline/db");
  await tx("readwrite", (store) => store.clear());
}

describe("offline queue", () => {
  beforeEach(async () => {
    await clearDb();
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("keeps the same idempotency key across every retry of one draft", async () => {
    const { saveDraft, flush, pendingDrafts } = await freshQueue();

    const draft = await saveDraft({ endpoint: "/breakdowns", payload: { note: "Line 3 stopped" } });

    // Two dead-connection attempts, then success — three sends of the
    // SAME draft, not three drafts.
    fetch
      .mockRejectedValueOnce(new Error("network down"))
      .mockRejectedValueOnce(new Error("network down"))
      .mockResolvedValueOnce(new Response(JSON.stringify({ success: true, data: {} }), { status: 201 }));

    await flush();
    await flush();
    await flush();

    expect(fetch).toHaveBeenCalledTimes(3);
    const keysSent = fetch.mock.calls.map(([, options]) => JSON.parse(options.body).idempotencyKey);
    expect(new Set(keysSent)).toEqual(new Set([draft.key]));

    expect(await pendingDrafts()).toHaveLength(0);
  });

  it("treats a replayed 409 as success and removes the draft exactly once", async () => {
    const { saveDraft, flush, pendingDrafts } = await freshQueue();

    await saveDraft({ endpoint: "/breakdowns", payload: { note: "Dye vat overheating" } });

    // The server actually created the record on attempt 1, but the
    // response never reached the client (a dropped connection, not a
    // dropped request) — attempt 2 replays the same key and the server
    // says so.
    fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ success: false, code: "IDEMPOTENCY_CONFLICT" }), {
        status: 409,
        headers: { "idempotent-replay": "true" },
      }),
    );

    await flush();

    expect(await pendingDrafts()).toHaveLength(0);
  });

  it("keeps a still-in-flight 409 pending rather than discarding it", async () => {
    const { saveDraft, flush, pendingDrafts } = await freshQueue();

    await saveDraft({ endpoint: "/breakdowns", payload: { note: "Boiler pressure alarm" } });

    // No idempotent-replay header: the first attempt might still be
    // processing. Discarding here could lose the report if that attempt
    // goes on to fail.
    fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ success: false, code: "IDEMPOTENCY_CONFLICT" }), { status: 409 }),
    );

    await flush();

    const remaining = await pendingDrafts();
    expect(remaining).toHaveLength(1);
    expect(remaining[0].attempts).toBe(1);
    expect(remaining[0].state).toBe("pending");
  });

  it("never sends two drafts concurrently as one — a second flush call while one is in flight is a no-op", async () => {
    const { saveDraft, flush, pendingDrafts } = await freshQueue();

    await saveDraft({ endpoint: "/breakdowns", payload: {} });

    let resolveFirst;
    const inFlight = new Promise((resolve) => {
      resolveFirst = resolve;
    });
    fetch.mockImplementationOnce(() => inFlight);

    const firstFlush = flush();
    const secondFlush = flush(); // Should be swallowed by the `flushing` guard.

    resolveFirst(new Response(JSON.stringify({ success: true, data: {} }), { status: 201 }));
    await Promise.all([firstFlush, secondFlush]);

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(await pendingDrafts()).toHaveLength(0);
  });
});
