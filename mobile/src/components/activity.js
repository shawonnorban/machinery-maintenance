/**
 * Recent activities (user, 2026-11-27): the person's notifications as a
 * timeline — each on a rule with a coloured dot, its kind as a label (Service
 * Due, Maintenance, Breakdown, Approval…), the date and time, unread marked.
 * One opens its record (a breakdown, a work order, a budget) or, when the app
 * has no screen for it, its full text in a sheet; opening marks it read.
 */
import { useState } from "react";
import { Pressable, View } from "react-native";
import { router } from "expo-router";
import { AlarmClock, BellRing, ChevronRight, ClipboardList, FileClock, Siren, Stamp, Wrench } from "lucide-react-native";
import { Badge, Button, Text } from "./ui";
import { Sheet } from "./tally";
import { dateTimeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { useSession } from "../lib/session";
import { fonts, radius, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const at = (key) => t("mobile", `act.${key}`);

/** What a notification is about: its label, tone and icon. */
export function activityKind(n) {
  const e = n.event_type ?? "";
  if (e.startsWith("MAINTENANCE_")) return { key: "service_due", tone: e.endsWith("OVERDUE") ? "danger" : "warning", icon: AlarmClock };
  if (e.startsWith("WORK_ORDER") || n.entity_type === "work_order") return { key: "maintenance", tone: "info", icon: Wrench };
  if (e.startsWith("BREAKDOWN") || n.entity_type === "breakdown") return { key: "breakdown", tone: "danger", icon: Siren };
  if (e.startsWith("APPROVAL") || e.startsWith("PB_") || n.entity_type === "pre_budget") return { key: "approval", tone: "brand", icon: Stamp };
  if (e.startsWith("OS_") || e.startsWith("DS_") || e.startsWith("ES_")) return { key: "order", tone: "success", icon: FileClock };
  return { key: "other", tone: "neutral", icon: BellRing };
}

/** Where a notification opens in the app, if anywhere. */
function screenOf(n) {
  if (n.entity_type === "breakdown" && n.entity_id) return { pathname: "/breakdown/[id]", params: { id: n.entity_id } };
  if (n.entity_type === "work_order" && n.entity_id) return { pathname: "/work-order/[id]", params: { id: n.entity_id } };
  const budget = /PB-[A-Z0-9-]+/.exec(`${n.title} ${n.body}`);
  if ((n.entity_type ?? "").toLowerCase() === "pre_budget" && budget) return { pathname: "/budget/[no]", params: { no: budget[0] } };
  // An Order Summary or Dev Sample notice opens its own orders (user, 2026-11-27), not the whole list:
  // a changed-rows notice the numbers it names (#12, #13), the morning's notice those it counted.
  const e = n.event_type ?? "";
  if (e.startsWith("OS_") || e.startsWith("DS_")) {
    const serials = e.endsWith("_CHANGED") ? [...`${n.body ?? ""}`.matchAll(/#(\d+)/g)].map((m) => m[1]).join(",") : "";

    return { pathname: "/attention/[kind]", params: { kind: e.startsWith("OS_") ? "os" : "ds", ...(serials ? { serials } : {}) } };
  }

  return null;
}

/** "3d ago" — how long since. */
export function agoOf(iso) {
  const mins = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
  if (mins < 1) return at("ago_now");
  if (mins < 60) return t("mobile", "act.ago_m", { n: mins });
  if (mins < 60 * 24) return t("mobile", "act.ago_h", { n: Math.round(mins / 60) });

  return t("mobile", "act.ago_d", { n: Math.round(mins / 1440) });
}

/**
 * `cards` (Home, as the reference screen): each on a white card — the
 * timeline's dot at the left, the kind's icon in a soft circle, its badge,
 * the title bold, a line under it and how long ago.
 */
export function ActivityTimeline({ items, onRead, onGo, cards = false }) {
  const colors = useColors();
  const { call } = useSession();
  const [open, setOpen] = useState(null);

  const read = (n) => {
    if (!n.is_read) {
      call(`/notifications/${n.id}/read`, { method: "POST" }).then(() => onRead?.(n.id), () => {});
    }
  };
  const press = (n) => {
    read(n);
    const to = screenOf(n);
    if (to) {
      // Inside the bell's popup the popup closes before the record opens.
      onGo?.();
      router.push(to);
    } else setOpen(n);
  };

  const sheet = (
      <Sheet open={Boolean(open)} onClose={() => setOpen(null)} title={open?.title ?? ""} hint={open ? `${at(activityKind(open).key)} · ${dateTimeOf(open.created_at)}` : undefined}>
        {open?.body ? <Text>{open.body}</Text> : null}
        {open?.severity ? <Badge tone={{ CRITICAL: "danger", WARNING: "warning" }[open.severity] ?? "info"}>{open.severity}</Badge> : null}
        <Button variant="outline" icon={ClipboardList} onPress={() => setOpen(null)}>
          {t("mobile", "act.close")}
        </Button>
      </Sheet>
  );
  const toneOf = (kind) => ({ danger: colors.danger, warning: colors.warning, info: colors.info, brand: colors.brand, success: colors.success })[kind.tone] ?? colors.foregroundSubtle;

  if (cards) {
    return (
      <View style={{ gap: space[3] }}>
        {items.map((n, i) => {
          const kind = activityKind(n);
          const Icon = kind.icon;
          const tone = toneOf(kind);

          return (
            <View key={n.id} style={{ flexDirection: "row", alignItems: "stretch", gap: space[2] }}>
              {/* The timeline: a ringed dot, the rule to the next. */}
              <View style={{ width: 14, alignItems: "center" }}>
                <View style={{ flex: 1, width: 2, backgroundColor: i === 0 ? "transparent" : colors.border }} />
                <View style={{ width: 12, height: 12, borderRadius: 6, backgroundColor: colors.surface, borderWidth: 3, borderColor: tone }} />
                <View style={{ flex: 1, width: 2, backgroundColor: i === items.length - 1 ? "transparent" : colors.border }} />
              </View>
              <Pressable
                accessibilityRole="button"
                onPress={() => press(n)}
                style={({ pressed }) => [{ flex: 1, flexDirection: "row", alignItems: "center", gap: space[3], padding: space[3], borderRadius: 18, backgroundColor: colors.surface, opacity: pressed ? 0.85 : 1 }, shadow(colors)]}
              >
                <View style={{ width: 50, height: 50, borderRadius: 25, backgroundColor: `${tone}1f`, alignItems: "center", justifyContent: "center" }}>
                  <Icon size={24} color={tone} />
                </View>
                <View style={{ flex: 1, gap: 3 }}>
                  <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                    <Badge tone={kind.tone}>{at(kind.key)}</Badge>
                    {n.is_read ? null : <View style={{ width: 7, height: 7, borderRadius: radius.full, backgroundColor: colors.brand }} />}
                  </View>
                  <Text style={{ fontFamily: fonts.bold, fontSize: 15 }} numberOfLines={1}>
                    {n.title}
                  </Text>
                  {n.body ? (
                    <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
                      {n.body}
                    </Text>
                  ) : null}
                </View>
                <Text variant="small" tone="foregroundSubtle" style={{ fontSize: 12, alignSelf: "flex-start", marginTop: 2 }}>
                  {agoOf(n.created_at)}
                </Text>
              </Pressable>
            </View>
          );
        })}
        {sheet}
      </View>
    );
  }

  return (
    <View>
      {items.map((n, i) => {
        const kind = activityKind(n);
        const Icon = kind.icon;
        const last = i === items.length - 1;
        const tone = toneOf(kind);

        return (
          <Pressable key={n.id} accessibilityRole="button" onPress={() => press(n)} style={({ pressed }) => ({ flexDirection: "row", gap: space[3], opacity: pressed ? 0.7 : 1 })}>
            {/* The rule and its dot. */}
            <View style={{ width: 30, alignItems: "center" }}>
              <View style={{ width: 30, height: 30, borderRadius: radius.full, backgroundColor: `${tone}1f`, alignItems: "center", justifyContent: "center", marginTop: 2 }}>
                <Icon size={15} color={tone} />
              </View>
              {last ? null : <View style={{ flex: 1, width: 2, backgroundColor: colors.border, marginVertical: 2 }} />}
            </View>
            <View style={{ flex: 1, gap: 3, paddingBottom: last ? 0 : space[4] }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                <Badge tone={kind.tone}>{at(kind.key)}</Badge>
                {n.is_read ? null : <View style={{ width: 7, height: 7, borderRadius: radius.full, backgroundColor: colors.brand }} />}
                <Text variant="small" tone="foregroundSubtle" style={{ marginLeft: "auto", fontSize: 12 }}>
                  {dateTimeOf(n.created_at)}
                </Text>
              </View>
              <Text variant="label" numberOfLines={2}>
                {n.title}
              </Text>
              {n.body ? (
                <Text variant="small" tone="foregroundMuted" numberOfLines={2}>
                  {n.body}
                </Text>
              ) : null}
            </View>
            <ChevronRight size={16} color={colors.foregroundSubtle} style={{ marginTop: 8 }} />
          </Pressable>
        );
      })}
      {sheet}
    </View>
  );
}
