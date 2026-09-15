"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

function ok() {
  return { status: "success" };
}

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

/** The no-body transitions (mirrors `WorkOrderApiController`'s equivalents, ADR-003). */
function transitionAction(step) {
  return async function (workOrderId) {
    try {
      await apiFetch(`/work-orders/${workOrderId}/${step}`, { method: "POST" });
      revalidatePath(`/work-orders/${workOrderId}`);
      return ok();
    } catch (error) {
      return fail(error);
    }
  };
}

const submitForApproval = transitionAction("submit-for-approval");
const start = transitionAction("start");
const resume = transitionAction("resume");
const complete = transitionAction("complete");
const verify = transitionAction("verify");
const close = transitionAction("close");

/** `reason_code` is a code, not free text (SRS 13.1) — the Select in HoldDialog picks from `WorkOrder::HOLD_REASONS`. */
async function hold(workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/hold`, {
      method: "POST",
      body: JSON.stringify({ reason_code: formData.get("reason_code"), notes: formData.get("notes") || null }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

async function cancel(workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/cancel`, {
      method: "POST",
      body: JSON.stringify({ reason: formData.get("reason") }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

async function reopen(workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/reopen`, {
      method: "POST",
      body: JSON.stringify({ reason: formData.get("reason") }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `WorkOrderAssignmentApiController::store` — takes a list, though this form only ever sends one technician at a time. */
async function assignTechnician(workOrderId, technicianId) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/assign`, {
      method: "POST",
      body: JSON.stringify({ technician_ids: [technicianId] }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

async function unassignTechnician(workOrderId, technicianId) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/unassign`, {
      method: "POST",
      body: JSON.stringify({ technician_id: technicianId }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

/**
 * Mirrors `WorkOrderChecklistApiController::store` — one answer at a time,
 * never one big form: a connection dropping mid-checklist should not cost
 * fourteen already-recorded answers because the fifteenth failed. A
 * `FormData` body (never JSON) because a fail-needing-photo item attaches
 * a real file.
 */
async function recordChecklistAnswer(workOrderId, previousState, formData) {
  try {
    const body = new FormData();
    body.set("checklist_item_id", formData.get("checklist_item_id"));
    body.set("result", formData.get("result"));
    for (const key of ["numeric_value", "text_value", "observation"]) {
      const value = formData.get(key);
      if (value) body.set(key, value);
    }
    const photo = formData.get("photo");
    if (photo instanceof File && photo.size > 0) body.set("photo", photo);

    await apiFetch(`/work-orders/${workOrderId}/checklist/results`, { method: "POST", body });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `WorkOrderLaborApiController::store` — time only, never a rate or an amount (ADR-050). */
async function recordLabor(workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/labor`, {
      method: "POST",
      body: JSON.stringify({
        technician_id: formData.get("technician_id"),
        started_at: new Date(formData.get("started_at")).toISOString(),
        ended_at: new Date(formData.get("ended_at")).toISOString(),
        notes: formData.get("notes") || null,
      }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

async function deleteLabor(workOrderId, entryId) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/labor/${entryId}`, { method: "DELETE" });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `WorkOrderPartApiController::store` — the technician's own way in; only the store may hand a part over, which is why issuing is a separate permission. */
async function requestPart(workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts`, {
      method: "POST",
      body: JSON.stringify({ spare_part_id: formData.get("spare_part_id"), quantity: formData.get("quantity") }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `WorkOrderPartApiController::issueDirect` — the store handing a part over unprompted, no prior request needed. */
async function issuePart(workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/issue`, {
      method: "POST",
      body: JSON.stringify({
        spare_part_id: formData.get("spare_part_id"),
        bin_id: formData.get("bin_id"),
        quantity: formData.get("quantity"),
      }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

/**
 * Fulfils one specific pending request line (mirrors `WorkOrderPartApi
 * Controller::issue`, distinct from `issuePart` above which is a blind
 * hand-over with no request behind it) — closes the loop between "asked
 * for" and "handed over" instead of leaving the store to re-key the same
 * part/quantity into the standalone Issue form disconnected from the
 * request it was answering.
 */
async function issueRequestedPart(workOrderId, lineId, binId, quantity) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/${lineId}/issue`, {
      method: "POST",
      body: JSON.stringify({ bin_id: binId, quantity }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

async function consumePart(workOrderId, lineId, quantity) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/${lineId}/consume`, {
      method: "POST",
      body: JSON.stringify({ quantity }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

async function returnPart(workOrderId, lineId, quantity) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/${lineId}/return`, {
      method: "POST",
      body: JSON.stringify({ quantity }),
    });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

/** No web upload form exists for this — the web only ever lists attachments read-only. Added because the API already fully supports it. */
async function uploadAttachment(workOrderId, formData) {
  try {
    const file = formData.get("file");
    if (!(file instanceof File) || file.size === 0) {
      return { status: "error", message: "Choose a file first." };
    }
    const body = new FormData();
    body.set("file", file);
    await apiFetch(`/work-orders/${workOrderId}/attachments`, { method: "POST", body });
    revalidatePath(`/work-orders/${workOrderId}`);
    return ok();
  } catch (error) {
    return fail(error);
  }
}

export {
  submitForApproval, start, resume, complete, verify, close, hold, cancel, reopen, assignTechnician, unassignTechnician,
  recordChecklistAnswer, recordLabor, deleteLabor, requestPart, issuePart, issueRequestedPart, consumePart, returnPart,
  uploadAttachment,
};
