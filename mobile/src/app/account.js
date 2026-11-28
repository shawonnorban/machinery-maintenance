/**
 * Account (user, 2026-11-27): who is signed in, their employee information
 * (the HR record by their employee code — or, for a QC by PIN, their own
 * details, phone and line), the language and the theme, and for a QC the
 * PIN and the lock.
 */
import { View } from "react-native";
import { router } from "expo-router";
import { BadgeInfo, Building2, CalendarCheck, CircleUserRound, Droplet, IdCard, KeyRound, Languages, Layers, Lock, Mail, MapPin, Moon, Palette, Phone, Smartphone, Sun, UserRoundCog } from "lucide-react-native";
import { Avatar, Badge, Divider, ListRow, Screen, Segmented, Text } from "../components/ui";
import { PageHero, SectionCard } from "../components/hero";
import { MODES, setAppearance, useAppearance } from "../lib/appearance";
import { LOCALES, applyLocale, currentLocale, t } from "../lib/i18n";
import { qcOf, useSession } from "../lib/session";
import { fonts, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const ac = (key, params) => t("mobile", `acct.${key}`, params);
const m = (key, params) => t("mobile", key, params);
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const day = (d) => (d && /^\d{4}-\d{2}-\d{2}/.test(d) ? `${d.slice(8, 10)} ${MONTHS[Number(d.slice(5, 7)) - 1]} ${d.slice(0, 4)}` : d);

/** One line of information: its icon, what it is, and the value on the right. */
function Info({ icon: Icon, label, value, last = false }) {
  const colors = useColors();
  if (value === null || value === undefined || value === "") return null;

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], minHeight: 44, borderBottomWidth: last ? 0 : 1, borderBottomColor: colors.divider ?? colors.border }}>
      <View style={{ width: 30, height: 30, borderRadius: 15, backgroundColor: colors.brandSubtle, alignItems: "center", justifyContent: "center" }}>
        <Icon size={15} color={colors.brand} />
      </View>
      <Text style={{ fontSize: 12 }} tone="foregroundMuted">
        {label}
      </Text>
      <Text style={{ flex: 1, textAlign: "right", fontSize: 13, fontFamily: fonts.semibold, color: colors.heading }} numberOfLines={2}>
        {value}
      </Text>
    </View>
  );
}

export default function Account() {
  const colors = useColors();
  const { session, lock, signOut } = useSession();
  const appearance = useAppearance();
  const pin = session.kind === "PIN";
  const qc = qcOf(session);
  const user = pin ? null : session.me.user;
  const emp = user?.employee ?? null;
  const name = pin ? qc.qc.name : user?.name;
  const company = pin ? qc.company.name : session.me.company?.name;
  const designation = pin ? qc.qc.designation : user?.designation;
  const modeIcon = { light: Sun, dark: Moon, system: Smartphone };

  const rows = pin
    ? [
        [IdCard, ac("employee_id"), qc.qc.employee_id],
        [BadgeInfo, ac("designation"), qc.qc.designation],
        [Building2, ac("company"), company],
        [Smartphone, ac("phone_device"), qc.phone?.name],
        [MapPin, ac("line"), qc.phone?.line],
      ]
    : [
        [IdCard, ac("employee_id"), emp?.code],
        [IdCard, ac("card_no"), emp?.card_no],
        [BadgeInfo, ac("designation"), emp?.designation ?? designation],
        [Layers, ac("department"), emp?.department],
        [Layers, ac("section"), emp?.section],
        [MapPin, ac("line"), emp?.line],
        [Building2, ac("factory"), emp?.factory],
        [CalendarCheck, ac("joining"), day(emp?.joining_date)],
        [Phone, ac("mobile"), emp?.mobile],
        [Droplet, ac("blood"), emp?.blood_group],
        [Mail, ac("email"), user?.email],
        [Building2, ac("company"), company],
      ];
  const shown = rows.filter((r) => r[2]);

  return (
    <Screen bleed header={<PageHero title={ac("title")} icon={CircleUserRound} image="account" onBack={() => (router.canGoBack() ? router.back() : router.replace("/more"))} />}>
      <View style={[{ alignItems: "center", gap: space[2], paddingVertical: space[5], borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface }, shadow(colors)]}>
        <Avatar name={name} size={72} />
        <Text variant="pageTitle" style={{ textAlign: "center" }}>
          {name}
        </Text>
        {designation || company ? (
          <Text tone="foregroundMuted" style={{ fontSize: 13, textAlign: "center" }}>
            {[designation, company].filter(Boolean).join(" · ")}
          </Text>
        ) : null}
        <View style={{ alignItems: "center" }}>
          <Badge tone="brand">{pin ? m("more.by_pin") : m("more.by_email")}</Badge>
        </View>
      </View>

      <SectionCard icon={IdCard} title={ac("employee_info")} bodyStyle={{ gap: 0 }}>
        {shown.map(([icon, label, value], i) => (
          <Info key={label} icon={icon} label={label} value={value} last={i === shown.length - 1} />
        ))}
        {!pin && !emp ? (
          <Text variant="helper" tone="foregroundSubtle" style={{ paddingTop: space[2] }}>
            {ac("no_hr")}
          </Text>
        ) : null}
      </SectionCard>

      <SectionCard icon={Languages} title={m("settings.language")}>
        <Segmented value={currentLocale()} onChange={applyLocale} options={LOCALES} />
      </SectionCard>

      <SectionCard icon={Palette} title={ac("theme")}>
        <Segmented value={appearance} onChange={setAppearance} options={MODES.map((value) => ({ value, label: m(`settings.${value}`), icon: modeIcon[value] }))} />
        <Text variant="helper" tone="foregroundSubtle">
          {ac("theme_hint")}
        </Text>
      </SectionCard>

      {pin ? (
        <SectionCard icon={KeyRound} title={ac("security")} bodyStyle={{ gap: 0 }}>
          {session.offline ? null : (
            <>
              <ListRow icon={KeyRound} title={m("home.change_pin")} tone="brand" onPress={() => router.push("/change-pin")} />
              <Divider inset={34} />
            </>
          )}
          <ListRow icon={Lock} title={m("home.lock")} tone="brand" onPress={lock} />
          <Divider inset={34} />
          <ListRow icon={UserRoundCog} title={m("home.switch")} tone="brand" onPress={signOut} />
        </SectionCard>
      ) : null}
    </Screen>
  );
}
