/**
 * Sign in: a QC by Company Code, Employee ID and PIN (user, 2026-11-25), a
 * person by Company Code, email and password. A new phone stops at "waiting
 * for approval"; with no network a QC seen here lately still gets in.
 */
import { useRef, useState } from "react";
import { Image, Keyboard, Pressable, View } from "react-native";
import { Redirect, router } from "expo-router";
import { Building2, IdCard, KeyRound, LogIn, Mail, RefreshCw, Settings } from "lucide-react-native";
import { Alert, Button, Card, Chip, Header, IconButton, Input, Screen, Segmented, Text } from "../components/ui";
import { LOCALES, applyLocale, currentLocale, t } from "../lib/i18n";
import { known, qcKey, recentQcs } from "../lib/qcs";
import { serverLabel, useServer } from "../lib/server";
import { lastCompany, useSession } from "../lib/session";
import { radius, space, touch } from "../theme/tokens";
import { useColors } from "../theme/theme";

const m = (key, params) => t("mobile", key, params);

export default function SignIn() {
  const server = useServer();
  const { session, ended } = useSession();
  const [mode, setMode] = useState("qc");
  const [company, setCompany] = useState(lastCompany);
  const [pending, setPending] = useState(null);

  if (!server) return <Redirect href="/server" />;
  if (session) return <Redirect href="/" />;

  return (
    <Screen
      header={
        <Header title="">
          <Language />
          <IconButton icon={Settings} label={m("settings.title")} onPress={() => router.push("/settings")} />
        </Header>
      }
    >
      <View style={{ alignItems: "center", gap: space[2], paddingTop: space[2] }}>
        <Image source={require("../../assets/icon.png")} style={{ width: 64, height: 64, borderRadius: radius.lg }} accessibilityIgnoresInvertColors />
        <Text variant="pageTitle" tone="brand">
          Annotech ERP
        </Text>
      </View>

      {pending ? <Pending code={pending.code} onAgain={pending.again} onOther={() => setPending(null)} /> : null}
      {/* Kept under "waiting": what was typed is tried again, and a refusal is shown. */}
      <Card style={pending ? { display: "none" } : null}>
        {ended ? <Alert tone="info">{m("sign_in.ended")}</Alert> : null}
        <Segmented
          value={mode}
          onChange={setMode}
          options={[
            { value: "qc", label: m("sign_in.qc") },
            { value: "email", label: m("sign_in.email_mode") },
          ]}
        />
        {mode === "qc" ? <QcForm company={company} setCompany={setCompany} onPending={setPending} /> : <EmailForm company={company} setCompany={setCompany} />}
      </Card>

      <Text variant="small" tone="foregroundMuted" style={{ textAlign: "center" }}>
        {m("sign_in.forgot")}
      </Text>
      <Text variant="small" tone="foregroundSubtle" style={{ textAlign: "center" }}>
        {serverLabel(server)}
      </Text>
    </Screen>
  );
}

function Language() {
  const colors = useColors();
  const next = LOCALES.find((l) => l.value !== currentLocale()) ?? LOCALES[0];

  return (
    <Pressable accessibilityRole="button" onPress={() => applyLocale(next.value)} style={{ minHeight: touch, justifyContent: "center", paddingHorizontal: space[3] }}>
      <Text variant="label" style={{ color: colors.brand, fontFamily: next.value === "bn" ? "AnekBangla_600SemiBold" : "Inter_600SemiBold" }}>
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

function QcForm({ company, setCompany, onPending }) {
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
      <Text variant="small" tone="foregroundMuted">
        {m("sign_in.qc_hint")}
      </Text>
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

function EmailForm({ company, setCompany }) {
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
      <Text variant="small" tone="foregroundMuted">
        {m("sign_in.email_hint")}
      </Text>
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
