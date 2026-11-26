"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Work Study → Master Data (AnnotechERP 14/01): the screen kit's resources,
 * the two access grids, responsible owners and criticality lines.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/work-study", "layout");
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

/** Floor-Wise Target Setup or Finishing Floor Access Setup, every line at once. */
async function saveAccess(kind, rows) {
  try {
    return done({
      rows: await apiFetch(`/workstudy/${kind}`, {
        method: "PUT",
        body: JSON.stringify({ rows }),
      }),
    });
  } catch (error) {
    return fail(error);
  }
}

async function createDepartment(previousState, formData) {
  try {
    await apiFetch("/workstudy/departments", {
      method: "POST",
      body: JSON.stringify(body(formData, [])),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadPersons(dept) {
  try {
    return {
      status: "success",
      rows: await apiFetch(`/workstudy/departments/${dept}/persons`),
    };
  } catch (error) {
    return fail(error);
  }
}

async function savePersons(dept, rows) {
  try {
    return done({
      rows: await apiFetch(`/workstudy/departments/${dept}/persons`, {
        method: "PUT",
        body: JSON.stringify({
          rows: rows.map((r) => ({ ...r, user_id: r.user_id || null })),
        }),
      }),
    });
  } catch (error) {
    return fail(error);
  }
}

async function loadBand(band) {
  try {
    return {
      status: "success",
      rows: await apiFetch(`/workstudy/criticality/${band}`),
    };
  } catch (error) {
    return fail(error);
  }
}

async function saveBand(band, rows) {
  try {
    return done({
      rows: await apiFetch(`/workstudy/criticality/${band}`, {
        method: "PUT",
        body: JSON.stringify({
          rows: rows.map((r) => ({
            planning_line_id: r.planning_line_id,
            runs: !!r.runs,
            preferred_group: r.preferred_group || null,
          })),
        }),
      }),
    });
  } catch (error) {
    return fail(error);
  }
}

/** Planning Learning Curve: one fabric class's whole curve. */
async function saveCurve(fabricClass, points) {
  try {
    return done({
      points: await apiFetch(`/workstudy/learning-curve/${fabricClass}`, {
        method: "PUT",
        body: JSON.stringify({ points }),
      }),
    });
  } catch (error) {
    return fail(error);
  }
}

/** Garment Spec Entry's New tab: the specification without its grids. */
async function createSpec(previousState, formData) {
  try {
    const out = body(formData, ["is_smv_artwork", "is_fusing"]);
    await apiFetch("/workstudy/specs", {
      method: "POST",
      body: JSON.stringify(out),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Every read and save Item Specification (14/04) makes in place — a specification by style and variation, a group or a category by its code. */
async function specAction(op, ...args) {
  const enc = encodeURIComponent;
  const one = (style, v) =>
    `/workstudy/specs/one?${new URLSearchParams({ style, v })}`;
  const calls = {
    specs: () => ["/workstudy/specs", { method: "GET" }],
    spec: ([style, v]) => [one(style, v), { method: "GET" }],
    storeSpec: ([payload]) => [
      "/workstudy/specs",
      { method: "POST", body: JSON.stringify(payload) },
    ],
    updateSpec: ([style, v, payload]) => [
      one(style, v),
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    itemList: ([f]) => [
      `/workstudy/item-list?${new URLSearchParams(Object.fromEntries(Object.entries(f ?? {}).filter(([, x]) => x)))}`,
      { method: "GET" },
    ],
  };
  if (!calls[op]) throw new Error(`Unknown work study call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET") revalidatePath("/work-study", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function loadCategories(code) {
  try {
    return {
      status: "success",
      rows: await apiFetch(
        `/workstudy/item-groups/${encodeURIComponent(code)}/categories`,
      ),
    };
  } catch (error) {
    return fail(error);
  }
}

async function saveCategories(code, rows) {
  try {
    return done({
      rows: await apiFetch(
        `/workstudy/item-groups/${encodeURIComponent(code)}/categories`,
        { method: "PUT", body: JSON.stringify({ rows }) },
      ),
    });
  } catch (error) {
    return fail(error);
  }
}

async function loadVariations(code) {
  try {
    return {
      status: "success",
      rows: await apiFetch(
        `/workstudy/categories/${encodeURIComponent(code)}/variations`,
      ),
    };
  } catch (error) {
    return fail(error);
  }
}

async function saveVariations(code, rows) {
  try {
    const clean = rows.map((r) =>
      Object.fromEntries(
        Object.entries(r).map(([k, v]) => [k, v === "" ? null : v]),
      ),
    );
    return done({
      rows: await apiFetch(
        `/workstudy/categories/${encodeURIComponent(code)}/variations`,
        { method: "PUT", body: JSON.stringify({ rows: clean }) },
      ),
    });
  } catch (error) {
    return fail(error);
  }
}

/** Every read and save Costing (14/05) makes in place: a month's sheet, a section's heads, a floor's day. */
async function costAction(op, payload = {}) {
  const day = (p) =>
    `?${new URLSearchParams({ floor: p.floor, date: p.date })}`;
  const calls = {
    sheet: (p) => [
      `/workstudy/costing/sheet?${new URLSearchParams(Object.fromEntries(Object.entries({ period: p.period, copy: p.copy }).filter(([, v]) => v)))}`,
      { method: "GET" },
    ],
    saveSheet: (p) => [
      "/workstudy/costing/sheet",
      { method: "PUT", body: JSON.stringify(p) },
    ],
    config: (p) => [
      `/workstudy/costing/day/config${day(p)}`,
      { method: "GET" },
    ],
    saveConfig: (p) => [
      `/workstudy/costing/day/config${day(p)}`,
      { method: "PUT", body: JSON.stringify({ rows: p.rows }) },
    ],
    lines: (p) => [`/workstudy/costing/day/lines${day(p)}`, { method: "GET" }],
    saveLines: (p) => [
      `/workstudy/costing/day/lines${day(p)}`,
      { method: "PUT", body: JSON.stringify({ rows: p.rows }) },
    ],
    profit: (p) => [
      `/workstudy/costing/day/profit${day(p)}`,
      { method: "GET" },
    ],
  };
  try {
    const [path, init] = calls[op](payload);
    const data = await apiFetch(path, init);
    return init.method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadHeads(section) {
  try {
    return {
      status: "success",
      rows: await apiFetch(
        `/workstudy/costing/sections/${encodeURIComponent(section)}/heads`,
      ),
    };
  } catch (error) {
    return fail(error);
  }
}

async function saveHeads(section, rows) {
  try {
    const clean = rows.map(({ in_use, ...r }) =>
      Object.fromEntries(
        Object.entries(r).map(([k, v]) => [k, v === "" ? null : v]),
      ),
    );
    return done({
      rows: await apiFetch(
        `/workstudy/costing/sections/${encodeURIComponent(section)}/heads`,
        { method: "PUT", body: JSON.stringify({ rows: clean }) },
      ),
    });
  } catch (error) {
    return fail(error);
  }
}

/** Every read and save OT & Salary (14/06) makes in place: a year's rates, a floor's OT plan. */
async function otAction(op, payload = {}) {
  const day = (p) =>
    `?${new URLSearchParams({ floor: p.floor, date: p.date })}`;
  const calls = {
    rates: (p) => [
      `/workstudy/ot/rates?${new URLSearchParams({ year: p.year })}`,
      { method: "GET" },
    ],
    saveRates: (p) => [
      "/workstudy/ot/rates",
      { method: "PUT", body: JSON.stringify(p) },
    ],
    plan: (p) => [`/workstudy/ot/plan${day(p)}`, { method: "GET" }],
    savePlan: (p) => [
      `/workstudy/ot/plan${day(p)}`,
      { method: "PUT", body: JSON.stringify({ rows: p.rows }) },
    ],
  };
  try {
    const [path, init] = calls[op](payload);
    const data = await apiFetch(path, init);
    return init.method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/** Every read and save Target & Feeding (14/03) makes in place: a month's yarn targets, the change-over board, a floor's target sheet, a line's allocations and NPT. */
async function tfAction(op, payload = {}) {
  const q = (keys) =>
    `?${new URLSearchParams(Object.fromEntries(keys.filter((k) => payload[k]).map((k) => [k, payload[k]])))}`;
  const put = (path, body) => [
    path,
    { method: "PUT", body: JSON.stringify(body) },
  ];
  const calls = {
    yarn: () => [
      `/workstudy/targets/yarn${q(["period", "copy"])}`,
      { method: "GET" },
    ],
    saveYarn: () =>
      put("/workstudy/targets/yarn", {
        period: payload.period,
        rows: payload.rows,
      }),
    changeovers: () => [
      `/workstudy/targets/changeovers${q(["floor", "line", "from", "to", "buyer", "order"])}`,
      { method: "GET" },
    ],
    changeoverAction: () => [
      "/workstudy/targets/changeovers/action",
      {
        method: "POST",
        body: JSON.stringify({ action: payload.action, ids: payload.ids }),
      },
    ],
    targets: () => [
      `/workstudy/targets/sheet${q(["floor", "date"])}`,
      { method: "GET" },
    ],
    saveTargets: () =>
      put(`/workstudy/targets/sheet${q(["floor", "date"])}`, {
        rows: payload.rows,
      }),
    copyTargets: () => [
      `/workstudy/targets/sheet/copy${q(["floor", "date"])}`,
      { method: "POST", body: JSON.stringify({ from: payload.from }) },
    ],
    allocations: () => [
      `/workstudy/targets/allocations${q(["floor", "line", "view"])}`,
      { method: "GET" },
    ],
    saveAllocations: () =>
      put(`/workstudy/targets/allocations${q(["floor", "line"])}`, {
        rows: payload.rows,
      }),
    savePlans: () =>
      put(`/workstudy/targets/plans${q(["floor", "line"])}`, {
        rows: payload.rows,
      }),
    npt: () => [
      `/workstudy/targets/npt${q(["floor", "date", "line"])}`,
      { method: "GET" },
    ],
    saveNpt: () =>
      put(`/workstudy/targets/npt${q(["floor", "date", "line"])}`, {
        rows: payload.rows,
      }),
  };
  try {
    const [path, init] = calls[op]();
    const data = await apiFetch(path, init);
    return init.method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/** Every read and save Floor Operations (14/07) makes in place: a floor's hour of output corrections, a line's man-minute sheet, the wash requisition list and its Take Action. */
async function foAction(op, payload = {}) {
  const q = (keys) =>
    `?${new URLSearchParams(Object.fromEntries(keys.filter((k) => payload[k]).map((k) => [k, payload[k]])))}`;
  const put = (path, body) => [
    path,
    { method: "PUT", body: JSON.stringify(body) },
  ];
  const calls = {
    output: () => [
      `/workstudy/floor-ops/output${q(["floor", "date", "hour"])}`,
      { method: "GET" },
    ],
    saveOutput: () =>
      put(`/workstudy/floor-ops/output${q(["floor", "date", "hour"])}`, {
        rows: payload.rows,
      }),
    minutes: () => [
      `/workstudy/floor-ops/minutes${q(["floor", "line", "date"])}`,
      { method: "GET" },
    ],
    saveMinutes: () =>
      put(`/workstudy/floor-ops/minutes${q(["floor", "line", "date"])}`, {
        rows: payload.rows,
      }),
    wash: () => [
      `/workstudy/floor-ops/wash${q(["status", "buyer", "no", "style", "order", "from", "to"])}`,
      { method: "GET" },
    ],
    washAction: () => [
      "/workstudy/floor-ops/wash/action",
      { method: "POST", body: JSON.stringify({ rows: payload.rows }) },
    ],
  };
  try {
    const [path, init] = calls[op]();
    const data = await apiFetch(path, init);
    return init.method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/** Every read and save Section Profitability (14/08) makes in place: a month's income targets (and Update from another month), a day's section results. */
async function spAction(op, payload = {}) {
  const calls = {
    targets: () => [
      `/workstudy/section-pl/targets?${new URLSearchParams({ period: payload.period })}`,
      { method: "GET" },
    ],
    saveTargets: () => [
      "/workstudy/section-pl/targets",
      {
        method: "PUT",
        body: JSON.stringify({ period: payload.period, rows: payload.rows }),
      },
    ],
    copyTargets: () => [
      "/workstudy/section-pl/targets/copy",
      {
        method: "POST",
        body: JSON.stringify({ period: payload.period, from: payload.from }),
      },
    ],
    results: () => [
      `/workstudy/section-pl/results?${new URLSearchParams({ date: payload.date })}`,
      { method: "GET" },
    ],
    saveResults: () => [
      "/workstudy/section-pl/results",
      {
        method: "PUT",
        body: JSON.stringify({ date: payload.date, rows: payload.rows }),
      },
    ],
  };
  try {
    const [path, init] = calls[op]();
    const data = await apiFetch(path, init);
    return init.method === "GET" ? { status: "success", data } : done({ data });
  } catch (error) {
    return fail(error);
  }
}

/** Work Study Reports (14/02): a report previewed for the parameters chosen. */
async function reportAction(op, payload = {}) {
  try {
    const data = await apiFetch(
      `/workstudy/reports/${payload.report.replaceAll("_", "-")}/preview?${new URLSearchParams(payload.query)}`,
    );
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export {
  reportAction,
  spAction,
  foAction,
  tfAction,
  otAction,
  costAction,
  loadHeads,
  saveHeads,
  createResource,
  updateResource,
  deleteResource,
  saveAccess,
  createDepartment,
  loadPersons,
  savePersons,
  loadBand,
  saveBand,
  saveCurve,
  createSpec,
  specAction,
  loadCategories,
  saveCategories,
  loadVariations,
  saveVariations,
};
