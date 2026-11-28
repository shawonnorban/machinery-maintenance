/**
 * Talking to the API. Every request has a deadline: a floor's Wi-Fi that holds
 * a request open without answering must never hold a screen with it (the
 * fault this app was rewritten to cure). `status` 0 is "no answer" — no
 * network, or the deadline passed — and is never read as a refusal.
 */
import Constants from "expo-constants";
import * as Crypto from "expo-crypto";
import { currentLocale, t } from "./i18n";
import { apiUrl, currentServer } from "./server";
import { noteServerTime } from "./clock";

export const APP_VERSION = Constants.expoConfig?.version ?? "0";
// A development computer running Docker, the emulator and Metro together answers slower than a server does.
export const DEADLINE_MS = (__DEV__ && Number(process.env.EXPO_PUBLIC_DEV_DEADLINE_MS)) || 8000;

export class ApiError extends Error {
  constructor(status, body, timedOut = false) {
    super(body?.message ?? (status === 0 ? t("mobile", timedOut ? "net.timeout" : "net.unreachable") : t("mobile", "net.failed", { status })));
    this.name = "ApiError";
    this.status = status;
    this.timedOut = timedOut;
    this.errors = body?.errors ?? null;
    // What to branch on: PHONE_PENDING, PIN_LOCKED… (QtPhones::refuse).
    this.reason = body?.meta?.reason ?? null;
    this.meta = body?.meta ?? null;
  }

  field(name) {
    return this.errors?.[name]?.[0] ?? null;
  }
}

/**
 * `body` is JSON, or a FormData for a photo. Every write carries an
 * Idempotency-Key (the machinery API requires one): a press repeated on a
 * slow network, or a report sent again from the phone's queue, is done once.
 */
export async function request(path, { method = "GET", body, token, headers, server = currentServer(), deadline = DEADLINE_MS, whole = false } = {}) {
  if (!server) throw new ApiError(0, null);
  const form = typeof FormData !== "undefined" && body instanceof FormData;
  const write = method !== "GET";
  const stop = new AbortController();
  const timer = setTimeout(() => stop.abort(), deadline);
  let response;
  let payload = null;

  try {
    response = await fetch(`${apiUrl(server)}${path}`, {
      method,
      signal: stop.signal,
      headers: {
        Accept: "application/json",
        "Accept-Language": currentLocale(),
        "X-App-Version": APP_VERSION,
        ...(body && !form ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(write ? { "Idempotency-Key": Crypto.randomUUID() } : {}),
        ...headers,
      },
      ...(body ? { body: form ? body : JSON.stringify(body) } : {}),
    });
    // Inside the deadline too: headers can arrive and the body never finish.
    if (response.status !== 204) payload = await response.json().catch(() => null);
  } catch {
    throw new ApiError(0, null, stop.signal.aborted);
  } finally {
    clearTimeout(timer);
  }

  if (!response.ok) throw new ApiError(response.status, payload);
  noteServerTime(payload?.data?.server_time);

  // `whole`: a page of a list, with its `meta` (current_page, last_page, total).
  return whole ? payload : (payload?.data ?? payload);
}
