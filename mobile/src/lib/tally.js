/**
 * What the tally screen shows, asked as the phone for the QC's session and
 * kept on the phone, so the tally opens and counts with no network (fault #4
 * of annotech-rmg's app): the lines, today's jobs and targets, panels,
 * defects, the cutting table's bundle cards, and each line and job's last
 * running total. On screen the total is the server's last one plus every tap
 * it does not hold yet — so a reply never rolls the count back.
 */
import Storage from "expo-sqlite/kv-store";
import { request } from "./api";
import { phoneHeaders } from "./phone";
import { completedSeq, lastWaiting, pendingFor, removeTap, sendSessions, sessionsPending, settled, useTaps } from "./taps";

const qs = (params) =>
  Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== "")
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
    .join("&");

async function phoneCall(qc, path, { params = {}, method = "GET", body } = {}) {
  // A sign-in made with no network is told to the server before anything is asked in its name —
  // else the server, not knowing it yet, answers "session ended" and the QC is signed out.
  if (sessionsPending()) await sendSessions();
  const query = qs({ session: qc.session.uuid, ...(method === "GET" ? params : {}) });

  return request(`/quality-tally/phone/${path}?${query}`, { method, body, headers: await phoneHeaders(qc.company.id) });
}

const readJson = (key) => {
  try {
    return JSON.parse(Storage.getItemSync(key) ?? "null");
  } catch {
    return null;
  }
};
const qcKey = (qc) => `${qc.company.code}/${qc.qc.employee_id}`.toUpperCase();

/** The line, job (and on a cutting table, bundle) a tap counts towards. */
export const pickKey = (line, jobId, bundleId) => (line && jobId ? `${line.section}:${line.line_id}:${jobId}${line.section === "CUTTING" ? `:${bundleId ?? ""}` : ""}` : "");

/* ── Context ────────────────────────────────────────────────────────── */

export const cachedContext = (qc) => readJson(`tally.ctx.${qcKey(qc)}`);

/** Fresh from the server and kept; a session opened with no network is sent first, so the server knows it. */
export async function loadContext(qc) {
  const data = await phoneCall(qc, "tally");
  const kept = { data, at: Date.now() };
  Storage.setItemSync(`tally.ctx.${qcKey(qc)}`, JSON.stringify(kept));

  return kept;
}

/* ── Running total ──────────────────────────────────────────────────── */

export const cachedSummary = (qc, key) => readJson(`tally.sum.${qcKey(qc)}.${key}`);

export async function loadSummary(qc, line, jobId, bundleId) {
  const seq = completedSeq();
  const data = await phoneCall(qc, "summary", { params: { section: line.section, line_id: line.line_id, job_id: jobId, cut_bundle_id: bundleId } });
  const kept = { data, seq, at: Date.now() };
  Storage.setItemSync(`tally.sum.${qcKey(qc)}.${pickKey(line, jobId, bundleId)}`, JSON.stringify(kept));

  return kept;
}

const EMPTY = { rft: 0, defect: 0, rectify: 0, reject: 0, defect_tags: 0, mine: 0, target: null, checked: 0, pass: 0, hourly: [], top_defects: [] };

/** One tap's share of the counts (the web's Tally Entry does the same). */
function add(s, action, tags) {
  const n = { ...s };
  const k = action.toLowerCase();
  n[k] = (n[k] ?? 0) + 1;
  n.defect_tags = (n.defect_tags ?? 0) + tags;
  n.mine = (n.mine ?? 0) + 1;
  n.checked = n.rft + n.defect;
  if (n.ready > 0 && (action === "RFT" || action === "DEFECT")) n.ready -= 1;
  n.pass = Math.min(n.rft + n.rectify, n.checked);
  n.dhu = n.checked ? Math.round((n.defect_tags / n.checked) * 10000) / 100 : null;
  n.rft_pct = n.checked ? Math.round((n.rft / n.checked) * 10000) / 100 : null;
  n.achievement = n.target ? Math.round((n.pass / n.target) * 10000) / 100 : null;

  return n;
}

/** A cutting tap's share of its panel's counts and of the lot. */
function addCut(s, action, panelId) {
  const c = s.cutting ?? { lot_checked: 0, lot_reject: 0, lot_pass: 0, bundles: 1, panels: {} };
  const cur = { checked: 0, pass: 0, defect: 0, rectify: 0, reject: 0, ...(c.panels?.[panelId] ?? {}) };
  const first = action === "RFT" || action === "DEFECT" ? 1 : 0;
  const next = { ...cur, checked: cur.checked + first, defect: cur.defect + (action === "DEFECT" ? 1 : 0), rectify: cur.rectify + (action === "RECTIFY" ? 1 : 0), reject: cur.reject + (action === "REJECT" ? 1 : 0) };
  next.pass = Math.min(next.checked - next.defect + next.rectify, next.checked);

  return { ...s, cutting: { ...c, lot_checked: c.lot_checked + first, lot_reject: c.lot_reject + (action === "REJECT" ? 1 : 0), panels: { ...c.panels, [panelId]: next } } };
}

/** The server's last total and every tap it does not hold yet. Redrawn whenever the taps change. */
export function useShownSummary(kept, key) {
  useTaps();
  const base = kept?.data ?? null;
  const pending = key ? pendingFor(key, kept?.seq ?? -1) : [];
  if (!base && !pending.length) return null;

  return pending.reduce((s, t) => {
    const next = add(s, t.action, t.tags);

    return t.panel_id ? addCut(next, t.action, t.panel_id) : next;
  }, base ?? { ...EMPTY, target: null });
}

/* ── A bundle card ──────────────────────────────────────────────────── */

/**
 * The card scanned or typed: asked of the server, or — with no network — found
 * among the table's cards kept on the phone. { found: INPUT|CARD|null, bundle, offline }
 */
export async function findBundle(qc, code, ctx) {
  const value = code.trim().toUpperCase();
  try {
    return await phoneCall(qc, "scan", { params: { code: value } });
  } catch (error) {
    if (error.status !== 0) throw error;
    const plain = value.replace(/-/g, "");
    const bundle = (ctx?.bundles ?? []).find((b) => b.barcode === plain || b.bundle_no?.toUpperCase() === value);

    return bundle ? { found: "INPUT", bundle, offline: true } : { found: null, offline: true };
  }
}

/* ── Undo ───────────────────────────────────────────────────────────── */

/**
 * Takes back the QC's latest tap on this line and job: one still on the phone
 * is simply removed (no network needed); else the server's. { action, where }
 */
export async function undoTap(qc, line, jobId, key) {
  await settled();
  const here = lastWaiting(key, qc.session.uuid);
  if (here) {
    removeTap(here.uuid);

    return { action: here.action, where: "PHONE" };
  }
  const res = await phoneCall(qc, "undo", { method: "POST", body: { section: line.section, line_id: line.line_id, job_id: jobId } });
  if (res.undone?.client_uuid) removeTap(res.undone.client_uuid);

  return { action: res.undone.action, where: "SERVER" };
}
