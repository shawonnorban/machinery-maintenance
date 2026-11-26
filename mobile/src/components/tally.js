/**
 * The tally's pieces (AnnotechERP 37, phase 2): four enormous buttons a QC
 * hits without aiming, the running figures, a sheet that rises from the
 * bottom for defects and jobs, and the line that says what is still to send.
 */
import { Modal, Pressable, ScrollView, View } from "react-native";
import { router } from "expo-router";
import { CheckCircle2, CloudOff, CloudUpload, ShieldAlert, TriangleAlert, Wrench, X, XCircle } from "lucide-react-native";
import { SafeAreaView, useSafeAreaInsets } from "react-native-safe-area-context";
import { Text } from "./ui";
import { t } from "../lib/i18n";
import { syncTaps, useTaps } from "../lib/taps";
import { timeOf } from "../lib/clock";
import { radius, space, touch } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const m = (key, params) => t("mobile", `tally.${key}`, params);
const q = (key, params) => t("qtally", key, params);

export const ACTIONS = [
  { key: "RFT", icon: CheckCircle2, tone: "success" },
  { key: "DEFECT", icon: ShieldAlert, tone: "warning" },
  { key: "RECTIFY", icon: Wrench, tone: "info" },
  { key: "REJECT", icon: XCircle, tone: "danger" },
];

export const num = (n) => (n === null || n === undefined ? "—" : Number(n).toLocaleString("en-US"));
export const pct = (n) => (n === null || n === undefined ? "—" : `${Number(n).toFixed(1)}%`);

/** One of the four. Pressed in, it darkens and the count moves at once. */
export function TapButton({ action, count, onPress, flash = false, disabled = false }) {
  const colors = useColors();
  const { icon: Icon, tone } = ACTIONS.find((a) => a.key === action);
  const bg = colors[tone];

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${q(`action.${action}`)} ${num(count)}`}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [
        { flex: 1, minHeight: 148, borderRadius: radius.lg, backgroundColor: bg, alignItems: "center", justifyContent: "center", gap: space[1], padding: space[3], opacity: disabled ? 0.45 : pressed ? 0.8 : 1 },
        flash ? { borderWidth: 4, borderColor: colors.foreground } : null,
        shadow(colors),
      ]}
    >
      <Icon size={34} color="#ffffff" />
      <Text variant="sectionTitle" style={{ color: "#ffffff" }}>
        {q(`action.${action}`)}
      </Text>
      <Text style={{ color: "#ffffff", fontSize: 34, lineHeight: 40, fontWeight: "800", fontVariant: ["tabular-nums"] }}>{num(count)}</Text>
      <Text variant="small" style={{ color: "#ffffff", opacity: 0.9 }} numberOfLines={1}>
        {q(`action_hint.${action}`)}
      </Text>
    </Pressable>
  );
}

/** A figure and its name. */
export function Stat({ label, value, tone = "foreground", sub }) {
  const colors = useColors();

  return (
    <View style={{ flexBasis: "31%", flexGrow: 1, backgroundColor: colors.surface, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, paddingVertical: space[2], paddingHorizontal: space[3] }}>
      <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
        {label}
      </Text>
      <Text variant="figure" tone={tone} style={{ fontSize: 22, lineHeight: 28 }} numberOfLines={1} adjustsFontSizeToFit>
        {value}
      </Text>
      {sub ? (
        <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
          {sub}
        </Text>
      ) : null}
    </View>
  );
}

/**
 * A message over the screen, top middle, gone in a few seconds. Never in the
 * page: a line pushed in above the buttons moves them under a thumb already
 * on its way down (the PIN pad's lesson in phase 1).
 */
export function Toast({ notice, onClose }) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  if (!notice) return null;
  const [bg, fg] = { danger: [colors.danger, "#ffffff"], warning: [colors.warning, "#ffffff"], success: [colors.success, "#ffffff"] }[notice.tone] ?? [colors.foreground, colors.background];

  return (
    <View pointerEvents="box-none" style={{ position: "absolute", top: insets.top + 64, left: space[4], right: space[4], alignItems: "center" }}>
      <Pressable accessibilityRole="alert" onPress={onClose} style={[{ maxWidth: 480, width: "100%", backgroundColor: bg, borderRadius: radius.md, paddingVertical: space[3], paddingHorizontal: space[4] }, shadow(colors, "md")]}>
        <Text variant="label" style={{ color: fg, textAlign: "center" }}>
          {notice.text}
        </Text>
      </Pressable>
    </View>
  );
}

/** A sheet from the bottom: defects, jobs. */
export function Sheet({ open, onClose, title, hint, children, footer }) {
  const colors = useColors();

  return (
    <Modal visible={open} transparent animationType="slide" onRequestClose={onClose} statusBarTranslucent>
      <View style={{ flex: 1, backgroundColor: colors.overlay, justifyContent: "flex-end" }}>
        <Pressable accessibilityLabel={m("close")} style={{ flex: 1 }} onPress={onClose} />
        <SafeAreaView edges={["bottom"]} style={{ maxHeight: "88%", backgroundColor: colors.surface, borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg }}>
          <View style={{ flexDirection: "row", alignItems: "center", paddingHorizontal: space[4], paddingTop: space[4], gap: space[2] }}>
            <View style={{ flex: 1 }}>
              <Text variant="sectionTitle">{title}</Text>
              {hint ? (
                <Text variant="small" tone="foregroundMuted">
                  {hint}
                </Text>
              ) : null}
            </View>
            <Pressable accessibilityRole="button" accessibilityLabel={m("close")} onPress={onClose} style={{ width: touch, height: touch, alignItems: "center", justifyContent: "center" }}>
              <X size={24} color={colors.foregroundMuted} />
            </Pressable>
          </View>
          <ScrollView contentContainerStyle={{ padding: space[4], gap: space[2] }}>{children}</ScrollView>
          {footer ? <View style={{ flexDirection: "row", gap: space[3], padding: space[4], borderTopWidth: 1, borderTopColor: colors.border }}>{footer}</View> : null}
        </SafeAreaView>
      </View>
    </Modal>
  );
}

/** A choice in a sheet: a defect, a job, a bundle, a panel. */
export function Option({ title, sub, on = false, onPress, tone = "brand", style }) {
  const colors = useColors();

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: on }}
      onPress={onPress}
      style={[{ minHeight: 56, borderRadius: radius.md, borderWidth: 2, borderColor: on ? colors[tone] : colors.border, backgroundColor: on ? colors[`${tone}Subtle`] : colors.surface, paddingHorizontal: space[3], paddingVertical: space[2], justifyContent: "center" }, style]}
    >
      <Text variant="label" numberOfLines={2}>
        {title}
      </Text>
      {sub ? (
        <Text variant="small" tone="foregroundMuted" numberOfLines={2}>
          {sub}
        </Text>
      ) : null}
    </Pressable>
  );
}

/** What is still on the phone, what was refused, and the phone's own fault if sending stopped. */
export function SyncBar({ offline = false }) {
  const colors = useColors();
  const { waiting, refused, sending, error, lastSentAt } = useTaps();
  const status = sending ? m("sending") : waiting ? m("waiting", { n: waiting }) : lastSentAt ? m("last_sent", { time: timeOf(new Date(lastSentAt).toISOString()) }) : m("all_sent");

  return (
    <View style={{ gap: space[2] }}>
      {error ? (
        <View style={{ flexDirection: "row", gap: space[2], alignItems: "center", backgroundColor: colors.dangerSubtle, borderRadius: radius.md, padding: space[3] }}>
          <TriangleAlert size={18} color={colors.danger} />
          <Text variant="small" tone="danger" style={{ flex: 1 }}>
            {m("phone_error", { msg: error })}
          </Text>
        </View>
      ) : null}
      <View style={{ flexDirection: "row", gap: space[2] }}>
        <Pressable
          accessibilityRole="button"
          onPress={() => syncTaps({ now: true }).catch(() => {})}
          style={{ flex: 1, minHeight: 44, flexDirection: "row", alignItems: "center", gap: space[2], paddingHorizontal: space[3], borderRadius: radius.md, borderWidth: 1, borderColor: waiting || offline ? colors.warning : colors.border, backgroundColor: waiting || offline ? colors.warningSubtle : colors.surface }}
        >
          {waiting || offline ? <CloudOff size={18} color={colors.warning} /> : <CloudUpload size={18} color={colors.success} />}
          <Text variant="small" tone={waiting || offline ? "warning" : "foregroundMuted"} numberOfLines={1} style={{ flex: 1 }}>
            {offline ? `${t("mobile", "home.offline")} · ${status}` : status}
          </Text>
        </Pressable>
        {refused ? (
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push("/refused")}
            style={{ minHeight: 44, flexDirection: "row", alignItems: "center", gap: space[2], paddingHorizontal: space[3], borderRadius: radius.md, borderWidth: 1, borderColor: colors.danger, backgroundColor: colors.dangerSubtle }}
          >
            <XCircle size={18} color={colors.danger} />
            <Text variant="small" tone="danger">
              {m("refused", { n: refused })}
            </Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}
