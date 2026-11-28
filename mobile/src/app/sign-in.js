/**
 * Sign in: a QC by Company Code, Employee ID and PIN (user, 2026-11-25), a
 * person by Company Code, email and password. A new phone stops at "waiting
 * for approval"; with no network a QC seen here lately still gets in.
 * The app always opens here, even before a server is set: the ⚙ at the top
 * right sets it in a popup (user, 2026-11-27).
 *
 * Redesigned (user, 2026-11-28: "the login page looks plain"): the factory
 * and Norban's white logo on the blue hero, the form on a card rising over
 * it, the server and the product under it.
 */
import { useRef, useState } from "react";
import { Image, Keyboard, KeyboardAvoidingView, Pressable, ScrollView, View } from "react-native";
import { StatusBar } from "expo-status-bar";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Redirect } from "expo-router";
import { Building2, ClipboardCheck, IdCard, KeyRound, LogIn, Mail, RefreshCw, Server, Settings } from "lucide-react-native";
import { Alert, Button, Card, Chip, Header, IconButton, Input, Screen, Segmented, Text } from "../components/ui";
import { ServerDialog } from "../components/server-dialog";
import { HeroBackdrop } from "../components/hero";
import { LOCALES, applyLocale, currentLocale, t } from "../lib/i18n";
import { known, qcKey, recentQcs } from "../lib/qcs";
import { serverLabel, useServer } from "../lib/server";
import { lastCompany, useSession } from "../lib/session";
import { welcomed } from "../lib/welcome";
import { fonts, space, touch } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const m = (key, params) => t("mobile", key, params);

export default function SignIn() {
  const colors = useColors();
  const insets = useSafeAreaInsets();
  const hero = insets.top + 272;
  const server = useServer();
  const { session, ended } = useSession();
  const [mode, setMode] = useState("qc");
  const [company, setCompany] = useState(lastCompany);
  const [pending, setPending] = useState(null);
  const [serverOpen, setServerOpen] = useState(false);
  // Signing in with no server set opens the popup instead.
  const needServer = () => {
    if (server) return false;
    setServerOpen(true);
    return true;
  };

  if (session) return <Redirect href="/" />;
  // A new phone first chooses its language.
  if (!welcomed()) return <Redirect href="/welcome" />;

  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <StatusBar style="light" />
      <KeyboardAvoidingView behavior="padding" style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={{ flexGrow: 1, paddingBottom: insets.bottom + space[6] }} keyboardShouldPersistTaps="handled">
          {/* The hero: the factory on Norban's blue, the language and the server at the top, the white logo. */}
          <View style={{ height: hero }}>
            <HeroBackdrop height={hero} imageWidth="100%" wave={44} />
            <View style={{ marginTop: insets.top + space[2], paddingHorizontal: space[4], flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
              <Language light />
              <Pressable accessibilityRole="button" accessibilityLabel={m("server.title")} onPress={() => setServerOpen(true)} hitSlop={8} style={({ pressed }) => ({ width: 42, height: 42, borderRadius: 21, alignItems: "center", justifyContent: "center", backgroundColor: pressed ? "rgba(255,255,255,0.35)" : "rgba(255,255,255,0.2)", borderWidth: 1, borderColor: "rgba(255,255,255,0.35)" })}>
                <Settings size={21} color="#ffffff" />
              </Pressable>
            </View>
            <View style={{ alignItems: "center", gap: space[1], marginTop: space[5] }}>
              {/* Norban's logo (user, 2026-11-27): the app is Norban Group's. */}
              <Image source={require("../../assets/norban-logo-white.png")} style={{ width: 230, height: 70 }} resizeMode="contain" accessibilityLabel="Norban Group" />
              <Text style={{ color: "#ffffff", fontFamily: fonts.semibold, fontSize: 16, marginTop: space[2] }}>{m("sign_in.welcome")}</Text>
              <Text style={{ color: "#dbeafe", fontSize: 13 }}>{m("sign_in.welcome_sub")}</Text>
            </View>
          </View>

          <View style={{ marginTop: -54, paddingHorizontal: space[4], alignItems: "center", gap: space[4] }}>
            {server ? null : (
              <Pressable accessibilityRole="button" onPress={() => setServerOpen(true)} style={{ width: "100%", maxWidth: 480 }}>
                <Alert tone="info">{m("server.not_set")}</Alert>
              </Pressable>
            )}
            {pending ? (
              <View style={{ width: "100%", maxWidth: 480 }}>
                <Pending code={pending.code} onAgain={pending.again} onOther={() => setPending(null)} />
              </View>
            ) : null}
            {/* Kept under "waiting": what was typed is tried again, and a refusal is shown. */}
            <View style={[{ width: "100%", maxWidth: 480, borderRadius: 24, backgroundColor: colors.surface, padding: space[5], gap: space[4], borderWidth: 1, borderColor: colors.border }, shadow(colors, "md"), pending ? { display: "none" } : null]}>
              <View style={{ gap: 2 }}>
                <Text style={{ fontFamily: fonts.bold, fontSize: 20, color: colors.heading ?? colors.foreground }}>{m("sign_in.title")}</Text>
                <Text style={{ fontSize: 13 }} tone="foregroundMuted">
                  {mode === "qc" ? m("sign_in.qc_hint") : m("sign_in.email_hint")}
                </Text>
              </View>
              {ended ? <Alert tone="info">{m("sign_in.ended")}</Alert> : null}
              <Segmented
                value={mode}
                onChange={setMode}
                options={[
                  { value: "qc", label: m("sign_in.qc"), icon: ClipboardCheck },
                  { value: "email", label: m("sign_in.email_mode"), icon: Mail },
                ]}
              />
              {mode === "qc" ? <QcForm company={company} setCompany={setCompany} onPending={setPending} needServer={needServer} /> : <EmailForm company={company} setCompany={setCompany} needServer={needServer} />}
            </View>

            <Text variant="small" tone="foregroundMuted" style={{ textAlign: "center" }}>
              {m("sign_in.forgot")}
            </Text>
            {server ? (
              <Pressable accessibilityRole="button" onPress={() => setServerOpen(true)} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 36, paddingHorizontal: space[3], borderRadius: 18, backgroundColor: pressed ? colors.surfaceMuted : colors.surface, borderWidth: 1, borderColor: colors.border })}>
                <Server size={14} color={colors.brand} />
                <Text variant="small" tone="foregroundMuted">
                  {serverLabel(server)}
                </Text>
              </Pressable>
            ) : null}
            <Text variant="helper" tone="foregroundSubtle" style={{ textAlign: "center" }}>
              {t("mobile", "about.product_line")}
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
      <ServerDialog open={serverOpen} onClose={() => setServerOpen(false)} />
    </View>
  );
}

function Language({ light = false }) {
  const colors = useColors();
  const next = LOCALES.find((l) => l.value !== currentLocale()) ?? LOCALES[0];

  return (
    <Pressable
      accessibilityRole="button"
      onPress={() => applyLocale(next.value)}
      style={light ? { minHeight: 36, justifyContent: "center", paddingHorizontal: space[4], borderRadius: 18, backgroundColor: "rgba(255,255,255,0.2)", borderWidth: 1, borderColor: "rgba(255,255,255,0.35)" } : { minHeight: touch, justifyContent: "center", paddingHorizontal: space[3] }}
    >
      <Text variant="label" style={{ color: light ? "#ffffff" : colors.brand, fontFamily: next.value === "bn" ? fonts.bengaliSemibold : fonts.semibold }}>
        {next.label}
      </Text>
    </Pressable>
  );
}

function CompanyInput({ value, onChange, error, onNext }) {
  return <Input label={m("sign_in.company")} icon={Building2} value={value} onChangeText={onChange} error={error} hint={value ? undefined : m("sign_in.company_hint")} autoCapitalize="characters" returnKeyType="next" submitBehavior="submit" onSubmitEditing={onNext} maxLength={50} />;
}

/** What went wrong, in words: the server's own, or the phone's with no network. */
function explain(error, key) {
  if (error.reason === "OFFLINE_PIN") return error.check.minutes ? m("sign_in.offline_locked", { minutes: error.check.minutes }) : m("sign_in.offline_wrong", { left: error.check.left });
  if (error.status === 0 && key && !error.timedOut && !known(key) && recentQcs().length) return m("sign_in.offline_unknown", { days: 7 });

  return error.message;
}

function QcForm({ company, setCompany, onPending, needServer }) {
  const { signInPin } = useSession();
  const [employee, setEmployee] = useState("");
  const [pin, setPin] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const employeeRef = useRef(null);
  const pinRef = useRef(null);
  const recent = recentQcs().filter((r) => !company.trim() || r.company_code === company.trim().toUpperCase());

  const submit = async () => {
    if (busy || !company.trim() || !employee.trim() || pin.length !== 6) return;
    Keyboard.dismiss();
    if (needServer()) return;
    setBusy(true);
    setError(null);
    try {
      await signInPin(company, employee, pin);
      onPending(null);
    } catch (caught) {
      if (caught.reason === "PHONE_PENDING") {
        onPending({ code: caught.meta.code, again: submit });
        // Kept for "try again"; every other failure clears the PIN.
      } else {
        onPending(null);
        setPin("");
        setError(explain(caught, qcKey(company, employee)));
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={{ gap: space[4] }}>
      {error ? <Alert>{error}</Alert> : null}
      <CompanyInput value={company} onChange={setCompany} onNext={() => employeeRef.current?.focus()} />
      <Input label={m("sign_in.employee")} icon={IdCard} inputRef={employeeRef} value={employee} onChangeText={setEmployee} autoCapitalize="characters" returnKeyType="next" submitBehavior="submit" onSubmitEditing={() => pinRef.current?.focus()} maxLength={50} />
      {recent.length ? (
        <View style={{ gap: space[2] }}>
          <Text variant="small" tone="foregroundMuted">
            {m("sign_in.recent")}
          </Text>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
            {recent.map((r) => (
              <Chip
                key={r.key}
                on={employee.trim().toUpperCase() === r.employee_id.toUpperCase()}
                onPress={() => {
                  setCompany(r.company_code);
                  setEmployee(r.employee_id);
                  pinRef.current?.focus();
                }}
              >
                {`${r.name} · ${r.employee_id}`}
              </Chip>
            ))}
          </View>
        </View>
      ) : null}
      <Input label={m("sign_in.pin")} icon={KeyRound} inputRef={pinRef} secure value={pin} onChangeText={(v) => setPin(v.replace(/\D/g, "").slice(0, 6))} keyboardType="number-pad" returnKeyType="go" onSubmitEditing={submit} maxLength={6} />
      <Button icon={LogIn} loading={busy} disabled={!company.trim() || !employee.trim() || pin.length !== 6} onPress={submit}>
        {m("sign_in.go")}
      </Button>
    </View>
  );
}

function EmailForm({ company, setCompany, needServer }) {
  const { signInEmail } = useSession();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const emailRef = useRef(null);
  const passwordRef = useRef(null);
  const fieldError = (name) => error?.field?.(name);

  const submit = async () => {
    if (busy || !company.trim() || !email.trim() || !password) return;
    Keyboard.dismiss();
    if (needServer()) return;
    setBusy(true);
    setError(null);
    try {
      await signInEmail(company, email, password);
    } catch (caught) {
      setError(caught);
      setBusy(false);
    }
  };

  return (
    <View style={{ gap: space[4] }}>
      {error && !fieldError("email") && !fieldError("company_code") ? <Alert>{error.message}</Alert> : null}
      <CompanyInput value={company} onChange={setCompany} error={fieldError("company_code")} onNext={() => emailRef.current?.focus()} />
      <Input label={m("sign_in.email")} icon={Mail} inputRef={emailRef} value={email} onChangeText={setEmail} error={fieldError("email")} keyboardType="email-address" autoCapitalize="none" autoComplete="email" returnKeyType="next" submitBehavior="submit" onSubmitEditing={() => passwordRef.current?.focus()} />
      <Input label={m("sign_in.password")} icon={KeyRound} inputRef={passwordRef} secure value={password} onChangeText={setPassword} autoCapitalize="none" autoComplete="current-password" returnKeyType="go" onSubmitEditing={submit} />
      <Button icon={LogIn} loading={busy} disabled={!company.trim() || !email.trim() || !password} onPress={submit}>
        {m("sign_in.go")}
      </Button>
    </View>
  );
}

function Pending({ code, onAgain, onOther }) {
  const [busy, setBusy] = useState(false);

  return (
    <Card>
      <Text variant="sectionTitle">{m("pending.title")}</Text>
      <Text tone="foregroundMuted">{m("pending.body")}</Text>
      <Text variant="figure" selectable style={{ textAlign: "center", letterSpacing: 6, paddingVertical: space[3] }}>
        {code}
      </Text>
      <Button
        icon={RefreshCw}
        loading={busy}
        onPress={async () => {
          setBusy(true);
          await onAgain();
          setBusy(false);
        }}
      >
        {m("pending.again")}
      </Button>
      <Button variant="outline" onPress={onOther}>
        {m("pending.other")}
      </Button>
    </Card>
  );
}
