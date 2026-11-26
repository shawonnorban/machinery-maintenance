"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { getT } from "@/lib/i18n-server";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

/** "Add X" modal — the same master-data endpoint the settings screen uses. */
async function createRow(type, previousState, formData) {
  try {
    await apiFetch(`/master-data/${type}`, {
      method: "POST",
      body: JSON.stringify(Object.fromEntries(formData.entries())),
    });
    revalidatePath("/company-management", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * The grid's Save: every edited row, one PATCH each. A row that fails keeps its
 * draft on screen and the rest still save, so one bad cell does not throw away
 * a page of edits.
 */
async function saveRows(type, changes) {
  const results = [];
  for (const { id, payload } of changes) {
    try {
      await apiFetch(`/master-data/${type}/${id}`, { method: "PATCH", body: JSON.stringify(payload) });
      results.push({ id, status: "success" });
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      const firstFieldError = error.errors ? Object.values(error.errors)[0]?.[0] : null;
      results.push({ id, status: "error", message: firstFieldError ?? error.message });
    }
  }
  revalidatePath("/company-management", "layout");
  return { results };
}

async function deleteRow(type, rowId) {
  try {
    await apiFetch(`/master-data/${type}/${rowId}`, { method: "DELETE" });
    revalidatePath("/company-management", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

function refresh() {
  revalidatePath("/company-management", "layout");
}

/** Company Profile's single Save. */
async function updateProfile(previousState, formData) {
  try {
    await apiFetch("/company/profile", { method: "PUT", body: JSON.stringify(Object.fromEntries(formData.entries())) });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Company Profile's logo — the sidebar mark and every print's letterhead read it. */
async function uploadLogo(previousState, formData) {
  try {
    const file = formData.get("logo");
    if (!(file instanceof File) || file.size === 0) {
      const t = await getT("settings");
      return { status: "error", message: t("choose_image_first") };
    }
    const body = new FormData();
    body.set("logo", file);
    await apiFetch("/settings/company/logo", { method: "POST", body });
    refresh();
    revalidatePath("/", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function removeLogo() {
  try {
    await apiFetch("/settings/company/logo", { method: "DELETE" });
    refresh();
    revalidatePath("/", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/*
 * The Factory tab reuses the grid over the factory API (SaveFactory keeps its
 * entitlement check and five-character code), so these mirror the
 * master-data actions' shapes: (type, …) is ignored.
 */
async function createFactory(type, previousState, formData) {
  try {
    const body = Object.fromEntries(formData.entries());
    await apiFetch("/factories", { method: "POST", body: JSON.stringify({ timezone: "Asia/Dhaka", ...body }) });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function saveFactories(type, changes) {
  const results = [];
  for (const { id, payload } of changes) {
    try {
      await apiFetch(`/factories/${id}`, { method: "PATCH", body: JSON.stringify(payload) });
      results.push({ id, status: "success" });
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      results.push({ id, status: "error", message: (error.errors ? Object.values(error.errors)[0]?.[0] : null) ?? error.message });
    }
  }
  refresh();
  return { results };
}

async function deleteFactory(type, id) {
  try {
    await apiFetch(`/factories/${id}`, { method: "DELETE" });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function createDocument(type, previousState, formData) {
  try {
    await apiFetch("/company/documents", { method: "POST", body: JSON.stringify(Object.fromEntries(formData.entries())) });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function saveDocuments(type, changes) {
  const results = [];
  for (const { id, payload } of changes) {
    try {
      await apiFetch(`/company/documents/${id}`, { method: "PATCH", body: JSON.stringify(payload) });
      results.push({ id, status: "success" });
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      results.push({ id, status: "error", message: (error.errors ? Object.values(error.errors)[0]?.[0] : null) ?? error.message });
    }
  }
  refresh();
  return { results };
}

async function deleteDocument(type, id) {
  try {
    await apiFetch(`/company/documents/${id}`, { method: "DELETE" });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function listDocumentFiles(documentId) {
  try {
    return { status: "success", files: await apiFetch(`/company/documents/${documentId}/files`) };
  } catch (error) {
    return fail(error);
  }
}

async function uploadDocumentFile(documentId, formData) {
  try {
    const file = formData.get("file");
    if (!(file instanceof File) || file.size === 0) {
      return { status: "error", message: "Choose a file first." };
    }
    const body = new FormData();
    body.set("file", file);
    await apiFetch(`/company/documents/${documentId}/files`, { method: "POST", body });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * Machine Master's Save: capacity through Company Management, status through
 * machinery maintenance's own status action (its rules and history apply).
 */
async function saveMachines(changes) {
  const results = [];
  for (const change of changes) {
    try {
      if ("capacity" in change) {
        await apiFetch(`/company/machines/${change.id}`, { method: "PATCH", body: JSON.stringify({ capacity: change.capacity || null }) });
      }
      if (change.assetStatus) {
        await apiFetch(`/assets/${change.id}/status`, {
          method: "POST",
          body: JSON.stringify({ status: change.assetStatus, version: change.version, reason: "Company Management" }),
        });
      }
      results.push({ id: change.id, status: "success" });
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      results.push({ id: change.id, status: "error", message: error.message });
    }
  }
  refresh();
  revalidatePath("/assets", "layout");
  return { results };
}

export {
  createRow, saveRows, deleteRow, updateProfile, uploadLogo, removeLogo,
  createFactory, saveFactories, deleteFactory,
  createDocument, saveDocuments, deleteDocument, listDocumentFiles, uploadDocumentFile,
  saveMachines,
};
