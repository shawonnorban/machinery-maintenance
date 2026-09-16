"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { platformApiFetch } from "@/lib/platform-api-server";
import { setSessionToken } from "@/lib/session";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function ok(extra = {}) {
  return { status: "success", ...extra };
}

async function updateTenantDetails(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}`, {
      method: "PATCH",
      body: JSON.stringify({
        name: formData.get("name"),
        legal_name: formData.get("legal_name") || null,
        email: formData.get("email") || null,
        phone: formData.get("phone") || null,
        country: formData.get("country") || null,
        address: formData.get("address") || null,
        base_currency: formData.get("base_currency"),
        timezone: formData.get("timezone"),
        default_locale: formData.get("default_locale"),
      }),
    });
  } catch (error) {
    return fail(error);
  }

  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function suspendTenant(companyId, reason) {
  try {
    await platformApiFetch(`/tenants/${companyId}/suspend`, {
      method: "POST",
      body: JSON.stringify({ reason }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function reactivateTenant(companyId) {
  try {
    await platformApiFetch(`/tenants/${companyId}/reactivate`, { method: "POST" });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

/** Mirrors `TenantController::destroy` — a soft close, not an erase. Redirects since this account's own page has nothing left worth showing. */
async function closeTenant(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}`, {
      method: "DELETE",
      body: JSON.stringify({
        confirm_code: formData.get("confirm_code"),
        reason: formData.get("reason"),
      }),
    });
  } catch (error) {
    return fail(error);
  }

  revalidatePath("/platform");
  redirect("/platform");
}

/** Reachable only from an already-closed account — two decisions on two days, per `TenantController::purge`'s own docblock. */
async function purgeTenant(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}/purge`, {
      method: "DELETE",
      body: JSON.stringify({
        confirm_code: formData.get("confirm_code"),
        reason: formData.get("reason"),
      }),
    });
  } catch (error) {
    return fail(error);
  }

  revalidatePath("/platform");
  redirect("/platform");
}

async function storeContract(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}/contracts`, {
      method: "POST",
      body: JSON.stringify({
        contract_number: formData.get("contract_number"),
        start_date: formData.get("start_date"),
        end_date: formData.get("end_date") || null,
        billing_cycle: formData.get("billing_cycle"),
        amount: formData.get("amount"),
        currency: formData.get("currency"),
        trial_end: formData.get("trial_end") || null,
        grace_period_days: Number(formData.get("grace_period_days") || 14),
        auto_renew: formData.get("auto_renew") === "on",
        included_factories: formData.get("included_factories") || null,
        included_assets: formData.get("included_assets") || null,
        included_users: formData.get("included_users") || null,
        overage_policy: formData.get("overage_policy"),
        notes: formData.get("notes") || null,
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function updateEntitlements(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}/entitlements`, {
      method: "PATCH",
      body: JSON.stringify({
        included_factories: formData.get("included_factories") || null,
        included_assets: formData.get("included_assets") || null,
        included_users: formData.get("included_users") || null,
        overage_policy: formData.get("overage_policy"),
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function draftInvoice(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}/invoices`, {
      method: "POST",
      body: JSON.stringify({
        period_start: formData.get("period_start"),
        period_end: formData.get("period_end"),
        tax_rate: formData.get("tax_rate") || undefined,
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function issueInvoice(companyId, invoiceId) {
  try {
    await platformApiFetch(`/invoices/${invoiceId}/issue`, { method: "POST" });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function payInvoice(companyId, invoiceId, formData) {
  try {
    await platformApiFetch(`/invoices/${invoiceId}/payments`, {
      method: "POST",
      body: JSON.stringify({
        amount: formData.get("amount"),
        method: formData.get("method"),
        payment_reference: formData.get("payment_reference") || null,
        paid_at: formData.get("paid_at") || null,
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function voidInvoice(companyId, invoiceId, reason) {
  try {
    await platformApiFetch(`/invoices/${invoiceId}/void`, {
      method: "POST",
      body: JSON.stringify({ reason }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function addDomain(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}/domains`, {
      method: "POST",
      body: JSON.stringify({
        kind: formData.get("kind"),
        host: formData.get("host"),
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function verifyDomain(companyId, domainId) {
  try {
    await platformApiFetch(`/domains/${domainId}/verify`, { method: "POST" });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function primaryDomain(companyId, domainId) {
  try {
    await platformApiFetch(`/domains/${domainId}/primary`, { method: "POST" });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function removeDomain(companyId, domainId) {
  try {
    await platformApiFetch(`/domains/${domainId}`, { method: "DELETE" });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function updateMemberEmail(companyId, memberId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}/members/${memberId}/email`, {
      method: "PATCH",
      body: JSON.stringify({
        email: formData.get("email"),
        reason: formData.get("reason"),
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function resetMemberPassword(companyId, memberId, previousState, formData) {
  let result;
  try {
    result = await platformApiFetch(`/tenants/${companyId}/members/${memberId}/reset-password`, {
      method: "POST",
      body: JSON.stringify({ reason: formData.get("reason") }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok({ password: result.password, email: result.email });
}

async function openSupportGrant(companyId, previousState, formData) {
  try {
    await platformApiFetch(`/tenants/${companyId}/support-grants`, {
      method: "POST",
      body: JSON.stringify({
        reason: formData.get("reason"),
        hours: Number(formData.get("hours") || 1),
      }),
    });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

async function closeSupportGrant(companyId, grantId) {
  try {
    await platformApiFetch(`/support-grants/${grantId}/close`, { method: "POST" });
  } catch (error) {
    return fail(error);
  }
  revalidatePath(`/platform/tenants/${companyId}`);
  return ok();
}

/**
 * Steps inside an open grant as a named user of the company, right from the
 * platform console — no cross-app handoff to design, unlike the old Blade
 * flow (`TenantController::enterSupport`'s own "KNOWN GAP" comment): the
 * platform console and the tenant app are the same Next.js deployment, so
 * the token `PlatformSupportGrantApiController::enter` mints just becomes
 * this app's own tenant session cookie, in the same request.
 *
 * Not bound to a `companyId` like its siblings — the grant id alone is
 * enough for the API call, and the redirect target is always the tenant
 * app's own root, not a platform URL.
 */
async function enterSupportGrant(grantId, userId) {
  let session;

  try {
    session = await platformApiFetch(`/support-grants/${grantId}/enter`, {
      method: "POST",
      body: JSON.stringify({ user_id: userId }),
    });
  } catch (error) {
    return fail(error);
  }

  await setSessionToken(session.access_token, session.expires_at);

  // Deliberately outside the try/catch above: `redirect()` works by
  // throwing, and catching that here would turn a successful hand-off into
  // a reported "error".
  redirect("/");
}

export {
  updateTenantDetails,
  suspendTenant,
  reactivateTenant,
  closeTenant,
  purgeTenant,
  storeContract,
  updateEntitlements,
  draftInvoice,
  issueInvoice,
  payInvoice,
  voidInvoice,
  addDomain,
  verifyDomain,
  primaryDomain,
  removeDomain,
  updateMemberEmail,
  resetMemberPassword,
  openSupportGrant,
  closeSupportGrant,
  enterSupportGrant,
};
