/**
 * Where the server is: the address and port typed on this phone (user,
 * 2026-11-25: the app connects straight to an IP and a port). Kept on the
 * device; nothing is built into the app.
 */
import { useEffect, useState } from "react";
import Storage from "expo-sqlite/kv-store";

const KEY = "server";
export const DEFAULT_PORT = "8010";
const listeners = new Set();

function read() {
  try {
    const saved = JSON.parse(Storage.getItemSync(KEY) ?? "null");
    if (saved?.host) return saved;
  } catch {
    // An unreadable setting is asked for again.
  }
  // The emulator reaches the development computer at 10.0.2.2.
  const dev = __DEV__ ? /^https?:\/\/([^:/]+):?(\d+)?/.exec(process.env.EXPO_PUBLIC_API_URL ?? "") : null;

  return dev ? { host: dev[1], port: dev[2] ?? DEFAULT_PORT } : null;
}

let server = read();

export const currentServer = () => server;
export const serverLabel = (s = server) => (s ? `${s.host}:${s.port}` : "");
export const apiUrl = (s = server) => `http://${s.host}:${s.port}/api/v1`;

/** An IP or a name, with or without http:// and a port; null when it is neither. */
export function parseServer(host, port) {
  const typed = /^(?:https?:\/\/)?([A-Za-z0-9.-]+)(?::(\d+))?\/?$/.exec(host.trim());
  const number = Number(typed?.[2] ?? (port.trim() || DEFAULT_PORT));
  if (!typed || !Number.isInteger(number) || number < 1 || number > 65535) return null;

  const name = typed[1];
  const ip = /^\d+(\.\d+){3}$/.test(name);
  if (/^[\d.]+$/.test(name) && (!ip || name.split(".").some((n) => Number(n) > 255))) return null;

  return { host: name, port: String(number) };
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
