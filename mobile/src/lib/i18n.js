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
// Machinery (phase 4): the web's own words for breakdowns, work orders, machines and the scan.
import enBreakdown from "../../../frontend/messages/en/breakdown.json";
import bnBreakdown from "../../../frontend/messages/bn/breakdown.json";
import enWorkOrder from "../../../frontend/messages/en/work_order.json";
import bnWorkOrder from "../../../frontend/messages/bn/work_order.json";
import enAsset from "../../../frontend/messages/en/asset.json";
import bnAsset from "../../../frontend/messages/bn/asset.json";
import enScan from "../../../frontend/messages/en/scan.json";
import bnScan from "../../../frontend/messages/bn/scan.json";
// Phase 5: the change logs and the budgets, in the web's own words.
import enOrderSummary from "../../../frontend/messages/en/ordersummary.json";
import bnOrderSummary from "../../../frontend/messages/bn/ordersummary.json";
import enDevSample from "../../../frontend/messages/en/devsample.json";
import bnDevSample from "../../../frontend/messages/bn/devsample.json";
import enPreBudget from "../../../frontend/messages/en/prebudget.json";
import bnPreBudget from "../../../frontend/messages/bn/prebudget.json";
// Chat (2026-11-27): the web's chat in the web's own words.
import enChat from "../../../frontend/messages/en/chat.json";
import bnChat from "../../../frontend/messages/bn/chat.json";

export const LOCALES = [
  { value: "en", label: "English" },
  { value: "bn", label: "বাংলা" },
];

const messages = {
  en: { mobile: enMobile, qtally: enTally, breakdown: enBreakdown, work_order: enWorkOrder, asset: enAsset, scan: enScan, ordersummary: enOrderSummary, devsample: enDevSample, prebudget: enPreBudget, chat: enChat },
  bn: { mobile: bnMobile, qtally: bnTally, breakdown: bnBreakdown, work_order: bnWorkOrder, asset: bnAsset, scan: bnScan, ordersummary: bnOrderSummary, devsample: bnDevSample, prebudget: bnPreBudget, chat: bnChat },
};
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
