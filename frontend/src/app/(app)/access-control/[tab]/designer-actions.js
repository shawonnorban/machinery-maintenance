"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * Access Control → Approval Workflow Designer (AnnotechERP 02/03): action
 * types, their steps, and who each step lands on.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/access-control/approval-workflow");
  return { status: "success", ...extra };
}

function workflowBody(formData) {
  const out = {};
  for (const [key, value] of formData.entries())
    out[key] = value === "" ? null : value;
  out.active = formData.get("active") === "1";
  return out;
}

async function createWorkflow(previousState, formData) {
  try {
    await apiFetch("/approval-center/workflows", {
      method: "POST",
      body: JSON.stringify(workflowBody(formData)),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateWorkflow(id, previousState, formData) {
  try {
    await apiFetch(`/approval-center/workflows/${id}`, {
      method: "PATCH",
      body: JSON.stringify(workflowBody(formData)),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadSteps(id) {
  try {
    return {
      status: "success",
      ...(await apiFetch(`/approval-center/workflows/${id}/steps`)),
    };
  } catch (error) {
    return fail(error);
  }
}

/** The whole chain at once, as the grid holds it. */
async function saveSteps(id, steps) {
  try {
    const result = await apiFetch(`/approval-center/workflows/${id}/steps`, {
      method: "PUT",
      body: JSON.stringify({ steps }),
    });
    return done(result);
  } catch (error) {
    return fail(error);
  }
}

async function loadStepUsers(ruleId) {
  try {
    return {
      status: "success",
      users: await apiFetch(`/approval-center/steps/${ruleId}/users`),
    };
  } catch (error) {
    return fail(error);
  }
}

async function saveStepUsers(ruleId, userIds) {
  try {
    const users = await apiFetch(`/approval-center/steps/${ruleId}/users`, {
      method: "PUT",
      body: JSON.stringify({ user_ids: userIds }),
    });
    return done({ users });
  } catch (error) {
    return fail(error);
  }
}

export {
  createWorkflow,
  updateWorkflow,
  loadSteps,
  saveSteps,
  loadStepUsers,
  saveStepUsers,
};
