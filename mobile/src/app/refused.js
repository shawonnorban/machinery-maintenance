/**
 * Taps the server would not take (AnnotechERP 37 §৩ 3a): a closed job, another
 * line, too old… Each stays here with its reason until somebody clears it —
 * never dropped unseen, as annotech-rmg's app did. The server keeps them too.
 */
import { useState } from "react";
import { Alert as Ask, View } from "react-native";
import { router } from "expo-router";
import { Trash2 } from "lucide-react-native";
import { Alert, Button, Card, Header, Screen, Text } from "../components/ui";
import { timeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { clearRefused, refusedTaps, useTaps } from "../lib/taps";
import { space } from "../theme/tokens";

const m = (key, params) => t("mobile", `tally.${key}`, params);

export default function Refused() {
  useTaps();
  const [, redraw] = useState(0);
  const rows = refusedTaps();

  const clearAll = () =>
    Ask.alert(m("clear_all_ask"), undefined, [
      { text: m("close"), style: "cancel" },
      {
        text: m("clear_all"),
        style: "destructive",
        onPress: () => {
          clearRefused();
          redraw((n) => n + 1);
        },
      },
    ]);

  return (
    <Screen header={<Header title={m("refused_title")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <Text tone="foregroundMuted">{m("refused_hint")}</Text>
      {rows.length === 0 ? <Alert tone="success">{m("refused_empty")}</Alert> : null}
      {rows.map((r) => (
        <Card key={r.uuid} style={{ padding: space[4], gap: space[1] }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
            <Text variant="cardTitle" style={{ flex: 1 }}>
              {t("qtally", `action.${r.action}`)} · {timeOf(new Date(r.made_at).toISOString())}
            </Text>
            <Button variant="outline" icon={Trash2} onPress={() => clearRefused(r.uuid)} style={{ minHeight: 40, paddingHorizontal: space[3] }}>
              {m("clear")}
            </Button>
          </View>
          {r.label ? (
            <Text variant="small" tone="foregroundMuted">
              {r.label}
            </Text>
          ) : null}
          <Text tone="danger">{r.message}</Text>
        </Card>
      ))}
      {rows.length > 1 ? (
        <Button variant="outline" icon={Trash2} onPress={clearAll}>
          {m("clear_all")}
        </Button>
      ) : null}
    </Screen>
  );
}
