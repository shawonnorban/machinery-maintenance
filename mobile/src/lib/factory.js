/**
 * The factory the phone shows (user, 2026-11-27: a factory selector on
 * Home): all of the person's factories, or one. Kept on the phone. It narrows
 * the lists whose API takes a factory — machines, breakdowns, work orders —
 * and never widens what the server lets the person see.
 */
import { useEffect, useState } from "react";
import Storage from "expo-sqlite/kv-store";

const KEY = "factory";
const listeners = new Set();
let chosen = Storage.getItemSync(KEY) || null;

export function chooseFactory(id) {
  chosen = id || null;
  if (chosen) Storage.setItemSync(KEY, chosen);
  else Storage.removeItemSync(KEY);
  listeners.forEach((listener) => listener(chosen));
}

/** The chosen factory if the person still reaches it, else null (all). */
export function useFactory(factories = []) {
  const [current, setCurrent] = useState(chosen);

  useEffect(() => {
    listeners.add(setCurrent);

    return () => listeners.delete(setCurrent);
  }, []);

  return factories.find((f) => f.id === current) ?? null;
}

/** "&factory_id=…" (or the asset list's own name for it) for a list's address; "" for all. */
export const factoryParam = (factory, name = "factory_id") => (factory ? `${name}=${encodeURIComponent(factory.id)}` : "");
