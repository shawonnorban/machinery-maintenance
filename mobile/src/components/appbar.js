/**
 * The bar over every tab (user, 2026-11-27): a navy-to-blue band with the
 * menu (the modules, from the left), the app's mark, the notifications and
 * the person's initials — ringed green online, red offline — which open the
 * profile and settings (the language is chosen there).
 */
import { useCallback, useEffect, useState } from "react";
import { Image, Pressable, View } from "react-native";
import { router, useFocusEffect, useIsFocused } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Bell, Menu } from "lucide-react-native";
import { Text } from "./ui";
import { Band } from "./band";
import { SideMenu } from "./side-menu";
import { t } from "../lib/i18n";
import { qcOf, useSession } from "../lib/session";
import { fonts, radius, space, touch } from "../theme/tokens";
import { useColors } from "../theme/theme";

/** How many notifications are unread: read when the screen shows, and every minute. */
function useUnread(on) {
  const { call } = useSession();
  const [count, setCount] = useState(0);
  const read = useCallback(() => {
    if (on) call("/notifications?per_page=1", { whole: true }).then((a) => setCount(a?.meta?.unread_count ?? 0), () => {});
  }, [on, call]);

  useFocusEffect(read);
  useEffect(() => {
    const timer = setInterval(read, 60000);

    return () => clearInterval(timer);
  }, [read]);

  return count;
}

/** The bell, its count a small dot of a badge (user, 2026-11-27: smaller and more elegant). */
function NotificationBell({ count }) {
  const colors = useColors();

  return (
    <Pressable accessibilityRole="button" accessibilityLabel={t("mobile", "act.title")} onPress={() => router.push("/notifications")} hitSlop={6} style={{ width: 38, height: 38, borderRadius: radius.full, backgroundColor: "rgba(255,255,255,0.14)", alignItems: "center", justifyContent: "center" }}>
      <Bell size={19} color={colors.barText} />
      {count ? (
        <View style={{ position: "absolute", top: 5, right: 4, minWidth: 15, height: 15, paddingHorizontal: 3, borderRadius: radius.full, backgroundColor: "#ef4444", borderWidth: 1.5, borderColor: colors.barFrom, alignItems: "center", justifyContent: "center" }}>
          <Text style={{ color: "#ffffff", fontSize: 8.5, lineHeight: 11, fontFamily: fonts.bold }}>{count > 99 ? "99+" : count}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

export { Band };

export function AppBar() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { session, online } = useSession();
  const unread = useUnread(session?.kind === "EMAIL");
  const [menu, setMenu] = useState(false);
  // Light only while this tab shows; a white screen pushed over it gets the dark clock back.
  const focused = useIsFocused();
  const name = session?.kind === "PIN" ? qcOf(session)?.qc?.name : session?.me?.user?.name;
  const initials = (name ?? "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join("");

  return (
    <Band>
      {focused ? <StatusBar style="light" /> : null}
      <View style={{ paddingTop: insets.top + space[2], paddingBottom: space[3], paddingHorizontal: space[3], flexDirection: "row", alignItems: "center", gap: space[2] }}>
        <Pressable accessibilityRole="button" accessibilityLabel={t("mobile", "side.open")} onPress={() => setMenu(true)} style={{ width: touch, height: touch, alignItems: "center", justifyContent: "center" }}>
          <Menu size={26} color={colors.barText} />
        </Pressable>
        {/* Norban's logo in white (user, 2026-11-27: the app is Norban Group's). */}
        <View style={{ flex: 1, flexDirection: "row", alignItems: "center" }}>
          <Image source={require("../../assets/norban-logo-white.png")} style={{ width: 118, height: 37 }} resizeMode="contain" accessibilityLabel="Norban Group" />
        </View>
        {session?.kind === "EMAIL" ? <NotificationBell count={unread} /> : null}
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${name ?? ""} · ${t("mobile", online ? "home.online" : "home.offline")}`}
          onPress={() => router.push("/more")}
          style={{ width: 40, height: 40, borderRadius: radius.full, borderWidth: 2.5, borderColor: online ? "#4ade80" : "#f87171", backgroundColor: "#ffffff", alignItems: "center", justifyContent: "center", marginLeft: space[1] }}
        >
          <Text style={{ color: colors.barFrom, fontFamily: fonts.bold, fontSize: 15, lineHeight: 20 }}>{initials || "?"}</Text>
        </Pressable>
      </View>
      <SideMenu open={menu} onClose={() => setMenu(false)} />
    </Band>
  );
}
