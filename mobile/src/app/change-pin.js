/** A QC's own PIN: the current one, a new one, and the new one again. */
import { useState } from "react";
import { View } from "react-native";
import { Redirect, router } from "expo-router";
import { Alert, Button, Header, PinPad, Screen, Text } from "../components/ui";
import { t } from "../lib/i18n";
import { useSession } from "../lib/session";
import { space } from "../theme/tokens";

const m = (key, params) => t("mobile", key, params);
const START = { step: "current", current: "", next: "", again: "" };

export default function ChangePin() {
  const { session, changePin } = useSession();
  const [v, setV] = useState(START);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [done, setDone] = useState(false);
  const name = v.step === "new" ? "next" : v.step;

  if (session?.kind !== "PIN") return <Redirect href="/" />;

  const enter = async (pin) => {
    setError(null);
    const all = { ...v, [name]: pin };
    if (pin.length < 6) return setV(all);
    if (v.step === "current") return setV({ ...all, step: "new" });
    if (v.step === "new") return setV({ ...all, step: "again" });
    if (all.next !== all.again) {
      setError(m("pin.mismatch"));
      return setV({ ...all, step: "new", next: "", again: "" });
    }
    setV(all);
    setBusy(true);
    try {
      await changePin(all.current, all.next);
      setDone(true);
    } catch (caught) {
      setError(caught.field?.("current_pin") ?? caught.field?.("pin") ?? caught.message);
      // A weak new PIN is chosen again; a wrong current one starts over; no answer keeps nothing.
      setV(caught.field?.("pin") ? { ...all, step: "new", next: "", again: "" } : START);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen header={<Header title={m("pin.title")} onBack={() => router.back()} backLabel={m("back")} />}>
      {done ? (
        <View style={{ gap: space[4] }}>
          <Alert tone="success">{m("pin.done")}</Alert>
          <Button onPress={() => router.back()}>{m("back")}</Button>
        </View>
      ) : (
        <View style={{ alignItems: "center", gap: space[4], paddingTop: space[6] }}>
          <Text variant="sectionTitle">{m(`pin.${v.step}`)}</Text>
          {/* A place of its own height: the pad must not move under a thumb when a message comes or goes. */}
          <View style={{ minHeight: 60, justifyContent: "center" }}>
            <Text variant="small" tone={error ? "danger" : "foregroundMuted"} accessibilityLiveRegion="polite" style={{ textAlign: "center" }}>
              {error ?? (v.step === "new" ? m("pin.rule") : "")}
            </Text>
          </View>
          <PinPad value={v[name]} onChange={enter} disabled={busy} deleteLabel={m("erase")} />
        </View>
      )}
    </Screen>
  );
}
