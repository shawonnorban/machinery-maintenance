"use server";

import { platformApiFetch } from "@/lib/platform-api-server";
import { ApiError } from "@/lib/api-error";

/** Mirrors `TenantController::store`/`OnboardTenant` — a company, its first factory, and an owner account, in one call. */
async function createTenant(previousState, formData) {
  try {
    const result = await platformApiFetch("/tenants", {
      method: "POST",
      body: JSON.stringify({
        name: formData.get("name"),
        code: formData.get("code"),
        legal_name: formData.get("legal_name") || undefined,
        base_currency: formData.get("base_currency"),
        timezone: formData.get("timezone"),
        default_locale: formData.get("default_locale"),
        factory_name: formData.get("factory_name"),
        factory_code: formData.get("factory_code"),
        owner_name: formData.get("owner_name"),
        owner_email: formData.get("owner_email"),
      }),
    });

    return {
      status: "success",
      companyId: result.company.id,
      companyName: result.company.name,
      ownerEmail: result.owner.email,
      password: result.password,
    };
  } catch (error) {
    if (error instanceof ApiError) {
      return { status: "error", message: error.message, errors: error.errors };
    }
    throw error;
  }
}

export { createTenant };
