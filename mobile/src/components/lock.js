/** The lock over every screen: the same QC's PIN, or another QC signs in. */
import { useEffect, useState } from "react";
import { BackHandler, StyleSheet, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { Lock, UserRoundCog } from "lucide-react-native";
import { Button, PinPad, Text } from "./ui";
import { t } from "../lib/i18n";
import { useSession } from "../lib/session";
import { radius, space } from "../theme/tokens";
import { useColors } from "../theme/theme";

const m = (key, params) => t("mobile", key, params);

export function LockScreen() {
  const colors = useColors();
  const { session, unlock, signOut } = useSession();
  const [pin, setPin] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  // Back must not show what is under the lock.
  useEffect(() => {
    const sub = BackHandler.addEventListener("hardwareBackPress", () => {
      BackHandler.exitApp();

      return true;
    });

    return () => sub.remove();
  }, []);

  const enter = async (next) => {
    setPin(next);
    setError(null);
    if (next.length < 6) return;
    setBusy(true);
    const result = await unlock(next);
    if (result.ok) return;
    setPin("");
    setBusy(false);
    setError(result.minutes ? m("lock.locked", { minutes: result.minutes }) : result.unknown ? m("lock.unknown") : m("lock.wrong", { left: result.left }));
  };

  return (
    <SafeAreaView style={[StyleSheet.absoluteFill, { backgroundColor: colors.background }]}>
      <View style={{ flex: 1, alignItems: "center", justifyContent: "center", padding: space[5], gap: space[5] }}>
        <View style={{ width: 64, height: 64, borderRadius: radius.full, backgroundColor: colors.brandSubtle, alignItems: "center", justifyContent: "center" }}>
          <Lock size={28} color={colors.brand} />
        </View>
        <View style={{ alignItems: "center", gap: space[1] }}>
          <Text variant="sectionTitle">{session.profile.qc.name}</Text>
          <Text variant="small" tone="foregroundMuted">
            {session.profile.qc.employee_id}
          </Text>
        </View>
        {/* A place of its own height: the pad must not move under a thumb when a message comes or goes. */}
        <View style={{ minHeight: 44, justifyContent: "center" }}>
          <Text variant="small" tone={error ? "danger" : "foregroundMuted"} accessibilityLiveRegion="polite" style={{ textAlign: "center" }}>
            {error ?? m("lock.hint")}
          </Text>
        </View>
        <PinPad value={pin} onChange={enter} disabled={busy} deleteLabel={m("erase")} />
        <Button variant="outline" icon={UserRoundCog} onPress={signOut}>
          {m("lock.switch")}
        </Button>
      </View>
    </SafeAreaView>
  );
}
