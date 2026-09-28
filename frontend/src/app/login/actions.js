"use server";

import { cookies } from "next/headers";
import { revalidatePath } from "next/cache";

/**
 * The signed-out counterpart to `(app)/locale-actions.js`'s `setLocale` —
 * that one also saves the choice to the account via `PATCH /auth/locale`,
 * which needs a session this page's visitor doesn't have yet. Nobody to
 * save a preference *for* before they've signed in, so this only sets the
 * `locale` cookie `frontend/src/i18n/request.js` reads to pick the message
 * bundle. Once they do sign in, `LocaleToggle`'s own save (or the language
 * field on their profile) takes over persisting it to the account.
 */
async function setGuestLocale(locale) {
  (await cookies()).set("locale", locale, { path: "/", maxAge: 60 * 60 * 24 * 365, sameSite: "lax" });
  revalidatePath("/login");
}

export { setGuestLocale };
