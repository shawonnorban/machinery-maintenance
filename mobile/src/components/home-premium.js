/**
 * Home as the reference screen (user, 2026-11-27): a blue header the width
 * of the phone — the time of day, the name large with a wave, the company,
 * the factory's photo fading in on the right, the bell — then the factory
 * card, the tiles on one white card (rounded-square glossy icons), Today's
 * Overview as soft cards with a wave, and the recent activities as a
 * timeline. Corners here are the reference's, rounder than the rest.
 */
import { useCallback, useState } from "react";
import { Pressable, View, useWindowDimensions } from "react-native";
import { router, useFocusEffect, useIsFocused } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Svg, { ClipPath, Defs, G, Image as SvgImage, LinearGradient, Mask, Path, Rect, Stop } from "react-native-svg";
import { ArrowRight, Check, ChevronDown, CircleCheck, Factory, Gauge, Target, TriangleAlert } from "lucide-react-native";
import { Alert, Text } from "./ui";
import { Option, Sheet } from "./tally";
import { ActivityTimeline } from "./activity";
import { HeroBell } from "./hero";
import { q, todayDhaka, useLoad } from "./manage";
import { t } from "../lib/i18n";
import { chooseFactory, useFactory } from "../lib/factory";
import { useSession } from "../lib/session";
import { accents, fonts, radius, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const hm = (key, params) => t("mobile", `home.${key}`, params);
/** The reference screen's own corners. */
export const ROUND = 18;

function partOfDay() {
  const hour = new Date().getHours();

  return hour < 12 ? "morning" : hour < 17 ? "afternoon" : "evening";
}

/**
 * The status bar's strip in the header's blue, laid over Home once its header
 * has scrolled away (user, 2026-11-27: at the top the header's own photo and
 * blue run up under the clock, with no strip of another colour).
 */
export function HomeTopInset({ shown }) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  // Light only while Home shows; a white screen pushed over it gets the dark clock back.
  const focused = useIsFocused();

  return (
    <View pointerEvents="none" style={{ position: "absolute", top: 0, left: 0, right: 0, zIndex: 10, height: insets.top, backgroundColor: shown ? colors.primaryDark : "transparent" }}>
      {focused ? <StatusBar style="light" /> : null}
    </View>
  );
}

/** Home's header under the status bar (the reference screen, 2026-11-27). */
const HOME_HERO = 158;
/** How far Home must scroll before the strip shows: past most of the header. */
export const HERO_SCROLLED = 130;
/** Home's photo: the building's part of the factory picture (assets/factory-home.jpg, 900 × 576). */
const HOME_PHOTO_RATIO = 900 / 576;
/** The light blue the header's curve opens onto, fading into the page below. */
const TINT = "#DCE7FD";

/**
 * The header, as the reference screen draws it: corporate blue from the left
 * lightening to sky blue on the right with a pale streak across the top
 * corner; its lower edge a curve that sits higher on the left and runs down
 * to the right, where the factory stands on it — the building over the curve,
 * not cut by it — and below, the page starts light blue and fades to grey.
 * The greeting light, the name large, the company in pale blue; the bell
 * level with the greeting. Laid in Home's scroll, so it scrolls away.
 */
export function HomeHero() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const { session } = useSession();
  const { user, company } = session.me;
  const height = insets.top + HOME_HERO;
  // The building a little smaller than the header, standing on the curve with sky above it (the reference).
  const photoH = Math.round(HOME_HERO * 0.82);
  const photoW = Math.min(photoH * HOME_PHOTO_RATIO, width * 0.6);
  const photoX = width - photoW;
  const photoY = height - photoH;
  // The header's shape: its lower edge higher on the left, running down to the right (the reference).
  const edge = `M0 ${height - 34} C ${width * 0.45} ${height - 14}, ${width * 0.75} ${height - 4}, ${width} ${height}`;
  const shape = `M0 0 H${width} V${height} C ${width * 0.75} ${height - 4}, ${width * 0.45} ${height - 14}, 0 ${height - 34} Z`;
  const deep = colors.primaryDark;
  const mid = colors.primary;
  const sky = "#8DB6FB";

  return (
    <View style={{ marginHorizontal: -space[4], marginTop: -space[4], height }}>
      {/* The page under the header: light blue fading to the page's grey, behind the cards that follow. */}
      <Svg style={{ position: "absolute", left: 0, right: 0, top: height - 2 }} width="100%" height={320} preserveAspectRatio="none" pointerEvents="none">
        <Defs>
          <LinearGradient id="homeTint" x1="0" y1="0" x2="0" y2="1">
            <Stop offset="0" stopColor={TINT} stopOpacity="1" />
            <Stop offset="1" stopColor={colors.page} stopOpacity="1" />
          </LinearGradient>
        </Defs>
        <Rect x="0" y="0" width="100%" height="100%" fill="url(#homeTint)" />
      </Svg>

      {/* The blue, its pale streak, the factory standing at the right cut along the same curve, and the curve's light edge. */}
      <Svg style={{ position: "absolute", top: 0, left: 0 }} width={width} height={height} pointerEvents="none">
        <Defs>
          <LinearGradient id="homeBlue" x1="0" y1="0" x2="1" y2="0.35">
            <Stop offset="0" stopColor={deep} />
            <Stop offset="0.45" stopColor={mid} />
            <Stop offset="1" stopColor={sky} />
          </LinearGradient>
          <LinearGradient id="homeStreak" x1="0" y1="0" x2="1" y2="1">
            <Stop offset="0" stopColor="#ffffff" stopOpacity="0" />
            <Stop offset="0.5" stopColor="#ffffff" stopOpacity="0.28" />
            <Stop offset="1" stopColor="#ffffff" stopOpacity="0" />
          </LinearGradient>
          {/* Opaque at the building's foot on the right, fading out to the top and to the left. */}
          <LinearGradient id="homeFadeLeft" x1={photoX} y1="0" x2={photoX + photoW * 0.45} y2="0" gradientUnits="userSpaceOnUse">
            <Stop offset="0" stopColor="#ffffff" stopOpacity="0" />
            <Stop offset="1" stopColor="#ffffff" stopOpacity="1" />
          </LinearGradient>
          <LinearGradient id="homeFadeTop" x1="0" y1={photoY} x2="0" y2={photoY + photoH * 0.5} gradientUnits="userSpaceOnUse">
            <Stop offset="0" stopColor="#ffffff" stopOpacity="0" />
            <Stop offset="1" stopColor="#ffffff" stopOpacity="1" />
          </LinearGradient>
          <Mask id="homeMaskLeft" maskUnits="userSpaceOnUse" x="0" y="0" width={width} height={height}>
            <Rect x={photoX} y={photoY} width={photoW} height={photoH} fill="url(#homeFadeLeft)" />
          </Mask>
          <Mask id="homeMaskTop" maskUnits="userSpaceOnUse" x="0" y="0" width={width} height={height}>
            <Rect x={photoX} y={photoY} width={photoW} height={photoH} fill="url(#homeFadeTop)" />
          </Mask>
          <ClipPath id="homeShape">
            <Path d={shape} />
          </ClipPath>
        </Defs>
        <Path d={shape} fill="url(#homeBlue)" />
        <Path d={`M${width * 0.55} 0 L${width * 0.75} 0 L${width} ${height * 0.42} L${width} ${height * 0.6} Z`} fill="url(#homeStreak)" />
        <G clipPath="url(#homeShape)">
          <G mask="url(#homeMaskLeft)">
            <G mask="url(#homeMaskTop)">
              <SvgImage href={require("../../assets/factory-home.jpg")} x={photoX} y={photoY} width={photoW} height={photoH} preserveAspectRatio="xMidYMid slice" />
            </G>
          </G>
        </G>
        <Path d={edge} fill="none" stroke="#ffffff" strokeOpacity={0.55} strokeWidth={1.5} />
      </Svg>

      <View style={{ paddingTop: insets.top + 22, paddingLeft: space[5], paddingRight: space[4], flexDirection: "row", alignItems: "flex-start" }}>
        <View style={{ flex: 1, maxWidth: "62%" }}>
          <Text style={{ color: "#ffffff", fontSize: 22, lineHeight: 28, fontFamily: fonts.regular }}>{`${hm(`part_${partOfDay()}`)},`}</Text>
          <Text style={{ color: "#ffffff", fontSize: 34, lineHeight: 42, fontFamily: fonts.bold }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.6}>
            {`${(user?.name ?? "").split(/\s+/)[0]} 👋`}
          </Text>
          <Text style={{ color: "#C9DBFF", fontSize: 15, lineHeight: 20 }} numberOfLines={1}>
            {company?.name}
          </Text>
        </View>
        <View style={{ marginLeft: "auto", marginTop: 2 }}>
          <HeroBell size={44} />
        </View>
      </View>
    </View>
  );
}

/** The factory card under the header: all, or one, chosen from a sheet. */
export function FactoryCard() {
  const colors = useColors();
  const { session } = useSession();
  const factories = session.me.factories ?? [];
  const factory = useFactory(factories);
  const [picking, setPicking] = useState(false);
  if (!factories.length) return null;

  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={hm("factory")}
        onPress={() => setPicking(true)}
        style={({ pressed }) => [{ marginTop: -12, flexDirection: "row", alignItems: "center", gap: space[3], paddingVertical: space[2], paddingHorizontal: space[3], borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, opacity: pressed ? 0.9 : 1 }, shadow(colors, "md")]}
      >
        <View style={{ width: 40, height: 40, borderRadius: 20, backgroundColor: colors.brandSubtle, alignItems: "center", justifyContent: "center" }}>
          <Factory size={20} color={colors.brand} />
        </View>
        <View style={{ flex: 1, gap: 1 }}>
          <Text style={{ fontFamily: fonts.bold, fontSize: 15, lineHeight: 20, color: colors.heading }} numberOfLines={1}>
            {factory ? factory.name : hm("all_factories_title")}
          </Text>
          <Text tone="foregroundMuted" style={{ fontSize: 12, lineHeight: 16 }} numberOfLines={1}>
            {factory ? hm("one_factory") : hm("every_factory")}
          </Text>
        </View>
        <ChevronDown size={20} color={colors.brand} />
      </Pressable>
      <Sheet open={picking} onClose={() => setPicking(false)} title={hm("factory")} hint={hm("factory_hint")}>
        {[{ id: null, name: hm("all_factories", { n: factories.length }) }, ...factories].map((f) => (
          <Option
            key={f.id ?? "all"}
            title={f.name}
            sub={f.code}
            on={(factory?.id ?? null) === f.id}
            onPress={() => {
              chooseFactory(f.id);
              setPicking(false);
            }}
          />
        ))}
      </Sheet>
    </>
  );
}

/** A section's title with the blue bar before it, and its link with an arrow. */
export function HomeSection({ title, action, onAction }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], marginTop: space[2] }}>
      <View style={{ width: 4, height: 22, borderRadius: 2, backgroundColor: colors.brand }} />
      <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 19 }} numberOfLines={1}>
        {title}
      </Text>
      {action ? (
        <Pressable onPress={onAction} hitSlop={10} accessibilityRole="link" style={{ flexDirection: "row", alignItems: "center", gap: space[1] }}>
          <Text variant="label" tone="brand" style={{ fontFamily: fonts.semibold }}>
            {action}
          </Text>
          <ArrowRight size={17} color={colors.brand} />
        </Pressable>
      ) : null}
    </View>
  );
}

/** One of the day's figures: its icon in a soft circle, the name, the number in its colour, a wave in the corner. */
function KpiCard({ label, value, unit, icon: Icon, tone, children }) {
  const colors = useColors();
  const fg = colors[tone] ?? accents[tone] ?? colors.brand;

  return (
    <View style={[{ flexBasis: "46%", flexGrow: 1, minHeight: 88, borderRadius: ROUND, backgroundColor: colors.surface, overflow: "hidden" }, shadow(colors)]}>
      <Svg style={{ position: "absolute", right: 0, bottom: 0 }} width={130} height={70} viewBox="0 0 130 70">
        <Path d="M0 70 C 40 66, 60 40, 90 30 C 108 24, 120 10, 130 0 L 130 70 Z" fill={fg} fillOpacity={0.1} />
        <Path d="M30 70 C 62 64, 84 48, 104 40 C 116 35, 124 26, 130 18 L 130 70 Z" fill={fg} fillOpacity={0.08} />
      </Svg>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], padding: space[3] }}>
        <View style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: `${fg}1f`, alignItems: "center", justifyContent: "center" }}>
          <Icon size={22} color={fg} />
        </View>
        <View style={{ flex: 1, gap: 2 }}>
          <Text style={{ fontSize: 12, color: colors.foregroundMuted }} numberOfLines={1}>
            {label}
          </Text>
          <Text style={{ fontFamily: fonts.bold, fontSize: 22, lineHeight: 28, color: fg }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.6}>
            {value ?? "—"}
            {value !== null && unit ? <Text style={{ fontSize: 12, color: fg, fontFamily: fonts.semibold }}>{` ${unit}`}</Text> : null}
          </Text>
          {children}
        </View>
      </View>
    </View>
  );
}

const n0 = (v) => Number(v ?? 0).toLocaleString("en-US");
const DHU_STATE = (dhu) => (dhu === null || dhu === undefined ? null : dhu <= 3 ? ["good", "success"] : dhu <= 5 ? ["watch", "warning"] : ["high", "danger"]);

/** Today in the chosen section. */
export function TodayOverview({ section }) {
  const colors = useColors();
  const load = useLoad(`/quality-tally/dashboard?section=${section}&date=${todayDhaka()}`, [section]);
  const { data, error, reload } = load;
  const tot = data?.sections?.[section] ?? null;
  const progress = tot?.achievement ?? null;
  const dhu = tot?.dhu ?? null;
  const state = DHU_STATE(dhu);

  useFocusEffect(
    useCallback(() => {
      reload();
    }, [reload]),
  );

  return (
    <>
      <HomeSection title={hm("overview_title")} action={hm("details")} onAction={() => router.navigate("/production")} />
      {error && !data ? (
        <Pressable onPress={reload}>
          <Alert>{error}</Alert>
        </Pressable>
      ) : null}
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[3] }}>
        <KpiCard label={q("kpi.target")} value={tot ? n0(tot.target) : null} unit={hm("pcs")} icon={Target} tone="brand" />
        <KpiCard label={hm("passed")} value={tot ? n0(tot.pass) : null} unit={hm("pcs")} icon={CircleCheck} tone="success" />
        <KpiCard label={hm("progress")} value={progress === null ? null : String(Math.round(progress))} unit="%" icon={Gauge} tone="purple">
          <View style={{ height: 4, borderRadius: 2, backgroundColor: colors.surfaceMuted, overflow: "hidden", marginTop: 2 }}>
            <View style={{ width: `${Math.max(0, Math.min(100, progress ?? 0))}%`, height: 4, backgroundColor: accents.purple }} />
          </View>
        </KpiCard>
        <KpiCard label={q("kpi.dhu")} value={dhu === null ? null : Number(dhu).toFixed(1)} unit="%" icon={TriangleAlert} tone={state?.[1] ?? "warning"}>
          {state ? (
            <Text variant="small" style={{ fontSize: 12, color: colors[state[1]], fontFamily: fonts.semibold }}>
              {`● ${hm(`dhu_${state[0]}`)}`}
            </Text>
          ) : null}
        </KpiCard>
      </View>
    </>
  );
}

/** The latest activities, and the way to all of them. */
export function RecentActivities() {
  const { call } = useSession();
  const [items, setItems] = useState(null);
  const [error, setError] = useState(null);

  useFocusEffect(
    useCallback(() => {
      call("/notifications?per_page=5").then(
        (d) => {
          setItems(d);
          setError(null);
        },
        (e) => setError(e.message),
      );
    }, [call]),
  );

  return (
    <>
      <HomeSection title={hm("recent")} action={hm("view_all")} onAction={() => router.push("/notifications")} />
      {error && !items ? <Alert tone="warning">{error}</Alert> : null}
      {items && items.length === 0 ? (
        <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], padding: space[3] }}>
          <Check size={18} color="#16a34a" />
          <Text tone="foregroundMuted">{hm("nothing_new")}</Text>
        </View>
      ) : null}
      {items?.length ? <ActivityTimeline cards items={items} onRead={(id) => setItems((list) => list.map((n) => (n.id === id ? { ...n, is_read: true } : n)))} /> : null}
      {items === null && !error ? <Text tone="foregroundMuted">{t("mobile", "mgr.loading")}</Text> : null}
    </>
  );
}

/** The tiles on one white card, as the reference screen. */
export function TilesCard({ children }) {
  const colors = useColors();

  return <View style={[{ flexDirection: "row", flexWrap: "wrap", rowGap: space[3], paddingVertical: space[3], paddingHorizontal: space[1], borderRadius: ROUND, backgroundColor: colors.surface }, shadow(colors)]}>{children}</View>;
}

export { radius };
