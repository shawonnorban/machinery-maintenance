/**
 * The bundle card's code, read by the camera (user, 2026-11-26: the cutting
 * QC scans with the phone's camera), with typing as the way round a damaged
 * card. From annotech-rmg's app, where it was proven on the floor:
 *
 *  - On Android the reading is done by GOOGLE'S scanner (Play services)
 *    wherever the phone has it: it focuses, zooms and lights the code itself,
 *    and needs no camera permission (it is Google's screen, not ours). It is
 *    fetched from the internet the first time; a phone that cannot (only the
 *    factory's own Wi-Fi) gets the in-app camera at once instead.
 *  - One read at a time: the in-app camera keeps seeing the card it just read,
 *    so the same code counts again only after two seconds out of view.
 */
import { useEffect, useRef, useState } from "react";
import { ActivityIndicator, Linking, Platform, Pressable, View } from "react-native";
import { CameraView, useCameraPermissions } from "expo-camera";
import { useIsFocused } from "expo-router";
import { Camera, Flashlight, FlashlightOff, Keyboard, ScanLine } from "lucide-react-native";
import { Button, Card, Input, Text } from "./ui";
import { t } from "../lib/i18n";
import { radius, space } from "../theme/tokens";
import { useColors } from "../theme/theme";

const m = (key, params) => t("mobile", `tally.${key}`, params);
const TYPES = ["qr", "code128", "code39", "ean13"];
const systemScanner = Platform.OS === "android" && CameraView.isModernBarcodeScannerAvailable;

/** `words`: the button and the typing field for what is scanned (a bundle card by default, a machine's label). */
export function Scanner({ onScan, busy = false, autoLaunch = true, words = {} }) {
  const colors = useColors();
  const [permission, requestPermission] = useCameraPermissions();
  const [inPage, setInPage] = useState(!systemScanner);
  const [launchError, setLaunchError] = useState(false);
  const [closedEmpty, setClosedEmpty] = useState(false);
  const [torch, setTorch] = useState(false);
  const [typed, setTyped] = useState("");
  const focused = useIsFocused();
  const working = useRef(false);
  const last = useRef({ text: null, at: 0 });

  const handle = async (raw, live = false) => {
    const text = String(raw ?? "").trim();
    if (text === "" || working.current || !focused) return;
    if (live && text === last.current.text && Date.now() - last.current.at < 2000) {
      last.current = { text, at: Date.now() };
      return;
    }
    working.current = true;
    last.current = { text, at: Date.now() };
    try {
      await onScan(text);
    } finally {
      working.current = false;
      last.current = { text, at: Date.now() };
    }
  };

  const latest = useRef(handle);
  latest.current = handle;

  useEffect(() => {
    if (!systemScanner) return undefined;
    const subscription = CameraView.onModernBarcodeScanned(({ data }) => latest.current(data));

    return () => subscription.remove();
  }, []);

  const launch = async () => {
    setLaunchError(false);
    setClosedEmpty(false);
    try {
      await CameraView.launchScanner({ barcodeTypes: TYPES });
    } catch (caught) {
      const reason = `${caught?.code ?? ""} ${caught?.message ?? ""}`;
      // Closed by the QC: stay. Anything else — above all a phone that never had the internet to
      // fetch Google's scanner (a factory's own Wi-Fi) — and the app's own camera takes over.
      if (/cancel/i.test(reason) && !/unavailable|not available|download/i.test(reason)) {
        setClosedEmpty(false);
        return;
      }
      setLaunchError(true);
      setInPage(true);
    }
  };

  // Opened at once: whoever opens a scan holds a card.
  useEffect(() => {
    if (systemScanner && autoLaunch) launch();
  }, []);

  const submitTyped = () => {
    const value = typed;
    setTyped("");
    handle(value);
  };

  return (
    <View style={{ gap: space[3] }}>
      {!inPage ? (
        <Button icon={ScanLine} onPress={launch} loading={busy} disabled={busy}>
          {words.scan ?? m("scan")}
        </Button>
      ) : permission === null ? (
        <ActivityIndicator color={colors.brand} />
      ) : permission.granted && focused ? (
        <View style={{ height: 260, borderRadius: radius.lg, overflow: "hidden", backgroundColor: colors.foreground }}>
          <CameraView style={{ flex: 1 }} facing="back" enableTorch={torch} barcodeScannerSettings={{ barcodeTypes: TYPES }} onBarcodeScanned={({ data }) => handle(data, true)} />
          <View pointerEvents="none" style={{ position: "absolute", top: 40, bottom: 40, left: "15%", right: "15%", borderWidth: 3, borderColor: colors.brandForeground, borderRadius: radius.md, opacity: 0.85 }} />
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={torch ? m("torch_off") : m("torch_on")}
            onPress={() => setTorch((on) => !on)}
            style={{ position: "absolute", right: space[3], bottom: space[3], width: 48, height: 48, borderRadius: radius.full, backgroundColor: colors.overlay, alignItems: "center", justifyContent: "center" }}
          >
            {torch ? <FlashlightOff size={22} color={colors.brandForeground} /> : <Flashlight size={22} color={colors.brandForeground} />}
          </Pressable>
          {busy ? (
            <View style={{ position: "absolute", top: 0, bottom: 0, left: 0, right: 0, alignItems: "center", justifyContent: "center", backgroundColor: colors.overlay }}>
              <ActivityIndicator color={colors.brandForeground} />
            </View>
          ) : null}
        </View>
      ) : permission.granted ? (
        <View style={{ height: 260, borderRadius: radius.lg, backgroundColor: colors.foreground }} />
      ) : (
        <Card>
          <View style={{ alignItems: "center", gap: space[2] }}>
            <Camera size={32} color={colors.foregroundMuted} />
            <Text variant="cardTitle">{m("allow_title")}</Text>
            <Text variant="small" tone="foregroundMuted" style={{ textAlign: "center" }}>
              {m("allow_hint")}
            </Text>
          </View>
          {permission.canAskAgain ? (
            <Button onPress={requestPermission}>{m("allow")}</Button>
          ) : (
            <Button variant="outline" onPress={() => Linking.openSettings()}>
              {m("phone_settings")}
            </Button>
          )}
        </Card>
      )}

      {systemScanner ? (
        <Pressable accessibilityRole="button" onPress={() => setInPage((on) => !on)} style={{ minHeight: 40, alignItems: "center", justifyContent: "center" }}>
          <Text variant="small" tone="brand">
            {inPage ? m("use_google") : m("use_camera")}
          </Text>
        </Pressable>
      ) : null}
      {closedEmpty && !inPage ? (
        <Text variant="small" tone="foregroundMuted" style={{ textAlign: "center" }}>
          {m("closed_empty")}
        </Text>
      ) : null}
      {launchError ? (
        <Text variant="small" tone="foregroundMuted" style={{ textAlign: "center" }}>
          {m("launch_failed")}
        </Text>
      ) : null}

      <View style={{ flexDirection: "row", gap: space[2], alignItems: "center" }}>
        <Input icon={Keyboard} value={typed} onChangeText={setTyped} placeholder={words.type ?? m("type")} autoCapitalize="characters" autoCorrect={false} returnKeyType="search" onSubmitEditing={submitTyped} style={{ flex: 1 }} />
        <Button onPress={submitTyped} disabled={typed.trim() === "" || busy} style={{ minHeight: 46 }}>
          {m("find")}
        </Button>
      </View>
    </View>
  );
}
