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

/** Mirrors `SettingsApiController::update`/`CompanySettingsController::update` — `factory_id` present means a factory-level override, absent means the company-wide answer. */
async function updateSetting(key, factoryId, previousState, formData) {
  try {
    const body = { value: formData.get("value") };
    if (factoryId) body.factory_id = factoryId;

    await apiFetch(`/settings/${key}`, { method: "PUT", body: JSON.stringify(body) });

    revalidatePath("/settings/company");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Drops a factory's own answer so it follows the company again — a no-op when the factory never set one. */
async function resetSetting(key, factoryId) {
  try {
    await apiFetch(`/settings/${key}?factory_id=${factoryId}`, { method: "DELETE" });
    revalidatePath("/settings/company");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Mirrors `SettingsApiController::updateLogo` — replaces whatever logo the company already had, if any. */
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

    revalidatePath("/settings/company");
    // The sidebar mark reads the logo from `/auth/me`, fetched in the
    // layout above every page — that has to re-run too, not just this page.
    revalidatePath("/", "layout");

    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function removeLogo() {
  try {
    await apiFetch("/settings/company/logo", { method: "DELETE" });
    revalidatePath("/settings/company");
    revalidatePath("/", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

export { updateSetting, resetSetting, uploadLogo, removeLogo };
