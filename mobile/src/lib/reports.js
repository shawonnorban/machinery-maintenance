/**
 * Breakdown reports made with no network (AnnotechERP 37, phase 4): kept on
 * the phone, photo and all, and sent when the server answers again — each
 * with its own Idempotency-Key, so a report the server took but whose answer
 * was lost is not made twice. A report the server refuses stays, with the
 * reason, until the person deletes it. The time of the failure is the time it
 * was reported on the phone, not the time it reached the server.
 */
import { useEffect, useState } from "react";
import Storage from "expo-sqlite/kv-store";
import * as Crypto from "expo-crypto";
import { serverNow } from "./clock";

const KEY = "breakdown_queue";
const listeners = new Set();
let queue = read();
let sending = false;

function read() {
  try {
    return JSON.parse(Storage.getItemSync(KEY) ?? "[]");
  } catch {
    return [];
  }
}

function save(next) {
  queue = next;
  Storage.setItemSync(KEY, JSON.stringify(next));
  listeners.forEach((listener) => listener(next));
}

/** Kept for later: the body as it would have been sent, and what to show meanwhile. */
export function keepReport(body, label) {
  const at = serverNow().toISOString();
  save([...queue, { key: Crypto.randomUUID(), body: { ...body, reported_at: body.reported_at ?? at, failure_at: body.failure_at ?? at }, label, at, error: null }]);
}

export function dropReport(key) {
  save(queue.filter((r) => r.key !== key));
}

/**
 * Each report, oldest first, through `call` (the signed-in session's).
 * No answer stops the round; a refusal is kept on the report.
 */
export async function sendReports(call) {
  if (sending || queue.length === 0) return 0;
  sending = true;
  let sent = 0;
  try {
    for (const report of [...queue]) {
      if (report.error) continue;
      try {
        await call("/breakdowns", { method: "POST", body: report.body, headers: { "Idempotency-Key": report.key }, deadline: 30000 });
        dropReport(report.key);
        sent += 1;
      } catch (error) {
        if (error.status === 0 || error.status === 401 || error.status >= 500) break;
        save(queue.map((r) => (r.key === report.key ? { ...r, error: error.message } : r)));
      }
    }
  } finally {
    sending = false;
  }

  return sent;
}

/** Try a refused report again (the person fixed what was wrong on the server). */
export function retryReport(key) {
  save(queue.map((r) => (r.key === key ? { ...r, error: null } : r)));
}

export function useReports() {
  const [list, setList] = useState(queue);

  useEffect(() => {
    listeners.add(setList);

    return () => listeners.delete(setList);
  }, []);

  return list;
}
