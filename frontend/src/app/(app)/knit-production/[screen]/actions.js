"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Knit Production → Master Data (AnnotechERP 15/01): the screen kit's
 * resources, the machine reference lists, user and office mapping, a
 * machine's postings, and Sub-Contract Party Rate (Dye House's quotation).
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/knit-production", "layout");
  return { status: "success", ...extra };
}

function body(formData, booleans) {
  const out = {};
  for (const [key, value] of formData.entries())
    if (key !== "__intent") out[key] = value === "" ? null : value;
  for (const key of booleans)
    if (formData.has(key)) out[key] = formData.get(key) === "1";
  return out;
}

async function createResource(resource, booleans, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, {
      method: "POST",
      body: JSON.stringify(body(formData, booleans)),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, {
      method: "PATCH",
      body: JSON.stringify(body(formData, booleans)),
    });
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

/** Withdraw a row: clear Active? — it stays readable for whatever already names it. */
async function withdrawResource(resource, id) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, {
      method: "PATCH",
      body: JSON.stringify({ active: false }),
    });
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

const saver = (path) => async (key, rows) => {
  try {
    return done({
      rows: await apiFetch(`${path}/${key}`, {
        method: "PUT",
        body: JSON.stringify({ rows }),
      }),
    });
  } catch (error) {
    return fail(error);
  }
};

async function loadRefEntries(list) {
  return loader("/knitting/ref-lists")(list);
}

async function saveRefEntries(list, rows) {
  return saver("/knitting/ref-lists")(
    list,
    rows.map((r) => ({
      id: r.id ?? null,
      code: r.code,
      name: r.name,
      sort_order: r.sort_order === "" ? null : r.sort_order,
      active: !!r.active,
    })),
  );
}

async function loadOffices(user) {
  return loader("/knitting/office-users")(user);
}

async function saveOffices(user, rows) {
  return saver("/knitting/office-users")(
    user,
    rows.map((r) => ({
      factory_id: r.factory_id,
      selected: !!r.selected,
      is_primary: !!r.is_primary,
      can_write: !!r.can_write,
    })),
  );
}

async function loadPostings(machine) {
  return loader("/knitting/posting-machines")(machine);
}

async function savePostings(machine, rows) {
  return saver("/knitting/posting-machines")(
    machine,
    rows.map((r) => ({ id: r.id, until_at: r.until_at || null })),
  );
}

async function loadQuotation(id) {
  try {
    return {
      status: "success",
      quotation: await apiFetch(`/dyehouse/sc-quotations/${id}`),
    };
  } catch (error) {
    return fail(error);
  }
}

async function loadHeads(group) {
  try {
    return {
      status: "success",
      heads: await apiFetch(`/dyehouse/sc-quotations/heads?group=${group}`),
    };
  } catch (error) {
    return fail(error);
  }
}

async function saveQuotation(id, payload) {
  try {
    const quotation = await apiFetch(
      id ? `/dyehouse/sc-quotations/${id}` : "/dyehouse/sc-quotations",
      { method: id ? "PUT" : "POST", body: JSON.stringify(payload) },
    );
    return done({ quotation });
  } catch (error) {
    return fail(error);
  }
}

/**
 * Every read and write Yarn Procurement (15/02) makes in place: `kind` is
 * requisitions, comparisons or orders; `op` list, show, options, source,
 * save (create or update by number), remove, an action (submit, cancel,
 * close, finalize, place) — or approvals / decide on the approve list.
 */
async function yarnAction(kind, op, payload = {}) {
  const base = `/knitting/yarn/${kind}`;
  const qs = (q) => {
    const clean = Object.fromEntries(
      Object.entries(q ?? {}).filter(
        ([, v]) => v !== "" && v !== null && v !== undefined,
      ),
    );
    return Object.keys(clean).length ? `?${new URLSearchParams(clean)}` : "";
  };
  const no = payload.no ? encodeURIComponent(payload.no) : "";
  const call =
    {
      list: () => [`${base}${qs(payload.query)}`, { method: "GET" }],
      show: () => [`${base}/${no}`, { method: "GET" }],
      source: () =>
        kind === "comparisons"
          ? [
              `${base}/source/${encodeURIComponent(payload.requisition)}`,
              { method: "GET" },
            ]
          : [`${base}/source${qs(payload.query)}`, { method: "GET" }],
      save: () => [
        no ? `${base}/${no}` : base,
        { method: no ? "PUT" : "POST", body: JSON.stringify(payload.body) },
      ],
      remove: () => [`${base}/${no}`, { method: "DELETE" }],
      approvals: () => [
        `/knitting/yarn/approvals${qs(payload.query)}`,
        { method: "GET" },
      ],
      decide: () => [
        "/knitting/yarn/approvals/decide",
        { method: "POST", body: JSON.stringify(payload.body) },
      ],
    }[op] ?? (() => [`${base}/${no}/${op}`, { method: "POST" }]);
  try {
    const [path, init] = call();
    const data = await apiFetch(path, init);
    return init.method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/**
 * Every read and write Yarn Store (15/03) makes in place: `path` under
 * /knitting/ystore (its query included), GET unless a method is given.
 */
async function ystoreAction(path, method = "GET", payload) {
  if (typeof path !== "string" || path.includes("..") || /^[/\\]/.test(path))
    throw new Error("Bad yarn store path");
  try {
    const data = await apiFetch(`/knitting/ystore/${path}`, {
      method,
      ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
    });
    return method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/**
 * Every read and write Knit Production → Production (15/04) makes in place:
 * `path` under /knitting/prod (its query included), GET unless a method is given.
 */
async function prodAction(path, method = "GET", payload) {
  if (typeof path !== "string" || path.includes("..") || /^[/\\]/.test(path))
    throw new Error("Bad production path");
  try {
    const data = await apiFetch(`/knitting/prod/${path}`, {
      method,
      ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
    });
    return method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/**
 * Every read and write Knit Production → Fabric Store (15/05) makes in
 * place: `path` under /knitting/fstore (its query included), GET unless a
 * method is given.
 */
async function fstoreAction(path, method = "GET", payload) {
  if (typeof path !== "string" || path.includes("..") || /^[/\\]/.test(path))
    throw new Error("Bad fabric store path");
  try {
    const data = await apiFetch(`/knitting/fstore/${path}`, {
      method,
      ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
    });
    return method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/**
 * Every read and write Knit Production → Needle Store (15/07) makes in
 * place: `path` under /knitting/needle (its query included), GET unless a
 * method is given.
 */
async function needleAction(path, method = "GET", payload) {
  if (typeof path !== "string" || path.includes("..") || /^[/\\]/.test(path))
    throw new Error("Bad needle store path");
  try {
    const data = await apiFetch(`/knitting/needle/${path}`, {
      method,
      ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
    });
    return method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/**
 * Knit Production → Reports (15/11): a report or a stock position, read
 * now — `path` under /knitting/reports, its query included. Reports only read.
 */
async function reportAction(path) {
  if (typeof path !== "string" || path.includes("..") || /^[/\\]/.test(path))
    throw new Error("Bad report path");
  try {
    return {
      status: "success",
      data: await apiFetch(`/knitting/reports/${path}`),
    };
  } catch (error) {
    return fail(error);
  }
}

export {
  reportAction,
  needleAction,
  fstoreAction,
  prodAction,
  ystoreAction,
  yarnAction,
  createResource,
  updateResource,
  deleteResource,
  withdrawResource,
  loadRefEntries,
  saveRefEntries,
  loadOffices,
  saveOffices,
  loadPostings,
  savePostings,
  loadQuotation,
  loadHeads,
  saveQuotation,
};
