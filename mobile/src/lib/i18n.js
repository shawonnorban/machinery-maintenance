/**
 * English and Bangla, from the WEB's own messages (frontend/messages, written
 * by scripts/extract-i18n.php from lang/*): `t("mobile", "server.title")`.
 * Placeholders are Laravel's `:name`, filled as frontend/src/lib/i18n.js fills
 * them. A namespace a screen needs is added here, so the app carries only the
 * words it shows. Numbers stay in Latin digits.
 */
import Storage from "expo-sqlite/kv-store";
import { getLocales } from "expo-localization";
import enMobile from "../../../frontend/messages/en/mobile.json";
import bnMobile from "../../../frontend/messages/bn/mobile.json";
import enTally from "../../../frontend/messages/en/qtally.json";
import bnTally from "../../../frontend/messages/bn/qtally.json";

export const LOCALES = [
  { value: "en", label: "English" },
  { value: "bn", label: "বাংলা" },
];

const messages = { en: { mobile: enMobile, qtally: enTally }, bn: { mobile: bnMobile, qtally: bnTally } };
const KEY = "locale";
const listeners = new Set();

const saved = Storage.getItemSync(KEY);
let locale = messages[saved] ? saved : getLocales()[0]?.languageCode === "bn" ? "bn" : "en";

export const currentLocale = () => locale;

/** Switch language; the root layout redraws every screen in it. */
export function applyLocale(next) {
  if (!messages[next] || next === locale) return;
  locale = next;
  Storage.setItemSync(KEY, next);
  listeners.forEach((listener) => listener(next));
}

export function onLocaleChange(listener) {
  listeners.add(listener);

  return () => listeners.delete(listener);
}

function fill(raw, params) {
  return Object.entries(params)
    .sort((a, b) => b[0].length - a[0].length)
    .reduce((text, [key, value]) => text.replaceAll(`:${key}`, String(value)), raw);
}

const find = (lang, namespace, key) => key.split(".").reduce((node, part) => node?.[part], messages[lang]?.[namespace]);

export function t(namespace, key, params) {
  const raw = find(locale, namespace, key) ?? find("en", namespace, key);

  if (typeof raw !== "string") {
    if (__DEV__) console.warn(`[i18n] missing ${namespace}.${key}`);
    return key;
  }

  return params ? fill(raw, params) : raw;
}
