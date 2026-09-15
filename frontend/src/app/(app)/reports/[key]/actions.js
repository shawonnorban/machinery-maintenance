"use server";

import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

async function requestExport(reportKey, query, format) {
  try {
    const job = await apiFetch("/report-jobs", {
      method: "POST",
      body: JSON.stringify({ report: reportKey, format, ...query }),
    });
    return { status: "success", job };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }
}

export { requestExport };
