/**
 * Machines (AnnotechERP 37; the reference screen, user 2026-11-27): the blue
 * header with today, the four figures — Total, Running, Idle, Down (each one
 * filters the list) — the status donut with its share of each and a word on
 * how the floor is, then the machines: search, and each with its status and
 * since when (a tab of the bottom bar since 2026-11-27; scanning, reporting a
 * breakdown and maintenance open from Home). Each part shows only with the
 * permission its API checks.
 */
import { useCallback, useEffect, useState } from "react";
import { Pressable, TextInput, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import Svg, { Circle } from "react-native-svg";
import { Boxes, ChartColumnBig, ChevronDown, ChevronRight, CircleCheck, CirclePause, Clock, Cog, Lightbulb, List, Scissors, Search, Shirt, TriangleAlert, Wrench } from "lucide-react-native";
import { Alert, Button, Screen, Text } from "../../components/ui";
import { HeroPill, IconDisc, KpiRow, KpiTile, PageHero, ROUND, SectionCard, SectionTitle, StatusPill, longDay } from "../../components/hero";
import { at, mt, usePages } from "../../components/machinery";
import { todayDhaka, useLoad } from "../../components/manage";
import { dateTimeOf, timeOf } from "../../lib/clock";
import { t } from "../../lib/i18n";
import { useMachineAccess } from "../../lib/machines";
import { factoryParam, useFactory } from "../../lib/factory";
import { useSession } from "../../lib/session";
import { useReports } from "../../lib/reports";
import { fonts, space } from "../../theme/tokens";
import { shadow, useColors } from "../../theme/theme";

const DOWN = ["BREAKDOWN", "UNDER_REPAIR", "UNDER_MAINTENANCE"];
/** A status's place: running, idle, down, or anything else (installed, retired…). */
const stateOf = (status) => (status === "RUNNING" ? "running" : status === "IDLE" ? "idle" : DOWN.includes(status) ? "down" : "other");
const TONE = { running: "success", idle: "warning", down: "danger", other: "brand" };
const n0 = (v) => (v === null || v === undefined ? null : Number(v).toLocaleString("en-US"));

export default function Machines() {
  const access = useMachineAccess();
  const colors = useColors();
  const { session } = useSession();
  const factory = useFactory(session?.me?.factories ?? []);
  const [typed, setTyped] = useState("");
  const [search, setSearch] = useState("");
  const [state, setState] = useState(null); // null = all, else RUNNING / IDLE / DOWN
  const scope = factoryParam(factory, "current_factory_id");
  const filter = state === "DOWN" ? "&state=DOWN" : state ? `&filter[status]=${state}` : "";
  const list = usePages(access.sees ? `/assets?sort=asset_code&search=${encodeURIComponent(search)}&${scope}${filter}` : null);
  const counts = useLoad(access.sees ? `/assets/counts?${scope}` : null, [scope]);
  const waiting = useReports().length;

  // Typing finds as it goes, a moment after the last key.
  useEffect(() => {
    const timer = setTimeout(() => setSearch(typed.trim()), 400);
    return () => clearTimeout(timer);
  }, [typed]);

  useFocusEffect(
    useCallback(() => {
      counts.reload();
    }, [counts.reload]),
  );

  const c = counts.data;
  const pick = (s) => setState((now) => (now === s ? null : s));

  return (
    <Screen tab header={<PageHero title={t("mobile", "nav.machines")} icon={Boxes} image="machines" onBack={() => router.navigate("/")} right={<HeroPill label={longDay(todayDhaka())} />} />}>
      {access.sees ? (
        <KpiRow>
          <KpiTile icon={Cog} image="machines" label={mt("total")} value={n0(c?.total)} tone="brand" valueTone="foreground" onPress={() => setState(null)} />
          <KpiTile icon={CircleCheck} image="running" label={at("status_running")} value={n0(c?.running)} tone="success" onPress={() => pick("RUNNING")} active={state === "RUNNING"} />
          <KpiTile icon={CirclePause} image="idle" label={at("status_idle")} value={n0(c?.idle)} tone="warning" onPress={() => pick("IDLE")} active={state === "IDLE"} />
          <KpiTile icon={Wrench} image="down" label={mt("down")} value={n0(c?.down)} tone="danger" onPress={() => pick("DOWN")} active={state === "DOWN"} />
        </KpiRow>
      ) : null}

      {waiting ? (
        <Pressable onPress={() => router.push("/breakdowns")}>
          <Alert tone="warning">{mt("queued", { n: waiting })}</Alert>
        </Pressable>
      ) : null}

      {access.sees && c ? <Overview counts={c} /> : null}

      {access.sees ? (
        <>
          <SectionTitle icon={List} title={mt("list")} action={state || search ? mt("view_all") : null} onAction={() => {
            setState(null);
            setTyped("");
          }} />
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], height: 46, paddingHorizontal: space[4], borderRadius: 23, backgroundColor: colors.surfaceMuted, borderWidth: 1, borderColor: colors.border }}>
            <Search size={20} color={colors.foregroundMuted} />
            <TextInput
              value={typed}
              onChangeText={setTyped}
              placeholder={mt("search_machine")}
              placeholderTextColor={colors.foregroundSubtle}
              autoCorrect={false}
              autoCapitalize="characters"
              returnKeyType="search"
              onSubmitEditing={() => setSearch(typed.trim())}
              style={{ flex: 1, color: colors.foreground, fontFamily: fonts.regular, fontSize: 15, paddingVertical: 0 }}
            />
          </View>
          {list.error && !list.rows ? <Alert>{list.error}</Alert> : null}
          {list.rows?.length === 0 ? <Text tone="foregroundMuted">{at("no_matching_assets")}</Text> : null}
          <View style={{ gap: space[2] }}>
            {(list.rows ?? []).map((a) => (
              <MachineRow key={a.id} asset={a} />
            ))}
          </View>
          {list.more ? (
            <Button variant="outline" icon={ChevronDown} loading={list.loading} onPress={list.more}>
              {mt("more")}
            </Button>
          ) : null}
        </>
      ) : null}
    </Screen>
  );
}

/** The donut: running, idle and down as shares of the whole, and a word on how the floor is. */
function Overview({ counts }) {
  const colors = useColors();
  const parts = [
    { key: "running", label: at("status_running"), n: counts.running ?? 0, color: colors.success },
    { key: "idle", label: at("status_idle"), n: counts.idle ?? 0, color: "#f59e0b" },
    { key: "down", label: mt("down"), n: counts.down ?? 0, color: colors.danger },
  ];
  const whole = parts.reduce((a, p) => a + p.n, 0);
  const share = (n) => (whole ? Math.round((n / whole) * 100) : 0);
  const size = 132;
  const stroke = 18;
  const r = (size - stroke) / 2;
  const round = 2 * Math.PI * r;
  let offset = 0;
  const running = share(parts[0].n);
  const good = whole > 0 && running >= 80;

  return (
    <SectionCard icon={ChartColumnBig} title={mt("overview")}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[4] }}>
        <View style={{ width: size, height: size, alignItems: "center", justifyContent: "center" }}>
          <Svg width={size} height={size} style={{ position: "absolute", transform: [{ rotate: "-90deg" }] }}>
            <Circle cx={size / 2} cy={size / 2} r={r} stroke={colors.surfaceMuted} strokeWidth={stroke} fill="none" />
            {whole
              ? parts.map((p) => {
                  const len = (p.n / whole) * round;
                  const el = p.n ? <Circle key={p.key} cx={size / 2} cy={size / 2} r={r} stroke={p.color} strokeWidth={stroke} fill="none" strokeDasharray={`${len} ${round - len}`} strokeDashoffset={-offset} /> : null;
                  offset += len;
                  return el;
                })
              : null}
          </Svg>
          <Text style={{ fontFamily: fonts.bold, fontSize: 26, lineHeight: 32 }}>{`${running}%`}</Text>
          <Text style={{ color: colors.success, fontFamily: fonts.semibold, fontSize: 13 }}>{at("status_running")}</Text>
        </View>
        <View style={{ flex: 1, gap: space[3], borderLeftWidth: 1, borderLeftColor: colors.border, paddingLeft: space[3] }}>
          {parts.map((p) => (
            <View key={p.key} style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
              <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: p.color }} />
              <Text style={{ flex: 1, fontSize: 15 }} numberOfLines={1}>
                {p.label}
              </Text>
              <Text style={{ fontFamily: fonts.bold, fontSize: 15 }}>{p.n}</Text>
              <Text tone="foregroundMuted" style={{ fontSize: 14, width: 48, textAlign: "right" }}>{`(${share(p.n)}%)`}</Text>
            </View>
          ))}
        </View>
      </View>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], padding: space[3], borderRadius: 12, backgroundColor: good ? colors.brandSubtle : colors.warningSubtle }}>
        <Lightbulb size={20} color={good ? colors.brand : colors.warning} />
        <Text style={{ flex: 1, color: good ? colors.brand : colors.warning, fontFamily: fonts.semibold, fontSize: 14 }}>{good ? mt("floor_good") : mt("floor_watch", { n: counts.down ?? 0 })}</Text>
      </View>
    </SectionCard>
  );
}

/** A machine's icon by its kind: a sewing machine, a cutter, the rest a cog; the fault sign when down. */
function kindIcon(asset, state) {
  if (state === "down") return TriangleAlert;
  const kind = `${asset.type?.name ?? ""} ${asset.category?.name ?? ""}`.toLowerCase();
  if (kind.includes("cut")) return Scissors;
  if (kind.includes("sew") || kind.includes("stitch") || kind.includes("lock")) return Shirt;

  return Cog;
}

function MachineRow({ asset }) {
  const colors = useColors();
  const state = stateOf(asset.status);
  const tone = TONE[state];
  // Today's change by its time alone, an older one with its day (both in Dhaka).
  const dhakaDay = (iso) => new Date(Date.parse(iso) + 6 * 3600 * 1000).toISOString().slice(0, 10);
  const since = asset.status_since ? (dhakaDay(asset.status_since) === todayDhaka() ? timeOf(asset.status_since) : dateTimeOf(asset.status_since)) : null;

  return (
    <Pressable
      accessibilityRole="button"
      onPress={() => router.push({ pathname: "/machine/[id]", params: { id: asset.id } })}
      style={({ pressed }) => [{ flexDirection: "row", alignItems: "center", gap: space[3], padding: space[3], borderRadius: ROUND, backgroundColor: colors.surface, opacity: pressed ? 0.85 : 1 }, shadow(colors)]}
    >
      <IconDisc icon={kindIcon(asset, state)} tone={tone} size={56} />
      <View style={{ flex: 1, minWidth: 0, gap: 2 }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
          <Text style={{ flex: 1, fontFamily: fonts.bold, fontSize: 16 }} numberOfLines={1}>
            {asset.asset_code}
          </Text>
          <StatusPill tone={tone} label={at(`status_${String(asset.status).toLowerCase()}`)} size="sm" />
        </View>
        <Text tone="foregroundMuted" style={{ fontSize: 14 }} numberOfLines={1}>
          {asset.name || asset.type?.name || "—"}
        </Text>
        {since ? (
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[1] }}>
            <Clock size={14} color={colors.foregroundMuted} />
            <Text style={{ fontSize: 13 }} numberOfLines={1}>
              <Text style={{ fontFamily: fonts.semibold, fontSize: 13 }}>{`${mt(`since_${state}`)} `}</Text>
              <Text tone="foregroundMuted" style={{ fontSize: 13 }}>
                {since}
              </Text>
            </Text>
          </View>
        ) : asset.factory?.name ? (
          <Text variant="small" tone="foregroundSubtle" numberOfLines={1}>
            {[asset.factory?.name, asset.location?.name].filter(Boolean).join(" · ")}
          </Text>
        ) : null}
      </View>
      <ChevronRight size={20} color={colors.foregroundMuted} />
    </Pressable>
  );
}
