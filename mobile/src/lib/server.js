/**
 * Where the server is, typed on this phone (user, 2026-11-25: the app
 * connects straight to an address and a port) — on the factory's own network
 * an IP and a port over HTTP (192.168.0.10 : 8010), on the internet a domain
 * over HTTPS (norbanit.com, user 2026-11-27). Kept on the device; nothing is
 * built into the app.
 *
 * Without "http://" or "https://" typed: a domain, or port 443, is HTTPS; an
 * IP is HTTP. With no port: HTTPS uses 443, HTTP the server's 8010.
 */
import { useEffect, useState } from "react";
import Storage from "expo-sqlite/kv-store";

const KEY = "server";
export const DEFAULT_PORT = "8010";
const listeners = new Set();

const isIp = (host) => /^\d+(\.\d+){3}$/.test(host);

function read() {
  try {
    const saved = JSON.parse(Storage.getItemSync(KEY) ?? "null");
    // Saved before HTTPS was known: plain HTTP.
    if (saved?.host) return { scheme: "http", ...saved };
  } catch {
    // An unreadable setting is asked for again.
  }
  // The emulator reaches the development computer at 10.0.2.2.
  const dev = __DEV__ ? /^(https?):\/\/([^:/]+):?(\d+)?/.exec(process.env.EXPO_PUBLIC_API_URL ?? "") : null;

  return dev ? { scheme: dev[1], host: dev[2], port: dev[3] ?? (dev[1] === "https" ? "" : DEFAULT_PORT) } : null;
}

let server = read();

export const currentServer = () => server;

/** "https://norbanit.com", "192.168.0.10:8010". */
export function serverLabel(s = server) {
  if (!s) return "";
  if (s.scheme === "https") return `https://${s.host}${s.port && s.port !== "443" ? `:${s.port}` : ""}`;

  return `${s.host}:${s.port}`;
}

export const apiUrl = (s = server) => `${s.scheme ?? "http"}://${s.host}${s.port ? `:${s.port}` : ""}/api/v1`;

/** An IP or a domain, with or without a scheme and a port; null when it is neither. */
export function parseServer(host, port) {
  const typed = /^(?:(https?):\/\/)?([A-Za-z0-9.-]+)(?::(\d+))?\/*(?:api\/v1\/?)?$/i.exec(host.trim());
  if (!typed) return null;
  const name = typed[2].toLowerCase();
  if (/^[\d.]+$/.test(name) && (!isIp(name) || name.split(".").some((n) => Number(n) > 255))) return null;
  if (!isIp(name) && !name.includes(".") && name !== "localhost") return null;

  const given = typed[3] ?? port.trim();
  const scheme = typed[1]?.toLowerCase() ?? (given === "443" || (!isIp(name) && name !== "localhost" && !given) ? "https" : "http");
  const number = given ? Number(given) : scheme === "https" ? 443 : Number(DEFAULT_PORT);
  if (!Number.isInteger(number) || number < 1 || number > 65535) return null;

  return { scheme, host: name, port: scheme === "https" && number === 443 ? "" : String(number) };
}

export function setServer(next) {
  server = next;
  Storage.setItemSync(KEY, JSON.stringify(next));
  listeners.forEach((listener) => listener(next));
}

export function useServer() {
  const [current, setCurrent] = useState(server);

  useEffect(() => {
    listeners.add(setCurrent);

    return () => listeners.delete(setCurrent);
  }, []);

  return current;
}
