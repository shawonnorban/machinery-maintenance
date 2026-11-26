/** Light, dark or the device's own: kept on the DEVICE, not on the login. */
import { useEffect, useState } from "react";
import { Appearance } from "react-native";
import Storage from "expo-sqlite/kv-store";

const KEY = "appearance";
export const MODES = ["system", "light", "dark"];

const listeners = new Set();
let mode = MODES.includes(Storage.getItemSync(KEY)) ? Storage.getItemSync(KEY) : "system";

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
