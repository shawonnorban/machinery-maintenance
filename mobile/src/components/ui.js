/**
 * The app's component kit: every screen is built from these, with the web
 * kit's names (Button, Card, Badge, Alert). A screen styling its own button is
 * a defect. More pieces join as the phases need them.
 */
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { ActivityIndicator, Animated, KeyboardAvoidingView, Modal, Pressable, ScrollView, StyleSheet, Text as RNText, TextInput, View, useWindowDimensions } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { Check, CheckCircle2, ChevronDown, ChevronLeft, ChevronRight, Delete, Eye, EyeOff, Info, Search, TriangleAlert, X, XCircle } from "lucide-react-native";
import { TileIcon } from "./tile-icon";
import Svg, { Circle, Defs, Ellipse, LinearGradient as SvgLinearGradient, Rect, Stop } from "react-native-svg";
import { currentLocale } from "../lib/i18n";
import { control, fontWeights, fonts, radius, space, touch, type } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const BENGALI = { [fonts.regular]: fonts.bengali, [fonts.medium]: fonts.bengali, [fonts.semibold]: fonts.bengaliSemibold, [fonts.bold]: fonts.bengaliSemibold };
export const face = (family) => (currentLocale() === "bn" ? (BENGALI[family] ?? family ?? fonts.bengali) : family);

/** A face and its weight, for the few places that draw text without Text. */
export const faceStyle = (family) => ({ fontFamily: face(family), fontWeight: fontWeights[face(family)] ?? "400" });

/** Every word on screen; Bangla is set in Anek Bangla, as on the web. */
const HEADINGS = new Set(["pageTitle", "sectionTitle", "cardTitle", "figure"]);

export function Text({ variant = "body", tone = "foreground", style, children, ...props }) {
  const colors = useColors();
  const base = type[variant] ?? type.body;
  // The face after any style given (a button's semibold), in the language shown, with its weight.
  const given = StyleSheet.flatten(style) ?? {};
  const family = face(given.fontFamily ?? base.fontFamily);

  // Titles and figures in the heading navy (the design standard), unless a tone is asked for.
  const color = tone === "foreground" && HEADINGS.has(variant) ? colors.heading : colors[tone] ?? tone;

  return (
    <RNText {...props} style={[base, { color }, style, { fontFamily: family, fontWeight: given.fontWeight ?? fontWeights[family] ?? "400" }]}>
      {children}
    </RNText>
  );
}

/**
 * A screen: the background, the safe area, a scroll above the keyboard, and a
 * phone's width even on a tablet. `tab`: one of the bottom bar's screens — its
 * header (the AppBar) draws under the status bar, the bottom bar keeps the
 * phone's bottom edge.
 */
/**
 * A page: its header, then what scrolls. `bleed` (the reference screens'
 * blue header, 2026-11-27): the header itself runs up under the status bar,
 * so the screen keeps no top inset of its own. `onScroll` hears the scroll.
 */
export function Screen({ children, header, width = 480, tab = false, bleed = false, onScroll }) {
  const colors = useColors();

  return (
    <SafeAreaView edges={tab ? [] : bleed ? ["bottom"] : ["top", "bottom"]} style={{ flex: 1, backgroundColor: colors.background }}>
      {header}
      <KeyboardAvoidingView behavior="padding" style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={{ flexGrow: 1, alignItems: "center", padding: space[4] }} keyboardShouldPersistTaps="handled" onScroll={onScroll} scrollEventThrottle={onScroll ? 32 : undefined}>
          <View style={{ width: "100%", maxWidth: width, gap: space[4] }}>{children}</View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

/** A screen's top: back, its name, and what else it needs. */
export function Header({ title, onBack, backLabel, children }) {
  const colors = useColors();

  return (
    <View style={{ minHeight: 56, flexDirection: "row", alignItems: "center", paddingHorizontal: space[2], gap: space[1], backgroundColor: colors.surface, borderBottomWidth: 1, borderBottomColor: colors.border }}>
      {onBack ? <IconButton icon={ChevronLeft} label={backLabel} onPress={onBack} /> : <View style={{ width: space[2] }} />}
      <Text variant="sectionTitle" numberOfLines={1} style={{ flex: 1 }}>
        {title}
      </Text>
      {children}
    </View>
  );
}

export function IconButton({ icon: Icon, label, onPress, tone = "foregroundMuted" }) {
  const colors = useColors();

  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} style={({ pressed }) => ({ width: touch, height: touch, borderRadius: radius.full, alignItems: "center", justifyContent: "center", backgroundColor: pressed ? colors.surfaceMuted : "transparent" })}>
      <Icon size={22} color={colors[tone]} />
    </Pressable>
  );
}

/** A labelled field; `secure` gets its own show/hide. */
export function Input({ label, icon: Icon, error, hint, secure = false, inputRef, style, ...props }) {
  const colors = useColors();
  const [focused, setFocused] = useState(false);
  const [hidden, setHidden] = useState(secure);
  const Eyes = hidden ? Eye : EyeOff;

  return (
    <View style={[{ gap: space[1] }, style]}>
      {label ? (
        <Text variant="label" tone="foregroundMuted">
          {label}
        </Text>
      ) : null}
      {/* Half the old height (user, 2026-11-27): 28dp, the border thicker when focused without changing it. */}
      <View style={{ minHeight: 28, flexDirection: "row", alignItems: props.multiline ? "flex-start" : "center", gap: space[1.5], paddingLeft: space[2], borderRadius: radius.md, borderWidth: focused || error ? 1.5 : 1, borderColor: error ? colors.danger : focused ? colors.brand : colors.borderStrong, backgroundColor: colors.surface }}>
        {Icon ? <Icon size={15} color={colors.foregroundSubtle} /> : null}
        <TextInput
          {...props}
          ref={inputRef}
          accessibilityLabel={label}
          secureTextEntry={hidden}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          placeholderTextColor={colors.foregroundSubtle}
          autoCorrect={false}
          style={{ flex: 1, minHeight: props.multiline ? 70 : 26, paddingVertical: props.multiline ? space[2] : 0, textAlignVertical: props.multiline ? "top" : "center", color: colors.foreground, fontFamily: fonts.medium, fontSize: 13 }}
        />
        {secure ? (
          <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={() => setHidden((h) => !h)} hitSlop={10} style={{ paddingHorizontal: space[2], height: 26, justifyContent: "center" }}>
            <Eyes size={16} color={colors.foregroundMuted} />
          </Pressable>
        ) : (
          <View style={{ width: space[2] }} />
        )}
      </View>
      {error ? (
        <Text variant="small" tone="danger">
          {error}
        </Text>
      ) : hint ? (
        <Text variant="small" tone="foregroundMuted">
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

/** Six dots and a number pad: a PIN is tapped with a thumb, the keyboard never covers it. */
export function PinPad({ value, onChange, disabled = false, deleteLabel, length = 6 }) {
  const colors = useColors();
  // Thumbs are faster than a redraw: the next key adds to what was pressed, not to what is drawn.
  const typed = useRef(value);
  // Every time: a PIN cleared before it was drawn is the same empty value as before.
  useLayoutEffect(() => {
    typed.current = value;
  });
  const press = (key) => {
    if (disabled || (key !== "<" && typed.current.length >= length)) return;
    typed.current = key === "<" ? typed.current.slice(0, -1) : typed.current + key;
    onChange(typed.current);
  };

  return (
    <View style={{ alignItems: "center", gap: space[5] }}>
      <View style={{ flexDirection: "row", gap: space[4] }} accessibilityLabel={`${value.length}/${length}`}>
        {Array.from({ length }, (_, i) => (
          <View key={i} style={{ width: 16, height: 16, borderRadius: radius.full, borderWidth: 2, borderColor: i < value.length ? colors.brand : colors.borderStrong, backgroundColor: i < value.length ? colors.brand : "transparent" }} />
        ))}
      </View>
      <View style={{ width: "100%", maxWidth: 320, flexDirection: "row", flexWrap: "wrap", justifyContent: "center" }}>
        {["1", "2", "3", "4", "5", "6", "7", "8", "9", "", "0", "<"].map((key, i) => (
          <View key={i} style={{ width: "33.33%", alignItems: "center", paddingVertical: space[1.5] }}>
            {key === "" ? null : (
              <Pressable
                accessibilityRole="button"
                accessibilityLabel={key === "<" ? deleteLabel : key}
                disabled={disabled}
                onPress={() => press(key)}
                style={({ pressed }) => ({ width: 72, height: 64, borderRadius: radius.lg, alignItems: "center", justifyContent: "center", backgroundColor: pressed ? colors.brandSubtle : key === "<" ? "transparent" : colors.surface, borderWidth: key === "<" ? 0 : 1, borderColor: colors.border, opacity: disabled ? 0.5 : 1 })}
              >
                {key === "<" ? <Delete size={26} color={colors.foregroundMuted} /> : <RNText style={{ ...faceStyle(fonts.semibold), fontSize: 26, color: colors.foreground }}>{key}</RNText>}
              </Pressable>
            )}
          </View>
        ))}
      </View>
    </View>
  );
}

/** A short choice: a QC who signed in here lately. */
export function Chip({ children, onPress, on = false }) {
  const colors = useColors();

  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={{ minHeight: 34, paddingHorizontal: space[3], borderRadius: radius.md, borderWidth: 1, borderColor: on ? colors.brand : colors.border, backgroundColor: on ? colors.brandSubtle : colors.surface, justifyContent: "center" }}>
      <Text variant="small" style={{ color: on ? colors.brand : colors.foreground, fontFamily: face(fonts.semibold) }}>
        {children}
      </Text>
    </Pressable>
  );
}

/** A white surface: rounded, borderless, lifted by a soft shadow; pressable when it opens something. */
export function Card({ children, style, onPress }) {
  const colors = useColors();
  const look = [{ backgroundColor: colors.surface, borderRadius: radius.lg, padding: space[4], gap: space[3] }, shadow(colors), style];

  return onPress ? (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => [look, { opacity: pressed ? 0.85 : 1 }]}>
      {children}
    </Pressable>
  ) : (
    <View style={look}>{children}</View>
  );
}

const BUTTONS = {
  primary: (c) => ({ bg: c.brand, fg: c.brandForeground, border: c.brand }),
  outline: (c) => ({ bg: c.surface, fg: c.foreground, border: c.borderStrong }),
  danger: (c) => ({ bg: c.danger, fg: "#ffffff", border: c.danger }),
};

export function Button({ children, onPress, variant = "primary", icon: Icon, loading = false, disabled = false, style }) {
  const colors = useColors();
  const look = (BUTTONS[variant] ?? BUTTONS.primary)(colors);
  const off = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: off, busy: loading }}
      disabled={off}
      onPress={onPress}
      style={({ pressed }) => [
        { minHeight: control, paddingHorizontal: space[4], paddingVertical: space[1.5], borderRadius: radius.md, borderWidth: 1, borderColor: look.border, backgroundColor: look.bg, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[2], opacity: off ? 0.5 : pressed ? 0.85 : 1 },
        variant === "outline" ? null : shadow(colors),
        style,
      ]}
    >
      {loading ? <ActivityIndicator color={look.fg} size="small" /> : Icon ? <Icon size={17} color={look.fg} /> : null}
      <Text variant="label" style={{ color: look.fg, fontFamily: face(fonts.semibold) }}>
        {children}
      </Text>
    </Pressable>
  );
}

export function toneColors(colors, tone) {
  return (
    { brand: [colors.brandSubtle, colors.brand], success: [colors.successSubtle, colors.success], warning: [colors.warningSubtle, colors.warning], danger: [colors.dangerSubtle, colors.danger], info: [colors.infoSubtle, colors.info] }[tone] ?? [colors.surfaceMuted, colors.foregroundMuted]
  );
}

export function Badge({ children, tone = "neutral" }) {
  const [bg, fg] = toneColors(useColors(), tone);

  return (
    <View style={{ alignSelf: "flex-start", backgroundColor: bg, borderRadius: radius.full, paddingHorizontal: space[3], paddingVertical: space[1] }}>
      <Text variant="small" style={{ color: fg, fontFamily: face(fonts.semibold) }}>
        {children}
      </Text>
    </View>
  );
}

const ICONS = { info: Info, success: CheckCircle2, warning: TriangleAlert, danger: XCircle };

export function Alert({ children, tone = "danger" }) {
  const [bg, fg] = toneColors(useColors(), tone);
  const Icon = ICONS[tone] ?? Info;

  return (
    <View accessibilityRole="alert" style={{ flexDirection: "row", gap: space[3], backgroundColor: bg, borderRadius: radius.md, padding: space[4] }}>
      <Icon size={20} color={fg} />
      <Text style={{ flex: 1, color: fg }}>{children}</Text>
    </View>
  );
}

/** A label and its value on one line. */
export function DetailRow({ label, value }) {
  return (
    <View style={{ flexDirection: "row", justifyContent: "space-between", gap: space[4] }}>
      <Text variant="small" tone="foregroundMuted">
        {label}
      </Text>
      <Text variant="label" style={{ flexShrink: 1, textAlign: "right" }}>
        {value}
      </Text>
    </View>
  );
}

/** One of a few: language, light or dark. */
export function Segmented({ value, onChange, options }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", backgroundColor: colors.surfaceMuted, borderRadius: radius.md, padding: 3, gap: 3 }}>
      {options.map((o) => {
        const on = o.value === value;

        return (
          <Pressable
            key={o.value}
            accessibilityRole="button"
            accessibilityState={{ selected: on }}
            onPress={() => onChange(o.value)}
            style={{ flex: 1, minHeight: 32, flexDirection: "row", gap: 5, borderRadius: radius.sm, alignItems: "center", justifyContent: "center", backgroundColor: on ? colors.surface : "transparent" }}
          >
            {o.icon ? <o.icon size={15} color={on ? colors.brand : colors.foregroundMuted} /> : null}
            <Text variant="label" style={{ fontSize: 13, color: on ? colors.brand : colors.foregroundMuted, fontFamily: o.value === "bn" ? fonts.bengaliSemibold : face(fonts.semibold) }}>
              {o.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

/** A feature on Home: its icon in its own colour on a soft square of it, four to a row. */
export function FeatureTile({ icon: Icon, image, label, color, onPress, badge, square = false }) {
  const colors = useColors();

  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} style={({ pressed }) => ({ width: "25%", alignItems: "center", gap: space[1.5], paddingVertical: space[1], opacity: pressed ? 0.7 : 1 })}>
      <View>
        {image ? (
          // A picture tile (user, 2026-11-28): the drawn icon on its own rounded square.
          <TileIcon image={image} size={58} lift />
        ) : (
          <GlossyIcon icon={Icon} color={color} square={square} size={square ? 58 : 54} />
        )}
        {badge ? (
          <View style={{ position: "absolute", top: -6, right: -6, minWidth: 22, height: 22, paddingHorizontal: 5, borderRadius: radius.full, backgroundColor: colors.danger, borderWidth: 2, borderColor: colors.background, alignItems: "center", justifyContent: "center" }}>
            <Text style={{ color: "#ffffff", fontSize: 11, lineHeight: 14, fontFamily: fonts.bold }}>{badge > 99 ? "99+" : badge}</Text>
          </View>
        ) : null}
      </View>
      <Text variant="small" style={{ textAlign: "center", alignSelf: "stretch", fontSize: 12, lineHeight: 16, paddingHorizontal: 2, fontFamily: square ? fonts.semibold : undefined, color: square ? colors.heading : colors.foreground }} numberOfLines={2}>
        {label}
      </Text>
    </Pressable>
  );
}

/**
 * One of the day's figures, as the reference dashboard draws them: a card
 * tinted in its tone, the icon on a solid square, the name in capitals, the
 * number large in the tone, and a line under it.
 */
export function StatCard({ label, value, unit, icon: Icon, tone = "brand", sub, onPress }) {
  const colors = useColors();
  const [bg, fg] = toneColors(colors, tone);
  const known = value !== null && value !== undefined && value !== "";

  return (
    <Card onPress={onPress} style={{ flexBasis: "46%", flexGrow: 1, backgroundColor: bg, borderWidth: 1, borderColor: `${fg}33`, gap: space[2], padding: space[3], shadowOpacity: 0.04 }}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
        {Icon ? (
          <View style={{ width: 30, height: 30, borderRadius: radius.sm, backgroundColor: fg, alignItems: "center", justifyContent: "center" }}>
            <Icon size={17} color="#ffffff" />
          </View>
        ) : null}
        <Text variant="small" style={{ flex: 1, fontFamily: fonts.semibold, letterSpacing: 0.3, textTransform: "uppercase", fontSize: 12 }} numberOfLines={2}>
          {label}
        </Text>
      </View>
      <Text variant="figure" style={{ fontSize: 24, lineHeight: 30, color: known ? fg : colors.foregroundSubtle }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.6}>
        {known ? value : "—"}
        {known && unit ? <Text variant="label" style={{ color: fg }}>{` ${unit}`}</Text> : null}
      </Text>
      {sub ? (
        <Text variant="small" tone="foregroundMuted" numberOfLines={1} style={{ fontSize: 12 }}>
          {sub}
        </Text>
      ) : null}
    </Card>
  );
}

/** A group of tiles on white, its name in the brand blue, as the reference home. */
export function TileSection({ title, action, onAction, children }) {
  const colors = useColors();

  return (
    <Card style={{ paddingHorizontal: space[2], paddingTop: space[3], gap: space[2] }}>
      <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", paddingHorizontal: space[2] }}>
        <Text variant="cardTitle" style={{ color: colors.brand, fontSize: 16 }}>
          {title}
        </Text>
        {action ? (
          <Pressable onPress={onAction} hitSlop={10} accessibilityRole="link">
            <Text variant="small" tone="foregroundSubtle" style={{ fontSize: 12 }}>
              {action}
            </Text>
          </Pressable>
        ) : null}
      </View>
      <View style={{ flexDirection: "row", flexWrap: "wrap", rowGap: space[2] }}>{children}</View>
    </Card>
  );
}

/** A section's name over its cards, with a link on the right. */
export function SectionHeader({ title, action, onAction }) {
  return (
    <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between", marginBottom: -space[1] }}>
      <Text variant="sectionTitle">{title}</Text>
      {action ? (
        <Pressable onPress={onAction} hitSlop={10} accessibilityRole="link">
          <Text variant="label" tone="brand">
            {action}
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}

/** A tab's own title at the top of its page, with what else it needs on the right. */
export function PageHeader({ title, subtitle, children }) {
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], paddingTop: space[2] }}>
      <View style={{ flex: 1, gap: 2 }}>
        <Text variant="pageTitle" numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? (
          <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
            {subtitle}
          </Text>
        ) : null}
      </View>
      {children}
    </View>
  );
}

/** A row of a list: icon, two lines, and a chevron when it opens something. */
export function ListRow({ icon: Icon, title, subtitle, right, onPress, tone = "neutral", chevron = Boolean(onPress) }) {
  const colors = useColors();

  return (
    <Pressable onPress={onPress} disabled={!onPress} accessibilityRole={onPress ? "button" : undefined} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: touch + 8, paddingVertical: space[2], opacity: pressed ? 0.75 : 1 })}>
      {Icon ? <Icon size={22} color={tone === "neutral" ? colors.foregroundMuted : toneColors(colors, tone)[1]} /> : null}
      <View style={{ flex: 1, gap: 2 }}>
        <Text>{title}</Text>
        {subtitle ? (
          <Text variant="small" tone="foregroundMuted">
            {subtitle}
          </Text>
        ) : null}
      </View>
      {right}
      {chevron ? <ChevronRight size={18} color={colors.foregroundSubtle} /> : null}
    </Pressable>
  );
}

export function Divider({ inset = 0 }) {
  const colors = useColors();

  return <View style={{ height: 1, backgroundColor: colors.border, marginLeft: inset }} />;
}

/** Initials in a circle. */
export function Avatar({ name = "", size = 60 }) {
  const colors = useColors();
  const initials = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");

  return (
    <View style={{ width: size, height: size, borderRadius: radius.full, backgroundColor: colors.brand, alignItems: "center", justifyContent: "center" }}>
      <Text style={{ color: colors.brandForeground, fontFamily: fonts.bold, fontSize: size * 0.36, lineHeight: size * 0.46 }}>{initials}</Text>
    </View>
  );
}

/**
 * Tabs across the top, drawn as the Cutting / Sewing / Quality choice (user,
 * 2026-11-27): a grey track, the chosen tab white with its words in the
 * brand blue; each with its icon and, when given, a count.
 */
export function PillTabs({ value, onChange, options }) {
  const colors = useColors();
  if (options.length < 2) return null;

  return (
    <View style={{ flexDirection: "row", backgroundColor: colors.surfaceMuted, borderRadius: radius.md, padding: 3, gap: 3 }}>
      {options.map((o) => {
        const on = o.value === value;
        const Icon = o.icon;

        return (
          <Pressable
            key={o.value}
            accessibilityRole="tab"
            accessibilityState={{ selected: on }}
            onPress={() => onChange(o.value)}
            style={[{ flex: 1, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[1.5], minHeight: 32, borderRadius: radius.sm, backgroundColor: on ? colors.surface : "transparent" }, on ? shadow(colors) : null]}
          >
            {Icon ? <Icon size={15} color={on ? colors.brand : colors.foregroundMuted} /> : null}
            <Text variant="label" style={{ fontSize: 13, color: on ? colors.brand : colors.foregroundMuted, fontFamily: fonts.semibold }}>
              {o.label}
            </Text>
            {o.count !== undefined && o.count !== null ? (
              <Text variant="small" style={{ fontSize: 11, color: on ? colors.brand : colors.foregroundSubtle }}>{`(${o.count})`}</Text>
            ) : null}
          </Pressable>
        );
      })}
    </View>
  );
}

/**
 * A small dropdown: its value and a chevron; a press opens the choices in a
 * popup by the button (user, 2026-11-27: the Order filters, by the title).
 */
export function Dropdown({ value, options, onChange, label, icon: Icon, field = false, style }) {
  const colors = useColors();
  const [open, setOpen] = useState(false);
  const current = options.find((o) => o.value === value) ?? options[0];

  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={label ? `${label}: ${current?.label}` : current?.label}
        onPress={() => setOpen(true)}
        style={({ pressed }) => [
          field
            ? // The reference screens' filter (2026-11-27): a white rounded field, its icon blue, a chevron.
              [{ flexDirection: "row", alignItems: "center", gap: 4, height: 42, paddingHorizontal: space[2], borderRadius: 14, borderWidth: 1, borderColor: colors.border, backgroundColor: pressed ? colors.surfaceMuted : colors.surface }, shadow(colors)]
            : { flexDirection: "row", alignItems: "center", gap: space[1], minHeight: 32, paddingLeft: 10, paddingRight: 8, borderRadius: radius.md, borderWidth: 1, borderColor: colors.border, backgroundColor: pressed ? colors.surfaceMuted : colors.surface },
          style,
        ]}
      >
        {Icon ? <Icon size={field ? 16 : 15} color={colors.brand} /> : null}
        <Text variant="small" style={{ flexShrink: 1, fontSize: field ? 12.5 : 13, fontFamily: fonts.semibold }} numberOfLines={1} adjustsFontSizeToFit={field} minimumFontScale={0.85}>
          {current?.label}
        </Text>
        <ChevronDown size={field ? 15 : 15} color={field ? colors.foreground : colors.foregroundMuted} style={field ? { marginLeft: "auto" } : undefined} />
      </Pressable>
      <Popup open={open} onClose={() => setOpen(false)} title={label} icon={Icon} width={420}>
        {options.map((o) => (
          <PopupChoice
            key={String(o.value)}
            label={o.label}
            on={o.value === current?.value}
            onPress={() => {
              onChange(o.value);
              setOpen(false);
            }}
          />
        ))}
      </Popup>
    </>
  );
}

/** "#2f6fe8" a little lighter (+) or darker (−): `amount` from −1 to 1. */
export function shade(hex, amount) {
  const n = parseInt(hex.replace("#", ""), 16);
  const mix = (c) => Math.round(amount >= 0 ? c + (255 - c) * amount : c * (1 + amount));
  const r = mix((n >> 16) & 255);
  const g = mix((n >> 8) & 255);
  const b = mix(n & 255);

  return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
}

/**
 * A round, lifelike icon (user, 2026-11-27): a sphere of its colour — lit from
 * above, deeper below — a soft shine across the top, and the icon in white.
 */
export function GlossyIcon({ icon: Icon, color, size = 54, square = false }) {
  const colors = useColors();
  const id = `g${color.replace("#", "")}`;
  // Round, or a rounded square (the home tiles, as the reference screen).
  const corner = square ? size * 0.24 : size / 2;

  if (square) {
    // The home tiles (the reference screen, 2026-11-27): lighter at the top, the colour at the foot, no shine.
    return (
      <View style={[{ width: size, height: size, borderRadius: corner }, shadow(colors, "md"), { shadowColor: color, shadowOpacity: 0.3, elevation: 5 }]}>
        <Svg width={size} height={size} style={{ position: "absolute" }}>
          <Defs>
            <SvgLinearGradient id={`${id}q`} x1="0" y1="0" x2="0.25" y2="1">
              <Stop offset="0" stopColor={shade(color, 0.22)} />
              <Stop offset="1" stopColor={shade(color, -0.12)} />
            </SvgLinearGradient>
          </Defs>
          <Rect x={0} y={0} width={size} height={size} rx={corner} ry={corner} fill={`url(#${id}q)`} />
        </Svg>
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <Icon size={size * 0.5} color="#ffffff" strokeWidth={2.2} />
        </View>
      </View>
    );
  }

  return (
    <View style={[{ width: size, height: size, borderRadius: corner }, shadow(colors, "md"), { shadowColor: color, shadowOpacity: 0.35 }]}>
      <Svg width={size} height={size} style={{ position: "absolute" }}>
        <Defs>
          <SvgLinearGradient id={id} x1="0" y1="0" x2="0.4" y2="1">
            <Stop offset="0" stopColor={shade(color, 0.32)} />
            <Stop offset="0.55" stopColor={color} />
            <Stop offset="1" stopColor={shade(color, -0.28)} />
          </SvgLinearGradient>
          <SvgLinearGradient id={`${id}s`} x1="0" y1="0" x2="0" y2="1">
            <Stop offset="0" stopColor="#ffffff" stopOpacity="0.55" />
            <Stop offset="1" stopColor="#ffffff" stopOpacity="0" />
          </SvgLinearGradient>
        </Defs>
        {square ? <Rect x={0} y={0} width={size} height={size} rx={corner} ry={corner} fill={`url(#${id})`} /> : <Circle cx={size / 2} cy={size / 2} r={size / 2} fill={`url(#${id})`} />}
        <Ellipse cx={size / 2} cy={size * (square ? 0.22 : 0.3)} rx={size * (square ? 0.42 : 0.34)} ry={size * 0.2} fill={`url(#${id}s)`} />
      </Svg>
      <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
        <Icon size={size * 0.46} color="#ffffff" strokeWidth={2.1} />
      </View>
    </View>
  );
}

/**
 * A table drawn as the web's Budget Breakdown: a grey head, thin rules
 * round every cell, totals in bold on grey, the last line tinted. `columns`:
 * [{ label, flex, align }]; `rows`: [{ cells, kind: "total" | "highlight" | undefined }].
 * Wider than the phone, it scrolls sideways.
 */
export function DataTable({ title, columns, rows, minWidth }) {
  const colors = useColors();
  // A table that scrolls sideways gets a fixed width — the screen's, or more — so every row's columns line up.
  const { width: screen } = useWindowDimensions();
  const wide = minWidth ? Math.max(minWidth, Math.min(screen, 480) - space[4] * 2) : null;
  const rule = colors.borderStrong;
  const cell = (c, i, value, bold, last) => (
    <View key={i} style={{ flex: c.flex ?? 1, paddingVertical: 6, paddingHorizontal: space[2], borderRightWidth: last ? 0 : 1, borderRightColor: rule, justifyContent: "center" }}>
      <Text variant="small" style={{ fontSize: 12, lineHeight: 16, textAlign: c.align ?? "left", fontFamily: bold ? fonts.bold : fonts.regular, fontVariant: c.align === "right" ? ["tabular-nums"] : undefined }}>
        {value ?? "—"}
      </Text>
    </View>
  );
  const body = (
    <View style={{ width: wide ?? "100%", borderWidth: 1, borderColor: rule, borderRadius: radius.md, overflow: "hidden", backgroundColor: colors.surface }}>
      <View style={{ flexDirection: "row", backgroundColor: colors.surfaceMuted, borderBottomWidth: 1, borderBottomColor: rule }}>
        {columns.map((c, i) => (
          <View key={i} style={{ flex: c.flex ?? 1, paddingVertical: 7, paddingHorizontal: space[2], borderRightWidth: i === columns.length - 1 ? 0 : 1, borderRightColor: rule, justifyContent: "center" }}>
            <Text variant="small" style={{ fontSize: 11, lineHeight: 15, textAlign: c.align ?? "left", fontFamily: fonts.bold, color: colors.foregroundMuted }}>
              {c.label}
            </Text>
          </View>
        ))}
      </View>
      {rows.map((r, j) => (
        <View key={j} style={{ flexDirection: "row", borderBottomWidth: j === rows.length - 1 ? 0 : 1, borderBottomColor: rule, backgroundColor: r.kind === "total" ? colors.surfaceMuted : r.kind === "highlight" ? colors.brandSubtle : colors.surface }}>
          {r.cells.map((v, i) => cell(columns[i], i, v, r.kind === "total" || r.kind === "highlight" || (i === 0 && r.strong), i === columns.length - 1))}
        </View>
      ))}
    </View>
  );

  return (
    <View style={{ gap: space[2] }}>
      {title ? <Text variant="cardTitle">{title}</Text> : null}
      {minWidth ? (
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {body}
        </ScrollView>
      ) : (
        body
      )}
    </View>
  );
}

/** A search field and its Find button, the same height and on one line (user, 2026-11-27). */
export function SearchRow({ value, onChange, onFind, placeholder, label }) {
  return (
    <View style={{ flexDirection: "row", gap: space[2], alignItems: "center" }}>
      <Input icon={Search} value={value} onChangeText={onChange} placeholder={placeholder} autoCorrect={false} autoCapitalize="none" returnKeyType="search" onSubmitEditing={onFind} style={{ flex: 1 }} />
      <Button onPress={onFind} style={{ minHeight: 46, paddingHorizontal: space[4] }}>
        {label}
      </Button>
    </View>
  );
}

/**
 * Every popup in the app (user, 2026-11-27: choices and details in the
 * middle, and designed): the dimmed page, a white card with 20dp corners
 * that grows in softly, its header — an icon in a soft circle if given, the
 * title, a line under it, anything on the right (a state's badge), and the
 * close button in a grey circle — then what it holds (scrolling when long)
 * and its buttons along the foot. Stays above the keyboard.
 */
export function Popup({ open, onClose, title, hint, icon: Icon, tone = "brand", right, footer, children, scroll = true, width = 520, closeLabel }) {
  const colors = useColors();
  const grow = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (open) {
      grow.setValue(0);
      Animated.spring(grow, { toValue: 1, useNativeDriver: true, friction: 8, tension: 90 }).start();
    }
  }, [open, grow]);

  const fg = colors[tone] ?? colors.brand;
  const body = <View style={{ padding: space[4], gap: space[2] }}>{children}</View>;

  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <KeyboardAvoidingView behavior="padding" style={{ flex: 1, backgroundColor: "rgba(15, 23, 42, 0.5)", justifyContent: "center", alignItems: "center", padding: space[5] }}>
        <Pressable accessibilityLabel={closeLabel} style={{ position: "absolute", top: 0, left: 0, right: 0, bottom: 0 }} onPress={onClose} />
        <Animated.View
          style={[
            { width: "100%", maxWidth: width, maxHeight: "82%", backgroundColor: colors.surface, borderRadius: 20, overflow: "hidden", borderWidth: 1, borderColor: colors.border },
            { shadowColor: "#0f172a", shadowOffset: { width: 0, height: 12 }, shadowRadius: 28, shadowOpacity: 0.18, elevation: 12 },
            { opacity: grow, transform: [{ scale: grow.interpolate({ inputRange: [0, 1], outputRange: [0.94, 1] }) }] },
          ]}
        >
          {title || Icon ? (
            <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], paddingLeft: space[4], paddingRight: space[3], paddingTop: space[4], paddingBottom: space[3], borderBottomWidth: 1, borderBottomColor: colors.divider ?? colors.border }}>
              {Icon ? (
                <View style={{ width: 38, height: 38, borderRadius: 19, backgroundColor: colors[`${tone}Subtle`] ?? colors.brandSubtle, alignItems: "center", justifyContent: "center" }}>
                  <Icon size={19} color={fg} strokeWidth={2.2} />
                </View>
              ) : null}
              <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
                <Text variant="sectionTitle" numberOfLines={2}>
                  {title}
                </Text>
                {hint ? (
                  <Text style={{ fontSize: 12, lineHeight: 16 }} tone="foregroundMuted" numberOfLines={3}>
                    {hint}
                  </Text>
                ) : null}
              </View>
              {right}
              {onClose ? (
                <Pressable accessibilityRole="button" accessibilityLabel={closeLabel} onPress={onClose} hitSlop={8} style={({ pressed }) => ({ width: 32, height: 32, borderRadius: 16, alignItems: "center", justifyContent: "center", backgroundColor: pressed ? colors.border : colors.surfaceMuted, alignSelf: "flex-start" })}>
                  <X size={17} color={colors.foregroundMuted} strokeWidth={2.4} />
                </Pressable>
              ) : null}
            </View>
          ) : null}
          {scroll ? (
            <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ flexGrow: 0 }}>
              {body}
            </ScrollView>
          ) : (
            body
          )}
          {footer ? <View style={{ flexDirection: "row", gap: space[3], paddingHorizontal: space[4], paddingVertical: space[3], borderTopWidth: 1, borderTopColor: colors.divider ?? colors.border, backgroundColor: colors.surfaceMuted }}>{footer}</View> : null}
        </Animated.View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

/** A choice inside a popup: its words, a line under them, and the blue tick when it is the chosen one. */
export function PopupChoice({ label, sub, on = false, onPress, icon: Icon, tone }) {
  const colors = useColors();

  return (
    <Pressable
      accessibilityRole="menuitem"
      accessibilityState={{ selected: on }}
      onPress={onPress}
      style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 40, paddingHorizontal: space[3], paddingVertical: 6, borderRadius: 12, borderWidth: 1, borderColor: on ? colors.brand : colors.border, backgroundColor: on ? colors.brandSubtle : pressed ? colors.surfaceMuted : colors.surface })}
    >
      {Icon ? <Icon size={17} color={tone ?? (on ? colors.brand : colors.foregroundMuted)} /> : null}
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={{ fontSize: 13, lineHeight: 18, fontFamily: on ? fonts.semibold : fonts.medium, color: on ? colors.brand : colors.foreground }} numberOfLines={2}>
          {label}
        </Text>
        {sub ? (
          <Text style={{ fontSize: 11, lineHeight: 15 }} tone="foregroundMuted" numberOfLines={2}>
            {sub}
          </Text>
        ) : null}
      </View>
      <View style={{ width: 20, height: 20, borderRadius: 10, borderWidth: on ? 0 : 1.5, borderColor: colors.borderStrong, backgroundColor: on ? colors.brand : "transparent", alignItems: "center", justifyContent: "center" }}>
        {on ? <Check size={13} color="#ffffff" strokeWidth={3} /> : null}
      </View>
    </Pressable>
  );
}
