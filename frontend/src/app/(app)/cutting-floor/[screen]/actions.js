"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Cutting Floor (AnnotechERP 19): the bundle cards — listed, added and
 * corrected, printed and reprinted, cancelled — the serial search, and the
 * bundle reports (19/04).
 */

function fail(error) {
  if (error instanceof ApiError) return { status: "error", message: error.message, errors: error.errors };
  throw error;
}

const query = (q) => new URLSearchParams(Object.entries(q ?? {}).filter(([, v]) => v !== "" && v !== null && v !== undefined)).toString();

async function listBundles(q) {
  try {
    const s = query(q);
    return { status: "success", data: await apiFetch(`/cutting/bundles${s ? `?${s}` : ""}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveBundle(no, payload) {
  try {
    const data = await apiFetch(no ? `/cutting/bundles/${encodeURIComponent(no)}` : "/cutting/bundles", { method: no ? "PUT" : "POST", body: JSON.stringify(payload) });
    revalidatePath("/cutting-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function printBundles(bundles, reprint) {
  try {
    const data = await apiFetch("/cutting/bundles/print", { method: "POST", body: JSON.stringify({ bundles, reprint: Boolean(reprint) }) });
    revalidatePath("/cutting-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function cancelBundle(no) {
  try {
    const data = await apiFetch(`/cutting/bundles/${encodeURIComponent(no)}/cancel`, { method: "POST" });
    revalidatePath("/cutting-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function findSerial(q) {
  try {
    return { status: "success", data: await apiFetch(`/cutting/serial?${query(q)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function cutReport(kind, q) {
  if (!["bundle", "po", "style", "size"].includes(kind)) throw new Error("Bad report");
  try {
    const s = query(q);
    return { status: "success", data: await apiFetch(`/cutting/reports/${kind}${s ? `?${s}` : ""}`) };
  } catch (error) {
    return fail(error);
  }
}

async function additionalCutting(q) {
  try {
    const s = query(q);
    return { status: "success", data: await apiFetch(`/cutting/additional-cutting${s ? `?${s}` : ""}`) };
  } catch (error) {
    return fail(error);
  }
}

async function cuttingContext(q) {
  try {
    return { status: "success", data: await apiFetch(`/cutting/context?${query(q)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function listInputs(q) {
  try {
    const s = query(q);
    return { status: "success", data: await apiFetch(`/cutting/inputs${s ? `?${s}` : ""}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveInput(no, payload) {
  try {
    const data = await apiFetch(no ? `/cutting/inputs/${encodeURIComponent(no)}` : "/cutting/inputs", { method: no ? "PUT" : "POST", body: JSON.stringify(payload) });
    revalidatePath("/cutting-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function cancelInput(no) {
  try {
    const data = await apiFetch(`/cutting/inputs/${encodeURIComponent(no)}/cancel`, { method: "POST" });
    revalidatePath("/cutting-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function saveOutput(no, payload) {
  try {
    const data = await apiFetch(`/cutting/inputs/${encodeURIComponent(no)}/output`, { method: "PUT", body: JSON.stringify(payload) });
    revalidatePath("/cutting-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function cardsFromOutput(no, payload) {
  try {
    const data = await apiFetch(`/cutting/inputs/${encodeURIComponent(no)}/bundles`, { method: "POST", body: JSON.stringify(payload) });
    revalidatePath("/cutting-floor", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

export { listBundles, saveBundle, printBundles, cancelBundle, findSerial, cutReport, additionalCutting, cuttingContext, listInputs, saveInput, cancelInput, saveOutput, cardsFromOutput };
