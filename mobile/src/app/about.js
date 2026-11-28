/**
 * About (user, 2026-11-27): the app — Norban Group's logo and name, what it
 * is for, its version — the server it talks to and whether it answers, this
 * phone, and the company.
 */
import { useCallback, useEffect, useState } from "react";
import { Image, Linking, Pressable, View } from "react-native";
import { router } from "expo-router";
import { Building2, Code, Copyright, ExternalLink, Info, Layers, Package, Server, ShieldCheck, Smartphone, Sparkles } from "lucide-react-native";
import { Badge, Screen, Text } from "../components/ui";
import { PageHero, SectionCard } from "../components/hero";
import { APP_VERSION, request } from "../lib/api";
import { t } from "../lib/i18n";
import { thisPhone } from "../lib/phone";
import { serverLabel, useServer } from "../lib/server";
import { qcOf, useSession } from "../lib/session";
import { fonts, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const ab = (key, params) => t("mobile", `about.${key}`, params);
// The maker's website; the app version shows in the details only (user, 2026-11-28).
const MAKER_SITE = "https://datastateltd.com";

function Line({ icon: Icon, label, value, right, last = false }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 46, borderBottomWidth: last ? 0 : 1, borderBottomColor: colors.divider ?? colors.border }}>
      <View style={{ width: 30, height: 30, borderRadius: 15, backgroundColor: colors.brandSubtle, alignItems: "center", justifyContent: "center" }}>
        <Icon size={15} color={colors.brand} />
      </View>
      <Text style={{ fontSize: 12 }} tone="foregroundMuted">
        {label}
      </Text>
      <View style={{ flex: 1, alignItems: "flex-end" }}>{right ?? <Text style={{ fontSize: 13, fontFamily: fonts.semibold, color: colors.heading, textAlign: "right" }}>{value}</Text>}</View>
    </View>
  );
}

export default function About() {
  const colors = useColors();
  const server = useServer();
  const { session } = useSession();
  const [state, setState] = useState("checking");
  const [phone, setPhone] = useState("");
  const company = session?.kind === "PIN" ? qcOf(session)?.company?.name : session?.me?.company?.name;

  const check = useCallback(async () => {
    setState("checking");
    setState(await request("/health").then(() => "ready", () => "down"));
  }, []);

  useEffect(() => {
    check();
    thisPhone().then((p) => setPhone(`${p.model} · ${p.os}`));
  }, [check, server]);

  return (
    <Screen bleed header={<PageHero title={ab("title")} icon={Info} image="about" onBack={() => (router.canGoBack() ? router.back() : router.replace("/more"))} />}>
      <View style={[{ alignItems: "center", gap: space[2], paddingVertical: space[6], paddingHorizontal: space[4], borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface }, shadow(colors)]}>
        <Image source={require("../../assets/norban-logo.png")} style={{ width: 210, height: 66 }} resizeMode="contain" accessibilityLabel="Norban Group" />
        <Text variant="sectionTitle" style={{ textAlign: "center" }}>
          {ab("app_name")}
        </Text>
        <Text tone="foregroundMuted" style={{ fontSize: 13, textAlign: "center" }}>
          {ab("tagline")}
        </Text>
      </View>

      <SectionCard icon={Sparkles} title={ab("what")}>
        <Text style={{ fontSize: 13, lineHeight: 20 }}>{ab("what_text")}</Text>
      </SectionCard>

      {/* The product and who made it (user, 2026-11-27). */}
      <SectionCard icon={Package} title={ab("product_title")} bodyStyle={{ gap: 0 }}>
        <Line icon={Package} label={ab("product")} value="Annotech RMG" />
        {/* The maker's name opens its website (user, 2026-11-28). */}
        <Line
          icon={Building2}
          label={ab("made_by")}
          right={
            <Pressable accessibilityRole="link" onPress={() => Linking.openURL(MAKER_SITE)} hitSlop={8} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: 4, opacity: pressed ? 0.6 : 1 })}>
              <Text style={{ fontSize: 13, fontFamily: fonts.semibold, color: colors.brand, textDecorationLine: "underline" }}>Data State Ltd</Text>
              <ExternalLink size={13} color={colors.brand} />
            </Pressable>
          }
        />
        <Line icon={Code} label={ab("developer")} value="Shawonoor Rahman" last />
      </SectionCard>

      <SectionCard icon={Layers} title={ab("details")} bodyStyle={{ gap: 0 }}>
        <Line icon={Info} label={ab("app_version")} value={APP_VERSION} />
        <Line icon={Server} label={ab("server")} value={serverLabel(server)} />
        <Line icon={ShieldCheck} label={ab("server_state")} right={<Badge tone={{ checking: "info", ready: "success", down: "danger" }[state]}>{t("mobile", `server.${state}`)}</Badge>} />
        <Line icon={Smartphone} label={ab("device")} value={phone || "—"} />
        <Line icon={Building2} label={ab("company")} value={company ?? "—"} last />
      </SectionCard>

      <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "center", gap: space[1.5], paddingVertical: space[2] }}>
        <Copyright size={13} color={colors.foregroundSubtle} />
        <Text variant="helper" tone="foregroundSubtle">
          {ab("product_line")}
        </Text>
      </View>
    </Screen>
  );
}
