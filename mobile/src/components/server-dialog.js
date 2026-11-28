/**
 * The server's IP and port, in a popup over the sign-in screen (user,
 * 2026-11-27: the app opens on sign-in, and the ⚙ at its top right sets the
 * server): typed, tried with "Test connection", kept with "Save". Save tries
 * it too when the address was not tested as typed.
 */
import { useEffect, useState } from "react";
import { Keyboard, KeyboardAvoidingView, Modal, Pressable, View } from "react-native";
import { CheckCircle2, Plug, Save, Server, X } from "lucide-react-native";
import { Alert, Button, Input, Text } from "./ui";
import { DEADLINE_MS, request } from "../lib/api";
import { t } from "../lib/i18n";
import { apiUrl, currentServer, parseServer, serverLabel, setServer } from "../lib/server";
import { radius, space, touch } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const m = (key, params) => t("mobile", key, params);

export function ServerDialog({ open, onClose }) {
  const colors = useColors();
  const [host, setHost] = useState("");
  const [port, setPort] = useState("");
  const [portTyped, setPortTyped] = useState(false);
  const [busy, setBusy] = useState(null);
  const [error, setError] = useState(null);
  // The address that answered, as typed: Save keeps it without asking again.
  const [passed, setPassed] = useState(null);

  useEffect(() => {
    if (!open) return;
    const saved = currentServer();
    setHost(saved ? (saved.scheme === "https" ? `https://${saved.host}` : saved.host) : "");
    setPort(saved?.port ?? "");
    setError(null);
    setPassed(null);
    setPortTyped(false);
  }, [open]);

  const typed = parseServer(host, port);
  const ok = passed && typed && serverLabel(passed) === serverLabel(typed);
  const plain = (typed ?? currentServer())?.scheme !== "https";

  const test = async () => {
    Keyboard.dismiss();
    if (!typed) {
      setError(m("server.bad"));
      return null;
    }
    setBusy("test");
    setError(null);
    try {
      // A server that sends plain HTTP on to HTTPS (norbanit.com does) is kept as HTTPS:
      // a POST sent to the old address would arrive as a GET.
      const moved = await movedTo(typed);
      const next = moved ?? typed;
      await request("/health", { server: next });
      if (moved) {
        setHost(`https://${moved.host}`);
        setPort(moved.port);
      }
      setPassed(next);
      return next;
    } catch (caught) {
      setPassed(null);
      setError(caught.message);
      return null;
    } finally {
      setBusy(null);
    }
  };

  const save = async () => {
    const next = ok ? typed : await test();
    if (!next) return;
    setServer(next);
    onClose(true);
  };

  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={() => onClose(false)} statusBarTranslucent>
      <KeyboardAvoidingView behavior="padding" style={{ flex: 1, backgroundColor: colors.overlay, justifyContent: "center", padding: space[4] }}>
        <Pressable accessibilityLabel={m("cancel")} style={{ position: "absolute", top: 0, bottom: 0, left: 0, right: 0 }} onPress={() => onClose(false)} />
        <View style={[{ alignSelf: "center", width: "100%", maxWidth: 440, backgroundColor: colors.surface, borderRadius: radius.xl, padding: space[5], gap: space[4] }, shadow(colors, "md")]}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[3] }}>
            <View style={{ width: 44, height: 44, borderRadius: radius.full, backgroundColor: colors.brandSubtle, alignItems: "center", justifyContent: "center" }}>
              <Server size={22} color={colors.brand} />
            </View>
            <Text variant="sectionTitle" style={{ flex: 1 }}>
              {m("server.title")}
            </Text>
            <Pressable accessibilityRole="button" accessibilityLabel={m("cancel")} onPress={() => onClose(false)} style={{ width: touch, height: touch, alignItems: "center", justifyContent: "center", marginRight: -space[2] }}>
              <X size={22} color={colors.foregroundMuted} />
            </Pressable>
          </View>
          <Text variant="small" tone="foregroundMuted">
            {m("server.hint")}
          </Text>
          <View style={{ flexDirection: "row", gap: space[3] }}>
            <Input
              label={m("server.host")}
              value={host}
              onChangeText={(v) => {
                setHost(v);
                setError(null);
                // A domain (or https://) typed over an IP: its port goes with it, unless one was typed here.
                if (!portTyped && (/^https:/i.test(v.trim()) || /[a-z]/i.test(v.replace(/^https?:\/\//i, "")))) setPort("");
              }}
              placeholder="norbanit.com"
              keyboardType="url"
              autoCapitalize="none"
              autoCorrect={false}
              style={{ flex: 1 }}
            />
            <Input
              label={m("server.port")}
              value={port}
              onChangeText={(v) => {
                setPort(v.replace(/\D/g, "").slice(0, 5));
                setPortTyped(true);
                setError(null);
              }}
              keyboardType="number-pad"
              placeholder={typed?.scheme === "https" ? "443" : "8010"}
              onSubmitEditing={test}
              style={{ width: 96 }}
            />
          </View>
          {error ? <Alert>{error}</Alert> : null}
          {ok ? (
            <View accessibilityRole="alert" style={{ flexDirection: "row", alignItems: "center", gap: space[2], backgroundColor: colors.successSubtle, borderRadius: radius.md, padding: space[3] }}>
              <CheckCircle2 size={20} color={colors.success} />
              <Text variant="label" tone="success" style={{ flex: 1 }}>
                {m("server.test_ok", { server: serverLabel(typed) })}
              </Text>
            </View>
          ) : null}
          <View style={{ flexDirection: "row", gap: space[3] }}>
            <Button variant="outline" icon={Plug} loading={busy === "test"} disabled={!host.trim() || Boolean(busy)} onPress={test} style={{ flex: 1, paddingHorizontal: space[3] }}>
              {m("server.test")}
            </Button>
            <Button icon={Save} loading={busy === "save"} disabled={!host.trim() || Boolean(busy)} onPress={save} style={{ flex: 1, paddingHorizontal: space[3] }}>
              {m("save")}
            </Button>
          </View>
          <Text variant="small" tone="foregroundSubtle">
            {m(plain ? "server.plain" : "server.secure")}
          </Text>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

/** Where plain HTTP ends up: the HTTPS address when the server sends it there, else null. */
async function movedTo(server) {
  if (server.scheme !== "http") return null;
  const stop = new AbortController();
  const timer = setTimeout(() => stop.abort(), DEADLINE_MS);
  try {
    const answer = await fetch(`${apiUrl(server)}/health`, { signal: stop.signal, headers: { Accept: "application/json" } });
    const final = /^https:\/\/([^/:]+)(?::(\d+))?/i.exec(answer.url ?? "");

    return final ? parseServer(`https://${final[1]}${final[2] ? `:${final[2]}` : ""}`, "") : null;
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

