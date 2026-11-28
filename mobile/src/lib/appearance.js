/** Light, dark or the device's own: kept on the DEVICE, not on the login. */
import { useEffect, useState } from "react";
import { Appearance } from "react-native";
import Storage from "expo-sqlite/kv-store";

const KEY = "appearance";
export const MODES = ["light", "dark", "system"];

const listeners = new Set();
let mode = MODES.includes(Storage.getItemSync(KEY)) ? Storage.getItemSync(KEY) : "light";

// Light, dark or the device's own — chosen in Account (user, 2026-11-27); light until someone chooses.
const apply = () => Appearance.setColorScheme(mode === "system" ? "auto" : mode);

/** Once, at start-up. */
export function loadAppearance() {
  apply();
}

export function setAppearance(next) {
  mode = MODES.includes(next) ? next : "system";
  Storage.setItemSync(KEY, mode);
  apply();
  listeners.forEach((listener) => listener(mode));
}

export function useAppearance() {
  const [current, setCurrent] = useState(mode);

  useEffect(() => {
    listeners.add(setCurrent);

    return () => listeners.delete(setCurrent);
  }, []);

  return current;
}
