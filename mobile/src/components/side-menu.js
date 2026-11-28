/**
 * The side menu (user, 2026-11-27): the menu at the bar's left opens the
 * modules from the left — Quality Tally, Machinery, Orders, Budget — each
 * screen this person may open (the same rules as Home's tiles), the
 * notifications, and the profile and settings (Home has no initials).
 */
import { useEffect, useRef } from "react";
import { Animated, Image, Modal, Pressable, ScrollView, View, useWindowDimensions } from "react-native";
import { router } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Bell, UserRound, X } from "lucide-react-native";
import { TileIcon } from "./tile-icon";
import { GlossyIcon, Text } from "./ui";
import { Band } from "./band";
import { mm, useAccess } from "./manage";
import { machineFeatures, orderFeatures, tallyFeatures } from "../lib/features";
import { t } from "../lib/i18n";
import { useMachineAccess } from "../lib/machines";
import { useSection } from "../lib/section";
import { useSession } from "../lib/session";
import { accents, fonts, radius, space, touch } from "../theme/tokens";
import { useColors } from "../theme/theme";

export function SideMenu({ open, onClose }) {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const panel = Math.min(width * 0.82, 340);
  const slide = useRef(new Animated.Value(-panel)).current;
  const { session } = useSession();
  const access = useAccess();
  const section = useSection(access);
  const machines = useMachineAccess();
  const email = session?.kind === "EMAIL";
  const groups = [
    { title: mm("title"), items: tallyFeatures({ session, section }) },
    { title: t("mobile", "mach.title"), items: machineFeatures(machines) },
    { title: t("mobile", "ord.title"), items: email ? orderFeatures(session.permissions ?? []) : [] },
    {
      title: t("mobile", "side.general"),
      items: [
        ...(email ? [{ key: "notifications", label: t("mobile", "act.title"), icon: Bell, color: accents.orange, href: { pathname: "/notifications" } }] : []),
        { key: "profile", label: t("mobile", "side.profile"), icon: UserRound, color: accents.purple, href: { pathname: "/more" } },
      ],
    },
  ].filter((g) => g.items.length);
  const user = email ? session.me.user : null;

  useEffect(() => {
    Animated.timing(slide, { toValue: open ? 0 : -panel, duration: 220, useNativeDriver: true }).start();
  }, [open, panel]);

  const go = (href) => {
    onClose();
    if (typeof href === "string") router.navigate(href);
    else router.push(href);
  };

  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable accessibilityLabel={t("mobile", "act.close")} onPress={onClose} style={{ position: "absolute", top: 0, bottom: 0, left: 0, right: 0, backgroundColor: colors.overlay }} />
      <Animated.View style={{ position: "absolute", top: 0, bottom: 0, left: 0, width: panel, backgroundColor: colors.surface, transform: [{ translateX: slide }] }}>
        <Band>
          <View style={{ paddingTop: insets.top + space[4], paddingBottom: space[4], paddingHorizontal: space[4], gap: space[3] }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
              <View style={{ flex: 1 }}>
                <Image source={require("../../assets/norban-logo-white.png")} style={{ width: 150, height: 47 }} resizeMode="contain" accessibilityLabel="Norban Group" />
              </View>
              <Pressable accessibilityRole="button" accessibilityLabel={t("mobile", "act.close")} onPress={onClose} style={{ width: touch, height: touch, alignItems: "center", justifyContent: "center", marginRight: -space[2] }}>
                <X size={22} color="#ffffff" />
              </Pressable>
            </View>
            {user ? (
              <View>
                <Text style={{ color: "#ffffff", fontFamily: fonts.semibold }}>{user.name}</Text>
                <Text variant="small" style={{ color: "#dbe7fb" }}>
                  {[user.designation, session.me.company?.name].filter(Boolean).join(" · ")}
                </Text>
              </View>
            ) : null}
          </View>
        </Band>
        <ScrollView contentContainerStyle={{ paddingVertical: space[2], paddingBottom: insets.bottom + space[4] }}>
          {groups.map((g) => (
            <View key={g.title} style={{ paddingTop: space[2] }}>
              <Text variant="small" style={{ paddingHorizontal: space[4], paddingVertical: space[1.5], color: colors.brand, fontFamily: fonts.bold, textTransform: "uppercase", letterSpacing: 0.6, fontSize: 11 }}>
                {g.title}
              </Text>
              {g.items.map((item) => (
                <Pressable
                  key={item.key}
                  accessibilityRole="button"
                  onPress={() => go(item.href)}
                  style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 48, paddingHorizontal: space[4], backgroundColor: pressed ? colors.surfaceMuted : "transparent" })}
                >
                  {item.image ? <TileIcon image={item.image} size={32} /> : <GlossyIcon icon={item.icon} color={item.color} size={32} />}
                  <Text style={{ flex: 1 }}>{item.label}</Text>
                </Pressable>
              ))}
            </View>
          ))}
        </ScrollView>
      </Animated.View>
    </Modal>
  );
}
