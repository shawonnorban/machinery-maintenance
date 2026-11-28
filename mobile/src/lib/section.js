/**
 * The Quality Tally section Home and Production show, for a person who sees
 * more than one (Owner, Factory Admin, Factory Manager). Kept on the phone. A
 * section manager or QC sees only their own (user, 2026-11-27: Cutting does
 * not see Sewing, nor Sewing Cutting), so the choice never appears for them.
 */
import { useEffect, useState } from "react";
import Storage from "expo-sqlite/kv-store";

const KEY = "qt_section";
const listeners = new Set();
let chosen = Storage.getItemSync(KEY);

export function chooseSection(next) {
  chosen = next;
  Storage.setItemSync(KEY, next);
  listeners.forEach((listener) => listener(next));
}

/** The chosen section if this person may see it, else their first. */
export function useSection(access) {
  const [current, setCurrent] = useState(chosen);

  useEffect(() => {
    listeners.add(setCurrent);

    return () => listeners.delete(setCurrent);
  }, []);

  return access.find((a) => a.section === current) ?? access[0] ?? null;
}
