"use client";

import { useMessages } from "next-intl";

/**
 * lang/en and lang/bn use Laravel's own `:placeholder` interpolation
 * (`Illuminate\Translation\Translator::makeReplacements`), not next-intl's
 * ICU `{placeholder}` syntax — and the extraction script deliberately never
 * rewrites that (see scripts/extract-i18n.php's docblock: a literal like
 * import.php's "HH:MM" is indistinguishable from a real placeholder by
 * syntax alone). So translated strings are read here as raw values and
 * interpolated the same way Laravel does — a plain `:key` string
 * replacement — rather than through next-intl's own `t()`, which expects
 * ICU syntax it would either mis-parse or throw on.
 *
 * Mirrors Laravel's three case variants for a replacement value: `:key`,
 * `:Key` (first letter capitalised), `:KEY` (all caps).
 */
function formatMessage(raw, params) {
  if (!params) {
    return raw;
  }

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
 * `const t = useT("asset"); t("status.running"); t("greeting", { name })`
 *
 * @param {string} namespace one of lang/{locale}/*.php's filenames, e.g. "asset"
 */
function useT(namespace) {
  const messages = useMessages();
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

export { useT, formatMessage };
