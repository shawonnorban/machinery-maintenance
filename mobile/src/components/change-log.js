/**
 * A change log on the phone (AnnotechERP 37, phase 5): Order Summary's or Dev
 * Sample's — every save, newest first, with the cells it changed, old and
 * new. Only what the person sees on the web's sheet (the API decides). The
 * period, the action and a search narrow it; read only, nothing is edited.
 *
 * Redesigned (user, 2026-11-28: "the Order page has gone plain"): a summary
 * card, a live search, the saves grouped by day, each card with its kind's
 * colour and icon, who saved it, and the cells as old → new pills.
 */
import { useEffect, useRef, useState } from "react";
import { Pressable, TextInput, View } from "react-native";
import { ArrowRight, ChevronDown, ChevronUp, Clock, FileClock, Pencil, Plus, RefreshCw, RotateCcw, Search, Trash2, X } from "lucide-react-native";
import { Alert, Avatar, Button, Dropdown, Text } from "./ui";
import { mt } from "./machinery";
import { useSession } from "../lib/session";
import { serverNow, timeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { fonts, space } from "../theme/tokens";
import { shadow, useColors } from "../theme/theme";

export const KINDS = {
  "order-summary": { ns: "ordersummary", path: "/order-summary/audit-log", title: "os_log" },
  "dev-sample": { ns: "devsample", path: "/dev-sample/audit-log", title: "ds_log" },
};
const TONE = { CREATE: "success", EDIT: "warning", DELETE: "danger", RECALC: "info", RESTORE: "brand" };
const KIND_ICON = { CREATE: Plus, EDIT: Pencil, DELETE: Trash2, RECALC: RefreshCw, RESTORE: RotateCcw };
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
/** The Dhaka day of a moment, as YYYY-MM-DD. */
const dayOf = (iso) => new Date(Date.parse(iso) + 6 * 3600 * 1000).toISOString().slice(0, 10);
/** A long decimal read at a glance: 22.674470886 → 22.67 (the web keeps every place). */
const shown = (v) => {
  if (v === null || v === undefined || v === "") return "—";
  const text = String(v);
  return /^-?\d+\.\d{3,}$/.test(text) ? Number(text).toLocaleString("en-US", { maximumFractionDigits: 2 }) : text;
};
const PERIODS = [
  ["today", 0],
  ["7d", 6],
  ["30d", 29],
  ["all", null],
];
const ot = (key, params) => t("mobile", `ord.${key}`, params);

/** The Dhaka day `n` days back, as YYYY-MM-DD. */
const daysBack = (n) => new Date(serverNow().getTime() + 6 * 3600 * 1000 - n * 86400 * 1000).toISOString().slice(0, 10);

/**
 * The two filters as dropdowns (user, 2026-11-27): the period and the action.
 * The Order tab puts them by its title; a log opened on its own shows them
 * above its search.
 */
export function LogFilters({ kind, period, action, onPeriod, onAction }) {
  const spec = KINDS[kind] ?? KINDS["order-summary"];
  const at = (key) => t(spec.ns, `audit.${key}`);

  return (
    <View style={{ flexDirection: "row", gap: space[2] }}>
      <Dropdown label={ot("period_label")} value={period} onChange={onPeriod} options={PERIODS.map(([key]) => ({ value: key, label: ot(`period.${key}`) }))} />
      <Dropdown label={at("action")} value={action} onChange={onAction} options={[{ value: "", label: mt("all") }, ...["CREATE", "EDIT", "DELETE"].map((k) => ({ value: k, label: at(`kind.${k}`) }))]} />
    </View>
  );
}

/** `period` and `action` given: the caller shows the filters; else the log shows its own. */
export function ChangeLog({ kind, period: givenPeriod, action: givenAction }) {
  const spec = KINDS[kind] ?? KINDS["order-summary"];
  const at = (key, params) => t(spec.ns, `audit.${key}`, params);
  const { call } = useSession();
  const colors = useColors();
  const outside = givenPeriod !== undefined;
  const [ownPeriod, setPeriod] = useState("7d");
  const [ownAction, setAction] = useState("");
  const period = outside ? givenPeriod : ownPeriod;
  const action = outside ? givenAction : ownAction;
  const [typed, setTyped] = useState("");
  const [find, setFind] = useState("");
  const [pages, setPages] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const asked = useRef(0);

  const read = async (page, keep) => {
    const turn = ++asked.current;
    const back = PERIODS.find(([k]) => k === period)[1];
    const q = new URLSearchParams({ page: String(page) });
    if (back !== null) q.set("from", daysBack(back));
    if (action) q.set("kind", action);
    if (find) q.set("q", find);
    setLoading(true);
    try {
      const data = await call(`${spec.path}?${q}`);
      if (turn !== asked.current) return;
      setPages({ entries: [...(keep ?? []), ...data.entries], page: data.page });
      setError(null);
    } catch (caught) {
      if (turn === asked.current) setError(caught.message);
    } finally {
      if (turn === asked.current) setLoading(false);
    }
  };

  useEffect(() => {
    read(1, null);
  }, [kind, period, action, find]);
  // The search asks 400 ms after the typing stops.
  useEffect(() => {
    const timer = setTimeout(() => setFind(typed.trim()), 400);
    return () => clearTimeout(timer);
  }, [typed]);

  return (
    <>
      {outside ? null : <LogFilters kind={kind} period={period} action={action} onPeriod={setPeriod} onAction={setAction} />}
      <LogSearch value={typed} onChange={setTyped} placeholder={at("search")} />

      {error && !pages ? <Alert>{error}</Alert> : null}
      {pages ? <LogSummary total={pages.page.total} period={period} action={action} at={at} /> : loading ? <Text tone="foregroundMuted">{t("mobile", "mgr.loading")}</Text> : null}
      {pages?.entries.length === 0 ? (
        <View style={{ alignItems: "center", gap: space[2], paddingVertical: space[6] }}>
          <FileClock size={34} color={colors.foregroundSubtle} />
          <Text tone="foregroundMuted">{at("empty")}</Text>
        </View>
      ) : null}
      {(pages?.entries ?? []).map((e, i, all) => {
        const day = dayOf(e.at);
        const head = i === 0 || dayOf(all[i - 1].at) !== day;
        return (
          <View key={`${e.at}-${e.serial_no}-${i}`} style={{ gap: space[2] }}>
            {head ? <DayHeader day={day} /> : null}
            <EntryCard e={e} at={at} />
          </View>
        );
      })}
      {pages && pages.page.current < pages.page.last ? (
        <Button variant="outline" icon={ChevronDown} loading={loading} onPress={() => read(pages.page.current + 1, pages.entries)}>
          {mt("more")}
        </Button>
      ) : null}
    </>
  );
}

/** The search as a pill: live, with a clear button. */
function LogSearch({ value, onChange, placeholder }) {
  const colors = useColors();

  return (
    <View style={[{ flexDirection: "row", alignItems: "center", gap: space[2], height: 46, paddingHorizontal: space[3], borderRadius: 14, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border }, shadow(colors)]}>
      <Search size={19} color={colors.brand} />
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={colors.foregroundSubtle}
        autoCorrect={false}
        autoCapitalize="none"
        returnKeyType="search"
        accessibilityLabel={placeholder}
        style={{ flex: 1, color: colors.foreground, fontFamily: fonts.regular, fontSize: 14, paddingVertical: 0 }}
      />
      {value ? (
        <Pressable accessibilityRole="button" onPress={() => onChange("")} hitSlop={10}>
          <X size={18} color={colors.foregroundSubtle} />
        </Pressable>
      ) : null}
    </View>
  );
}

/** How many saves the filters hold, on a blue band. */
function LogSummary({ total, period, action, at }) {
  const colors = useColors();

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[3], padding: space[3], borderRadius: 16, backgroundColor: colors.brand }}>
      <View style={{ width: 44, height: 44, borderRadius: 22, backgroundColor: "rgba(255,255,255,0.18)", alignItems: "center", justifyContent: "center" }}>
        <FileClock size={22} color="#ffffff" />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: 22, lineHeight: 28 }}>{Number(total ?? 0).toLocaleString("en-US")}</Text>
        <Text style={{ color: "#dbeafe", fontSize: 12 }} numberOfLines={1}>
          {[ot("saves"), ot(`period.${period}`), action ? at(`kind.${action}`) : mt("all")].join(" · ")}
        </Text>
      </View>
    </View>
  );
}

/** Today, yesterday or the date, over that day's saves. */
function DayHeader({ day }) {
  const colors = useColors();
  const today = daysBack(0);
  const label = day === today ? ot("period.today") : day === daysBack(1) ? ot("yesterday") : `${Number(day.slice(8, 10))} ${MONTHS[Number(day.slice(5, 7)) - 1]} ${day.slice(0, 4)}`;

  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], marginTop: space[2] }}>
      <Text style={{ fontFamily: fonts.bold, fontSize: 13, color: colors.heading ?? colors.foreground }}>{label}</Text>
      <View style={{ flex: 1, height: 1, backgroundColor: colors.border }} />
    </View>
  );
}

/** One save: its kind's colour and icon, the record, who and when, the cells it changed. */
function EntryCard({ e, at }) {
  const colors = useColors();
  const [all, setAll] = useState(false);
  const tone = TONE[e.kind] ?? "brand";
  const fg = colors[tone] ?? colors.brand;
  const bg = colors[`${tone}Subtle`] ?? colors.brandSubtle;
  const Icon = KIND_ICON[e.kind] ?? Pencil;
  const cells = all ? e.cells : e.cells.slice(0, 3);

  return (
    <View style={[{ flexDirection: "row", borderRadius: 16, backgroundColor: colors.surface, overflow: "hidden" }, shadow(colors)]}>
      <View style={{ width: 5, backgroundColor: fg }} />
      <View style={{ flex: 1, padding: space[3], gap: space[2] }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
          <View style={{ width: 34, height: 34, borderRadius: 17, backgroundColor: bg, alignItems: "center", justifyContent: "center" }}>
            <Icon size={17} color={fg} strokeWidth={2.4} />
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
              <Text style={{ color: fg, fontFamily: fonts.semibold, fontSize: 13 }}>{at(`kind.${e.kind}`)}</Text>
              <Text style={{ fontFamily: fonts.bold, fontSize: 14, color: colors.heading ?? colors.foreground }}>{`#${e.serial_no ?? "—"}`}</Text>
              {e.deleted ? (
                <View style={{ paddingHorizontal: 6, paddingVertical: 1, borderRadius: 6, backgroundColor: colors.dangerSubtle }}>
                  <Text style={{ color: colors.danger, fontSize: 11, fontFamily: fonts.semibold }}>{at("kind.DELETE")}</Text>
                </View>
              ) : null}
            </View>
            <Text style={{ fontFamily: fonts.semibold, fontSize: 14, color: colors.foreground }} numberOfLines={1}>
              {[e.buyer, e.order ?? e.sample].filter(Boolean).join(" · ") || "—"}
            </Text>
          </View>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
            <Clock size={13} color={colors.foregroundSubtle} />
            <Text style={{ fontSize: 12, color: colors.foregroundMuted }}>{timeOf(e.at)}</Text>
          </View>
        </View>

        <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
          <Avatar name={e.by ?? "?"} size={22} />
          <Text style={{ fontSize: 12, color: colors.foregroundMuted }} numberOfLines={1}>
            {e.by ?? "—"}
          </Text>
        </View>

        {e.cells.length ? (
          <View style={{ gap: space[2], padding: space[2.5] ?? 10, borderRadius: 12, backgroundColor: colors.surfaceMuted }}>
            {cells.map((c, j) => (
              <View key={j} style={{ gap: 4 }}>
                <Text style={{ fontSize: 12, fontFamily: fonts.semibold, color: colors.foregroundMuted }}>{c.column}</Text>
                <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], flexWrap: "wrap" }}>
                  <View style={{ paddingHorizontal: 8, paddingVertical: 3, borderRadius: 8, backgroundColor: colors.dangerSubtle }}>
                    <Text style={{ fontSize: 12, color: colors.danger, textDecorationLine: "line-through" }}>{shown(c.old)}</Text>
                  </View>
                  <ArrowRight size={14} color={colors.foregroundSubtle} />
                  <View style={{ paddingHorizontal: 8, paddingVertical: 3, borderRadius: 8, backgroundColor: colors.successSubtle }}>
                    <Text style={{ fontSize: 12, color: colors.success, fontFamily: fonts.semibold }}>{shown(c.new)}</Text>
                  </View>
                </View>
              </View>
            ))}
            {e.cells.length > 3 ? (
              <Pressable onPress={() => setAll((x) => !x)} hitSlop={8} style={{ flexDirection: "row", alignItems: "center", gap: 4, alignSelf: "flex-start" }}>
                <Text style={{ color: colors.brand, fontFamily: fonts.semibold, fontSize: 12 }}>{all ? ot("show_less") : ot("more_changes", { n: e.cells.length - 3 })}</Text>
                {all ? <ChevronUp size={15} color={colors.brand} /> : <ChevronDown size={15} color={colors.brand} />}
              </Pressable>
            ) : null}
          </View>
        ) : null}
      </View>
    </View>
  );
}
