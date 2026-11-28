/**
 * The section manager's screens (AnnotechERP 37, phase 3), signed in by
 * email on their own phone: who may see and change which section, reading a
 * screen's data with its token, and the pieces every such screen has — the
 * day it shows, a refresh, a message over the screen.
 */
import { useCallback, useEffect, useRef, useState } from "react";
import { Pressable, View } from "react-native";
import { ChevronLeft, ChevronRight, RefreshCw } from "lucide-react-native";
import { Alert, Button, Text } from "./ui";
import { t } from "../lib/i18n";
import { serverNow } from "../lib/clock";
import { useSession } from "../lib/session";
import { radius, space, touch } from "../theme/tokens";
import { useColors } from "../theme/theme";

export const SECTIONS = ["CUTTING", "SEWING", "QUALITY"];
export const mm = (key, params) => t("mobile", `mgr.${key}`, params);
export const q = (key, params) => t("qtally", key, params);

/** As the web decides it (QtFlow): a section seen, managed (targets, input, review), or neither. */
export function qtAccess(permissions = []) {
  const has = (p) => permissions.includes(p);
  const manages = (s) => has("qtally.target.manage") || has(`qtally.${s.toLowerCase()}.manage`);
  const sees = (s) => has("qtally.view") || has(`qtally.${s.toLowerCase()}.view`) || manages(s);

  return SECTIONS.filter(sees).map((s) => ({ section: s, manages: manages(s) }));
}

export function useAccess() {
  const { session } = useSession();

  return qtAccess(session?.permissions ?? []);
}

const DAY = 24 * 60 * 60 * 1000;
/** Today in the factory (Dhaka), by the server's clock. */
export const todayDhaka = () => new Date(serverNow().getTime() + 6 * 60 * 60 * 1000).toISOString().slice(0, 10);
export const shiftDay = (iso, n) => new Date(Date.parse(`${iso}T00:00:00Z`) + n * DAY).toISOString().slice(0, 10);
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
export const dayLabel = (iso) => (iso ? `${iso.slice(8, 10)} ${MONTHS[Number(iso.slice(5, 7)) - 1]} ${iso.slice(0, 4)}` : "");

/**
 * A screen's data from the server, read again on `deps` and on demand. The
 * last answer stays on screen while the next is read.
 */
export function useLoad(path, deps = []) {
  const { call } = useSession();
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const asked = useRef(0);

  const load = useCallback(async () => {
    if (!path) return;
    const turn = ++asked.current;
    setLoading(true);
    try {
      const next = await call(path);
      if (turn === asked.current) {
        setData(next);
        setError(null);
      }
    } catch (e) {
      if (turn === asked.current) setError(e.message);
    } finally {
      if (turn === asked.current) setLoading(false);
    }
  }, [path, call]);

  useEffect(() => {
    load();
  }, [load, ...deps]);

  return { data, error, loading, reload: load, setData };
}

/** The day a screen shows: back, today, forward. */
export function DateBar({ value, onChange, max = null }) {
  const colors = useColors();
  const today = todayDhaka();
  const arrow = (Icon, label, n, off) => (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      disabled={off}
      onPress={() => onChange(shiftDay(value, n))}
      style={{ width: touch, height: touch, alignItems: "center", justifyContent: "center", borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, opacity: off ? 0.4 : 1 }}
    >
      <Icon size={22} color={colors.foreground} />
    </Pressable>
  );

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
      {arrow(ChevronLeft, mm("prev_day"), -1, false)}
      <Pressable accessibilityRole="button" onPress={() => onChange(today)} style={{ flex: 1, minHeight: touch, alignItems: "center", justifyContent: "center", borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface }}>
        <Text variant="label">{dayLabel(value)}</Text>
        {value === today ? (
          <Text variant="small" tone="brand">
            {mm("today")}
          </Text>
        ) : null}
      </Pressable>
      {arrow(ChevronRight, mm("next_day"), 1, max !== null && value >= max)}
    </View>
  );
}

/** Nothing yet, or the server could not be read. */
export function LoadState({ data, error, loading, reload }) {
  if (data) return error ? <Alert tone="warning">{error}</Alert> : null;
  if (error) {
    return (
      <View style={{ gap: space[2] }}>
        <Alert>{error}</Alert>
        <Button variant="outline" icon={RefreshCw} onPress={reload}>
          {mm("retry")}
        </Button>
      </View>
    );
  }

  return loading ? <Text tone="foregroundMuted">{mm("loading")}</Text> : null;
}

/** A labelled line of figures in a list card. */
export function Figures({ items }) {
  return (
    <View style={{ flexDirection: "row", flexWrap: "wrap", columnGap: space[4], rowGap: space[1] }}>
      {items.map(([label, value, tone]) => (
        <Text key={label} variant="small" tone="foregroundMuted">
          {label}{" "}
          <Text variant="label" tone={tone ?? "foreground"}>
            {value}
          </Text>
        </Text>
      ))}
    </View>
  );
}
