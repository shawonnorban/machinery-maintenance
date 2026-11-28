/**
 * The reference screens' look (user, 2026-11-27: "100% as the pictures"):
 * a blue header that runs up under the status bar — the factory's photo
 * fading in on the right, a wave for its lower edge, the back arrow, the
 * page's icon, its name and the company, and on the right a date pill or
 * the bell — then white cards with round corners: a figure's tile (its icon
 * in a soft circle, the name, the number in its colour), a section's card
 * (blue icon, bold title, a "View Details →" link) and a status pill with
 * its dot.
 */
import { useCallback, useState } from "react";
import { Image, Pressable, View, useWindowDimensions } from "react-native";
import { router, useFocusEffect, useIsFocused } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Svg, { Defs, LinearGradient, Path, Rect, Stop } from "react-native-svg";
import { ArrowLeft, ArrowRight, Bell, CalendarDays, ChevronDown } from "lucide-react-native";
import { Text } from "./ui";
import { NotificationsPopup } from "./notify-popup";
import { TileIcon } from "./tile-icon";
import { t } from "../lib/i18n";
import { useSession } from "../lib/session";
import { fonts, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

/** The reference screens' corners — rounder than the rest of the app. */
export const ROUND = 18;
/** Every page's header under the status bar (the enterprise design, 2026-11-27). */
export const HERO_HEIGHT = 84;
/** The factory photo's width over its height (assets/factory.jpg, 1400 × 651). */
const PHOTO_RATIO = 1400 / 651;
/** Home's photo: the building's part of the same picture (assets/factory-home.jpg, 900 × 576). */
const HOME_PHOTO_RATIO = 900 / 576;

const WEEKDAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
/** "2026-11-27" → "Fri, Nov 27, 2026", as the pictures write a day. */
export function longDay(iso) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ""));
  if (!m) return "";
  const d = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));

  return `${WEEKDAYS[d.getUTCDay()]}, ${MONTHS[d.getUTCMonth()]} ${d.getUTCDate()}, ${m[1]}`;
}

/** The blue of the header with the photo and the wave: Home's tall one, every page's short one. */
export function HeroBackdrop({ height, imageWidth = "70%", wave = 34, fill, photo = "cover", top = 0 }) {
  const colors = useColors();
  // The reference screens' bright corporate blue (#1D4ED8 → #2563EB → lighter), not navy.
  const deep = colors.primaryDark;
  const mid = colors.primary;
  const light = "#5B9BF8";
  // `fit` (Home): the building's part of the photo, as tall as the header from under the status bar (`top`)
  // down to the wave — so it does not sink with blue above it (user, 2026-11-27) — and as wide as that makes it.
  const { width: screen } = useWindowDimensions();
  const fitHeight = height - top - wave * 0.55;
  const fitWidth = Math.min(fitHeight * HOME_PHOTO_RATIO, (screen * parseFloat(imageWidth)) / 100);

  return (
    <View style={{ position: "absolute", top: 0, left: 0, right: 0, height, backgroundColor: deep, overflow: "hidden" }} pointerEvents="none">
      {/* The factory (Norban's own photo, 2026-11-27) at its own proportions, anchored right. `fit` (Home, as its
          reference): the whole building, smaller, standing on the wave; `cover`: the header's height, the building's side. */}
      {photo === "fit" ? (
        <View style={{ position: "absolute", right: 0, bottom: wave * 0.55, width: fitWidth, height: fitHeight }}>
          <Image source={require("../../assets/factory-home.jpg")} style={{ width: "100%", height: "100%" }} resizeMode="cover" accessibilityIgnoresInvertColors />
          {/* Its top and left edges melt into the blue, as the reference's photo does. */}
          <Svg style={{ position: "absolute", top: 0, left: 0, right: 0, bottom: 0 }} width="100%" height="100%" preserveAspectRatio="none">
            <Defs>
              <LinearGradient id="photoLeft" x1="0" y1="0" x2="1" y2="0">
                <Stop offset="0" stopColor={mid} stopOpacity="1" />
                <Stop offset="0.38" stopColor={mid} stopOpacity="0" />
              </LinearGradient>
              <LinearGradient id="photoTop" x1="0" y1="0" x2="0" y2="1">
                <Stop offset="0" stopColor={light} stopOpacity="0.9" />
                <Stop offset="0.3" stopColor={light} stopOpacity="0" />
              </LinearGradient>
            </Defs>
            <Rect x="0" y="0" width="100%" height="100%" fill="url(#photoTop)" />
            <Rect x="0" y="0" width="100%" height="100%" fill="url(#photoLeft)" />
          </Svg>
        </View>
      ) : (
        <Image source={require("../../assets/factory.jpg")} style={{ position: "absolute", right: 0, top: 0, bottom: 0, aspectRatio: PHOTO_RATIO, minWidth: imageWidth }} resizeMode="cover" accessibilityIgnoresInvertColors />
      )}
      <Svg style={{ position: "absolute", top: 0, left: 0, right: 0, bottom: 0 }} width="100%" height="100%" preserveAspectRatio="none">
        <Defs>
          <LinearGradient id="heroFadeX" x1="0" y1="0" x2="1" y2="0">
            <Stop offset="0" stopColor={deep} stopOpacity="1" />
            <Stop offset={photo === "fit" ? "0.42" : "0.32"} stopColor={mid} stopOpacity="1" />
            <Stop offset={photo === "fit" ? "0.6" : "0.6"} stopColor={light} stopOpacity={photo === "fit" ? "0.3" : "0.7"} />
            <Stop offset="1" stopColor={light} stopOpacity={photo === "fit" ? "0" : "0.25"} />
          </LinearGradient>
          <LinearGradient id="heroFadeY" x1="0" y1="0" x2="0" y2="1">
            <Stop offset="0" stopColor={deep} stopOpacity="0.5" />
            <Stop offset="0.45" stopColor={deep} stopOpacity="0" />
          </LinearGradient>
        </Defs>
        <Rect x="0" y="0" width="100%" height="100%" fill="url(#heroFadeX)" />
        <Rect x="0" y="0" width="100%" height="100%" fill="url(#heroFadeY)" />
      </Svg>
      <Wave height={wave} fill={fill} />
    </View>
  );
}

/** The header's lower edge: the page's own colour cut as a wave, a light streak along it. */
function Wave({ height = 34, fill }) {
  const colors = useColors();

  return (
    <Svg style={{ position: "absolute", left: 0, right: 0, bottom: -1 }} width="100%" height={height} viewBox="0 0 400 34" preserveAspectRatio="none">
      <Path d="M0 22 C 110 36, 250 30, 400 2 L 400 34 L 0 34 Z" fill={fill ?? colors.background} />
      <Path d="M0 22 C 110 36, 250 30, 400 2" fill="none" stroke="#ffffff" strokeOpacity={0.35} strokeWidth={2} />
    </Svg>
  );
}

/**
 * A page's header: back (when there is somewhere to go back to), the page's
 * icon, its name and the company, and `right` (a date pill, the bell).
 */
export function PageHero({ title, subtitle, icon: Icon, image, onBack, right, fill }) {
  const insets = useSafeAreaInsets();
  const focused = useIsFocused();
  const { session } = useSession();
  const company = subtitle ?? session?.me?.company?.name ?? session?.profile?.company?.name ?? "";
  // 84dp under the status bar on every page (user, 2026-11-27), the wave its last 22.
  const height = insets.top + HERO_HEIGHT;

  return (
    <View style={{ height }}>
      {focused ? <StatusBar style="light" /> : null}
      <HeroBackdrop height={height} wave={22} fill={fill} />
      <View style={{ height: HERO_HEIGHT - 22, marginTop: insets.top, paddingHorizontal: space[4], flexDirection: "row", alignItems: "center", gap: space[3] }}>
        {onBack ? (
          <Pressable accessibilityRole="button" accessibilityLabel={t("mobile", "back")} onPress={onBack} hitSlop={12} style={{ width: 32, height: 40, justifyContent: "center", marginRight: -space[1] }}>
            <ArrowLeft size={24} color="#ffffff" />
          </Pressable>
        ) : null}
        {/* The module's own picture when it has one (user, 2026-11-28), else the white line icon. */}
        {image ? <TileIcon image={image} size={36} lift /> : Icon ? <Icon size={24} color="#ffffff" strokeWidth={2.2} /> : null}
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: 20, lineHeight: 26 }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8}>
            {title}
          </Text>
          {company ? (
            <Text style={{ color: "#e2ecff", fontSize: 12, lineHeight: 16 }} numberOfLines={1}>
              {company}
            </Text>
          ) : null}
        </View>
        {right}
      </View>
    </View>
  );
}

/** The dark pill on the header: a calendar and the day, or any choice with its chevron. */
export function HeroPill({ label, icon: Icon = CalendarDays, onPress, accessibilityLabel }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      onPress={onPress}
      disabled={!onPress}
      style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[1.5], height: 34, paddingHorizontal: space[3], borderRadius: 17, backgroundColor: pressed ? "rgba(7,31,69,0.8)" : "rgba(11,40,96,0.62)", borderWidth: 1, borderColor: "rgba(255,255,255,0.18)" })}
    >
      <Icon size={17} color="#ffffff" />
      <Text style={{ color: "#ffffff", fontFamily: fonts.semibold, fontSize: 13 }} numberOfLines={1}>
        {label}
      </Text>
      {onPress ? <ChevronDown size={16} color="#ffffff" /> : null}
    </Pressable>
  );
}

/** How many notifications are unread, read again each time the page shows (and on `refresh`). */
export function useUnreadCount() {
  const { call, session } = useSession();
  const [count, setCount] = useState(0);
  const email = session?.kind === "EMAIL";

  const refresh = useCallback(() => {
    if (!email) return;
    call("/notifications?per_page=1", { whole: true }).then((a) => setCount(a?.meta?.unread_count ?? 0), () => {});
  }, [call, email]);

  useFocusEffect(refresh);

  return [count, refresh];
}

/** The white bell with its small red count; a tap opens the latest ten in a popup (user, 2026-11-28). */
export function HeroBell({ size = 40 }) {
  const colors = useColors();
  const [unread, refresh] = useUnreadCount();
  const [open, setOpen] = useState(false);

  return (
    <>
      <Pressable accessibilityRole="button" accessibilityLabel={t("mobile", "act.title")} onPress={() => setOpen(true)} style={[{ width: size, height: size, borderRadius: size / 2, backgroundColor: "#ffffff", alignItems: "center", justifyContent: "center" }, shadow(colors, "md")]}>
        <Bell size={size * 0.46} color={colors.foreground} />
        {unread ? (
          <View style={{ position: "absolute", top: -4, right: -4, minWidth: 18, height: 18, paddingHorizontal: 3, borderRadius: 9, backgroundColor: "#ef4444", borderWidth: 1.5, borderColor: "#ffffff", alignItems: "center", justifyContent: "center" }}>
            <Text style={{ color: "#ffffff", fontSize: 10, lineHeight: 13, fontFamily: fonts.bold }}>{unread > 99 ? "99+" : unread}</Text>
          </View>
        ) : null}
      </Pressable>
      <NotificationsPopup open={open} onClose={() => setOpen(false)} onChanged={refresh} />
    </>
  );
}

/** A tone's colour: the palette's own (brand, success…) or any colour given as it is. */
export function toneColor(colors, tone) {
  return colors[tone] ?? tone ?? colors.brand;
}

/**
 * One figure, as the pictures' row of four: the icon in a soft circle of its
 * colour, the name, the number large in the colour (or `valueTone`), the unit
 * under it. `active` rings it (the list it filters is showing).
 */
export function KpiTile({ icon: Icon, image, label, value, unit, tone = "brand", valueTone, onPress, active = false, children }) {
  const colors = useColors();
  const fg = toneColor(colors, tone);
  const big = valueTone ? toneColor(colors, valueTone) : fg;
  const Box = onPress ? Pressable : View;

  return (
    <Box
      accessibilityRole={onPress ? "button" : undefined}
      onPress={onPress}
      style={[{ flex: 1, minWidth: 0, alignItems: "center", gap: 2, paddingVertical: space[3], paddingHorizontal: space[1], borderRadius: ROUND, backgroundColor: colors.surface, borderWidth: active ? 2 : 0, borderColor: fg }, shadow(colors)]}
    >
      {image ? (
        <TileIcon image={image} size={44} style={{ marginBottom: 2 }} />
      ) : (
        <View style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: `${fg}1f`, alignItems: "center", justifyContent: "center", marginBottom: 2 }}>
          <Icon size={24} color={fg} strokeWidth={2.4} />
        </View>
      )}
      <Text style={{ fontSize: 13, color: colors.foreground }} numberOfLines={1}>
        {label}
      </Text>
      <Text style={{ fontFamily: fonts.bold, fontSize: 21, lineHeight: 27, color: big }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.6}>
        {value ?? "—"}
      </Text>
      {unit ? (
        <Text style={{ fontSize: 12, color: colors.foregroundMuted }} numberOfLines={1}>
          {unit}
        </Text>
      ) : null}
      {children}
    </Box>
  );
}

/** A row of tiles, four across. */
export function KpiRow({ children }) {
  return <View style={{ flexDirection: "row", gap: space[2] }}>{children}</View>;
}

/** A section on its white card: blue icon, bold title, and its link with an arrow — or `right`. */
export function SectionCard({ icon: Icon, title, action, onAction, right, children, style, bodyStyle }) {
  const colors = useColors();

  return (
    <View style={[{ borderRadius: ROUND, backgroundColor: colors.surface, paddingVertical: space[4] }, shadow(colors), style]}>
      {title ? (
        <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], paddingHorizontal: space[4], marginBottom: space[3] }}>
          {Icon ? <Icon size={22} color={colors.brand} strokeWidth={2.4} /> : null}
          <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 17 }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8}>
            {title}
          </Text>
          {right}
          {action ? (
            <Pressable onPress={onAction} hitSlop={10} accessibilityRole="link" style={{ flexDirection: "row", alignItems: "center", gap: space[1] }}>
              <Text style={{ color: colors.brand, fontFamily: fonts.semibold, fontSize: 14 }}>{action}</Text>
              <ArrowRight size={17} color={colors.brand} />
            </Pressable>
          ) : null}
        </View>
      ) : null}
      <View style={[{ paddingHorizontal: space[4], gap: space[3] }, bodyStyle]}>{children}</View>
    </View>
  );
}

/** A section's title on the page itself (not on a card): blue icon, bold title, its link. */
export function SectionTitle({ icon: Icon, title, action, onAction }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], marginTop: space[1] }}>
      {Icon ? <Icon size={22} color={colors.brand} strokeWidth={2.4} /> : null}
      <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 18 }} numberOfLines={1}>
        {title}
      </Text>
      {action ? (
        <Pressable onPress={onAction} hitSlop={10} accessibilityRole="link" style={{ flexDirection: "row", alignItems: "center", gap: space[1] }}>
          <Text style={{ color: colors.brand, fontFamily: fonts.semibold, fontSize: 14 }}>{action}</Text>
          <ArrowRight size={17} color={colors.brand} />
        </Pressable>
      ) : null}
    </View>
  );
}

/** A status as the pictures draw it: a soft pill of its colour, a dot (or `icon`) and the word. */
export function StatusPill({ tone = "brand", label, icon: Icon, size = "md" }) {
  const colors = useColors();
  const fg = toneColor(colors, tone);
  const small = size === "sm";

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 6, alignSelf: "flex-start", paddingHorizontal: small ? 8 : 12, height: small ? 24 : 30, borderRadius: 15, backgroundColor: `${fg}1f` }}>
      {Icon ? <Icon size={small ? 13 : 16} color={fg} strokeWidth={2.6} /> : <View style={{ width: small ? 7 : 9, height: small ? 7 : 9, borderRadius: 5, backgroundColor: fg }} />}
      <Text style={{ color: fg, fontFamily: fonts.semibold, fontSize: small ? 12 : 14 }} numberOfLines={1}>
        {label}
      </Text>
    </View>
  );
}

/** An icon in a soft circle of its colour (a list row's, an activity's). */
export function IconDisc({ icon: Icon, image, tone = "brand", size = 52 }) {
  const colors = useColors();
  const fg = toneColor(colors, tone);
  if (image) return <TileIcon image={image} size={size} />;

  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: `${fg}1f`, alignItems: "center", justifyContent: "center" }}>
      <Icon size={size * 0.48} color={fg} strokeWidth={2.2} />
    </View>
  );
}
