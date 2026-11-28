/**
 * The bottom bar (the reference screens, 2026-11-27): Home, Production,
 * Tally, Machines, More. A tab shows only to somebody it serves —
 * Production to whoever sees a Quality Tally section, Tally to a QC or
 * them, Machines to whoever reads machines — never one that opens on a
 * refusal. Orders and Budgets open from Home's tiles and More. Screens
 * opened from a tab (a tally, a target, a machine) are pushed over the bar
 * by the root stack and come back to it.
 */
import { useEffect } from "react";
import { Redirect, Tabs } from "expo-router";
import { Text as RNText, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Boxes, ChartLine, ClipboardCheck, House, Menu } from "lucide-react-native";
import { useAccess } from "../../components/manage";
import { useMachineAccess } from "../../lib/machines";
import { sendReports } from "../../lib/reports";
import { t } from "../../lib/i18n";
import { useServer } from "../../lib/server";
import { qcOf, useSession } from "../../lib/session";
import { faceStyle } from "../../components/ui";
import { fonts } from "../../theme/tokens";
import { shadow, useColors } from "../../theme/theme";

const nav = (key) => t("mobile", `nav.${key}`);

/** A tab's icon; the chosen tab sits blue on a soft blue pill (the reference screen, 2026-11-27). */
function tabIcon(Icon) {
  return function TabIcon({ color, focused }) {
    return <Icon size={22} color={color} strokeWidth={focused ? 2.3 : 2} />;
  };
}

export default function TabsLayout() {
  const server = useServer();
  const { session, call, online } = useSession();
  const access = useAccess();
  const machines = useMachineAccess();
  const insets = useSafeAreaInsets();
  const colors = useColors();

  // Breakdown reports kept with no network go out once signed in, every half minute, and when the network is back.
  const email = session?.kind === "EMAIL";
  useEffect(() => {
    if (!email) return undefined;
    sendReports(call).catch(() => {});
    const timer = setInterval(() => sendReports(call).catch(() => {}), 30000);

    return () => clearInterval(timer);
  }, [email, call, online]);

  if (!server || !session) return <Redirect href="/sign-in" />;

  // `href: null` hides a tab rather than offering one that answers 403.
  const show = (allowed) => (allowed ? {} : { href: null });

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.brand,
        tabBarActiveBackgroundColor: colors.brandSubtle,
        tabBarItemStyle: { marginHorizontal: 4, marginTop: 4, marginBottom: 4, borderRadius: 18, overflow: "hidden" },
        tabBarInactiveTintColor: colors.foregroundSubtle,
        // Taller by the phone's own bottom inset, so the labels sit above Android's gesture bar.
        // The design standard: 64dp above the safe area, a thin top border, white.
        tabBarStyle: {
          backgroundColor: colors.surface,
          borderTopWidth: 1,
          borderTopColor: colors.border,
          height: 64 + insets.bottom,
          paddingTop: 2,
          paddingBottom: insets.bottom,
          paddingHorizontal: 4,
          ...shadow(colors),
        },
        // Five tabs on a small phone: a long label shrinks a little rather than being cut.
        // The chosen tab: its soft blue pill, and a blue line along its foot (the reference screens).
        tabBarLabel: ({ children, color, focused }) => (
          <View style={{ alignItems: "center" }}>
            <RNText numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8} style={{ color, ...faceStyle(fonts.medium), fontSize: 11, marginBottom: 3, paddingHorizontal: 2 }}>
              {children}
            </RNText>
            <View style={{ width: 36, height: 3, borderRadius: 2, backgroundColor: focused ? colors.brand : "transparent" }} />
          </View>
        ),
        sceneStyle: { backgroundColor: colors.background },
      }}
    >
      {/* Home · Production · Tally · Machines · More (the reference screens, 2026-11-27); Orders and Budgets open from Home and More. */}
      <Tabs.Screen name="index" options={{ title: nav("home"), tabBarIcon: tabIcon(House) }} />
      <Tabs.Screen name="production" options={{ title: nav("production"), tabBarIcon: tabIcon(ChartLine), ...show(access.length > 0) }} />
      <Tabs.Screen name="qc" options={{ title: nav("tally"), tabBarIcon: tabIcon(ClipboardCheck), ...show(Boolean(qcOf(session)) || access.length > 0) }} />
      <Tabs.Screen name="machines" options={{ title: nav("machines"), tabBarIcon: tabIcon(Boxes), ...show(machines.sees) }} />
      <Tabs.Screen name="more" options={{ title: nav("more"), tabBarIcon: tabIcon(Menu) }} />
    </Tabs>
  );
}
