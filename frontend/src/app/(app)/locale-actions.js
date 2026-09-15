"use server";

import { cookies } from "next/headers";
import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/**
 * Two separate things share the name "locale" here, and this sets both:
 * the account-level preference (`PATCH /auth/locale`, mirroring the web's
 * `PreferenceController::locale`) and the `locale` cookie
 * `frontend/src/i18n/request.js` actually reads to pick which message
 * bundle next-intl loads. The DB column has no bearing on rendering by
 * itself — only the cookie does — so setting one without the other would
 * save a preference that visibly does nothing.
 */
async function setLocale(locale) {
  try {
    await apiFetch("/auth/locale", { method: "PATCH", body: JSON.stringify({ locale }) });
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message };
    }
    throw error;
  }

  (await cookies()).set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 365, sameSite: "lax" });
  revalidatePath("/", "layout");

  return { status: "success" };
}

export { setLocale };
