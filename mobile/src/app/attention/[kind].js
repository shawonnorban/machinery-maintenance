/**
 * The orders a notification is about (user, 2026-11-27: "an Order Summary or
 * Dev Sample notification opens every order" — it must open its own). `os`:
 * the Order Summary's — the morning's past RFI/SD, due within 7 days and
 * booking pending, or (`serials`, from a changed-orders notice) those very
 * orders; `ds`: the Dev Samples past or near their submission deadline, or
 * the changed ones. Soonest first, each with why it is here.
 */
import { router, useLocalSearchParams } from "expo-router";
import { View } from "react-native";
import { CalendarClock, CalendarDays, ClipboardList, Clock, FileClock, Hash, PackageCheck, Shirt, TriangleAlert } from "lucide-react-native";
import { Screen, Text } from "../../components/ui";
import { HeroBell, IconDisc, KpiRow, KpiTile, PageHero, ROUND, StatusPill } from "../../components/hero";
import { LoadState, useLoad } from "../../components/manage";
import { t } from "../../lib/i18n";
import { fonts, space } from "../../theme/tokens";
import { shadow, useColors } from "../../theme/theme";

const an = (key, params) => t("mobile", `attn.${key}`, params);
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const dayOf = (d) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(d ?? ""));

  return m ? `${m[3]} ${MONTHS[Number(m[2]) - 1]} ${m[1]}` : "—";
};
const FLAG = {
  overdue: { tone: "danger", icon: TriangleAlert },
  week: { tone: "warning", icon: CalendarClock },
  soon: { tone: "warning", icon: CalendarClock },
  booking: { tone: "purple", icon: PackageCheck },
};
const PURPLE = "#7c5cf2";
const toneOf = (tone) => (tone === "purple" ? PURPLE : tone);

export default function Attention() {
  const { kind, serials } = useLocalSearchParams();
  const os = kind !== "ds";
  const path = os ? "/order-summary/orders/attention" : "/dev-sample/samples/attention";
  const load = useLoad(`${path}${serials ? `?serials=${encodeURIComponent(serials)}` : ""}`, [kind, serials]);
  const data = load.data;
  const counts = data?.counts ?? {};

  return (
    <Screen bleed header={<PageHero title={os ? an("os_title") : an("ds_title")} icon={os ? ClipboardList : FileClock} image="order" onBack={() => (router.canGoBack() ? router.back() : router.replace("/"))} right={<HeroBell />} />}>
      {serials ? null : (
        <KpiRow>
          <KpiTile icon={TriangleAlert} label={os ? an("overdue") : an("ds_overdue")} value={data ? String(counts.overdue ?? 0) : null} tone="danger" />
          <KpiTile icon={CalendarClock} label={os ? an("week") : an("soon")} value={data ? String((os ? counts.week : counts.soon) ?? 0) : null} tone="warning" />
          {os ? <KpiTile icon={PackageCheck} label={an("booking")} value={data ? String(counts.booking ?? 0) : null} tone={PURPLE} /> : null}
        </KpiRow>
      )}
      <Text tone="foregroundMuted" style={{ fontSize: 13 }}>
        {serials ? an("changed_hint") : os ? an("os_hint") : an("ds_hint")}
      </Text>
      <LoadState {...load} />
      {data && data.rows.length === 0 ? <Text tone="foregroundMuted">{an("none")}</Text> : null}
      {(data?.rows ?? []).map((r) => (os ? <OrderCard key={r.serial_no} r={r} /> : <SampleCard key={r.serial_no} r={r} />))}
    </Screen>
  );
}

/** How many days are left, as the pill reads it: "3 days left", "Today", "5 days late". */
function daysPill(days) {
  if (days === null || days === undefined) return null;
  if (days < 0) return { tone: "danger", label: an("late", { n: -days }) };
  if (days === 0) return { tone: "warning", label: an("today") };

  return { tone: days <= 7 ? "warning" : "success", label: an("left", { n: days }) };
}

function Frame({ icon, tone, children }) {
  const colors = useColors();

  return (
    <View style={[{ flexDirection: "row", gap: space[3], padding: space[3], borderRadius: ROUND, backgroundColor: colors.surface }, shadow(colors)]}>
      <IconDisc icon={icon} tone={tone} size={52} />
      <View style={{ flex: 1, minWidth: 0, gap: 3 }}>{children}</View>
    </View>
  );
}

function Line({ icon: Icon, children }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[1.5] }}>
      <Icon size={14} color={colors.foregroundMuted} />
      <Text tone="foregroundMuted" style={{ fontSize: 13, flex: 1 }} numberOfLines={1}>
        {children}
      </Text>
    </View>
  );
}

function OrderCard({ r }) {
  const first = r.flags?.[0];
  const look = FLAG[first] ?? { tone: "brand", icon: Shirt };
  const left = daysPill(r.days_left);

  return (
    <Frame icon={look.icon} tone={toneOf(look.tone)}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
        <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 16 }} numberOfLines={1}>
          {`#${r.serial_no}  ${r.style ?? "—"}`}
        </Text>
        {left ? <StatusPill size="sm" tone={left.tone} label={left.label} /> : null}
      </View>
      <Text style={{ fontSize: 14 }} numberOfLines={1}>
        {[r.buyer, r.item, r.department].filter(Boolean).join("  •  ")}
      </Text>
      {r.job_no ? <Line icon={Hash}>{`${an("job_no")}: ${r.job_no}`}</Line> : null}
      <Line icon={CalendarDays}>{`${an("rfi_sd")}: ${dayOf(r.rfi_sd)}  ·  ${an("ldd_etd")}: ${dayOf(r.ldd_etd)}`}</Line>
      <Line icon={Shirt}>{`${an("qty")}: ${r.qty === null ? "—" : Number(r.qty).toLocaleString("en-US")}${r.order_type ? `  ·  ${r.order_type}` : ""}`}</Line>
      {r.flags?.length ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[1.5], marginTop: 2 }}>
          {r.flags.map((f) => (
            <StatusPill key={f} size="sm" tone={toneOf(FLAG[f].tone)} icon={FLAG[f].icon} label={an(`flag_${f}`)} />
          ))}
        </View>
      ) : null}
    </Frame>
  );
}

function SampleCard({ r }) {
  const look = FLAG[r.flag] ?? { tone: "brand", icon: FileClock };
  const left = daysPill(r.days_left);

  return (
    <Frame icon={look.icon} tone={toneOf(look.tone)}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
        <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 16 }} numberOfLines={1}>
          {`#${r.serial_no}  ${r.styling ?? "—"}`}
        </Text>
        {left ? <StatusPill size="sm" tone={left.tone} label={left.label} /> : null}
      </View>
      <Text style={{ fontSize: 14 }} numberOfLines={1}>
        {[r.buyer, r.customer, r.season].filter(Boolean).join("  •  ")}
      </Text>
      {r.job_no ? <Line icon={Hash}>{`${an("job_no")}: ${r.job_no}`}</Line> : null}
      <Line icon={Clock}>{`${an("deadline")}: ${dayOf(r.deadline)}${r.status ? `  ·  ${r.status}` : ""}`}</Line>
      {r.qty !== null && r.qty !== undefined ? <Line icon={Shirt}>{`${an("qty")}: ${Number(r.qty).toLocaleString("en-US")}`}</Line> : null}
    </Frame>
  );
}
