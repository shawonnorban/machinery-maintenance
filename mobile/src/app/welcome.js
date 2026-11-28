/**
 * The first screen on a new phone (user, 2026-11-27, as the reference
 * screens): the logo, three slides of what the app does, and the language
 * chosen on a navy panel. Shown once; sign-in follows.
 */
import { useEffect, useRef, useState } from "react";
import { Image, Pressable, ScrollView, View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Svg, { Ellipse } from "react-native-svg";
import { BarChart3, Camera, CheckCircle2, ClipboardCheck, Gauge, Globe, LineChart, QrCode, ScanLine, Siren, Target, TriangleAlert, Wrench } from "lucide-react-native";
import { Text } from "../components/ui";
import { Band } from "../components/band";
import { LOCALES, applyLocale, currentLocale, t } from "../lib/i18n";
import { markWelcomed } from "../lib/welcome";
import { accents, fonts, radius, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const w = (key) => t("mobile", `welcome.${key}`);

const SLIDES = [
  { key: "tally", icon: ClipboardCheck, color: accents.blue, minis: [[CheckCircle2, accents.green], [TriangleAlert, accents.orange], [ScanLine, accents.teal]] },
  { key: "machines", icon: Wrench, color: accents.orange, minis: [[QrCode, accents.blue], [Camera, accents.purple], [Siren, accents.rose]] },
  { key: "insights", icon: LineChart, color: accents.green, minis: [[Target, accents.blue], [Gauge, accents.teal], [BarChart3, accents.purple]] },
];

/** A drawing of one slide: a soft blob, the main card, three small cards about it. */
function Scene({ slide, width }) {
  const colors = useColors();
  const Icon = slide.icon;
  const size = Math.min(width - space[8], 300);
  const mini = (Mini, colour, style) => (
    <View style={[{ position: "absolute", width: 58, height: 58, borderRadius: radius.md, backgroundColor: colors.surface, alignItems: "center", justifyContent: "center" }, shadow(colors, "md"), style]}>
      <Mini size={28} color={colour} />
    </View>
  );

  return (
    <View style={{ width: size, height: size * 0.82, alignSelf: "center" }}>
      <Svg width={size} height={size * 0.82} style={{ position: "absolute" }}>
        <Ellipse cx={size / 2} cy={size * 0.45} rx={size * 0.47} ry={size * 0.34} fill={colors.brandSubtle} />
        <Ellipse cx={size * 0.72} cy={size * 0.25} rx={size * 0.16} ry={size * 0.12} fill={colors.infoSubtle} />
      </Svg>
      <View style={[{ position: "absolute", left: size / 2 - 62, top: size * 0.41 - 62, width: 124, height: 124, borderRadius: radius.xl, backgroundColor: colors.surface, alignItems: "center", justifyContent: "center" }, shadow(colors, "md")]}>
        <View style={{ width: 84, height: 84, borderRadius: radius.lg, backgroundColor: `${slide.color}1f`, alignItems: "center", justifyContent: "center" }}>
          <Icon size={48} color={slide.color} />
        </View>
      </View>
      {mini(slide.minis[0][0], slide.minis[0][1], { left: size * 0.06, top: size * 0.12 })}
      {mini(slide.minis[1][0], slide.minis[1][1], { right: size * 0.04, top: size * 0.04 })}
      {mini(slide.minis[2][0], slide.minis[2][1], { right: size * 0.08, top: size * 0.55 })}
    </View>
  );
}

export default function Welcome() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const [page, setPage] = useState(0);
  const [, redraw] = useState(currentLocale());
  const strip = useRef(null);

  // The slides move on by themselves; a swipe moves them too.
  useEffect(() => {
    const timer = setInterval(() => {
      setPage((p) => {
        const next = (p + 1) % SLIDES.length;
        strip.current?.scrollTo({ x: next * width, animated: true });
        return next;
      });
    }, 4000);

    return () => clearInterval(timer);
  }, [width]);

  const choose = (locale) => {
    applyLocale(locale);
    redraw(locale);
    markWelcomed();
    router.replace("/sign-in");
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.surface }}>
      <StatusBar style="dark" />
      <View style={{ paddingTop: insets.top + space[6], alignItems: "center", flexDirection: "row", justifyContent: "center", gap: space[2] }}>
        <Image source={require("../../assets/norban-logo.png")} style={{ width: 220, height: 69 }} resizeMode="contain" accessibilityLabel="Norban Group" />
      </View>

      <ScrollView
        ref={strip}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(e) => setPage(Math.round(e.nativeEvent.contentOffset.x / width))}
        style={{ flexGrow: 0, marginTop: space[6] }}
      >
        {SLIDES.map((slide) => (
          <View key={slide.key} style={{ width, paddingHorizontal: space[6], gap: space[5] }}>
            <Scene slide={slide} width={width} />
            <View style={{ gap: space[2] }}>
              <Text variant="pageTitle" style={{ textAlign: "center", color: colors.foregroundMuted }}>
                {w(`${slide.key}_title`)}
              </Text>
              <Text style={{ textAlign: "center", color: colors.foregroundSubtle }}>{w(`${slide.key}_text`)}</Text>
            </View>
          </View>
        ))}
      </ScrollView>

      <View style={{ flexDirection: "row", justifyContent: "center", gap: space[1.5], marginTop: space[5] }}>
        {SLIDES.map((slide, i) => (
          <View key={slide.key} style={{ width: i === page ? 34 : 9, height: 9, borderRadius: radius.full, backgroundColor: i === page ? colors.date : colors.borderStrong }} />
        ))}
      </View>

      <View style={{ flex: 1 }} />
      <Band style={{ borderTopLeftRadius: 28, borderTopRightRadius: 28 }}>
        <View style={{ paddingTop: space[6], paddingHorizontal: space[6], paddingBottom: insets.bottom + space[6], gap: space[3] }}>
          <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[3] }}>
            <Globe size={24} color={colors.barText} />
            <Text variant="pageTitle" style={{ color: colors.barText, fontSize: 22 }}>
              {w("language")}
            </Text>
          </View>
          <Text style={{ textAlign: "center", color: "#dbe7fb", marginBottom: space[2] }}>{w("language_hint")}</Text>
          {LOCALES.map((l, i) => (
            <Pressable
              key={l.value}
              accessibilityRole="button"
              onPress={() => choose(l.value)}
              style={({ pressed }) => ({ minHeight: 56, borderRadius: radius.lg, alignItems: "center", justifyContent: "center", backgroundColor: i === 0 ? "#e8eef7" : "transparent", borderWidth: i === 0 ? 0 : 1.5, borderColor: "rgba(255,255,255,0.55)", opacity: pressed ? 0.8 : 1 })}
            >
              <Text variant="sectionTitle" style={{ color: i === 0 ? colors.barFrom : colors.barText, fontFamily: l.value === "bn" ? fonts.bengaliSemibold : fonts.semibold }}>
                {l.label}
              </Text>
            </Pressable>
          ))}
        </View>
      </Band>
    </View>
  );
}
