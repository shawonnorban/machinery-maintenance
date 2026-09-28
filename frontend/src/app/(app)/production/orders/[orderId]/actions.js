"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

const paths = (id) => ["/production/orders", `/production/orders/${id}`];

/** One request, the error shape every panel already toasts. */
async function call(path, method, body, revalidate = []) {
  try {
    const data = await apiFetch(path, { method, body: JSON.stringify(body ?? {}) });
    for (const target of revalidate) revalidatePath(target);
    return { status: "success", data };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

async function addStyle(orderId, style) {
  return call(`/production-orders/${orderId}/styles`, "POST", style, paths(orderId));
}

async function saveStyleBom(orderId, styleId, lines) {
  return call(`/production-styles/${styleId}/bom`, "PUT", { lines }, paths(orderId));
}

async function addCuttingProgram(orderId, styleId, program) {
  return call(`/production-styles/${styleId}/cutting-programs`, "POST", program, [
    ...paths(orderId),
    "/production/cutting-programs",
  ]);
}

async function setOrderStatus(orderId, status) {
  return call(`/production-orders/${orderId}/status`, "POST", { status }, paths(orderId));
}

export { addStyle, saveStyleBom, addCuttingProgram, setOrderStatus };
