/**
 * The server's time, not the phone's: a phone whose clock is two hours out must
 * not put its taps in the wrong hour. The difference is measured at every
 * answer that carries the server's time, and kept for when there is no network.
 */
import Storage from "expo-sqlite/kv-store";
import { clock12 } from "../../../frontend/src/lib/clock";

const KEY = "clock.offset";
let offset = Number(Storage.getItemSync(KEY)) || 0;

export function noteServerTime(iso) {
  const server = iso ? Date.parse(iso) : NaN;
  if (Number.isNaN(server)) return;
  const next = server - Date.now();
  // The answer's own seconds of travel are not a wrong clock.
  if (Math.abs(next - offset) < 3000) return;
  offset = next;
  Storage.setItemSync(KEY, String(next));
}

export const serverNow = () => new Date(Date.now() + offset);

const DHAKA = 6 * 60 * 60 * 1000;

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

/** 27 Nov 08:05 AM, factory time; the year only when it is not this one. */
export function dateTimeOf(iso) {
  if (!iso) return "—";
  const at = new Date(Date.parse(iso) + DHAKA).toISOString();
  const year = at.slice(0, 4) === new Date(serverNow().getTime() + DHAKA).toISOString().slice(0, 4) ? "" : ` ${at.slice(0, 4)}`;

  return `${at.slice(8, 10)} ${MONTHS[Number(at.slice(5, 7)) - 1]}${year} ${clock12(at.slice(11, 16))}`;
}

/** 08:05 AM, factory time, whatever zone the phone is set to. */
export function timeOf(iso) {
  return clock12(new Date(Date.parse(iso) + DHAKA).toISOString().slice(11, 16));
}
