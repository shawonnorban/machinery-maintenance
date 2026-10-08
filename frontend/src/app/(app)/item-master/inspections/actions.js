"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { postDocumentAction } from "@/components/procurement/document-action-helpers";

const paths = (id) => ["/item-master/inspections", `/item-master/inspections/${id}`];

/**
 * Tallying one roll onto the sheet. The whole sheet comes back, because
 * one roll's points move the lot's pooled figure.
 */
async function recordInspectionRoll(id, payload) {
  return postDocumentAction(`/fabric-inspections/${id}/rolls`, payload, paths(id));
}

async function submitInspection(id) {
  return postDocumentAction(`/fabric-inspections/${id}/submit`, {}, paths(id));
}

/** Accepting the lot is what puts its rolls on the shelf. */
async function approveInspection(id) {
  return postDocumentAction(`/fabric-inspections/${id}/approve`, {}, [...paths(id), "/item-master"]);
}

async function rejectInspection(id, reason) {
  return postDocumentAction(`/fabric-inspections/${id}/reject`, { reason }, paths(id));
}

async function escalateInspection(id) {
  return postDocumentAction(`/fabric-inspections/${id}/escalate`, {}, paths(id));
}

async function cancelInspection(id, reason) {
  return postDocumentAction(`/fabric-inspections/${id}/cancel`, { reason }, paths(id));
}

/**
 * Evidence for the whole lot. Not run through `postDocumentAction` — that
 * helper always JSON-encodes the body, which a multipart file upload
 * cannot be.
 */
async function uploadInspectionPhoto(id, formData) {
  try {
    const file = formData.get("file");

    if (!(file instanceof File) || file.size === 0) {
      return { status: "error", message: "Choose a file first." };
    }

    const body = new FormData();
    body.set("file", file);

    await apiFetch(`/fabric-inspections/${id}/attachments`, { method: "POST", body });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }

  revalidatePath(`/item-master/inspections/${id}`);

  return { status: "success" };
}

export {
  recordInspectionRoll,
  submitInspection,
  approveInspection,
  rejectInspection,
  escalateInspection,
  cancelInspection,
  uploadInspectionPhoto,
};
