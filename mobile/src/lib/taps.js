/**
 * The taps (AnnotechERP 37, phase 2). A tap is written to the phone's own
 * database first — one row each, so nothing typed while a batch is going out
 * is lost — and counted on screen at once; sending is the background's job.
 * The taps go as the PHONE, each naming the QC session it was made in, so a
 * QC's waiting taps still go after the next QC signs in (annotech-rmg's fault
 * #8). A tap the server refuses for good stays here, with its reason, until
 * somebody clears it (#3a).
 *
 * The sessions opened or closed with no network live here too: the server
 * must know a session before it takes that session's taps.
 */
import { useSyncExternalStore } from "react";
import { AppState } from "react-native";
import * as SQLite from "expo-sqlite";
import Storage from "expo-sqlite/kv-store";
import { request } from "./api";
import { phoneHeaders } from "./phone";

const BATCH = 200;
const DAY = 24 * 60 * 60 * 1000;

const db = SQLite.openDatabaseSync("tally.db");
db.execSync(`
  PRAGMA journal_mode = WAL;
  CREATE TABLE IF NOT EXISTS taps (
    uuid TEXT PRIMARY KEY NOT NULL,
    company_id TEXT NOT NULL,
    session TEXT NOT NULL,
    pick TEXT NOT NULL,
    action TEXT NOT NULL,
    panel_id TEXT,
    tags INTEGER NOT NULL DEFAULT 0,
    label TEXT,
    payload TEXT NOT NULL,
    made_at INTEGER NOT NULL,
    state TEXT NOT NULL DEFAULT 'WAITING',
    seq INTEGER,
    message TEXT
  );
  CREATE INDEX IF NOT EXISTS taps_state ON taps (state, made_at);
  CREATE INDEX IF NOT EXISTS taps_pick ON taps (pick, state);
`);

/* ── What the screens watch ─────────────────────────────────────────── */

const listeners = new Set();
const count = (state) => db.getFirstSync("SELECT count(*) AS n FROM taps WHERE state = ?", [state])?.n ?? 0;
let status = { waiting: count("WAITING"), refused: count("REFUSED"), sending: false, error: null, lastSentAt: Number(Storage.getItemSync("taps.last")) || null, completed: 0 };
let backoffUntil = 0;
let backoffMs = 0;

function changed(extra = {}) {
  status = { ...status, waiting: count("WAITING"), refused: count("REFUSED"), ...extra };
  listeners.forEach((listener) => listener());
}

function subscribe(listener) {
  listeners.add(listener);

  return () => listeners.delete(listener);
}

/** { waiting, refused, sending, error, lastSentAt, completed } — redrawn on every change. */
export const useTaps = () => useSyncExternalStore(subscribe, () => status);
export const onTapsChange = subscribe;

/** Batches answered so far: a summary read before batch n does not hold its taps. */
export const completedSeq = () => Number(Storage.getItemSync("taps.seq")) || 0;

/* ── Writing ────────────────────────────────────────────────────────── */

/**
 * One tap, kept. `pick` is the line, job (and bundle) it counts towards on
 * screen; `label` what the refused list shows of it.
 */
export function addTap({ companyId, session, pick, label, payload }) {
  db.runSync(
    "INSERT INTO taps (uuid, company_id, session, pick, action, panel_id, tags, label, payload, made_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [payload.client_uuid, companyId, session, pick, payload.action, payload.panel_id ?? null, payload.defects?.length ?? 0, label ?? null, JSON.stringify(payload), Date.now()],
  );
  changed();
  soon();
}

/**
 * The taps a summary read at `seq` does not hold yet, oldest first: those
 * still here, and those sent after it was read.
 */
export function pendingFor(pick, seq) {
  return db.getAllSync("SELECT action, panel_id, tags, payload FROM taps WHERE pick = ? AND (state = 'WAITING' OR (state = 'SENT' AND seq > ?)) ORDER BY made_at", [pick, seq]);
}

/** The newest tap of this session on this pick still on the phone, or null. */
export function lastWaiting(pick, session) {
  return db.getFirstSync("SELECT uuid, action FROM taps WHERE pick = ? AND session = ? AND state = 'WAITING' ORDER BY made_at DESC LIMIT 1", [pick, session]);
}

export function removeTap(uuid) {
  db.runSync("DELETE FROM taps WHERE uuid = ?", [uuid]);
  changed();
}

export const refusedTaps = () => db.getAllSync("SELECT uuid, action, label, message, made_at FROM taps WHERE state = 'REFUSED' ORDER BY made_at DESC LIMIT 500");

export function clearRefused(uuid = null) {
  if (uuid) db.runSync("DELETE FROM taps WHERE uuid = ? AND state = 'REFUSED'", [uuid]);
  else db.runSync("DELETE FROM taps WHERE state = 'REFUSED'");
  changed();
}

/* ── Sessions with no network ───────────────────────────────────────── */

const SESSIONS = "session.queue";
const sessionsWaiting = () => {
  try {
    return JSON.parse(Storage.getItemSync(SESSIONS) ?? "[]");
  } catch {
    return [];
  }
};

/** Sessions the server has not been told of yet. */
export const sessionsPending = () => sessionsWaiting().length > 0;

/** A session opened or closed here, for the server. */
export function queueSession(row) {
  const rows = sessionsWaiting();
  const at = rows.findIndex((r) => r.uuid === row.uuid);
  if (at === -1) rows.push(row);
  else rows[at] = { ...rows[at], ...row };
  Storage.setItemSync(SESSIONS, JSON.stringify(rows));
}

export async function sendSessions() {
  const rows = sessionsWaiting();
  for (const company of new Set(rows.map((r) => r.company_id))) {
    const mine = rows.filter((r) => r.company_id === company).slice(0, 100);
    await request("/quality-tally/phone/sessions", { method: "POST", headers: await phoneHeaders(company, status.waiting), body: { sessions: mine.map(({ company_id, ...r }) => r) } });
    // Sent as they were then: one changed since (ended meanwhile) stays to be sent again.
    const sent = new Map(mine.map((r) => [r.uuid, JSON.stringify(r)]));
    Storage.setItemSync(SESSIONS, JSON.stringify(sessionsWaiting().filter((r) => sent.get(r.uuid) !== JSON.stringify(r))));
  }
}

/* ── Sending ────────────────────────────────────────────────────────── */

let running = null;
let timer = null;

/** Soon, not at once: a QC's burst of taps goes in one request. */
function soon(ms = 800) {
  clearTimeout(timer);
  timer = setTimeout(() => syncTaps().catch(() => {}), ms);
}

/**
 * Sends what waits, a batch at a time. No answer: tried again later, each
 * time a little later (up to a minute). The phone itself refused (blocked,
 * no longer approved): nothing more is sent until it is let in again.
 */
export function syncTaps({ now = false } = {}) {
  if (running) return running;
  if (!now && Date.now() < backoffUntil) return Promise.resolve();
  running = (async () => {
    changed({ sending: true });
    try {
      await sendSessions();
      for (;;) {
        const rows = db.getAllSync("SELECT uuid, company_id, payload FROM taps WHERE state = 'WAITING' ORDER BY made_at LIMIT ?", [BATCH]);
        if (!rows.length) break;
        const company = rows[0].company_id;
        const batch = rows.filter((r) => r.company_id === company);
        const res = await request("/quality-tally/phone/taps", {
          method: "POST",
          headers: await phoneHeaders(company, status.waiting),
          body: { entries: batch.map((r) => JSON.parse(r.payload)) },
          deadline: 20000,
        });
        const refused = new Map((res.rejected ?? []).map((r) => [r.client_uuid, r.message]));
        const seq = completedSeq() + 1;
        db.withTransactionSync(() => {
          for (const r of batch) {
            if (refused.has(r.uuid)) db.runSync("UPDATE taps SET state = 'REFUSED', message = ? WHERE uuid = ?", [refused.get(r.uuid) ?? "", r.uuid]);
            else db.runSync("UPDATE taps SET state = 'SENT', seq = ? WHERE uuid = ? AND state = 'WAITING'", [seq, r.uuid]);
          }
        });
        Storage.setItemSync("taps.seq", String(seq));
        Storage.setItemSync("taps.last", String(Date.now()));
        changed({ lastSentAt: Date.now(), completed: seq });
      }
      // Sent taps are kept a day for the screen's counts, then let go.
      db.runSync("DELETE FROM taps WHERE state = 'SENT' AND made_at < ?", [Date.now() - DAY]);
      backoffMs = 0;
      backoffUntil = 0;
      changed({ sending: false, error: null });
    } catch (error) {
      if (error.status === 0 || error.status === 429 || error.status >= 500) {
        backoffMs = Math.min(60000, backoffMs ? backoffMs * 2 : 5000);
        backoffUntil = Date.now() + backoffMs;
        changed({ sending: false, error: null });
      } else {
        // 401/403: the phone is blocked or waits for approval again — the manager must act.
        backoffUntil = Date.now() + 60000;
        changed({ sending: false, error: error.message });
      }
    } finally {
      running = null;
    }
  })();

  return running;
}

/** Started once by the app: every few seconds while anything waits, and on coming back. */
export function startTapSync() {
  const tick = setInterval(() => {
    if ((status.waiting > 0 || sessionsWaiting().length > 0) && AppState.currentState === "active") syncTaps().catch(() => {});
  }, 5000);
  const sub = AppState.addEventListener("change", (state) => state === "active" && syncTaps({ now: true }).catch(() => {}));
  setTimeout(() => syncTaps({ now: true }).catch(() => {}), 1000);

  return () => {
    clearInterval(tick);
    sub.remove();
  };
}

/** Waits for a batch on its way, so an undo never races it. */
export const settled = () => running ?? Promise.resolve();
