/** The server's IP and port, tried before it is kept. */
import { useState } from "react";
import { Keyboard, View } from "react-native";
import { router, useCanGoBack } from "expo-router";
import { Plug, Server } from "lucide-react-native";
import { Alert, Button, Card, Header, Input, Screen, Text } from "../components/ui";
import { request } from "../lib/api";
import { t } from "../lib/i18n";
import { DEFAULT_PORT, currentServer, parseServer, setServer } from "../lib/server";
import { space } from "../theme/tokens";

const m = (key, params) => t("mobile", key, params);

export default function ServerScreen() {
  const saved = currentServer();
  const canGoBack = useCanGoBack();
  const [host, setHost] = useState(saved?.host ?? "");
  const [port, setPort] = useState(saved?.port ?? DEFAULT_PORT);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const connect = async () => {
    Keyboard.dismiss();
    const next = parseServer(host, port);
    if (!next) {
      setError(m("server.bad"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await request("/health", { server: next });
      setServer(next);
      router.replace("/");
    } catch (caught) {
      setError(caught.message);
      setBusy(false);
    }
  };

  return (
    <Screen header={<Header title={m("server.title")} onBack={saved && canGoBack ? () => router.back() : undefined} backLabel={m("back")} />}>
      <Card>
        <Text tone="foregroundMuted">{m("server.hint")}</Text>
        {error ? <Alert>{error}</Alert> : null}
        <View style={{ flexDirection: "row", gap: space[3] }}>
          <Input label={m("server.host")} icon={Server} value={host} onChangeText={setHost} placeholder="192.168.0.10" keyboardType="url" autoCapitalize="none" style={{ flex: 1 }} />
          <Input label={m("server.port")} value={port} onChangeText={(v) => setPort(v.replace(/\D/g, "").slice(0, 5))} keyboardType="number-pad" onSubmitEditing={connect} style={{ width: 96 }} />
        </View>
        <Button icon={Plug} loading={busy} disabled={!host.trim()} onPress={connect}>
          {m("server.connect")}
        </Button>
        <Text variant="small" tone="foregroundMuted">
          {m("server.plain")}
        </Text>
      </Card>
    </Screen>
  );
}
