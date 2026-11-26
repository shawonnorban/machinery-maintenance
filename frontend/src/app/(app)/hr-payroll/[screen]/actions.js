"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { getT } from "@/lib/i18n-server";

/*
 * HR & Payroll → Master Data (AnnotechERP 24/01): the screen kit's
 * resources; allowance and deduction entry; the wage gazette form; payroll
 * settings; and the grids saved as a whole — pay period actions and the
 * fiscal year register.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/hr-payroll", "layout");
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

/** Brickland's Status switch on a holiday or festival: deactivate it, or bring it back. */
async function setActive(resource, id, next) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, {
      method: "PATCH",
      body: JSON.stringify({ active: !!next }),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Festival Calendar → Holidays: put a holiday's days on the factory calendar. */
async function holidayToCalendar(id) {
  try {
    await apiFetch(`/hr-payroll/holidays/${id}/calendar`, { method: "POST" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Allowance → Manual Entry, Deduction → New Deduction: one employee typed in. */
async function saveEntry(kind, previousState, formData) {
  const data = body(formData, []);
  // In Excel Upload mode the employees come from a file on the tab beside this one.
  if (data.input_mode === "EXCEL")
    return {
      status: "error",
      message: (await getT("hrpayroll"))("master.al.excel_hint"),
    };
  try {
    await apiFetch(`/hr-payroll/entries/${kind}`, {
      method: "POST",
      body: JSON.stringify({
        pay_period_id: data.pay_period_id,
        pay_element_id: data.pay_element_id,
        replace: kind === "allowances",
        rows: [{ user_id: data.user_id, amount: data.amount }],
      }),
    });
    return done();
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      // The row's own errors belong to the form's fields.
      const errors = {};
      for (const [key, messages] of Object.entries(error.errors))
        errors[
          key.startsWith("rows.")
            ? key.endsWith(".amount")
              ? "amount"
              : key.endsWith(".pay_element")
                ? "pay_element_id"
                : "user_id"
            : key
        ] = messages;
      return { status: "error", message: error.message, errors };
    }
    return fail(error);
  }
}

/** Excel Upload: every clean row of a checked file. */
async function saveEntries(kind, payload) {
  try {
    return done({
      data: await apiFetch(`/hr-payroll/entries/${kind}`, {
        method: "POST",
        body: JSON.stringify(payload),
      }),
    });
  } catch (error) {
    return fail(error);
  }
}

async function loadGazette(id) {
  try {
    return {
      status: "success",
      data: await apiFetch(`/hr-payroll/gazettes/${id}`),
    };
  } catch (error) {
    return fail(error);
  }
}

async function saveGazette(id, payload) {
  try {
    return done({
      data: await apiFetch(
        id ? `/hr-payroll/gazettes/${id}` : "/hr-payroll/gazettes",
        { method: id ? "PUT" : "POST", body: JSON.stringify(payload) },
      ),
    });
  } catch (error) {
    return fail(error);
  }
}

async function deleteGazette(id) {
  try {
    await apiFetch(`/hr-payroll/gazettes/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function saveSettings(values) {
  try {
    return done({
      rows: await apiFetch("/hr-payroll/settings", {
        method: "PUT",
        body: JSON.stringify({ values }),
      }),
    });
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
    return done({
      rows: await apiFetch(`${path}/${key}`, {
        method: "PUT",
        body: JSON.stringify({ rows: rows.map(shape) }),
      }),
    });
  } catch (error) {
    return fail(error);
  }
};

async function loadTypeChildren(type) {
  return loader("/hr-payroll/type-tree")(type);
}

async function saveTypeChildren(type, rows) {
  return saver("/hr-payroll/type-tree", (r) => ({
    id: r.id,
    name: r.name,
    name_bn: r.name_bn,
    short_name: r.short_name,
    active: !!r.active,
  }))(type, rows);
}

async function loadPeriods(company) {
  return loader("/hr-payroll/period-companies")(company);
}

async function savePeriods(company, rows) {
  return saver("/hr-payroll/period-companies", (r) => ({
    id: r.id,
    action: r.action || null,
    show_for_report: !!r.show_for_report,
    remarks: r.remarks || null,
  }))(company, rows);
}

async function loadFiscalYears(register) {
  return loader("/hr-payroll/fiscal-registers")(register);
}

async function saveFiscalYears(register, rows) {
  return saver("/hr-payroll/fiscal-registers", (r) => ({
    id: r.id ?? null,
    name: r.name,
    fy_start: r.fy_start || null,
    fy_end: r.fy_end || null,
    cal_start: r.cal_start || null,
    cal_end: r.cal_end || null,
    active: !!r.active,
  }))(register, rows);
}

export {
  createResource,
  updateResource,
  deleteResource,
  setActive,
  holidayToCalendar,
  saveEntry,
  saveEntries,
  loadGazette,
  saveGazette,
  deleteGazette,
  saveSettings,
  loadTypeChildren,
  saveTypeChildren,
  loadPeriods,
  savePeriods,
  loadFiscalYears,
  saveFiscalYears,
};

/*
 * HR & Payroll → Employee (AnnotechERP 24/03): a page of the list, one
 * employee registered or edited, a posting for one or several, and an
 * employee's history. The screens keep their own state, so nothing reloads.
 */
async function employeeAction(op, ...args) {
  const base = "/hr-payroll/employees";
  const calls = {
    list: ([query]) => [
      `${base}?${new URLSearchParams(query)}`,
      { method: "GET" },
    ],
    store: ([payload]) => [
      base,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    update: ([code, payload]) => [
      `${base}/${encodeURIComponent(code)}`,
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    assign: ([payload]) => [
      `${base}/assign`,
      { method: "POST", body: JSON.stringify(payload) },
    ],
    history: ([code]) => [
      `${base}/${encodeURIComponent(code)}/history`,
      { method: "GET" },
    ],    // Employee Profile (24/03).
    profiles: ([query]) => [
      `/hr-payroll/profiles?${new URLSearchParams(query)}`,
      { method: "GET" },
    ],
    profile: ([code]) => [
      `/hr-payroll/profiles/${encodeURIComponent(code)}`,
      { method: "GET" },
    ],
    profileStore: ([payload]) => [
      "/hr-payroll/profiles",
      { method: "POST", body: JSON.stringify(payload) },
    ],
    profileUpdate: ([code, payload]) => [
      `/hr-payroll/profiles/${encodeURIComponent(code)}`,
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    profileDelete: ([code]) => [
      `/hr-payroll/profiles/${encodeURIComponent(code)}`,
      { method: "DELETE" },
    ],
    photoRemove: ([code]) => [
      `/hr-payroll/profiles/${encodeURIComponent(code)}/photo`,
      { method: "DELETE" },
    ],
    // ID Card (24/03).
    cards: ([query]) => [
      `/hr-payroll/id-cards?${new URLSearchParams(query)}`,
      { method: "GET" },
    ],
    cardPrint: ([ids]) => [
      `/hr-payroll/id-cards/print?${new URLSearchParams(ids ? { ids } : {})}`,
      { method: "GET" },
    ],
    cardAdd: ([payload]) => [
      "/hr-payroll/id-cards",
      { method: "POST", body: JSON.stringify(payload) },
    ],
    cardAddPicked: ([payload]) => [
      "/hr-payroll/id-cards/add",
      { method: "POST", body: JSON.stringify(payload) },
    ],
    cardUpdate: ([id, payload]) => [
      `/hr-payroll/id-cards/${encodeURIComponent(id)}`,
      { method: "PUT", body: JSON.stringify(payload) },
    ],
    cardRemove: ([id]) => [
      `/hr-payroll/id-cards/${encodeURIComponent(id)}`,
      { method: "DELETE" },
    ],
    cardClear: () => ["/hr-payroll/id-cards", { method: "DELETE" }],
    // Attendance & Shift (24/04).
    attDaily: ([query]) => [`/hr-payroll/attendance/daily?${new URLSearchParams(query)}`, { method: "GET" }],
    attSync: ([query]) => [`/hr-payroll/attendance/sync?${new URLSearchParams(query)}`, { method: "GET" }],
    attSyncNow: () => ["/hr-payroll/attendance/sync", { method: "POST" }],
    attMissing: ([query]) => [`/hr-payroll/attendance/missing?${new URLSearchParams(query)}`, { method: "GET" }],
    attRequest: ([body]) => ["/hr-payroll/attendance/corrections", { method: "POST", body: JSON.stringify(body) }],
    attDecide: ([id, decision]) => [`/hr-payroll/attendance/corrections/${encodeURIComponent(id)}/decide`, { method: "POST", body: JSON.stringify({ decision }) }],
    attMonth: ([query]) => [`/hr-payroll/attendance/month?${new URLSearchParams(query)}`, { method: "GET" }],
    attSaveDay: ([body]) => ["/hr-payroll/attendance/day", { method: "POST", body: JSON.stringify(body) }],
    attDeleteDay: ([body]) => ["/hr-payroll/attendance/day/delete", { method: "POST", body: JSON.stringify(body) }],
    attSummary: ([query]) => [`/hr-payroll/attendance/summary?${new URLSearchParams(query)}`, { method: "GET" }],
    rosterList: ([query]) => [`/hr-payroll/roster?${new URLSearchParams(query)}`, { method: "GET" }],
    rosterAssign: ([body]) => ["/hr-payroll/roster", { method: "POST", body: JSON.stringify(body) }],
    rosterRemove: ([id]) => [`/hr-payroll/roster/${encodeURIComponent(id)}`, { method: "DELETE" }],
    // Leave & Overtime (24/05).
    leaveList: () => ["/hr-payroll/leave", { method: "GET" }],
    leaveBalances: ([query]) => [`/hr-payroll/leave/balances?${new URLSearchParams(query)}`, { method: "GET" }],
    leaveStore: ([body]) => ["/hr-payroll/leave", { method: "POST", body: JSON.stringify(body) }],
    leaveDelete: ([ref]) => [`/hr-payroll/leave/${encodeURIComponent(ref)}`, { method: "DELETE" }],
    otCorrection: ([query]) => [`/hr-payroll/ot/correction?${new URLSearchParams(query)}`, { method: "GET" }],
    otCorrect: ([body]) => ["/hr-payroll/ot/correction", { method: "POST", body: JSON.stringify(body) }],
    otReset: ([body]) => ["/hr-payroll/ot/correction/reset", { method: "POST", body: JSON.stringify(body) }],
    otHistory: ([query]) => [`/hr-payroll/ot/correction/history?${new URLSearchParams(query)}`, { method: "GET" }],
    otEligibility: () => ["/hr-payroll/ot/eligibility", { method: "GET" }],
    otLimits: ([body]) => ["/hr-payroll/ot/limits", { method: "POST", body: JSON.stringify(body) }],
    otGrade: ([body]) => ["/hr-payroll/ot/grade", { method: "POST", body: JSON.stringify(body) }],
    otEmployee: ([body]) => ["/hr-payroll/ot/employee", { method: "POST", body: JSON.stringify(body) }],
    otLineEmployees: ([query]) => [`/hr-payroll/ot/line-employees?${new URLSearchParams(query)}`, { method: "GET" }],
    otRequestStore: ([body]) => ["/hr-payroll/ot/requests", { method: "POST", body: JSON.stringify(body) }],
    otRequestUpdate: ([no, body]) => [`/hr-payroll/ot/requests/${encodeURIComponent(no)}`, { method: "PUT", body: JSON.stringify(body) }],
    otApprovals: () => ["/hr-payroll/ot/approvals", { method: "GET" }],
    otDecide: ([no, body]) => [`/hr-payroll/ot/requests/${encodeURIComponent(no)}/decide`, { method: "POST", body: JSON.stringify(body) }],
    // Salary Processing (24/06).
    salaryDaily: ([query]) => [`/hr-payroll/salary/daily?${new URLSearchParams(query)}`, { method: "GET" }],
    salaryDailyCalc: ([body]) => ["/hr-payroll/salary/daily/calculate", { method: "POST", body: JSON.stringify(body) }],
    salaryDailyMove: ([body]) => ["/hr-payroll/salary/daily/move", { method: "POST", body: JSON.stringify(body) }],
    salaryOt: ([query]) => [`/hr-payroll/salary/ot?${new URLSearchParams(query)}`, { method: "GET" }],
    salaryOtRecalc: ([body]) => ["/hr-payroll/salary/ot/recalculate", { method: "POST", body: JSON.stringify(body) }],
    salaryMonthly: ([query]) => [`/hr-payroll/salary/monthly?${new URLSearchParams(query)}`, { method: "GET" }],
    salaryMonthlyCalc: ([body]) => ["/hr-payroll/salary/monthly/calculate", { method: "POST", body: JSON.stringify(body) }],
    salaryMonthlyMove: ([body]) => ["/hr-payroll/salary/monthly/move", { method: "POST", body: JSON.stringify(body) }],
    salaryBonus: ([query]) => [`/hr-payroll/salary/bonus?${new URLSearchParams(query)}`, { method: "GET" }],
    salaryBonusCalc: ([body]) => ["/hr-payroll/salary/bonus/calculate", { method: "POST", body: JSON.stringify(body) }],
    salaryBonusMove: ([body]) => ["/hr-payroll/salary/bonus/move", { method: "POST", body: JSON.stringify(body) }],
    salaryAdjustments: ([query]) => [`/hr-payroll/salary/adjustments?${new URLSearchParams(query ?? {})}`, { method: "GET" }],
    salaryAdjustmentStore: ([body]) => ["/hr-payroll/salary/adjustments", { method: "POST", body: JSON.stringify(body) }],
    salaryAdjustmentDecide: ([ref, body]) => [`/hr-payroll/salary/adjustments/${encodeURIComponent(ref)}/decide`, { method: "POST", body: JSON.stringify(body) }],
    // Employee Movement (24/07).
    mvSearch: ([query]) => [`/hr-payroll/movement/employees?${new URLSearchParams(query)}`, { method: "GET" }],
    trStore: ([body]) => ["/hr-payroll/transfers", { method: "POST", body: JSON.stringify(body) }],
    trUpdate: ([ref, body]) => [`/hr-payroll/transfers/${encodeURIComponent(ref)}`, { method: "PUT", body: JSON.stringify(body) }],
    trDelete: ([ref]) => [`/hr-payroll/transfers/${encodeURIComponent(ref)}`, { method: "DELETE" }],
    trFinalize: ([ref]) => [`/hr-payroll/transfers/${encodeURIComponent(ref)}/finalize`, { method: "POST" }],
    promoStore: ([body]) => ["/hr-payroll/promotions", { method: "POST", body: JSON.stringify(body) }],
    incStore: ([body]) => ["/hr-payroll/increments", { method: "POST", body: JSON.stringify(body) }],
    mvDecide: ([kind, ref, body]) => [`/hr-payroll/${kind === "increments" ? "increments" : "promotions"}/${encodeURIComponent(ref)}/decide`, { method: "POST", body: JSON.stringify(body) }],
    mvWithdraw: ([kind, ref]) => [`/hr-payroll/${kind === "increments" ? "increments" : "promotions"}/${encodeURIComponent(ref)}`, { method: "DELETE" }],
    rjStore: ([body]) => ["/hr-payroll/rejoins", { method: "POST", body: JSON.stringify(body) }],
    rjUpdate: ([ref, body]) => [`/hr-payroll/rejoins/${encodeURIComponent(ref)}`, { method: "PUT", body: JSON.stringify(body) }],
    rjDelete: ([ref]) => [`/hr-payroll/rejoins/${encodeURIComponent(ref)}`, { method: "DELETE" }],
    // Separation (24/08).
    sepSearch: ([query]) => [`/hr-payroll/separation/employees?${new URLSearchParams(query)}`, { method: "GET" }],
    sepStore: ([body]) => ["/hr-payroll/separations", { method: "POST", body: JSON.stringify(body) }],
    sepDecide: ([ref, body]) => [`/hr-payroll/separations/${encodeURIComponent(ref)}/decide`, { method: "POST", body: JSON.stringify(body) }],
    sepReopen: ([ref]) => [`/hr-payroll/separations/${encodeURIComponent(ref)}/reopen`, { method: "POST" }],
    sepSettle: ([ref]) => [`/hr-payroll/separations/${encodeURIComponent(ref)}/settlement`, { method: "POST" }],
    sepWithdraw: ([ref]) => [`/hr-payroll/separations/${encodeURIComponent(ref)}`, { method: "DELETE" }],
    leftySearch: ([query]) => [`/hr-payroll/lefty/candidates?${new URLSearchParams(query)}`, { method: "GET" }],
    leftyReport: ([body]) => ["/hr-payroll/lefty", { method: "POST", body: JSON.stringify(body) }],
    leftyMove: ([ref, body]) => [`/hr-payroll/lefty/${encodeURIComponent(ref)}/move`, { method: "POST", body: JSON.stringify(body) }],
    fsPreview: ([query]) => [`/hr-payroll/settlements/preview?${new URLSearchParams(query)}`, { method: "GET" }],
    fsStore: ([body]) => ["/hr-payroll/settlements", { method: "POST", body: JSON.stringify(body) }],
    fsUpdate: ([ref, body]) => [`/hr-payroll/settlements/${encodeURIComponent(ref)}`, { method: "PUT", body: JSON.stringify(body) }],
    fsDelete: ([ref]) => [`/hr-payroll/settlements/${encodeURIComponent(ref)}`, { method: "DELETE" }],
    fsMove: ([ref, body]) => [`/hr-payroll/settlements/${encodeURIComponent(ref)}/move`, { method: "POST", body: JSON.stringify(body) }],
    // Letter Management (24/09).
    ltAddType: ([body]) => ["/hr-payroll/letters/types", { method: "POST", body: JSON.stringify(body) }],
    ltToggleType: ([code]) => [`/hr-payroll/letters/types/${encodeURIComponent(code)}/toggle`, { method: "POST" }],
    ltSaveTemplate: ([body]) => ["/hr-payroll/letters/templates", { method: "POST", body: JSON.stringify(body) }],
    ltTemplateStatus: ([code, version, body]) => [`/hr-payroll/letters/templates/${encodeURIComponent(code)}/${Number(version)}/status`, { method: "POST", body: JSON.stringify(body) }],
    ltDeleteTemplate: ([code, version]) => [`/hr-payroll/letters/templates/${encodeURIComponent(code)}/${Number(version)}`, { method: "DELETE" }],
    ltEmployee: ([query]) => [`/hr-payroll/letters/employee?${new URLSearchParams(query)}`, { method: "GET" }],
    ltEmployees: ([body]) => ["/hr-payroll/letters/employees", { method: "POST", body: JSON.stringify(body) }],
    ltGenerate: ([body]) => ["/hr-payroll/letters", { method: "POST", body: JSON.stringify(body) }],
    ltPrint: ([body]) => ["/hr-payroll/letters/print", { method: "POST", body: JSON.stringify(body) }],
    ltPrinted: ([body]) => ["/hr-payroll/letters/printed", { method: "POST", body: JSON.stringify(body) }],
    // Dashboard and Reports (24/02): reading only.
    rpRun: ([key, body]) => [`/hr-payroll/reports/${encodeURIComponent(key)}`, { method: "POST", body: JSON.stringify(body ?? {}) }],
    rpDashboard: ([query]) => [`/hr-payroll/dashboard?${new URLSearchParams(query ?? {})}`, { method: "GET" }],
    // Biometric / Fingerprint (24/03).
    bioOverview: () => ["/hr-payroll/biometric/overview", { method: "GET" }],
    bioEmployees: ([query]) => [`/hr-payroll/biometric/employees?${new URLSearchParams(query)}`, { method: "GET" }],
    bioUnmapped: () => ["/hr-payroll/biometric/unmapped", { method: "GET" }],
    bioEnrol: ([body]) => ["/hr-payroll/biometric/enrol", { method: "POST", body: JSON.stringify(body ?? {}) }],
    bioSend: ([body]) => ["/hr-payroll/biometric/send", { method: "POST", body: JSON.stringify(body ?? {}) }],
    bioCollect: ([body]) => ["/hr-payroll/biometric/collect", { method: "POST", body: JSON.stringify(body ?? {}) }],
    bioDelete: ([body]) => ["/hr-payroll/biometric/delete", { method: "POST", body: JSON.stringify(body ?? {}) }],
    bioMap: ([body]) => ["/hr-payroll/biometric/map", { method: "POST", body: JSON.stringify(body ?? {}) }],
    bioUnmap: ([body]) => ["/hr-payroll/biometric/unmap", { method: "POST", body: JSON.stringify(body ?? {}) }],
    bioAutomap: ([body]) => ["/hr-payroll/biometric/automap", { method: "POST", body: JSON.stringify(body ?? {}) }],
  };
  if (!calls[op]) throw new Error(`Unknown employee call: ${op}`);
  const [url, init] = calls[op](args);
  try {
    const data = await apiFetch(url, init);
    if (init.method !== "GET" && !op.startsWith("rp")) revalidatePath("/hr-payroll", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { employeeAction };
