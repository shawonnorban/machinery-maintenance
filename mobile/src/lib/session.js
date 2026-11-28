/**
 * Who is signed in. A QC (Company Code + Employee ID + PIN, tally only, locked
 * when left alone) or a person (email and password; a QC among them gets the
 * tally too, phase 2). The session is drawn from the device at once and asked
 * after in the background: no network, no waiting.
 */
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import { AppState } from "react-native";
import * as Crypto from "expo-crypto";
import * as SecureStore from "expo-secure-store";
import Storage from "expo-sqlite/kv-store";
import { ApiError, request } from "./api";
import { serverNow } from "./clock";
import { phoneHeaders, thisPhone } from "./phone";
import { checkPin, forget, known, qcKey, remember } from "./qcs";
import { queueSession as queue, sendSessions as sendQueue } from "./taps";

const TOKEN = "token";
const SESSION = "session";
const COMPANY = "company.code";
/** Left alone this long, a QC's phone asks for the PIN again. */
export const LOCK_AFTER_MS = 5 * 60 * 1000;

const Context = createContext(null);
export const useSession = () => useContext(Context);
export const lastCompany = () => Storage.getItemSync(COMPANY) ?? "";

const readJson = (key, fallback) => {
  try {
    return JSON.parse(Storage.getItemSync(key) ?? "null") ?? fallback;
  } catch {
    return fallback;
  }
};

/** The QC this phone taps for: a PIN session's, or the QC signed in by email. */
export const qcOf = (session) => (session?.kind === "PIN" ? session.profile : (session?.qc ?? null));

/** The token kept on the phone, read once at start. */
const tokenRead = SecureStore.getItemAsync(TOKEN).catch(() => null);

export function SessionProvider({ children }) {
  const [session, setSession] = useState(() => readJson(SESSION, null));
  const [locked, setLocked] = useState(() => readJson(SESSION, null)?.kind === "PIN");
  const [ended, setEnded] = useState(null);
  const [online, setOnline] = useState(true);
  const token = useRef(null);
  // Whether the token saved on the phone has been taken in (once, at start; a sign-in or out settles it too).
  const loaded = useRef(false);
  const takeSaved = useCallback(async () => {
    if (loaded.current) return;
    const saved = await tokenRead;
    if (loaded.current) return;
    loaded.current = true;
    if (token.current === null) token.current = saved;
  }, []);
  const touched = useRef(Date.now());
  const current = useRef(session);
  current.current = session;

  const keep = useCallback(async (next, bearer) => {
    loaded.current = true;
    token.current = bearer;
    if (bearer) await SecureStore.setItemAsync(TOKEN, bearer);
    else await SecureStore.deleteItemAsync(TOKEN).catch(() => {});
    if (next) Storage.setItemSync(SESSION, JSON.stringify(next));
    else Storage.removeItemSync(SESSION);
    touched.current = Date.now();
    current.current = next;
    setSession(next);
    setLocked(false);
  }, []);

  /** Closed here, whatever the server says; told to it when it can be. */
  const close = useCallback(
    async (reason = null) => {
      const s = current.current;
      const bearer = token.current;
      if (!s) return;
      const qc = qcOf(s);
      if (qc) queue({ company_id: qc.company.id, uuid: qc.session.uuid, employee_id: qc.qc.employee_id, started_at: qc.session.started_at, ended_at: serverNow().toISOString() });
      await keep(null, null);
      setEnded(reason);
      if (reason) return;
      if (qc) sendQueue().catch(() => {});
      if (s.kind !== "PIN" && bearer) request("/auth/logout", { method: "POST", token: bearer }).catch(() => {});
    },
    [keep],
  );

  /** Every request of a signed-in screen: a 401 is the end of the session, no answer is not. */
  const call = useCallback(
    async (path, options = {}) => {
      // A screen opened at start asks before the saved token is read: it waits for it.
      await takeSaved();
      const bearer = token.current;
      try {
        const data = await request(path, { ...options, token: bearer });
        setOnline(true);

        return data;
      } catch (error) {
        if (error.status === 0) setOnline(false);
        else setOnline(true);
        if (error.status === 401 && bearer && bearer === token.current) await close("ended");
        throw error;
      }
    },
    [close, takeSaved],
  );

  const refresh = useCallback(async () => {
    await sendQueue().then(() => setOnline(true), (e) => e.status === 0 && setOnline(false));
    const s = current.current;
    if (!s || !token.current) return;
    if (s.kind === "PIN") {
      const { expires_at, ...profile } = await call("/quality-tally/mobile/session");
      if (current.current?.profile.session.uuid !== profile.session.uuid) return;
      const next = { ...s, profile };
      Storage.setItemSync(SESSION, JSON.stringify(next));
      setSession(next);
    } else {
      const [me, perms] = await Promise.all([call("/auth/me"), call("/auth/permissions")]);
      if (!token.current) return;
      const latest = current.current;
      const next = { ...latest, me, permissions: perms.permissions };
      Storage.setItemSync(SESSION, JSON.stringify(next));
      setSession(next);
      // A QC who signed in by email, not yet given a session on this phone (it waited for approval, or was offline).
      if (!latest.qc && latest.qcRefused?.reason !== "NOT_QC") attachQc().catch(() => {});
    }
  }, [call]);

  // The token, once; then the server, at start, on coming back, and every half minute.
  useEffect(() => {
    let live = true;
    takeSaved().then(() => {
      if (live) refresh().catch(() => {});
    });
    const timer = setInterval(() => AppState.currentState === "active" && refresh().catch(() => {}), 30000);

    return () => {
      live = false;
      clearInterval(timer);
    };
  }, [refresh]);

  // The lock: five minutes without a touch, or in the background.
  useEffect(() => {
    const check = () => current.current?.kind === "PIN" && Date.now() - touched.current > LOCK_AFTER_MS && setLocked(true);
    const timer = setInterval(check, 10000);
    const sub = AppState.addEventListener("change", (state) => {
      if (state !== "active") return;
      check();
      refresh().catch(() => {});
    });

    return () => {
      clearInterval(timer);
      sub.remove();
    };
  }, [refresh]);

  const signInPin = useCallback(
    async (companyCode, employeeId, pin) => {
      const key = qcKey(companyCode, employeeId);
      const uuid = Crypto.randomUUID();
      const started = serverNow().toISOString();
      try {
        const { access_token, expires_at, ...profile } = await request("/quality-tally/mobile/sign-in", {
          method: "POST",
          body: { company_code: companyCode.trim(), employee_id: employeeId.trim(), pin, session_uuid: uuid, phone: await thisPhone() },
        });
        Storage.setItemSync(COMPANY, profile.company.code);
        setOnline(true);
        setEnded(null);
        await keep({ kind: "PIN", key, profile, offline: false }, access_token);
        await remember(profile, pin);
      } catch (error) {
        // Refused by the server: this QC may not come in here without it either.
        if (["INACTIVE", "PHONE_BLOCKED", "PHONE_UNKNOWN", "MODULE_OFF", "OTHER_LINE"].includes(error.reason)) await forget(key);
        // The phone knows this PIN but the server does not: it was changed or reset elsewhere.
        else if (error.reason === "WRONG" && (await checkPin(key, pin)).ok) await forget(key);
        if (error.status !== 0) throw error;

        // No network: a QC seen here lately, by the PIN they used then.
        setOnline(false);
        const row = known(key);
        const check = row ? await checkPin(key, pin) : null;
        if (!check || check.unknown) throw error;
        if (!check.ok) throw Object.assign(new ApiError(0, null), { reason: "OFFLINE_PIN", check });
        const profile = { ...row.profile, session: { uuid, started_at: started } };
        queue({ company_id: profile.company.id, uuid, employee_id: profile.qc.employee_id, started_at: started, ended_at: null });
        setEnded(null);
        await keep({ kind: "PIN", key, profile, offline: true }, null);
      }
    },
    [keep],
  );

  /**
   * A person signed in by email who is a QC: a QC session on this phone, so the
   * tally works for them as for a PIN sign-in. Somebody who is not a QC keeps
   * the app without it; a phone waiting for approval says so on the home screen.
   */
  const attachQc = useCallback(async () => {
    const bearer = token.current;
    const s = current.current;
    if (!bearer || s?.kind !== "EMAIL") return;
    let qc = null;
    let qcRefused = null;
    try {
      qc = await request("/quality-tally/mobile/attach", { method: "POST", token: bearer, body: { session_uuid: Crypto.randomUUID() } });
    } catch (error) {
      if (error.status === 0) throw error;
      qcRefused = { reason: error.reason, message: error.message, code: error.meta?.code ?? null };
    }
    if (current.current !== s || token.current !== bearer) return;
    const next = { ...s, qc, qcRefused };
    current.current = next;
    Storage.setItemSync(SESSION, JSON.stringify(next));
    setSession(next);
  }, []);

  /** The server ended the QC's session (out of date, the QC taken off): a PIN sign-in signs out, an email one asks anew. */
  const sessionEnded = useCallback(async () => {
    const s = current.current;
    if (s?.kind === "PIN") await close("ended");
    else if (s?.kind === "EMAIL") {
      const next = { ...s, qc: null, qcRefused: null };
      current.current = next;
      Storage.setItemSync(SESSION, JSON.stringify(next));
      setSession(next);
      await attachQc().catch(() => {});
    }
  }, [close, attachQc]);

  const signInEmail = useCallback(
    async (companyCode, email, password) => {
      const data = await request("/auth/login", {
        method: "POST",
        body: { company_code: companyCode.trim(), email: email.trim(), password, device_name: "Norban Group (phone)", phone: await thisPhone() },
      });
      const [me, perms] = await Promise.all([request("/auth/me", { token: data.access_token }), request("/auth/permissions", { token: data.access_token })]);
      Storage.setItemSync(COMPANY, companyCode.trim().toUpperCase());
      setOnline(true);
      setEnded(null);
      await keep({ kind: "EMAIL", me, permissions: perms.permissions, qc: null, qcRefused: null }, data.access_token);
      await attachQc().catch(() => {});
    },
    [keep, attachQc],
  );

  /** The lock screen: the same QC's PIN, checked on the phone. */
  const unlock = useCallback(async (pin) => {
    const s = current.current;
    const result = await checkPin(s.key, pin);
    if (result.ok) {
      touched.current = Date.now();
      setLocked(false);
    }

    return result;
  }, []);

  const changePin = useCallback(
    async (currentPin, pin) => {
      await call("/quality-tally/mobile/pin", { method: "POST", body: { current_pin: currentPin, pin, pin_confirmation: pin } });
      const s = current.current;
      const profile = { ...s.profile, pin_default: false };
      await remember(profile, pin);
      Storage.setItemSync(SESSION, JSON.stringify({ ...s, profile }));
      setSession({ ...s, profile });
    },
    [call],
  );

  const touch = useCallback(() => {
    touched.current = Date.now();
  }, []);

  const value = useMemo(
    // `bearer`: for what cannot go through `call` — a photo the Image component fetches itself.
    () => ({ session, locked, ended, online, setOnline, signInPin, signInEmail, unlock, lock: () => setLocked(true), changePin, signOut: () => close(), sessionEnded, attachQc, refresh, call, touch, bearer: () => token.current }),
    [session, locked, ended, online, signInPin, signInEmail, unlock, changePin, close, sessionEnded, attachQc, refresh, call, touch],
  );

  return <Context.Provider value={value}>{children}</Context.Provider>;
}
