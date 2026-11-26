/**
 * This phone, to the server: an id and a secret made once, kept in Android's
 * encrypted store. A reinstalled app is a new phone and is approved anew.
 */
import { Platform } from "react-native";
import * as Crypto from "expo-crypto";
import * as SecureStore from "expo-secure-store";
import { APP_VERSION } from "./api";

const KEY = "phone";
let cached = null;

export async function thisPhone() {
  if (cached) return cached;
  let saved = null;
  try {
    saved = JSON.parse((await SecureStore.getItemAsync(KEY)) ?? "null");
  } catch {
    // Unreadable (a restored backup, a lost key): a new phone.
  }
  if (!saved?.install_id || !saved?.secret) {
    const bytes = await Crypto.getRandomBytesAsync(32);
    saved = { install_id: Crypto.randomUUID(), secret: Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("") };
    await SecureStore.setItemAsync(KEY, JSON.stringify(saved));
  }
  const { Brand = "", Model = "", Release = "" } = Platform.constants ?? {};
  cached = {
    ...saved,
    model: [Brand && Brand[0].toUpperCase() + Brand.slice(1), Model].filter(Boolean).join(" ").slice(0, 80) || "Android",
    os: `Android ${Release}`.trim().slice(0, 40),
    app_version: APP_VERSION,
  };

  return cached;
}

/** As the phone itself: its QCs' sessions and taps, whoever is signed in. */
export async function phoneHeaders(companyId, waitingTaps = 0) {
  const { install_id, secret } = await thisPhone();

  return { "X-Company-Id": companyId, "X-Phone-Id": install_id, "X-Phone-Secret": secret, "X-Waiting-Taps": String(waitingTaps) };
}
