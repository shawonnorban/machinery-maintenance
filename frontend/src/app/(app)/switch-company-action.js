"use server";

import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { setSessionToken } from "@/lib/session";

/**
 * Mints a new bearer token scoped to a different company the user belongs
 * to (`AuthController::switchCompany` — a fresh token rather than a mutation
 * of the current one, so a client already syncing this company's data keeps
 * working on it). Redirects to the dashboard root rather than the current
 * page: almost everything on screen (factories, assets, permissions) is
 * scoped to the company that just changed, so the safest landing spot is
 * the one page that re-fetches everything from scratch.
 */
async function switchCompany(companyId) {
  const data = await apiFetch("/auth/switch-company", {
    method: "POST",
    body: JSON.stringify({ company_id: companyId }),
  });

  await setSessionToken(data.access_token, data.expires_at);

  redirect("/");
}

export { switchCompany };
