/**
 * Machinery on the phone (AnnotechERP 37, phase 4): what this person may do,
 * with the permission each screen's API checks. A PIN session has none — the
 * QC's sign-in is for the tally alone.
 */
import { useSession } from "./session";

export function machineAccess(permissions = []) {
  const has = (p) => permissions.includes(p);

  return {
    sees: has("asset.asset.view_any"),
    breakdowns: has("breakdown.breakdown.view_any"),
    report: has("breakdown.breakdown.create"),
    workOrders: has("work_order.work_order.view_any"),
    schedules: has("maintenance.schedule.view_any"),
    can: has,
  };
}

export function useMachineAccess() {
  const { session } = useSession();

  return machineAccess(session?.kind === "EMAIL" ? (session.permissions ?? []) : []);
}

/** What a label says: { kind: "asset" | "location", code } — the web's scan link, or the code alone. */
export function readLabel(text) {
  const raw = String(text ?? "").trim();
  const place = /\/scan\/location\/([^/?#\s]+)/i.exec(raw);
  if (place) return { kind: "location", code: decodeURIComponent(place[1]) };
  const machine = /\/scan\/([^/?#\s]+)/i.exec(raw);
  if (machine) return { kind: "asset", code: decodeURIComponent(machine[1]) };

  return { kind: "asset", code: raw };
}
