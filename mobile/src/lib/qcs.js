/**
 * The QCs this phone knows: who signed in here (with the server) in the last
 * days. Their PIN is kept as a slow salted hash in Android's encrypted store,
 * never itself, so the lock screen — and a sign-in with no network — can check it.
 */
import * as Crypto from "expo-crypto";
import * as SecureStore from "expo-secure-store";
import Storage from "expo-sqlite/kv-store";
import { serverNow } from "./clock";

const LIST = "qcs";
const ROUNDS = 400;
const TRIES = 5;
const LOCK_MS = 15 * 60 * 1000;
const DAY = 24 * 60 * 60 * 1000;

export const qcKey = (companyCode, employeeId) => `${companyCode.trim().toUpperCase()}/${employeeId.trim().toUpperCase()}`;
const store = (key) => `pin.${key.replace(/[^A-Za-z0-9._-]/g, "_")}`;

function list() {
  try {
    return JSON.parse(Storage.getItemSync(LIST) ?? "[]");
  } catch {
    return [];
  }
}
const save = (rows) => Storage.setItemSync(LIST, JSON.stringify(rows));

async function hash(pin, salt) {
  let value = `${salt}:${pin}`;
  for (let i = 0; i < ROUNDS; i++) value = await Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, value + salt);

  return value;
}

/** After a real sign-in, or a new PIN: what a later check needs. */
export async function remember(profile, pin) {
  const key = qcKey(profile.company.code, profile.qc.employee_id);
  const salt = Crypto.randomUUID();
  await SecureStore.setItemAsync(store(key), JSON.stringify({ salt, hash: await hash(pin, salt) }));
  save([{ key, name: profile.qc.name, employee_id: profile.qc.employee_id, company_code: profile.company.code, seen_at: serverNow().toISOString(), days: profile.offline_days ?? 7, profile }, ...list().filter((r) => r.key !== key)].slice(0, 30));
}

/** The company refused this QC (or changed the PIN elsewhere): nothing of theirs stays. */
export async function forget(key) {
  save(list().filter((r) => r.key !== key));
  await SecureStore.deleteItemAsync(store(key)).catch(() => {});
}

/** Who signed in here lately, for the sign-in screen's shortcuts. */
export const recentQcs = () => list().slice(0, 6);

/** The QC as last seen here, if recent enough to sign in without a network. */
export function known(key) {
  const row = list().find((r) => r.key === key);

  return row && serverNow() - new Date(row.seen_at) <= row.days * DAY ? row : null;
}

const tries = (key) => JSON.parse(Storage.getItemSync(`tries.${key}`) ?? "null") ?? { wrong: 0, until: 0 };

/**
 * Checks a PIN on the phone: { ok } or { ok: false, left } or { ok: false, minutes }
 * when too many were wrong. Counted on the device, so closing the app does not clear it.
 */
export async function checkPin(key, pin) {
  const state = tries(key);
  const left = state.until - Date.now();
  // A clock set back does not stretch the wait past its length.
  if (left > 0 && left <= LOCK_MS) return { ok: false, minutes: Math.ceil(left / 60000) };

  let saved = null;
  try {
    saved = JSON.parse((await SecureStore.getItemAsync(store(key))) ?? "null");
  } catch {
    // Unreadable: the server must be asked.
  }
  if (!saved) return { ok: false, unknown: true };
  if ((await hash(pin, saved.salt)) === saved.hash) {
    Storage.removeItemSync(`tries.${key}`);

    return { ok: true };
  }
  const wrong = (state.until ? 0 : state.wrong) + 1;
  Storage.setItemSync(`tries.${key}`, JSON.stringify(wrong >= TRIES ? { wrong: 0, until: Date.now() + LOCK_MS } : { wrong, until: 0 }));

  return wrong >= TRIES ? { ok: false, minutes: LOCK_MS / 60000 } : { ok: false, left: TRIES - wrong };
}
