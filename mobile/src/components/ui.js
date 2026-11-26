/**
 * The app's component kit: every screen is built from these, with the web
 * kit's names (Button, Card, Badge, Alert). A screen styling its own button is
 * a defect. More pieces join as the phases need them.
 */
import { useLayoutEffect, useRef, useState } from "react";
import { ActivityIndicator, KeyboardAvoidingView, Pressable, ScrollView, Text as RNText, TextInput, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { CheckCircle2, ChevronLeft, Delete, Eye, EyeOff, Info, TriangleAlert, XCircle } from "lucide-react-native";
import { currentLocale } from "../lib/i18n";
import { fonts, radius, space, touch, type } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const BENGALI = { [fonts.regular]: fonts.bengali, [fonts.medium]: fonts.bengali, [fonts.semibold]: fonts.bengaliSemibold, [fonts.bold]: fonts.bengaliSemibold };
const face = (family) => (currentLocale() === "bn" ? (BENGALI[family] ?? fonts.bengali) : family);

/** Every word on screen; Bangla is set in Anek Bangla, as on the web. */
export function Text({ variant = "body", tone = "foreground", style, children, ...props }) {
  const colors = useColors();
  const base = type[variant] ?? type.body;

  return (
    <RNText {...props} style={[base, { fontFamily: face(base.fontFamily), color: colors[tone] ?? tone }, style]}>
      {children}
    </RNText>
  );
}

/** A screen: the background, the safe area, a scroll above the keyboard, and a phone's width even on a tablet. */
export function Screen({ children, header, width = 480 }) {
  const colors = useColors();

  return (
    <SafeAreaView edges={["top", "bottom"]} style={{ flex: 1, backgroundColor: colors.background }}>
      {header}
      <KeyboardAvoidingView behavior="padding" style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={{ flexGrow: 1, alignItems: "center", padding: space[4] }} keyboardShouldPersistTaps="handled">
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
    <View style={[{ gap: space[1.5] }, style]}>
      <Text variant="label" tone="foregroundMuted">
        {label}
      </Text>
      <View style={{ minHeight: 52, flexDirection: "row", alignItems: "center", gap: space[2], paddingLeft: space[3], borderRadius: radius.md, borderWidth: focused || error ? 2 : 1, borderColor: error ? colors.danger : focused ? colors.brand : colors.borderStrong, backgroundColor: colors.surface }}>
        {Icon ? <Icon size={20} color={colors.foregroundSubtle} /> : null}
        <TextInput
          {...props}
          ref={inputRef}
          accessibilityLabel={label}
          secureTextEntry={hidden}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          placeholderTextColor={colors.foregroundSubtle}
          autoCorrect={false}
          style={{ flex: 1, minHeight: 50, paddingVertical: 0, color: colors.foreground, fontFamily: fonts.medium, fontSize: 16 }}
        />
        {secure ? <IconButton icon={Eyes} label={label} onPress={() => setHidden((h) => !h)} /> : <View style={{ width: space[3] }} />}
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
                {key === "<" ? <Delete size={26} color={colors.foregroundMuted} /> : <RNText style={{ fontFamily: fonts.semibold, fontSize: 26, color: colors.foreground }}>{key}</RNText>}
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
    <Pressable accessibilityRole="button" onPress={onPress} style={{ minHeight: 40, paddingHorizontal: space[3], borderRadius: radius.full, borderWidth: 1, borderColor: on ? colors.brand : colors.border, backgroundColor: on ? colors.brandSubtle : colors.surface, justifyContent: "center" }}>
      <Text variant="small" style={{ color: on ? colors.brand : colors.foreground, fontFamily: face(fonts.semibold) }}>
        {children}
      </Text>
    </Pressable>
  );
}

export function Card({ children, style }) {
  const colors = useColors();

  return <View style={[{ backgroundColor: colors.surface, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.border, padding: space[5], gap: space[3] }, shadow(colors), style]}>{children}</View>;
}

const BUTTONS = {
  primary: (c) => ({ bg: c.brand, fg: c.brandForeground, border: c.brand }),
  outline: (c) => ({ bg: c.surface, fg: c.foreground, border: c.borderStrong }),
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
        { minHeight: touch, paddingHorizontal: space[5], borderRadius: radius.md, borderWidth: 1, borderColor: look.border, backgroundColor: look.bg, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[2], opacity: off ? 0.5 : pressed ? 0.85 : 1 },
        style,
      ]}
    >
      {loading ? <ActivityIndicator color={look.fg} /> : Icon ? <Icon size={18} color={look.fg} /> : null}
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
    <View style={{ flexDirection: "row", backgroundColor: colors.surfaceMuted, borderRadius: radius.md, padding: space[1], gap: space[1] }}>
      {options.map((o) => {
        const on = o.value === value;

        return (
          <Pressable
            key={o.value}
            accessibilityRole="button"
            accessibilityState={{ selected: on }}
            onPress={() => onChange(o.value)}
            style={{ flex: 1, minHeight: touch, borderRadius: radius.sm, alignItems: "center", justifyContent: "center", backgroundColor: on ? colors.surface : "transparent" }}
          >
            <Text variant="label" style={{ color: on ? colors.brand : colors.foregroundMuted, fontFamily: o.value === "bn" ? fonts.bengaliSemibold : face(fonts.semibold) }}>
              {o.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}
