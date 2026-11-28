/**
 * The app's floor (AnnotechERP 37, phase 6): the server says, in /health,
 * the oldest version it still takes. An older app stops here — after sending
 * the taps and breakdown reports still on the phone, so nothing is lost —
 * and offers the new one's download. Checked at start and every ten minutes;
 * with no answer the app goes on as it is.
 */
import { useCallback, useEffect, useState } from "react";
import { Image, Linking, View } from "react-native";
import { CloudUpload, Download, RefreshCw } from "lucide-react-native";
import { Alert, Button, Card, Text } from "./ui";
import { APP_VERSION, request } from "../lib/api";
import { t } from "../lib/i18n";
import { sendReports, useReports } from "../lib/reports";
import { useServer } from "../lib/server";
import { useSession } from "../lib/session";
import { syncTaps, useTaps } from "../lib/taps";
import { space } from "../theme/tokens";
import { useColors } from "../theme/theme";

const ut = (key, params) => t("mobile", `update.${key}`, params);

/** -1, 0 or 1: "0.6.0" against "0.10.1", number by number. */
export function compareVersions(a, b) {
  const x = String(a ?? "0").split(".").map((n) => parseInt(n, 10) || 0);
  const y = String(b ?? "0").split(".").map((n) => parseInt(n, 10) || 0);
  for (let i = 0; i < Math.max(x.length, y.length); i += 1) {
    if ((x[i] ?? 0) !== (y[i] ?? 0)) return (x[i] ?? 0) < (y[i] ?? 0) ? -1 : 1;
  }

  return 0;
}

export function UpdateGate({ children }) {
  const colors = useColors();
  const server = useServer();
  const { session, call } = useSession();
  const { waiting } = useTaps();
  const reports = useReports().length;
  const [floor, setFloor] = useState(null);
  const [sending, setSending] = useState(false);

  const check = useCallback(() => {
    if (!server) return;
    request("/health").then((d) => setFloor(d?.mobile ?? null), () => {});
  }, [server]);

  useEffect(() => {
    check();
    const timer = setInterval(check, 10 * 60 * 1000);

    return () => clearInterval(timer);
  }, [check]);

  const outdated = floor?.min_version && compareVersions(APP_VERSION, floor.min_version) < 0;

  // What is still on the phone goes first.
  const flush = useCallback(async () => {
    setSending(true);
    await syncTaps({ now: true }).catch(() => {});
    if (session?.kind === "EMAIL") await sendReports(call).catch(() => {});
    setSending(false);
  }, [session, call]);

  useEffect(() => {
    if (outdated) flush();
  }, [outdated]);

  if (!outdated) return children;
  const left = (waiting ?? 0) + reports;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background, justifyContent: "center", padding: space[6] }}>
      <Card style={{ alignItems: "stretch", gap: space[4] }}>
        <View style={{ alignItems: "center", gap: space[2] }}>
          <Image source={require("../../assets/splash-icon.png")} style={{ width: 72, height: 72 }} accessibilityIgnoresInvertColors />
          <Text variant="sectionTitle" style={{ textAlign: "center" }}>
            {ut("title")}
          </Text>
          <Text tone="foregroundMuted" style={{ textAlign: "center" }}>
            {ut("text", { have: APP_VERSION, need: floor.min_version })}
          </Text>
        </View>
        {left ? <Alert tone="warning">{ut("pending", { n: left })}</Alert> : <Alert tone="success">{ut("all_sent")}</Alert>}
        {left ? (
          <Button variant="outline" icon={CloudUpload} loading={sending} onPress={flush}>
            {ut("send")}
          </Button>
        ) : null}
        {floor.download_url ? (
          <Button icon={Download} disabled={Boolean(left)} onPress={() => Linking.openURL(floor.download_url)}>
            {ut("download")}
          </Button>
        ) : (
          <Text variant="small" tone="foregroundMuted" style={{ textAlign: "center" }}>
            {ut("ask")}
          </Text>
        )}
        <Button variant="outline" icon={RefreshCw} onPress={check}>
          {ut("check")}
        </Button>
      </Card>
    </View>
  );
}
