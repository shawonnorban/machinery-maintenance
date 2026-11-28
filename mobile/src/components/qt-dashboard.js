/**
 * Production (AnnotechERP 37; the reference screen, user 2026-11-27): the
 * chosen department's day — Target, Achieved, Progress, Shortfall — its
 * trend (today hour by hour, the week and the month day by day, against the
 * target), each department this person sees (Cutting, Sewing, Quality: its
 * achievement, pass of target; a tap chooses it), and the latest hours'
 * records — on track or behind their hourly target. The lines and jobs, and
 * the top defects, open under "View Details". Read again every minute.
 */
import { useEffect, useState } from "react";
import { Pressable, View } from "react-native";
import { ChartColumnBig, ChartPie, CircleAlert, CircleCheck, Gauge, List, Target, TrendingUp } from "lucide-react-native";
import { TileIcon } from "./tile-icon";
import { Text } from "./ui";
import { Sheet } from "./tally";
import { DateBar, LoadState, q, shiftDay, todayDhaka, useAccess, useLoad } from "./manage";
import { KpiRow, KpiTile, ROUND, SectionCard, StatusPill } from "./hero";
import { TrendChart, TrendLegend } from "./trend-chart";
import { clock12 } from "../../../frontend/src/lib/clock";
import { currentLocale, t } from "../lib/i18n";
import { chooseSection, useSection } from "../lib/section";
import { fonts, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

const pt = (key, params) => t("mobile", `prod.${key}`, params);
const n0 = (v) => (v === null || v === undefined ? null : Math.round(Number(v)).toLocaleString("en-US"));
// The user's pictures for the three departments (2026-11-28).
const TILE = { CUTTING: "cutting", SEWING: "sewing", QUALITY: "quality" };
const PURPLE = "#7c5cf2";
/** 8 → "8am", 13 → "1pm". */
const hourShort = (h) => `${h % 12 || 12}${h < 12 ? "am" : "pm"}`;

/** The day Production shows, chosen from the header's date pill. */
export function useProductionDate() {
  return useState(todayDhaka());
}

/** The date pill's sheet: the day before, the day after, back to today. */
export function DateSheet({ open, onClose, value, onChange }) {
  return (
    <Sheet open={open} onClose={onClose} title={pt("pick_day")}>
      <DateBar
        value={value}
        max={todayDhaka()}
        onChange={(d) => {
          onChange(d);
        }}
      />
    </Sheet>
  );
}

export function QtDashboard({ date }) {
  const access = useAccess();
  const chosen = useSection(access);
  if (!chosen) return null;

  return <Production key={chosen.section} section={chosen.section} access={access} date={date} />;
}

function Production({ section, access, date }) {
  const colors = useColors();
  const load = useLoad(`/quality-tally/dashboard?section=${section}&date=${date}`, [date]);
  const { data, reload } = load;
  const [range, setRange] = useState("TODAY");
  const [details, setDetails] = useState(false);
  const [all, setAll] = useState(false);

  useEffect(() => {
    const timer = setInterval(reload, 60000);
    return () => clearInterval(timer);
  }, [reload]);

  const tot = data?.sections?.[section] ?? null;
  const target = tot?.target ?? null;
  const pass = tot?.pass ?? null;
  const progress = target ? (pass / target) * 100 : null;
  const shortfall = target === null || pass === null ? null : Math.max(0, target - pass);

  // The hours of this section: the hourly target is the sum of its lines' and jobs'.
  const hourTarget = (data?.rows ?? []).reduce((a, r) => a + (r.hourly_target ?? 0), 0) || null;
  const hours = (data?.hourly ?? []).filter((h) => h.section === section).sort((a, b) => a.hour - b.hour);
  const records = [...hours].reverse();

  return (
    <>
      <KpiRow>
        <KpiTile icon={Target} label={q("kpi.target")} value={n0(target)} unit={pt("pcs")} tone="brand" valueTone="foreground" />
        <KpiTile icon={CircleCheck} label={pt("achieved")} value={n0(pass)} unit={pt("pcs")} tone="success" />
        <KpiTile icon={Gauge} label={pt("progress")} value={progress === null ? null : `${progress.toFixed(1)}%`} tone="brand">
          <View style={{ alignSelf: "stretch", marginHorizontal: space[3], marginTop: 4, height: 5, borderRadius: 3, backgroundColor: colors.surfaceMuted, overflow: "hidden" }}>
            <View style={{ width: `${Math.max(0, Math.min(100, progress ?? 0))}%`, height: 5, borderRadius: 3, backgroundColor: colors.brand }} />
          </View>
        </KpiTile>
        <KpiTile icon={TrendingUp} label={pt("shortfall")} value={n0(shortfall)} unit={pt("pcs")} tone={PURPLE} valueTone="danger" />
      </KpiRow>
      <LoadState {...load} />

      <Trend section={section} date={date} range={range} onRange={setRange} hours={hours} target={target} />

      <SectionCard icon={ChartPie} title={pt("by_department")} action={details ? pt("hide_details") : pt("view_details")} onAction={() => setDetails((d) => !d)}>
        <Departments access={access} section={section} date={date} />
      </SectionCard>
      {details && data ? <LineDetails data={data} section={section} /> : null}

      <SectionCard
        icon={List}
        title={pt("recent")}
        action={records.length > 3 ? (all ? pt("show_less") : pt("view_all")) : null}
        onAction={() => setAll((a) => !a)}
        bodyStyle={{ paddingHorizontal: 0, gap: 0 }}
      >
        <Records rows={all ? records : records.slice(0, 3)} section={section} hourTarget={hourTarget} />
      </SectionCard>
    </>
  );
}

/** The trend card: its blue band with Today / Week / Month, the legend and the chart. */
function Trend({ section, date, range, onRange, hours, target }) {
  const colors = useColors();
  const days = range === "WEEK" ? 7 : 30;
  const analytics = useLoad(range === "TODAY" ? null : `/quality-tally/analytics?section=${section}&from=${shiftDay(date, -(days - 1))}&to=${date}`, [range, date, section]);

  let labels = [];
  let achieved = [];
  let goal = target;
  let showLabel = () => true;
  if (range === "TODAY") {
    // From the first hour tapped (8am at the latest) to the last, the pass adding up.
    const first = Math.min(8, ...hours.map((h) => h.hour));
    const lastHour = Math.max(first + 4, ...hours.map((h) => h.hour));
    let sum = 0;
    for (let h = first; h <= lastHour; h++) {
      const row = hours.find((x) => x.hour === h);
      sum += row?.pass ?? 0;
      labels.push(hourShort(h));
      achieved.push(hours.some((x) => x.hour <= h) ? sum : null);
    }
    const step = Math.max(1, Math.ceil((labels.length - 1) / 3));
    showLabel = (i) => i % step === 0 || i === labels.length - 1;
  } else {
    const list = analytics.data?.days ?? [];
    labels = list.map((d) => `${Number(d.date.slice(8, 10))}/${Number(d.date.slice(5, 7))}`);
    achieved = list.map((d) => d.pass ?? 0);
    goal = list.map((d) => d.target ?? null);
    const step = range === "WEEK" ? 2 : 7;
    showLabel = (i) => i % step === 0 || i === labels.length - 1;
  }

  return (
    <View style={[{ borderRadius: ROUND, backgroundColor: colors.surface, overflow: "hidden" }, shadow(colors)]}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], paddingHorizontal: space[4], paddingVertical: space[3], backgroundColor: "#1546a8" }}>
        <ChartColumnBig size={22} color="#ffffff" strokeWidth={2.4} />
        <Text style={{ flex: 1, color: "#ffffff", fontFamily: fonts.bold, fontSize: 15 }} numberOfLines={1} adjustsFontSizeToFit minimumFontScale={0.8}>
          {pt("trend")}
        </Text>
        <View style={{ flexDirection: "row", padding: 3, borderRadius: 18, backgroundColor: "rgba(255,255,255,0.16)" }}>
          {["TODAY", "WEEK", "MONTH"].map((r) => (
            <Pressable key={r} accessibilityRole="button" accessibilityState={{ selected: range === r }} onPress={() => onRange(r)} style={{ paddingHorizontal: 10, height: 30, borderRadius: 15, justifyContent: "center", backgroundColor: range === r ? "#ffffff" : "transparent" }}>
              <Text style={{ fontSize: 12, fontFamily: fonts.semibold, color: range === r ? "#1546a8" : "#dbe7fb" }}>{pt(`range_${r.toLowerCase()}`)}</Text>
            </Pressable>
          ))}
        </View>
      </View>
      <View style={{ padding: space[3], gap: space[2] }}>
        <TrendLegend target={q("kpi.target")} achieved={pt("achieved")} />
        {range !== "TODAY" && !analytics.data ? <LoadState {...analytics} /> : null}
        {labels.length ? <TrendChart labels={labels} achieved={achieved} target={goal} showLabel={showLabel} /> : null}
      </View>
    </View>
  );
}

/** Each department this person sees, side by side: achievement and pass of target; a tap chooses it. */
function Departments({ access, section, date }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row" }}>
      {access.map((a, i) => [
        i ? <View key={`rule${a.section}`} style={{ width: 1, marginVertical: space[3], backgroundColor: colors.border }} /> : null,
        <Department key={a.section} section={a.section} chosen={a.section === section} date={date} />,
      ])}
    </View>
  );
}

function Department({ section, chosen, date }) {
  const colors = useColors();
  const load = useLoad(`/quality-tally/dashboard?section=${section}&date=${date}`, [date]);
  const tot = load.data?.sections?.[section] ?? null;
  const pct = tot?.target ? (tot.pass / tot.target) * 100 : null;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: chosen }}
      onPress={() => chooseSection(section)}
      style={{ flex: 1, alignItems: "center", gap: 3, paddingVertical: space[2], marginHorizontal: 2, borderRadius: 12, backgroundColor: chosen ? colors.brandSubtle : "transparent" }}
    >
      <TileIcon image={TILE[section]} size={50} />
      <Text style={{ fontSize: 14 }} numberOfLines={1}>
        {q(`section.${section}`)}
      </Text>
      <Text style={{ fontFamily: fonts.bold, fontSize: 19, color: colors.brand }}>{pct === null ? "—" : `${pct.toFixed(1)}%`}</Text>
      <Text tone="foregroundMuted" style={{ fontSize: 12 }} numberOfLines={1}>
        {tot ? `${n0(tot.pass)} / ${n0(tot.target ?? 0)}` : "—"}
      </Text>
    </Pressable>
  );
}

/** The latest hours: time, department, the hourly target, what passed, on track or behind. */
function Records({ rows, section, hourTarget }) {
  const colors = useColors();
  const head = (label, flex, align = "left") => (
    <Text style={{ flex, textAlign: align, fontFamily: fonts.semibold, fontSize: 13 }} numberOfLines={1}>
      {label}
    </Text>
  );

  return (
    <View>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[1], paddingHorizontal: space[4], paddingVertical: space[3], backgroundColor: colors.brandSubtle }}>
        {head(pt("time"), 1.15)}
        {head(pt("department"), 1.15)}
        {head(q("kpi.target"), 0.9, "right")}
        {head(pt("achieved"), 0.95, "right")}
        {head(pt("status"), 1.25, "center")}
      </View>
      {rows.length === 0 ? (
        <Text tone="foregroundMuted" style={{ paddingHorizontal: space[4], paddingTop: space[3] }}>
          {q("empty.dashboard")}
        </Text>
      ) : null}
      {rows.map((r, i) => {
        const onTrack = hourTarget ? r.pass >= hourTarget * 0.95 : null;

        return (
          <View key={r.hour} style={{ flexDirection: "row", alignItems: "center", gap: space[1], marginHorizontal: space[4], paddingVertical: 10, minHeight: 52, borderBottomWidth: i === rows.length - 1 ? 0 : 1, borderBottomColor: colors.border }}>
            <Text style={{ flex: 1.15, fontSize: 14 }} numberOfLines={1}>
              {clock12(`${String(r.hour).padStart(2, "0")}:00`)}
            </Text>
            <Text style={{ flex: 1.15, fontSize: 14 }} numberOfLines={1}>
              {q(`section.${section}`)}
            </Text>
            <Text style={{ flex: 0.9, fontSize: 14, textAlign: "right" }}>{hourTarget ? n0(hourTarget) : "—"}</Text>
            <Text style={{ flex: 0.95, fontSize: 14, textAlign: "right" }}>{n0(r.pass)}</Text>
            <View style={{ flex: 1.25, alignItems: "center" }}>
              {onTrack === null ? (
                <Text tone="foregroundSubtle">—</Text>
              ) : (
                <StatusPill size="sm" tone={onTrack ? "success" : "warning"} icon={onTrack ? CircleCheck : CircleAlert} label={onTrack ? pt("on_track") : pt("behind")} />
              )}
            </View>
          </View>
        );
      })}
    </View>
  );
}

/** Under "View Details": each line and job, and the day's top defects. */
function LineDetails({ data, section }) {
  const colors = useColors();
  const bn = currentLocale() === "bn";
  const rows = data.rows.filter((r) => r.section === section);
  const pct = (v) => (v === null || v === undefined ? "—" : `${Number(v).toFixed(1)}%`);

  return (
    <SectionCard icon={List} title={q("dash.lines_title")}>
      {rows.length === 0 ? <Text tone="foregroundMuted">{q("empty.dashboard")}</Text> : null}
      {rows.map((r) => (
        <View key={r.key} style={{ gap: 2, paddingBottom: space[2], borderBottomWidth: 1, borderBottomColor: colors.border }}>
          <View style={{ flexDirection: "row", gap: space[2] }}>
            <Text style={{ flex: 1, fontFamily: fonts.bold }} numberOfLines={1}>
              {r.line}
            </Text>
            <Text style={{ fontFamily: fonts.bold, color: r.achievement >= 100 ? colors.success : colors.brand }}>{pct(r.achievement)}</Text>
          </View>
          <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
            {[r.job_no, r.buyer_name, r.style].filter(Boolean).join(" · ")}
          </Text>
          <Text variant="small" numberOfLines={1}>
            {`${q("kpi.target")} ${n0(r.target) ?? "—"} · ${q("kpi.pass")} ${n0(r.pass)} · ${q("kpi.dhu")} ${pct(r.dhu)} · ${q("kpi.reject")} ${n0(r.reject)}`}
          </Text>
        </View>
      ))}
      {data.top_defects?.filter((d) => d.section === section).length ? (
        <View style={{ gap: space[1] }}>
          <Text style={{ fontFamily: fonts.bold }}>{q("dash.top_defects")}</Text>
          {data.top_defects
            .filter((d) => d.section === section)
            .map((d) => (
              <View key={d.code} style={{ flexDirection: "row", gap: space[3] }}>
                <Text variant="small" style={{ flex: 1 }} numberOfLines={1}>
                  {`${d.code} — ${bn && d.name_bn ? d.name_bn : d.name}`}
                </Text>
                <Text variant="label">{n0(d.count)}</Text>
              </View>
            ))}
        </View>
      ) : null}
    </SectionCard>
  );
}
