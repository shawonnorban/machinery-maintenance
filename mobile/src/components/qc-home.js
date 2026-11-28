/**
 * The QC's dashboard (user, 2026-11-28: "update the QCs' dashboard"): who is
 * tapping, on which shift and phone, today's figures over their lines, and a
 * card per line — its job, how far the line is against its target, and one
 * big button into the tally. Read from what the phone keeps first, then the
 * server, so it opens with no network too.
 */
import { useCallback, useState } from "react";
import { Pressable, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { StatusBar } from "expo-status-bar";
import { ChevronRight, Clock, IdCard, KeyRound, Layers, Lock, LogIn, Play, Smartphone, UserRoundCog, Wifi, WifiOff } from "lucide-react-native";
import { Alert, Button, Screen, Text } from "./ui";
import { HeroBackdrop } from "./hero";
import { SyncBar, num } from "./tally";
import { greeting } from "./home";
import { TileIcon } from "./tile-icon";
import { timeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { qcOf, useSession } from "../lib/session";
import { cachedContext, cachedSummary, loadContext, loadSummary, pickKey } from "../lib/tally";
import { clock12 } from "../../../frontend/src/lib/clock";
import { fonts, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const m = (key, params) => t("mobile", key, params);
const q = (key, params) => t("qtally", key, params);
const SECTION = {
  CUTTING: { color: "#0EA5E9", image: "cutting" },
  SEWING: { color: "#2563EB", image: "sewing" },
  QUALITY: { color: "#16A34A", image: "quality" },
};

/** Each line's targeted jobs and their running totals: kept ones at once, the server's after. */
function useLines(qc, lines) {
  const [ctx, setCtx] = useState(() => cachedContext(qc)?.data ?? null);
  const [sums, setSums] = useState({});

  const read = useCallback(() => {
    const keep = (c) => {
      const out = {};
      for (const l of lines) {
        if (l.section === "CUTTING") continue;
        for (const tg of c?.targets?.[`${l.section}:${l.line_id}`] ?? []) {
          const key = pickKey(l, tg.job_id);
          out[key] = cachedSummary(qc, key)?.data ?? null;
          loadSummary(qc, l, tg.job_id)
            .then((kept) => setSums((all) => ({ ...all, [key]: kept.data })))
            .catch(() => {});
        }
      }
      setSums((all) => ({ ...out, ...Object.fromEntries(Object.entries(all).filter(([k]) => k in out && all[k])) }));
    };
    keep(cachedContext(qc)?.data ?? null);
    loadContext(qc)
      .then((kept) => {
        setCtx(kept.data);
        keep(kept.data);
      })
      .catch(() => {});
  }, [qc?.session?.uuid]);

  useFocusEffect(read);

  return { ctx, sums };
}

export function QcDashboard() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { session, lock, signOut } = useSession();
  const qc = qcOf(session);
  const { qc: me, company, phone, lines, shift, pin_default: isDefault, session: at } = session.profile;
  const { ctx, sums } = useLines(qc, lines);
  const hero = insets.top + 150;

  // Each line's day: its targeted jobs, their target and running figures.
  const days = lines.map((l) => {
    const targets = l.section === "CUTTING" ? [] : (ctx?.targets?.[`${l.section}:${l.line_id}`] ?? []);
    const jobs = targets.map((tg) => ({ tg, job: (ctx?.jobs ?? []).find((j) => j.id === tg.job_id) ?? null, s: sums[pickKey(l, tg.job_id)] ?? null }));
    const target = jobs.reduce((a, x) => a + Number(x.s?.target ?? x.tg.target_qty ?? 0), 0);
    const checked = jobs.reduce((a, x) => a + Number(x.s?.checked ?? 0), 0);
    const pass = jobs.reduce((a, x) => a + Number(x.s?.pass ?? 0), 0);
    const tags = jobs.reduce((a, x) => a + Number(x.s?.defect_tags ?? 0), 0);
    const bundles = l.section === "CUTTING" ? (ctx?.bundles ?? []).filter((b) => !b.line_id || b.line_id === l.line_id).length : null;
    return { l, jobs, target, checked, pass, tags, bundles };
  });
  const total = days.reduce((a, d) => ({ target: a.target + d.target, checked: a.checked + d.checked, pass: a.pass + d.pass, tags: a.tags + d.tags }), { target: 0, checked: 0, pass: 0, tags: 0 });
  const dhu = total.checked ? (total.tags / total.checked) * 100 : 0;

  return (
    <Screen tab bleed>
      <StatusBar style="light" />
      {/* The hero: the greeting, who and where, online or not. */}
      <View style={{ height: hero, marginHorizontal: -space[4], marginTop: -space[4] }}>
        <HeroBackdrop height={hero} imageWidth="70%" wave={30} />
        <View style={{ marginTop: insets.top + space[4], paddingHorizontal: space[4], flexDirection: "row", alignItems: "center", gap: space[3] }}>
          <View style={{ width: 52, height: 52, borderRadius: 26, backgroundColor: "#ffffff", alignItems: "center", justifyContent: "center", borderWidth: 3, borderColor: "rgba(255,255,255,0.5)" }}>
            <Text style={{ fontFamily: fonts.bold, fontSize: 18, color: colors.brand }}>
              {me.name
                .split(/\s+/)
                .slice(0, 2)
                .map((x) => x[0]?.toUpperCase())
                .join("")}
            </Text>
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={{ color: "#dbeafe", fontSize: 13 }} numberOfLines={1}>
              {greeting(me.name)}
            </Text>
            <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: 20, lineHeight: 26 }} numberOfLines={1}>
              {me.name}
            </Text>
            <Text style={{ color: "#dbeafe", fontSize: 12 }} numberOfLines={1}>
              {company.name}
            </Text>
          </View>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6, paddingHorizontal: space[3], paddingVertical: 6, borderRadius: 16, backgroundColor: "rgba(255,255,255,0.18)" }}>
            {session.offline ? <WifiOff size={14} color="#FCA5A5" /> : <Wifi size={14} color="#86EFAC" />}
            <Text style={{ color: "#ffffff", fontFamily: fonts.semibold, fontSize: 12 }}>{session.offline ? m("tally.offline") : m("tally.online")}</Text>
          </View>
        </View>
      </View>

      {/* Who and when, on one card. */}
      <View style={[{ marginTop: -space[6], flexDirection: "row", borderRadius: 18, backgroundColor: colors.surface, paddingVertical: space[3] }, shadow(colors, "md")]}>
        {[
          [IdCard, m("qch.id"), me.employee_id],
          [Clock, m("home.shift"), shift ? `${clock12(shift.start.slice(0, 5))}–${clock12(shift.end.slice(0, 5))}` : "—"],
          [LogIn, m("home.since"), timeOf(at.started_at)],
          [Smartphone, m("home.phone"), phone.name || "—"],
        ].map(([Icon, label, value], i) => (
          <View key={label} style={{ flex: 1, alignItems: "center", gap: 3, paddingHorizontal: 2, borderLeftWidth: i ? 1 : 0, borderLeftColor: colors.border }}>
            <Icon size={17} color={colors.brand} />
            <Text style={{ fontSize: 11, color: colors.foregroundMuted }} numberOfLines={1}>
              {label}
            </Text>
            <Text style={{ fontSize: 12, fontFamily: fonts.semibold, color: colors.heading ?? colors.foreground }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.7}>
              {value}
            </Text>
          </View>
        ))}
      </View>

      {session.offline ? <Alert tone="warning">{m("home.offline_in")}</Alert> : null}
      {isDefault ? (
        <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], padding: space[3], borderRadius: 16, backgroundColor: "#FFF7E6", borderWidth: 1, borderColor: "#FDE68A" }}>
          <View style={{ width: 38, height: 38, borderRadius: 19, backgroundColor: "#F59E0B", alignItems: "center", justifyContent: "center" }}>
            <KeyRound size={19} color="#ffffff" />
          </View>
          <Text style={{ flex: 1, fontSize: 13, color: "#92400E" }}>{m("home.pin_default")}</Text>
          {session.offline ? null : (
            <Pressable onPress={() => router.push("/change-pin")} style={({ pressed }) => ({ paddingHorizontal: space[3], paddingVertical: space[2], borderRadius: 10, backgroundColor: pressed ? "#D97706" : "#F59E0B" })}>
              <Text style={{ color: "#ffffff", fontFamily: fonts.semibold, fontSize: 13 }}>{m("qch.set_pin")}</Text>
            </Pressable>
          )}
        </View>
      ) : null}

      {/* Today over all their lines. */}
      <Text style={{ fontFamily: fonts.bold, fontSize: 17, color: colors.heading ?? colors.foreground }}>{m("qch.today")}</Text>
      <View style={{ flexDirection: "row", gap: space[2] }}>
        {[
          [q("kpi.target"), num(total.target), "#3730A3", "#EEF2FF"],
          [q("kpi.checked"), num(total.checked), "#2563EB", "#EFF6FF"],
          [q("kpi.pass"), num(total.pass), "#16A34A", "#F0FDF4"],
          [m("tally.dhu_pct"), dhu.toFixed(1), dhu > 5 ? "#DC2626" : "#16A34A", dhu > 5 ? "#FEF2F2" : "#F0FDF4"],
        ].map(([label, value, fg, bg]) => (
          <View key={label} style={{ flex: 1, alignItems: "center", gap: 2, paddingVertical: space[3], borderRadius: 14, backgroundColor: bg }}>
            <Text style={{ fontFamily: fonts.bold, fontSize: 20, color: fg }} numberOfLines={1} adjustsFontSizeToFit>
              {value}
            </Text>
            <Text style={{ fontSize: 12, color: colors.foregroundMuted }} numberOfLines={1}>
              {label}
            </Text>
          </View>
        ))}
      </View>

      {/* A card per line: its job, how far it is, one button into the tally. */}
      <Text style={{ fontFamily: fonts.bold, fontSize: 17, color: colors.heading ?? colors.foreground }}>{m("home.lines")}</Text>
      {lines.length === 0 ? <Alert tone="warning">{m("home.no_lines")}</Alert> : null}
      {days.map(({ l, jobs, target, checked, pass, tags, bundles }) => {
        const look = SECTION[l.section] ?? SECTION.SEWING;
        const ratio = target > 0 ? Math.min(1, pass / target) : 0;
        const open = () => router.push({ pathname: "/tally", params: { line: `${l.section}:${l.line_id}` } });
        return (
          <View key={l.section + l.line_id} style={[{ flexDirection: "row", borderRadius: 18, backgroundColor: colors.surface, overflow: "hidden" }, shadow(colors)]}>
            <View style={{ width: 6, backgroundColor: look.color }} />
            <View style={{ flex: 1, padding: space[4], gap: space[3] }}>
              <Pressable onPress={open} style={{ flexDirection: "row", alignItems: "center", gap: space[3] }}>
                <TileIcon image={look.image} size={46} />
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={{ fontSize: 12, fontFamily: fonts.semibold, color: look.color }}>{q(`section.${l.section}`)}</Text>
                  <Text style={{ fontFamily: fonts.bold, fontSize: 18, color: colors.heading ?? colors.foreground }} numberOfLines={1}>
                    {(l.label ?? "").split(" — ").pop()}
                  </Text>
                  <Text style={{ fontSize: 12, color: colors.foregroundMuted }} numberOfLines={1}>
                    {l.section === "CUTTING" ? m("qch.bundles", { n: bundles ?? 0 }) : jobs.length ? jobs.map((x) => (x.job ? `${x.job.job_no} · ${x.job.style}` : "—")).join(", ") : m("tally.no_jobs")}
                  </Text>
                </View>
                <ChevronRight size={20} color={colors.foregroundSubtle} />
              </Pressable>

              {l.section === "CUTTING" ? null : (
                <View style={{ gap: space[1.5] ?? 6 }}>
                  <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
                    <Text style={{ fontSize: 12, color: colors.foregroundMuted }}>{m("qch.progress", { pass: num(pass), target: num(target) })}</Text>
                    <Text style={{ fontSize: 12, fontFamily: fonts.bold, color: look.color }}>{target > 0 ? `${Math.round(ratio * 100)}%` : "—"}</Text>
                  </View>
                  <View style={{ height: 8, borderRadius: 4, backgroundColor: colors.surfaceMuted, overflow: "hidden" }}>
                    <View style={{ width: `${ratio * 100}%`, height: 8, borderRadius: 4, backgroundColor: look.color }} />
                  </View>
                  <View style={{ flexDirection: "row", gap: space[2] }}>
                    {[
                      [q("kpi.checked"), num(checked)],
                      [q("kpi.pass"), num(pass)],
                      [m("tally.dhu_pct"), checked ? ((tags / checked) * 100).toFixed(1) : "0.0"],
                    ].map(([label, value]) => (
                      <View key={label} style={{ flex: 1, paddingVertical: 6, borderRadius: 10, backgroundColor: colors.surfaceMuted, alignItems: "center" }}>
                        <Text style={{ fontFamily: fonts.bold, fontSize: 15, color: colors.heading ?? colors.foreground }}>{value}</Text>
                        <Text style={{ fontSize: 11, color: colors.foregroundMuted }}>{label}</Text>
                      </View>
                    ))}
                  </View>
                </View>
              )}

              <Pressable onPress={open} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[2], minHeight: 48, borderRadius: 14, backgroundColor: pressed ? "#1D4ED8" : look.color })}>
                {l.section === "CUTTING" ? <Layers size={20} color="#ffffff" /> : <Play size={20} color="#ffffff" fill="#ffffff" />}
                <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: 16 }}>{m("tally.open")}</Text>
              </Pressable>
            </View>
          </View>
        );
      })}

      <SyncBar />

      {/* A shared phone: the next QC takes over from here. */}
      <View style={{ flexDirection: "row", gap: space[3] }}>
        <Button variant="outline" icon={Lock} onPress={lock} style={{ flex: 1 }}>
          {m("home.lock")}
        </Button>
        <Button variant="outline" icon={UserRoundCog} onPress={signOut} style={{ flex: 1 }}>
          {m("home.switch")}
        </Button>
      </View>
    </Screen>
  );
}
