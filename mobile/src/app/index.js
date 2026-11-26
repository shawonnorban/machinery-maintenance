/**
 * Home. Without a server it is the server screen, signed out the sign-in. A QC
 * sees who they are, where, their PIN's state, and each of their lines — one
 * press opens its tally (phase 2) — with what is still to send. A person
 * signed in by email sees their account, and their lines too if they are a QC.
 */
import { Alert as Ask, Pressable, View } from "react-native";
import { Redirect, router } from "expo-router";
import { ChevronRight, KeyRound, Lock, LogOut, RefreshCw, Settings, UserRoundCog, Wifi, WifiOff } from "lucide-react-native";
import { Alert, Badge, Button, Card, DetailRow, Header, IconButton, Screen, Text } from "../components/ui";
import { SyncBar } from "../components/tally";
import { timeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { useServer } from "../lib/server";
import { useSession } from "../lib/session";
import { space } from "../theme/tokens";
import { useColors } from "../theme/theme";
import { clock12 } from "../../../frontend/src/lib/clock";

const m = (key, params) => t("mobile", key, params);

export default function Home() {
  const server = useServer();
  const { session } = useSession();

  if (!server) return <Redirect href="/server" />;
  if (!session) return <Redirect href="/sign-in" />;

  return session.kind === "PIN" ? <QcHome /> : <PersonHome />;
}

function Net() {
  const colors = useColors();
  const { online } = useSession();
  const Icon = online ? Wifi : WifiOff;

  return (
    <View accessibilityLabel={m(online ? "home.online" : "home.offline")} style={{ flexDirection: "row", alignItems: "center", gap: space[1.5], paddingHorizontal: space[1] }}>
      <Icon size={20} color={online ? colors.success : colors.danger} />
      {online ? null : (
        <Text variant="small" tone="danger">
          {m("home.offline")}
        </Text>
      )}
    </View>
  );
}

function Top({ title }) {
  return (
    <Header title={title}>
      <Net />
      <IconButton icon={Settings} label={m("settings.title")} onPress={() => router.push("/settings")} />
    </Header>
  );
}

const askSignOut = (signOut) =>
  Ask.alert(m("home.sign_out_ask"), undefined, [
    { text: m("cancel"), style: "cancel" },
    { text: m("home.sign_out"), style: "destructive", onPress: signOut },
  ]);

function QcHome() {
  const { session, lock, signOut } = useSession();
  const { qc, company, phone, lines, shift, pin_default: isDefault, session: at } = session.profile;

  return (
    <Screen header={<Top title={company.name} />}>
      {session.offline ? <Alert tone="warning">{m("home.offline_in")}</Alert> : null}
      <Card>
        <View style={{ gap: space[1] }}>
          <Text variant="pageTitle">{qc.name}</Text>
          <Text variant="small" tone="foregroundMuted">
            {[qc.employee_id, qc.designation].filter(Boolean).join(" · ")}
          </Text>
        </View>
        {shift ? <DetailRow label={m("home.shift")} value={`${shift.name} · ${clock12(shift.start.slice(0, 5))} – ${clock12(shift.end.slice(0, 5))}`} /> : null}
        <DetailRow label={m("home.since")} value={timeOf(at.started_at)} />
        {phone.name ? <DetailRow label={m("home.phone")} value={phone.name} /> : null}
        {phone.line ? <DetailRow label={m("home.line")} value={phone.line} /> : null}
      </Card>

      {isDefault ? (
        <View style={{ gap: space[2] }}>
          <Alert tone="warning">{m("home.pin_default")}</Alert>
          {session.offline ? null : (
            <Button variant="outline" icon={KeyRound} onPress={() => router.push("/change-pin")}>
              {m("home.change_pin")}
            </Button>
          )}
        </View>
      ) : null}

      <Lines lines={lines} />
      <SyncBar />

      <View style={{ flexDirection: "row", gap: space[3] }}>
        <Button variant="outline" icon={Lock} onPress={lock} style={{ flex: 1 }}>
          {m("home.lock")}
        </Button>
        <Button variant="outline" icon={UserRoundCog} onPress={signOut} style={{ flex: 1 }}>
          {m("home.switch")}
        </Button>
      </View>
      {!isDefault && !session.offline ? (
        <Button variant="outline" icon={KeyRound} onPress={() => router.push("/change-pin")}>
          {m("home.change_pin")}
        </Button>
      ) : null}
    </Screen>
  );
}

/** Each line given to the QC: one press opens its tally. */
function Lines({ lines }) {
  const colors = useColors();

  return (
    <Card>
      <Text variant="sectionTitle">{m("home.lines")}</Text>
      {lines.length === 0 ? <Alert tone="warning">{m("home.no_lines")}</Alert> : null}
      {lines.map((l) => (
        <Pressable
          key={l.section + l.line_id}
          accessibilityRole="button"
          accessibilityLabel={`${m("tally.open")} ${l.label}`}
          onPress={() => router.push({ pathname: "/tally", params: { line: `${l.section}:${l.line_id}` } })}
          style={({ pressed }) => ({ minHeight: 64, flexDirection: "row", alignItems: "center", gap: space[3], paddingHorizontal: space[3], borderRadius: 12, borderWidth: 1, borderColor: colors.brand, backgroundColor: pressed ? colors.brandSubtle : colors.surface })}
        >
          <View style={{ justifyContent: "center" }}>
            <Badge tone={{ CUTTING: "info", SEWING: "brand", QUALITY: "success" }[l.section]}>{t("qtally", `section.${l.section}`)}</Badge>
          </View>
          <View style={{ flex: 1 }}>
            <Text variant="label">{l.label}</Text>
            <Text variant="small" tone="brand">
              {m("tally.open")}
            </Text>
          </View>
          <ChevronRight size={22} color={colors.brand} />
        </Pressable>
      ))}
    </Card>
  );
}

function PersonHome() {
  const { session, signOut, attachQc } = useSession();
  const { user, company } = session.me;
  const qc = session.qc;
  const refused = session.qcRefused;

  return (
    <Screen header={<Top title={company?.name ?? "Annotech ERP"} />}>
      {user?.must_change_password ? <Alert tone="warning">{m("home.must_change")}</Alert> : null}
      <Card>
        <Text variant="pageTitle">{user?.name}</Text>
        <DetailRow label={m("home.email")} value={user?.email} />
        <DetailRow label={m("home.company")} value={company?.name} />
        {qc?.phone?.name ? <DetailRow label={m("home.phone")} value={qc.phone.name} /> : null}
        <Text variant="small" tone="foregroundMuted">
          {m("home.modules_next")}
        </Text>
      </Card>
      {qc ? (
        <>
          <Lines lines={qc.lines} />
          <SyncBar />
        </>
      ) : refused?.reason === "PHONE_PENDING" || refused?.reason === "OTHER_LINE" || refused?.reason === "PHONE_BLOCKED" ? (
        <View style={{ gap: space[2] }}>
          <Alert tone="warning">{refused.reason === "PHONE_PENDING" ? m("tally.qc_pending", { code: refused.code ?? "" }) : refused.message}</Alert>
          <Button variant="outline" icon={RefreshCw} onPress={() => attachQc().catch(() => {})}>
            {m("tally.qc_check")}
          </Button>
        </View>
      ) : null}
      <Button variant="outline" icon={LogOut} onPress={() => askSignOut(signOut)}>
        {m("home.sign_out")}
      </Button>
    </Screen>
  );
}
