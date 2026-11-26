"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Quality Tally (AnnotechERP 36): every write the setup screens make —
 * Defect Types, Jobs, Line Target, QC Assignment — through one `qtAction`.
 * The QC's taps do not come here: they go from the browser through
 * /api/quality-tally (a tap must not wait on a server action).
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const json = (method, body) => ({ method, body: body === undefined ? undefined : JSON.stringify(body) });

const CALLS = {
  saveDefect: (body, id) => [id ? `/quality-tally/defect-types/${id}` : "/quality-tally/defect-types", json(id ? "PATCH" : "POST", body)],
  deleteDefect: (id) => [`/quality-tally/defect-types/${id}`, json("DELETE")],
  standardDefects: (section) => ["/quality-tally/defect-types/standard", json("POST", { section: section || null })],
  saveJob: (body, id) => [id ? `/quality-tally/jobs/${id}` : "/quality-tally/jobs", json(id ? "PATCH" : "POST", body)],
  deleteJob: (id) => [`/quality-tally/jobs/${id}`, json("DELETE")],
  saveTarget: (body, id) => [id ? `/quality-tally/targets/${id}` : "/quality-tally/targets", json(id ? "PATCH" : "POST", body)],
  deleteTarget: (id) => [`/quality-tally/targets/${id}`, json("DELETE")],
  copyTargets: (body) => ["/quality-tally/targets/copy", json("POST", body)],
  bulkTargets: (body) => ["/quality-tally/targets/bulk", json("POST", body)],
  shortfall: (date, section) => [`/quality-tally/targets/shortfall?date=${date}&section=${section}`, { method: "GET" }],
  saveAttendance: (body) => ["/quality-tally/attendance", json("POST", body)],
  runReport: (body) => ["/report-jobs", json("POST", body)],
  savePanel: (body, id) => [id ? `/quality-tally/panels/${id}` : "/quality-tally/panels", json(id ? "PATCH" : "POST", body)],
  deletePanel: (id) => [`/quality-tally/panels/${id}`, json("DELETE")],
  standardPanels: () => ["/quality-tally/panels/standard", json("POST", {})],
  deleteBundle: (id) => [`/quality-tally/cut-bundles/${id}`, json("DELETE")],
  panelLot: (key) => [`/quality-tally/panel-inspections/lot?${new URLSearchParams(Object.entries(key).filter(([, v]) => v !== null && v !== ""))}`, { method: "GET" }],
  inspectorOptions: () => ["/quality-tally/inspectors/options", { method: "GET" }],
  saveInspector: (body, id) => [id ? `/quality-tally/inspectors/${id}` : "/quality-tally/inspectors", json(id ? "PATCH" : "POST", body)],
  resetPin: (id) => [`/quality-tally/inspectors/${id}/reset-pin`, json("POST", {})],
  savePhone: (body, id) => [`/quality-tally/phones/${id}`, json("PATCH", body)],
  blockPhone: (id) => [`/quality-tally/phones/${id}/block`, json("POST", {})],
  unblockPhone: (id) => [`/quality-tally/phones/${id}/unblock`, json("POST", {})],
  deletePhone: (id) => [`/quality-tally/phones/${id}`, json("DELETE")],
  deleteInspector: (id) => [`/quality-tally/inspectors/${id}`, json("DELETE")],
};

const READS = new Set(["inspectorOptions", "shortfall", "runReport", "panelLot"]);

async function qtAction(name, ...args) {
  const call = CALLS[name];
  if (!call) return { status: "error", message: `Unknown action ${name}` };
  try {
    const [path, options] = call(...args);
    const data = await apiFetch(path, options);
    if (!READS.has(name)) revalidatePath("/quality-tally", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { qtAction };
