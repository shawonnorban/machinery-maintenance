"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Finishing Line (AnnotechERP 18): the screen kit's resources, the process
 * groups with their sub-groups (18/01), DF Production's sheets and the
 * delivery board (18/02), S/C Process's challans, receipts and bills (18/03),
 * QC Process's four-point inspections (18/04), Fabric Store's deliveries and
 * cutting requisitions (18/05), and the Fabric Finishing Reports (18/06).
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/finishing-line", "layout");
  return { status: "success", ...extra };
}

function body(formData, booleans) {
  const out = {};
  for (const [key, value] of formData.entries()) if (key !== "__intent") out[key] = value === "" ? null : value;
  for (const key of booleans) if (formData.has(key)) out[key] = formData.get(key) === "1";
  return out;
}

async function createResource(resource, booleans, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, { method: "POST", body: JSON.stringify(body(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deleteResource(resource, id) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(body(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function createGroup(previousState, formData) {
  try {
    await apiFetch("/finishing/groups", { method: "POST", body: JSON.stringify(body(formData, [])) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadSubgroups(group) {
  try {
    return { status: "success", rows: await apiFetch(`/finishing/groups/${group}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveSubgroups(group, rows) {
  try {
    const payload = rows.map((r) => ({ id: r.id ?? null, code: r.code, name: r.name, sort_order: r.sort_order === "" ? null : r.sort_order, active: !!r.active }));
    return done({ rows: await apiFetch(`/finishing/groups/${group}`, { method: "PUT", body: JSON.stringify({ rows: payload }) }) });
  } catch (error) {
    return fail(error);
  }
}

async function loadSheet(no) {
  try {
    return { status: "success", sheet: await apiFetch(`/finishing/production-sheets/${encodeURIComponent(no)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveSheet(no, payload) {
  try {
    const sheet = await apiFetch(no ? `/finishing/production-sheets/${encodeURIComponent(no)}` : "/finishing/production-sheets", { method: no ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ sheet });
  } catch (error) {
    return fail(error);
  }
}

async function postSheet(no) {
  try {
    await apiFetch(`/finishing/production-sheets/${encodeURIComponent(no)}/post`, { method: "POST" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deleteSheet(no) {
  try {
    await apiFetch(`/finishing/production-sheets/${encodeURIComponent(no)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deliverBatch(no, payload) {
  try {
    await apiFetch(`/finishing/delivery-board/${encodeURIComponent(no)}/deliver`, { method: "POST", body: JSON.stringify(payload) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** A bill is named by its own number and its party's code: "INV-77|SUP-1". */
function billPath(ref) {
  const [no, party] = String(ref).split("|");
  return `/finishing/sc-bills/${encodeURIComponent(no)}${party ? `?party=${encodeURIComponent(party)}` : ""}`;
}

async function scLoad(path) {
  try {
    return { status: "success", doc: await apiFetch(path) };
  } catch (error) {
    return fail(error);
  }
}

async function scSave(path, method, payload) {
  try {
    return done({ doc: await apiFetch(path, { method, body: JSON.stringify(payload) }) });
  } catch (error) {
    return fail(error);
  }
}

async function scPost(path, payload = {}) {
  try {
    await apiFetch(path, { method: "POST", body: JSON.stringify(payload) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadScChallan(no) {
  return scLoad(`/finishing/sc-challans/${encodeURIComponent(no)}`);
}

async function saveScChallan(no, payload) {
  return scSave(no ? `/finishing/sc-challans/${encodeURIComponent(no)}` : "/finishing/sc-challans", no ? "PUT" : "POST", payload);
}

async function postScChallan(no) {
  return scPost(`/finishing/sc-challans/${encodeURIComponent(no)}/post`);
}

async function cancelScChallan(no) {
  return scPost(`/finishing/sc-challans/${encodeURIComponent(no)}/cancel`);
}

async function loadScReceipt(no) {
  return scLoad(`/finishing/sc-receipts/${encodeURIComponent(no)}`);
}

async function saveScReceipt(no, payload) {
  return scSave(no ? `/finishing/sc-receipts/${encodeURIComponent(no)}` : "/finishing/sc-receipts", no ? "PUT" : "POST", payload);
}

async function postScReceipt(no) {
  return scPost(`/finishing/sc-receipts/${encodeURIComponent(no)}/post`);
}

async function loadScBill(ref) {
  return scLoad(billPath(ref));
}

async function saveScBill(ref, payload) {
  return scSave(ref ? billPath(ref) : "/finishing/sc-bills", ref ? "PUT" : "POST", payload);
}

async function setScBillStatus(to, ref) {
  const [path, query] = billPath(ref).split("?");
  return scPost(`${path}/status${query ? `?${query}` : ""}`, { to });
}

async function loadInspection(ref) {
  return scLoad(`/finishing/inspections/${encodeURIComponent(ref)}`);
}

async function saveInspection(ref, payload) {
  return scSave(ref ? `/finishing/inspections/${encodeURIComponent(ref)}` : "/finishing/inspections", ref ? "PUT" : "POST", payload);
}

async function postInspection(ref) {
  return scPost(`/finishing/inspections/${encodeURIComponent(ref)}/post`);
}

async function loadStoreDelivery(no) {
  return scLoad(`/finishing/store-deliveries/${encodeURIComponent(no)}`);
}

async function saveStoreDelivery(no, payload) {
  return scSave(no ? `/finishing/store-deliveries/${encodeURIComponent(no)}` : "/finishing/store-deliveries", no ? "PUT" : "POST", payload);
}

async function sendStoreDelivery(no) {
  return scPost(`/finishing/store-deliveries/${encodeURIComponent(no)}/send`);
}

async function receiveStoreDelivery(no) {
  return scPost(`/finishing/store-deliveries/${encodeURIComponent(no)}/receive`);
}

async function loadCutRequisition(no) {
  return scLoad(`/finishing/cut-requisitions/${encodeURIComponent(no)}`);
}

async function saveCutRequisition(no, payload) {
  return scSave(no ? `/finishing/cut-requisitions/${encodeURIComponent(no)}` : "/finishing/cut-requisitions", no ? "PUT" : "POST", payload);
}

/** One report read ("finishing/<report>?…" as the report card asks for it). */
async function finReportAction(path) {
  if (typeof path !== "string" || path.includes("..") || /^[/\\]/.test(path)) throw new Error("Bad report path");
  try {
    return { status: "success", data: await apiFetch(`/finishing/reports/${path.replace(/^finishing\//, "")}`) };
  } catch (error) {
    return fail(error);
  }
}

export { deleteResource, createResource, updateResource, createGroup, loadSubgroups, saveSubgroups, loadSheet, saveSheet, postSheet, deleteSheet, deliverBatch, loadScChallan, saveScChallan, postScChallan, cancelScChallan, loadScReceipt, saveScReceipt, postScReceipt, loadScBill, saveScBill, setScBillStatus, loadInspection, saveInspection, postInspection, loadStoreDelivery, saveStoreDelivery, sendStoreDelivery, receiveStoreDelivery, loadCutRequisition, saveCutRequisition, finReportAction };
