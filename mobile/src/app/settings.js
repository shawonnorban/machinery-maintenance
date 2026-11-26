/** This phone: language, light or dark, and the server it talks to. */
import { useCallback, useEffect, useState } from "react";
import { View } from "react-native";
import { router } from "expo-router";
import { Plug } from "lucide-react-native";
import { Badge, Button, Card, DetailRow, Header, Screen, Segmented, Text } from "../components/ui";
import { APP_VERSION, request } from "../lib/api";
import { MODES, setAppearance, useAppearance } from "../lib/appearance";
import { LOCALES, applyLocale, currentLocale, t } from "../lib/i18n";
import { thisPhone } from "../lib/phone";
import { serverLabel, useServer } from "../lib/server";
import { useSession } from "../lib/session";

const m = (key, params) => t("mobile", key, params);

export default function SettingsScreen() {
  const appearance = useAppearance();
  const server = useServer();
  const { session } = useSession();
  const [state, setState] = useState("checking");
  const [model, setModel] = useState("");

  const check = useCallback(async () => {
    setState("checking");
    setState(await request("/health").then(() => "ready", () => "down"));
  }, []);

  useEffect(() => {
    check();
    thisPhone().then((p) => setModel(`${p.model} · ${p.os}`));
  }, [check, server]);

  return (
    <Screen header={<Header title={m("settings.title")} onBack={() => (router.canGoBack() ? router.back() : router.replace("/"))} backLabel={m("back")} />}>
      <Card>
        <Text variant="label" tone="foregroundMuted">
          {m("settings.language")}
        </Text>
        <Segmented value={currentLocale()} onChange={applyLocale} options={LOCALES} />
        <Text variant="label" tone="foregroundMuted">
          {m("settings.look")}
        </Text>
        <Segmented value={appearance} onChange={setAppearance} options={MODES.map((value) => ({ value, label: m(`settings.${value}`) }))} />
      </Card>
      <Card>
        <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
          <Text variant="sectionTitle">{m("server.title")}</Text>
          <Badge tone={{ checking: "info", ready: "success", down: "danger" }[state]}>{m(`server.${state}`)}</Badge>
        </View>
        <DetailRow label={m("server.address")} value={serverLabel(server)} />
        <DetailRow label={m("settings.version")} value={APP_VERSION} />
        <DetailRow label={m("settings.model")} value={model} />
        {/* Signed in, the phone belongs to that server's company: sign out first. */}
        {session ? null : (
          <Button variant="outline" icon={Plug} onPress={() => router.push("/server")}>
            {m("server.change")}
          </Button>
        )}
      </Card>
    </Screen>
  );
}
