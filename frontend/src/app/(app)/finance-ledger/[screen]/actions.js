"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Finance & Ledger → Master Data (AnnotechERP 23/01): the screen kit's
 * resources, and the grids saved as a whole — maker-checker grants, a bank's
 * branches and a cheque book's leaves.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/finance-ledger", "layout");
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

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(body(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Brickland's ⊘: deactivate a row, or bring an inactive one back. */
async function toggleActive(resource, id) {
  try {
    const row = await apiFetch(`/erp/${resource}/${id}`);
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify({ active: !row.active }) });
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

const loader = (path) => async (key) => {
  try {
    return { status: "success", rows: await apiFetch(`${path}/${key}`) };
  } catch (error) {
    return fail(error);
  }
};

const saver = (path, shape) => async (key, rows) => {
  try {
    return done({ rows: await apiFetch(`${path}/${key}`, { method: "PUT", body: JSON.stringify({ rows: rows.map(shape) }) }) });
  } catch (error) {
    return fail(error);
  }
};

async function loadGrants(type) {
  return loader("/accounting/voucher-types")(type);
}

async function saveGrants(type, rows) {
  return saver("/accounting/voucher-types", (r) => ({ user_id: r.user_id || null, can_make: !!r.can_make, can_check: !!r.can_check }))(type, rows);
}

async function loadChildren(account) {
  return loader("/accounting/tree")(account);
}

async function noSave() {
  return { status: "error", message: "" };
}

async function loadBranches(bank) {
  return loader("/accounting/banks")(bank);
}

async function saveBranches(bank, rows) {
  const keys = ["address", "swift_code", "routing_no", "contact_person", "phone", "email"];
  return saver("/accounting/banks", (r) => ({ id: r.id ?? null, code: r.code, name: r.name, ...Object.fromEntries(keys.map((k) => [k, r[k] || null])), active: !!r.active }))(bank, rows);
}

async function loadLeaves(book) {
  return loader("/accounting/cheque-books")(book);
}

async function saveLeaves(book, rows) {
  return saver("/accounting/cheque-books", (r) => ({ id: r.id, active: !!r.active }))(book, rows);
}

const own = (path) => {
  if (typeof path !== "string" || !/^\/accounting\/(vouchers|mrr-bills|period|cheques|voucher-audit|reports)(\/|\?|$)/.test(path) || path.includes("..")) throw new Error("Not a voucher path");
  return path;
};

/** Voucher Entry (AnnotechERP 23/02): a read of the vouchers' or MRR bills' API. */
async function voRead(path) {
  try {
    return { status: "success", data: await apiFetch(own(path)) };
  } catch (error) {
    return fail(error);
  }
}

/** Voucher Entry (AnnotechERP 23/02): a write to the vouchers' or MRR bills' API. */
async function voWrite(method, path, payload) {
  try {
    const data = await apiFetch(own(path), { method, ...(payload === undefined ? {} : { body: JSON.stringify(payload) }) });
    revalidatePath("/finance-ledger", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { createResource, updateResource, toggleActive, deleteResource, loadGrants, saveGrants, loadChildren, noSave, loadBranches, saveBranches, loadLeaves, saveLeaves, voRead, voWrite };
