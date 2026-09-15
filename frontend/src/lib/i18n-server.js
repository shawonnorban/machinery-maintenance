import "server-only";
import { getMessages } from "next-intl/server";

/**
 * lang/en and lang/bn use Laravel's own `:placeholder` interpolation, not
 * next-intl's ICU `{placeholder}` syntax (see `lib/i18n.js`'s own docblock
 * for why the extraction never rewrites it) — duplicated here rather than
 * imported from that file so this server-only module never pulls in a
 * "use client" one for a plain string helper neither hooks nor touches the
 * DOM.
 */
function formatMessage(raw, params) {
  if (!params) return raw;

  let result = raw;
  for (const [key, value] of Object.entries(params)) {
    const str = String(value);
    result = result
      .replaceAll(`:${key.toUpperCase()}`, str.toUpperCase())
      .replaceAll(`:${key.charAt(0).toUpperCase()}${key.slice(1)}`, str.charAt(0).toUpperCase() + str.slice(1))
      .replaceAll(`:${key}`, str);
  }
  return result;
}

/**
 * Server Component equivalent of `useT()` (`lib/i18n.js`) — same lookup and
 * interpolation, sourced via next-intl's server-side `getMessages()` instead
 * of the client `useMessages()` hook, for pages/layouts that build
 * translated content before it reaches the browser (e.g. `(app)/layout.js`'s
 * nav labels).
 *
 * `const t = await getT("nav"); t("dashboard")`
 */
async function getT(namespace) {
  const messages = await getMessages();
  const scope = messages?.[namespace] ?? {};

  return (key, params) => {
    const raw = key.split(".").reduce((node, part) => node?.[part], scope);

    if (typeof raw !== "string") {
      if (process.env.NODE_ENV !== "production") {
        console.warn(`[i18n] Missing message: ${namespace}.${key}`);
      }
      return key;
    }

    return formatMessage(raw, params);
  };
}

export { getT };
