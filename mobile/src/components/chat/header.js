/**
 * A chat screen's header: the app's blue band (the same height and wave as
 * every page's), back, and either a title or — in a conversation — the
 * chat's photo, name and its line (online / last seen / typing… / a group's
 * people), a tap on which opens its info; the actions on the right.
 */
import { Pressable, View } from "react-native";
import { useIsFocused } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { ArrowLeft } from "lucide-react-native";
import { Text } from "../ui";
import { HERO_HEIGHT, HeroBackdrop } from "../hero";
import { fonts, space } from "../../theme/tokens";
import { ChatAvatar, ct } from "./parts";

export function ChatHeader({ title, subtitle, chat, onBack, onTitle, right, typing = false }) {
  const insets = useSafeAreaInsets();
  const focused = useIsFocused();
  const height = insets.top + HERO_HEIGHT;

  return (
    <View style={{ height }}>
      {focused ? <StatusBar style="light" /> : null}
      <HeroBackdrop height={height} wave={22} />
      <View style={{ height: HERO_HEIGHT - 22, marginTop: insets.top, paddingLeft: space[3], paddingRight: space[2], flexDirection: "row", alignItems: "center", gap: space[2] }}>
        <Pressable accessibilityRole="button" accessibilityLabel={ct("cancel")} onPress={onBack} hitSlop={12} style={{ width: 32, height: 40, justifyContent: "center" }}>
          <ArrowLeft size={24} color="#ffffff" />
        </Pressable>
        <Pressable onPress={onTitle} disabled={!onTitle} style={{ flex: 1, minWidth: 0, flexDirection: "row", alignItems: "center", gap: space[2] }}>
          {chat ? <ChatAvatar chat={chat} size={38} /> : null}
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: chat ? 17 : 20, lineHeight: chat ? 22 : 26 }} numberOfLines={1}>
              {title}
            </Text>
            {subtitle ? (
              <Text style={{ color: typing ? "#bbf7d0" : "#e2ecff", fontSize: 12, lineHeight: 16 }} numberOfLines={1}>
                {subtitle}
              </Text>
            ) : null}
          </View>
        </Pressable>
        {right}
      </View>
    </View>
  );
}

/** A round white-on-glass button in the header. */
export function HeaderButton({ icon: Icon, label, onPress }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} hitSlop={6} style={({ pressed }) => ({ width: 38, height: 38, borderRadius: 19, alignItems: "center", justifyContent: "center", backgroundColor: pressed ? "rgba(255,255,255,0.25)" : "rgba(255,255,255,0.14)" })}>
      <Icon size={19} color="#ffffff" />
    </Pressable>
  );
}
