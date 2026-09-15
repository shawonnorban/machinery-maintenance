"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `BreakdownTransitionController`'s equivalents (ADR-003) — the transitions with no request body. */
function transitionAction(step) {
  return async function (breakdownId) {
    try {
      await apiFetch(`/breakdowns/${breakdownId}/${step}`, { method: "POST" });
      revalidatePath(`/breakdowns/${breakdownId}`);
      return { status: "success" };
    } catch (error) {
      if (error instanceof ApiError) {
        return { status: "error", message: error.message };
      }
      throw error;
    }
  };
}

const acknowledge = transitionAction("acknowledge");
const arrive = transitionAction("arrive");
const startRepair = transitionAction("start-repair");
const completeRepair = transitionAction("complete-repair");
const resumeProduction = transitionAction("resume-production");
const resume = transitionAction("resume");

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

/** Mirrors `BreakdownTransitionController::assign`. */
async function assign(breakdownId, previousState, formData) {
  try {
    await apiFetch(`/breakdowns/${breakdownId}/assign`, {
      method: "POST",
      body: JSON.stringify({ technician_id: formData.get("technician_id") }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `BreakdownTransitionController::hold` — paused rather than progressing, so `hold_minutes` on the eventual downtime record is honest. */
async function hold(breakdownId, previousState, formData) {
  try {
    await apiFetch(`/breakdowns/${breakdownId}/hold`, {
      method: "POST",
      body: JSON.stringify({
        reason_code: formData.get("reason_code"),
        notes: formData.get("notes") || null,
      }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `BreakdownTransitionController::close` — a failure code and root cause are required, not just a repair (ERD Section 10 rule 3). */
async function close(breakdownId, previousState, formData) {
  try {
    await apiFetch(`/breakdowns/${breakdownId}/close`, {
      method: "POST",
      body: JSON.stringify({
        failure_code_id: formData.get("failure_code_id"),
        root_cause_id: formData.get("root_cause_id"),
        corrective_action: formData.get("corrective_action") || null,
        preventive_action: formData.get("preventive_action") || null,
        closure_notes: formData.get("closure_notes") || null,
      }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `BreakdownTransitionController::cancel` — the report itself was wrong, not merely a lesser outcome than closing it. */
async function cancel(breakdownId, previousState, formData) {
  try {
    await apiFetch(`/breakdowns/${breakdownId}/cancel`, {
      method: "POST",
      body: JSON.stringify({ cancellation_reason: formData.get("cancellation_reason") }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * The linked work order's own labor/parts/checklist endpoints, called from
 * this breakdown's screen instead of a separate work-order one — the
 * technician never has to know the work order exists as its own page.
 * Bound `(breakdownId, workOrderId, ...)`: identical wire calls to
 * `work-orders/[workOrderId]/actions.js`, just revalidating this page
 * instead of `/work-orders/:id`.
 */
async function recordChecklistAnswer(breakdownId, workOrderId, previousState, formData) {
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
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function recordLabor(breakdownId, workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/labor`, {
      method: "POST",
      body: JSON.stringify({
        technician_id: formData.get("technician_id") || null,
        started_at: new Date(formData.get("started_at")).toISOString(),
        ended_at: new Date(formData.get("ended_at")).toISOString(),
        notes: formData.get("notes") || null,
      }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function deleteLabor(breakdownId, workOrderId, entryId) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/labor/${entryId}`, { method: "DELETE" });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function requestPart(breakdownId, workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts`, {
      method: "POST",
      body: JSON.stringify({ spare_part_id: formData.get("spare_part_id"), quantity: formData.get("quantity") }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function issuePart(breakdownId, workOrderId, previousState, formData) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/issue`, {
      method: "POST",
      body: JSON.stringify({
        spare_part_id: formData.get("spare_part_id"),
        bin_id: formData.get("bin_id"),
        quantity: formData.get("quantity"),
      }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Fulfils one specific pending request line — see the equivalent in `work-orders/[workOrderId]/actions.js` for why this is distinct from `issuePart` above. */
async function issueRequestedPart(breakdownId, workOrderId, lineId, binId, quantity) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/${lineId}/issue`, {
      method: "POST",
      body: JSON.stringify({ bin_id: binId, quantity }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function consumePart(breakdownId, workOrderId, lineId, quantity) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/${lineId}/consume`, {
      method: "POST",
      body: JSON.stringify({ quantity }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function returnPart(breakdownId, workOrderId, lineId, quantity) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/parts/${lineId}/return`, {
      method: "POST",
      body: JSON.stringify({ quantity }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * Mirrors `BreakdownTransitionController::raiseWorkOrder` — puts the
 * line's whole standing roster onto a new work order for this breakdown
 * (`RaiseBreakdownWorkOrder::assignRoster()`), not one manager-picked
 * name. Returns the new work order's number so the caller can toast it,
 * the same information the web flash message (`breakdown.work_order_
 * raised`) carries.
 */
async function raiseWorkOrder(breakdownId) {
  try {
    const workOrder = await apiFetch(`/breakdowns/${breakdownId}/work-order`, { method: "POST" });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success", workOrderNumber: workOrder.work_order_number };
  } catch (error) {
    return fail(error);
  }
}

/**
 * Mirrors `WorkOrderApiController::start` — the roster technician who
 * opens the work order this raised and starts it. That call also moves
 * the breakdown itself along (ACKNOWLEDGED → IN_REPAIR), so this page's
 * own status updates on the same click without a separate "Start repair"
 * button here.
 */
async function startWorkOrder(breakdownId, workOrderId) {
  try {
    await apiFetch(`/work-orders/${workOrderId}/start`, { method: "POST" });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/**
 * Mirrors `BreakdownTransitionController::correctTimestamp` — backdates one
 * chain field without changing status. The value is already a real
 * timestamp with an offset by the time it reaches here (converted from the
 * factory-local `datetime-local` input in the modal itself), unlike the web
 * form which converts on the server.
 */
async function correctTimestamp(breakdownId, previousState, formData) {
  try {
    await apiFetch(`/breakdowns/${breakdownId}`, {
      method: "PATCH",
      body: JSON.stringify({
        field: formData.get("field"),
        value: formData.get("value"),
      }),
    });
    revalidatePath(`/breakdowns/${breakdownId}`);
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export {
  acknowledge, arrive, startRepair, completeRepair, resumeProduction, resume, assign, hold, close, cancel,
  raiseWorkOrder, startWorkOrder, correctTimestamp,
  recordChecklistAnswer, recordLabor, deleteLabor, requestPart, issuePart, issueRequestedPart, consumePart, returnPart,
};
