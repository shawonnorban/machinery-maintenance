import { getRequestConfig } from "next-intl/server";
import { cookies } from "next/headers";

/**
 * lang/en and lang/bn are the source of truth (extracted into
 * frontend/messages/*.json by scripts/extract-i18n.php — never hand-edit
 * those JSON files directly, regenerate them instead). No URL locale
 * segment: the Laravel app picks locale from a stored preference, not the
 * path, and this mirrors that rather than introducing /en/* and /bn/*
 * routes the rest of the product doesn't have.
 *
 * Missing-key behaviour (docs/12-Stack-Migration-Implementation-Plan.md
 * Phase C §6, decided explicitly rather than inherited unexamined): a
 * `bn` key missing from lang/bn falls back to the `en` string rather than
 * failing the build or rendering a raw key — a partially-translated screen
 * beats a broken one. `mergeMessages` below does that fallback per-key by
 * deep-merging English underneath Bengali, so English only ever shows
 * through in the exact gaps.
 */
const LOCALES = ["en", "bn"];
const DEFAULT_LOCALE = "en";
const LOCALE_COOKIE = "locale";

export default getRequestConfig(async () => {
  const store = await cookies();
  const requested = store.get(LOCALE_COOKIE)?.value;
  const locale = LOCALES.includes(requested) ? requested : DEFAULT_LOCALE;

  const english = (await import("../../messages/en.json")).default;
  const messages =
    locale === "en" ? english : mergeMessages(english, (await import(`../../messages/${locale}.json`)).default);

  return { locale, messages };
});

// Recursive, not shallow: several namespaces nest (lang/en/api.php's
// `errors.UNAUTHENTICATED`, for one), and a shallow merge would let one
// missing leaf key drop its whole surrounding group to English instead of
// just that key.
function mergeMessages(fallback, primary) {
  if (typeof primary !== "object" || primary === null || Array.isArray(primary)) {
    return primary ?? fallback;
  }

  const merged = { ...fallback };

  for (const key of Object.keys(primary)) {
    merged[key] =
      typeof fallback?.[key] === "object" && fallback[key] !== null
        ? mergeMessages(fallback[key], primary[key])
        : primary[key];
  }

  return merged;
}
