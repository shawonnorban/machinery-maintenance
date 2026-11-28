/**
 * The QC's tally on a phone (AnnotechERP 37, phase 2) — the web's Tally Entry,
 * held in one hand beside a line. A tap is counted and kept on the phone at
 * once and sent behind (lib/taps): no tap ever waits for the server, and none
 * is lost to a dropped Wi-Fi. What the screen needs is kept on the phone too,
 * so it opens and counts with no network.
 *
 *  - Sewing and Quality (user, 2026-11-28): the whole screen is the tally — the
 *    line and job on a dark bar with Undo, Online, switch job and a menu (who,
 *    history, logout), four big tiles filling the rest, the day's figures at
 *    the foot. The job: only those with a target on this line today.
 *  - Cutting (user, 2026-11-25/26; full screen 2026-11-28): the same screen,
 *    with the bundle and the panel chosen on it — the bundle card scanned with
 *    the camera (or typed, or picked from the table's list) in a popup, the
 *    panels as chips under the bar; no more first checks than the bundle holds.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Alert as Ask, Modal, Pressable, StatusBar as RNStatusBar, Vibration, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";
import { router, useLocalSearchParams } from "expo-router";
import { ArrowLeftRight, Check, ChevronDown, EllipsisVertical, History, Layers, LogOut, RefreshCw, ScanLine, ShieldAlert, TriangleAlert, Undo2, Wifi, WifiOff, Wrench, X } from "lucide-react-native";
import { ScrollView } from "react-native";
import * as Crypto from "expo-crypto";
import Storage from "expo-sqlite/kv-store";
import { Alert, Badge, Button, Card, DetailRow, Header, Screen, Text } from "../components/ui";
import { Scanner } from "../components/scanner";
import { ACTIONS, Option, Sheet, Stat, SyncBar, TapButton, Toast, num, pct } from "../components/tally";
import { serverNow, timeOf } from "../lib/clock";
import { currentLocale, t } from "../lib/i18n";
import { qcOf, useSession } from "../lib/session";
import { addTap, useTaps } from "../lib/taps";
import { cachedContext, cachedSummary, findBundle, loadContext, loadSummary, pickKey, undoTap, useShownSummary } from "../lib/tally";
import { fonts, space } from "../theme/tokens";
import { useColors } from "../theme/theme";

const m = (key, params) => t("mobile", `tally.${key}`, params);
const q = (key, params) => t("qtally", key, params);
const DHAKA = 6 * 60 * 60 * 1000;
const dhakaHour = () => new Date(serverNow().getTime() + DHAKA).getUTCHours();
const dhakaDay = () => new Date(serverNow().getTime() + DHAKA).toISOString().slice(0, 10);

const readPick = (key) => {
  try {
    return JSON.parse(Storage.getItemSync(key) ?? "null") ?? {};
  } catch {
    return {};
  }
};

export default function TallyScreen() {
  const colors = useColors();
  const { line: lineParam } = useLocalSearchParams();
  const { session, sessionEnded, setOnline, signOut } = useSession();
  const qc = qcOf(session);
  const bn = currentLocale() === "bn";

  const [ctxKept, setCtxKept] = useState(() => (qc ? cachedContext(qc) : null));
  const [loadError, setLoadError] = useState(null);
  const [offline, setOffline] = useState(false);
  const ctx = ctxKept?.data ?? null;

  const pickStore = qc ? `tally.pick.${qc.company.code}/${qc.qc.employee_id}.${lineParam}` : null;
  const [pick, setPickState] = useState(() => (pickStore ? readPick(pickStore) : {}));
  const setPick = (patch) =>
    setPickState((cur) => {
      const next = { ...cur, ...patch };
      if (pickStore) Storage.setItemSync(pickStore, JSON.stringify(next));
      return next;
    });

  // A refusal that means the session is over ends it; no answer is only "offline".
  const failed = useCallback(
    (error) => {
      if (error.reason === "SESSION_ENDED") {
        sessionEnded();
        return true;
      }
      const none = error.status === 0;
      setOffline(none);
      if (none) setOnline(false);
      return none;
    },
    [sessionEnded, setOnline],
  );

  const loadCtx = useCallback(() => {
    if (!qc) return;
    loadContext(qc)
      .then((kept) => {
        setCtxKept(kept);
        setLoadError(null);
        setOffline(false);
        setOnline(true);
      })
      .catch((error) => {
        if (!failed(error)) setLoadError(error.message);
      });
  }, [qc?.session.uuid]);
  useEffect(() => {
    loadCtx();
    const timer = setInterval(loadCtx, 60000);
    return () => clearInterval(timer);
  }, [loadCtx]);

  const line = ctx?.lines.find((l) => `${l.section}:${l.line_id}` === lineParam) ?? qc?.lines.find((l) => `${l.section}:${l.line_id}` === lineParam) ?? null;
  const cutting = line?.section === "CUTTING";
  const targeted = useMemo(() => (line ? (ctx?.targets?.[lineParam] ?? []) : []), [ctx, line, lineParam]);
  const jobs = useMemo(() => {
    const ids = new Set(targeted.map((x) => x.job_id));
    return (ctx?.jobs ?? []).filter((j) => ids.has(j.id));
  }, [ctx, targeted]);

  // One job with a target: that one. A job no longer targeted (or closed) is let go.
  useEffect(() => {
    if (!ctx || cutting) return;
    if (pick.job && !jobs.some((j) => j.id === pick.job)) setPick({ job: jobs.length === 1 ? jobs[0].id : null });
    else if (!pick.job && jobs.length === 1) setPick({ job: jobs[0].id });
  }, [ctx, jobs, cutting]);

  const bundle = cutting ? (pick.bundle ?? null) : null;
  const jobId = cutting ? (bundle?.job_id ?? null) : (pick.job ?? null);
  const job = (ctx?.jobs ?? []).find((j) => j.id === jobId) ?? null;
  const key = pickKey(line, jobId, bundle?.id);

  // The running total: the last kept at once, then the server's.
  const [kept, setKept] = useState(() => (qc && key ? cachedSummary(qc, key) : null));
  const { completed } = useTaps();
  const keyNow = useRef(key);
  keyNow.current = key;
  const loadSum = useCallback(() => {
    if (!qc || !line || !jobId) return;
    const asked = key;
    loadSummary(qc, line, jobId, bundle?.id)
      .then((next) => keyNow.current === asked && setKept(next))
      .catch(failed);
  }, [qc?.session.uuid, key]);
  useEffect(() => {
    setKept(qc && key ? cachedSummary(qc, key) : null);
    loadSum();
    const timer = setInterval(loadSum, 30000);
    return () => clearInterval(timer);
  }, [loadSum]);
  // A batch answered: the server's count has moved, read it again.
  useEffect(() => {
    if (completed) loadSum();
  }, [completed]);

  const s = useShownSummary(kept, key);
  const [flash, setFlash] = useState(null);
  const [defectOpen, setDefectOpen] = useState(false);
  const [chosen, setChosen] = useState([]);
  const [jobOpen, setJobOpen] = useState(false);
  const [bundleOpen, setBundleOpen] = useState(false);
  const [notice, setNotice] = useState(null);
  useEffect(() => {
    if (!notice) return undefined;
    const timer = setTimeout(() => setNotice(null), 4000);
    return () => clearTimeout(timer);
  }, [notice]);
  const [busy, setBusy] = useState(false);

  const panels = useMemo(() => {
    const all = (ctx?.garment_types ?? []).find((g) => g.name === bundle?.garment_type)?.panels ?? [];
    return bundle?.panel_id ? all.filter((x) => x.id === bundle.panel_id) : all;
  }, [ctx, bundle]);
  const panel = panels.find((x) => x.id === pick.panel) ?? null;
  const holds = (pn) => (bundle?.qty ?? 0) * (pn?.per_piece ?? 1);
  const before = cutting ? "" : q(`section.${line?.section === "SEWING" ? "CUTTING" : "SEWING"}`);
  const defects = line ? (ctx?.defects?.[line.section] ?? []) : [];
  const ready = Boolean(qc && line && job && (!cutting || (bundle && panel)));

  /** One tap: kept and counted now, sent behind. */
  const record = (action, tags = []) => {
    if (!ready) return false;
    if (action === "RFT" || action === "DEFECT") {
      if (cutting) {
        const done = s?.cutting?.panels?.[panel.id]?.checked ?? 0;
        if (done >= holds(panel)) {
          setNotice({ tone: "danger", text: q("v.bundle_done", { n: holds(panel) }) });
          Vibration.vibrate([0, 60, 80, 60]);
          return false;
        }
      } else if (s && !offline && kept?.data && (s.ready ?? 1) <= 0) {
        // Said at once, not after a round trip — with a network only: offline the count may be old.
        setNotice({ tone: "danger", text: q("v.none_ready", { from: before }) });
        Vibration.vibrate([0, 60, 80, 60]);
        return false;
      }
    }
    const payload = {
      session: qc.session.uuid, section: line.section, line_id: line.line_id, job_id: job.id, action, defects: tags,
      occurred_at: serverNow().toISOString(), client_uuid: Crypto.randomUUID(),
      ...(cutting ? { cut_bundle_id: bundle.id, panel_id: panel.id } : {}),
    };
    addTap({ companyId: qc.company.id, session: qc.session.uuid, pick: key, label: [line.label, job.job_no, cutting ? `${bundle.bundle_no} · ${panel.name}` : null].filter(Boolean).join(" · "), payload });
    Vibration.vibrate(25);
    setNotice(null);
    setFlash(action);
    setTimeout(() => setFlash(null), 200);
    return true;
  };

  const press = (action) => (action === "DEFECT" ? setDefectOpen(true) : record(action));

  const saveDefect = () => {
    if (!chosen.length) return;
    // Kept or refused, the sheet closes: a refusal's reason shows over the screen.
    record("DEFECT", chosen);
    setChosen([]);
    setDefectOpen(false);
  };

  const undo = () =>
    Ask.alert(m("undo_ask"), m("undo_body"), [
      { text: m("close"), style: "cancel" },
      {
        text: m("undo"),
        style: "destructive",
        onPress: async () => {
          try {
            const res = await undoTap(qc, line, job.id, key);
            setNotice({ tone: "success", text: m("undone", { action: q(`action.${res.action}`) }) });
            loadSum();
          } catch (error) {
            setNotice({ tone: "danger", text: error.status === 0 ? m("undo_offline") : error.message });
          }
        },
      },
    ]);

  const scan = async (code) => {
    setBusy(true);
    setNotice(null);
    try {
      const res = await findBundle(qc, code, ctx);
      if (res.found === "INPUT" && res.bundle) {
        if (res.bundle.line_id && res.bundle.line_id !== line.line_id) setNotice({ tone: "danger", text: q("cut.other_table", { line: res.bundle.line ?? "" }) });
        else takeBundle(res.bundle);
      } else if (res.found === "CARD") setNotice({ tone: "warning", text: q("cut.not_input", { no: res.card.bundle_no }) });
      else setNotice({ tone: "danger", text: res.offline ? m("bundle_offline", { code }) : q("cut.not_found", { code }) });
    } catch (error) {
      if (!failed(error)) setNotice({ tone: "danger", text: error.message });
    } finally {
      setBusy(false);
    }
  };

  const takeBundle = (b) => {
    const all = (ctx?.garment_types ?? []).find((g) => g.name === b.garment_type)?.panels ?? [];
    setPick({ bundle: b, panel: b.panel_id || all[0]?.id || null });
    setBundleOpen(false);
    Vibration.vibrate(25);
  };

  if (!qc) return null;
  const header = (
    <Header title={line?.label ?? m("line")} onBack={() => router.back()} backLabel={t("mobile", "back")}>
      <View style={{ paddingHorizontal: space[2] }}>{offline ? <WifiOff size={20} color={colors.danger} /> : <Wifi size={20} color={colors.success} />}</View>
    </Header>
  );

  if (!ctx) {
    return (
      <Screen header={header}>
        {loadError || offline ? <Alert>{m("load_failed", { msg: loadError ?? t("mobile", "net.unreachable") })}</Alert> : <Text tone="foregroundMuted">…</Text>}
        <Button variant="outline" icon={RefreshCw} onPress={loadCtx}>
          {m("retry")}
        </Button>
      </Screen>
    );
  }
  if (!line) {
    return (
      <Screen header={header}>
        <Alert tone="warning">{q("tally.no_lines")}</Alert>
      </Screen>
    );
  }

  const target = targeted.find((x) => x.job_id === job?.id);
  const thisHour = s?.hourly?.find((h) => h.hour === dhakaHour());
  const cur = cutting && panel ? (s?.cutting?.panels?.[panel.id] ?? {}) : {};
  const counts = cutting ? { RFT: Math.max(0, (cur.checked ?? 0) - (cur.defect ?? 0)), DEFECT: cur.defect ?? 0, RECTIFY: cur.rectify ?? 0, REJECT: cur.reject ?? 0 } : { RFT: s?.rft ?? 0, DEFECT: s?.defect ?? 0, RECTIFY: s?.rectify ?? 0, REJECT: s?.reject ?? 0 };

  const sheets = (
    <>
      <Sheet open={jobOpen} onClose={() => setJobOpen(false)} title={m("pick_job")}>
        {jobs.length === 0 ? <Alert tone="warning">{m("no_jobs")}</Alert> : null}
        {jobs.map((j) => {
          const tg = targeted.find((x) => x.job_id === j.id);
          return (
            <Option
              key={j.id}
              on={j.id === pick.job}
              title={`${j.job_no} · ${j.style}`}
              sub={[j.buyer_name, j.color, tg ? `${q("kpi.target")} ${num(tg.target_qty)}` : null].filter(Boolean).join(" · ")}
              onPress={() => {
                setPick({ job: j.id });
                setJobOpen(false);
              }}
            />
          );
        })}
      </Sheet>

      <Sheet
        open={defectOpen}
        onClose={() => {
          setDefectOpen(false);
          setChosen([]);
        }}
        title={m("defects_title")}
        hint={m("defects_hint")}
        footer={
          <Button icon={ShieldAlert} disabled={!chosen.length} onPress={saveDefect} style={{ flex: 1, backgroundColor: colors.warning, borderColor: colors.warning }}>
            {m("save_defect", { n: chosen.length })}
          </Button>
        }
      >
        {defects.length === 0 ? <Alert tone="warning">{q("tally.no_defect_types", { section: q(`section.${line.section}`) })}</Alert> : null}
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
          {defects.map((d) => {
            const on = chosen.includes(d.id);
            return (
              <Option
                key={d.id}
                on={on}
                tone="warning"
                title={bn && d.name_bn ? d.name_bn : d.name}
                sub={d.code}
                onPress={() => setChosen((all) => (on ? all.filter((x) => x !== d.id) : [...all, d.id]))}
                style={{ flexBasis: "47%", flexGrow: 1 }}
              />
            );
          })}
        </View>
      </Sheet>
    </>
  );

  // Cutting (user, 2026-11-28): the same full screen; the bundle and the panel are chosen on it.
  if (cutting) {
    const left = panel ? Math.max(0, holds(panel) - (cur.checked ?? 0)) : null;
    return (
      <View style={{ flex: 1 }}>
        <FullTally
          line={line}
          job={job}
          qc={qc}
          offline={offline}
          counts={counts}
          flash={flash}
          ready={ready}
          s={s}
          target={target}
          thisHour={thisHour}
          bn={bn}
          sub={bundle ? [`${m("bundle")} ${bundle.bundle_no}`, job ? job.job_no : bundle.job_no, bundle.size || null].filter(Boolean).join(" | ") : m("scan")}
          top={
            <View style={{ backgroundColor: "#ffffff", borderBottomWidth: 1, borderBottomColor: "#E2E8F0", paddingVertical: space[2], gap: space[2] }}>
              {/* The bundle: scan, type or pick — in a popup, never another page. */}
              <Pressable onPress={() => setBundleOpen(true)} style={({ pressed }) => ({ marginHorizontal: space[3], flexDirection: "row", alignItems: "center", gap: space[2], minHeight: 44, paddingHorizontal: space[3], borderRadius: 12, borderWidth: 1.5, borderColor: bundle ? "#2563EB" : "#F59E0B", backgroundColor: pressed ? "#F1F5F9" : bundle ? "#EFF6FF" : "#FFFBEB" })}>
                {bundle ? <Layers size={20} color="#2563EB" /> : <ScanLine size={20} color="#B45309" />}
                <View style={{ flex: 1, minWidth: 0 }}>
                  <Text style={{ fontFamily: fonts.bold, fontSize: 14, color: "#0F172A" }} numberOfLines={1}>
                    {bundle ? `${m("bundle")} ${bundle.bundle_no}` : m("scan")}
                  </Text>
                  {bundle ? (
                    <Text style={{ fontSize: 12, color: "#64748B" }} numberOfLines={1}>
                      {[job ? `${job.job_no} · ${job.style}` : bundle.job_no, bundle.size ? `${q("col.size")} ${bundle.size}` : null, `${q("col.bundle_qty")} ${num(bundle.qty)}`].filter(Boolean).join(" · ")}
                    </Text>
                  ) : null}
                </View>
                <Text style={{ fontFamily: fonts.semibold, fontSize: 13, color: bundle ? "#2563EB" : "#B45309" }}>{bundle ? m("change") : m("pick")}</Text>
                <ChevronDown size={18} color={bundle ? "#2563EB" : "#B45309"} />
              </Pressable>
              {/* The panels of the bundle's garment, as chips: the one in hand is tapped. */}
              {bundle ? (
                panels.length ? (
                  <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: space[3], gap: space[2] }}>
                    {panels.map((pn) => {
                      const n = s?.cutting?.panels?.[pn.id] ?? {};
                      const on = pick.panel === pn.id;
                      const full = (n.checked ?? 0) >= holds(pn);
                      return (
                        <Pressable key={pn.id} onPress={() => setPick({ panel: pn.id })} style={{ minWidth: 92, paddingVertical: 6, paddingHorizontal: space[3], borderRadius: 12, borderWidth: on ? 2 : 1, borderColor: on ? "#2563EB" : full ? "#86EFAC" : "#E2E8F0", backgroundColor: on ? "#2563EB" : full ? "#F0FDF4" : "#ffffff" }}>
                          <Text style={{ fontFamily: fonts.semibold, fontSize: 13, color: on ? "#ffffff" : "#0F172A" }} numberOfLines={1}>
                            {`${pn.name}${pn.per_piece > 1 ? ` ×${pn.per_piece}` : ""}`}
                          </Text>
                          <Text style={{ fontSize: 12, color: on ? "#DBEAFE" : full ? "#16A34A" : "#64748B" }}>{`${num(n.checked ?? 0)}/${num(holds(pn))}`}</Text>
                        </Pressable>
                      );
                    })}
                  </ScrollView>
                ) : (
                  <Text style={{ marginHorizontal: space[3], fontSize: 13, color: "#B45309" }}>{q("cpi.no_panels")}</Text>
                )
              ) : null}
            </View>
          }
          foot={[
            [q("cut.panel_checked"), panel ? `${num(cur.checked ?? 0)}/${num(holds(panel))}` : "—", "#2563EB"],
            [q("kpi.pass"), num(cur.pass ?? 0), "#16A34A"],
            [m("left"), left === null ? "—" : num(left), "#3730A3"],
            [m("lot_reject"), num(s?.cutting?.lot_reject ?? 0), "#DC2626"],
          ]}
          onTap={(a) => (ready ? press(a) : bundle ? setNotice({ tone: "warning", text: q("cut.pick_panel") }) : setBundleOpen(true))}
          onUndo={undo}
          onJob={() => setBundleOpen(true)}
          onLogout={() =>
            Ask.alert(m("logout_ask"), undefined, [
              { text: m("close"), style: "cancel" },
              { text: m("logout"), style: "destructive", onPress: signOut },
            ])
          }
          onBack={() => router.back()}
        />
        {sheets}
        <Sheet open={bundleOpen} onClose={() => setBundleOpen(false)} title={m("scan")} icon={ScanLine}>
          <Scanner onScan={scan} busy={busy} />
          <BundleList bundles={(ctx.bundles ?? []).filter((b) => !b.line_id || b.line_id === line.line_id)} onPick={takeBundle} flat />
        </Sheet>
        <Toast notice={notice} onClose={() => setNotice(null)} />
      </View>
    );
  }

  // Sewing and Quality: the whole screen is the tally (user, 2026-11-28).
  if (!cutting) {
    return (
      <View style={{ flex: 1 }}>
        <FullTally
          line={line}
          job={job}
          qc={qc}
          offline={offline}
          counts={counts}
          flash={flash}
          ready={ready}
          s={s}
          target={target}
          thisHour={thisHour}
          bn={bn}
          onTap={(a) => (ready ? press(a) : setJobOpen(true))}
          onUndo={undo}
          onJob={() => setJobOpen(true)}
          onLogout={() =>
            Ask.alert(m("logout_ask"), undefined, [
              { text: m("close"), style: "cancel" },
              { text: m("logout"), style: "destructive", onPress: signOut },
            ])
          }
          onBack={() => router.back()}
        />
        {sheets}
        <Toast notice={notice} onClose={() => setNotice(null)} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1 }}>
    <Screen header={header}>
      <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
        <Badge tone={{ CUTTING: "info", SEWING: "brand", QUALITY: "success" }[line.section]}>{q(`section.${line.section}`)}</Badge>
        <Text variant="small" tone="foregroundMuted" numberOfLines={1} style={{ flex: 1 }}>
          {qc.qc.name} · {qc.qc.employee_id}
        </Text>
      </View>
      {/* One fixed line, network or not: nothing above the buttons appears or goes while a QC taps. */}
      <SyncBar offline={offline} />

      {cutting ? (
        bundle ? (
          <Card style={{ padding: space[4], gap: space[2] }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
              <Layers size={20} color={colors.info} />
              <Text variant="sectionTitle" style={{ flex: 1 }}>
                {m("bundle")} {bundle.bundle_no}
              </Text>
              <Button variant="outline" icon={ScanLine} onPress={() => setPick({ bundle: null, panel: null })} style={{ minHeight: 40, paddingHorizontal: space[3] }}>
                {m("another")}
              </Button>
            </View>
            <DetailRow label={q("f.job")} value={job ? `${job.job_no} · ${job.style}` : (bundle.job_no ?? "—")} />
            <DetailRow label={q("col.size")} value={`${bundle.size || "—"} · ${q("col.bundle_qty")} ${num(bundle.qty)}`} />
            {bundle.serial || bundle.cutting_no ? <DetailRow label={q("col.bundle_serial")} value={[bundle.serial, bundle.cutting_no ? `${q("col.cutting_no")} ${bundle.cutting_no}` : null].filter(Boolean).join(" · ")} /> : null}
          </Card>
        ) : (
          <Card style={{ padding: space[4] }}>
            <Text variant="sectionTitle">{m("scan")}</Text>
            <Scanner onScan={scan} busy={busy} />
          </Card>
        )
      ) : (
        <Option
          title={job ? `${job.job_no} · ${job.style}` : m("pick_job")}
          sub={job ? [job.buyer_name, job.color].filter(Boolean).join(" · ") : jobs.length === 0 ? m("no_jobs") : null}
          onPress={() => setJobOpen(true)}
          on={Boolean(job)}
        />
      )}


      {cutting && !bundle ? (
        <BundleList bundles={(ctx.bundles ?? []).filter((b) => !b.line_id || b.line_id === line.line_id)} onPick={takeBundle} />
      ) : null}

      {cutting && bundle ? (
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
          {panels.length === 0 ? <Alert tone="warning">{q("cpi.no_panels")}</Alert> : null}
          {panels.map((pn) => {
            const n = s?.cutting?.panels?.[pn.id] ?? {};
            return (
              <Option
                key={pn.id}
                on={pick.panel === pn.id}
                onPress={() => setPick({ panel: pn.id })}
                title={`${pn.name}${pn.per_piece > 1 ? ` ×${pn.per_piece}` : ""}`}
                sub={m("panel_of", { checked: num(n.checked ?? 0), qty: num(holds(pn)) })}
                style={{ flexBasis: "31%", flexGrow: 1 }}
              />
            );
          })}
        </View>
      ) : null}

      {ready ? (
        <>
          {!cutting && s ? (
            <Text variant="small" tone={s.ready > 0 ? "foregroundMuted" : "warning"}>
              {q("tally.ready", { target: num(s.target ?? target?.target_qty), left: num(Math.max(0, (s.target ?? target?.target_qty ?? 0) - (s.pass ?? 0))), from: before, ready: num(s.ready) })}
            </Text>
          ) : null}
          <View style={{ gap: space[3] }}>
            {[ACTIONS.slice(0, 2), ACTIONS.slice(2)].map((row) => (
              <View key={row[0].key} style={{ flexDirection: "row", gap: space[3] }}>
                {row.map((a) => (
                  <TapButton key={a.key} action={a.key} count={counts[a.key]} flash={flash === a.key} onPress={() => press(a.key)} />
                ))}
              </View>
            ))}
          </View>

          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
            {cutting ? (
              <>
                <Stat label={q("cut.panel_checked")} value={`${num(cur.checked ?? 0)}/${num(holds(panel))}`} />
                <Stat label={q("kpi.pass")} value={num(cur.pass ?? 0)} tone="success" />
                <Stat label={m("lot_reject")} value={num(s?.cutting?.lot_reject ?? 0)} tone="danger" />
              </>
            ) : (
              <>
                <Stat label={q("kpi.target")} value={num(s?.target ?? target?.target_qty ?? null)} sub={thisHour ? m("this_hour", { n: num(thisHour.pass) }) : null} />
                <Stat label={q("kpi.checked")} value={num(s?.checked ?? 0)} />
                <Stat label={q("kpi.pass")} value={num(s?.pass ?? 0)} tone="success" />
                <Stat label={q("kpi.achievement")} value={pct(s?.achievement)} />
                <Stat label={q("kpi.dhu")} value={pct(s?.dhu)} tone={s?.dhu > 5 ? "danger" : "foreground"} />
                <Stat label={q("kpi.mine")} value={num(s?.mine ?? 0)} />
              </>
            )}
          </View>

          <Button variant="outline" icon={Undo2} onPress={undo} disabled={!s?.mine}>
            {m("undo")}
          </Button>

          {!cutting && s?.top_defects?.length ? (
            <Card style={{ padding: space[4], gap: space[2] }}>
              <Text variant="cardTitle">{q("tally.top_defects")}</Text>
              {s.top_defects.map((d) => (
                <DetailRow key={d.id} label={`${d.code} — ${bn && d.name_bn ? d.name_bn : d.name}`} value={num(d.count)} />
              ))}
            </Card>
          ) : null}
        </>
      ) : !cutting && job === null && jobs.length > 0 ? (
        <Alert tone="info">{q("tally.pick_first")}</Alert>
      ) : cutting && bundle && !panel ? (
        <Alert tone="info">{q("cut.pick_panel")}</Alert>
      ) : null}

      {offline && kept?.at ? (
        <Text variant="small" tone="warning">
          {m("cached", { time: timeOf(new Date(kept.at).toISOString()) })}
        </Text>
      ) : null}
      {ctx.today && ctx.today !== dhakaDay() && offline ? <Alert tone="warning">{m("stale_day", { day: ctx.today })}</Alert> : null}

      {sheets}
    </Screen>
    <Toast notice={notice} onClose={() => setNotice(null)} />
    </View>
  );
}

/* ── The full-screen tally (Sewing and Quality; user, 2026-11-28) ─────────── */

const BAR = "#1E3A5F";
const TILES = [
  { key: "RFT", icon: Check, bg: "#22C55E", ring: "#22C55E" },
  { key: "DEFECT", icon: TriangleAlert, bg: "#F59E0B", ring: null },
  { key: "RECTIFY", icon: Wrench, bg: "#86EFAC", ring: "#86EFAC" },
  { key: "REJECT", icon: X, bg: "#EF4444", ring: "#EF4444" },
];

function FullTally({ line, job, qc, offline, counts, flash, ready, s, target, thisHour, bn, onTap, onUndo, onJob, onLogout, onBack, top = null, sub: subGiven, foot }) {
  const { waiting } = useTaps();
  const [menu, setMenu] = useState(false);
  const [history, setHistory] = useState(false);
  const dhu = s?.dhu ?? null;
  const sub = subGiven ?? (job ? [`${m("job")}: ${job.job_no}`, job.po_no ? `PO: ${job.po_no}` : job.style].filter(Boolean).join(" | ") : m("pick_job"));

  return (
    <View style={{ flex: 1, backgroundColor: "#F1F5F9" }}>
      <RNStatusBar barStyle="light-content" backgroundColor={BAR} />
      <SafeAreaView edges={["top"]} style={{ backgroundColor: BAR }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], paddingLeft: space[4], paddingRight: space[1], paddingVertical: space[2] }}>
          <Pressable onPress={onJob} style={{ flex: 1, minWidth: 0 }} accessibilityRole="button" accessibilityLabel={m("pick_job")}>
            <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: 20, lineHeight: 26 }} numberOfLines={1}>
              {(line.label ?? "").split(" — ").pop()}
            </Text>
            <Text style={{ color: "#CBD5E1", fontSize: 13, lineHeight: 18 }} numberOfLines={1}>
              {sub}
            </Text>
          </Pressable>
          <Pressable accessibilityRole="button" accessibilityLabel={m("undo")} disabled={!s?.mine} onPress={onUndo} hitSlop={8} style={{ padding: space[1.5] ?? 6, opacity: s?.mine ? 1 : 0.4 }}>
            <Undo2 size={24} color="#F87171" strokeWidth={2.6} />
          </Pressable>
          <View style={{ flexDirection: "row", alignItems: "center", gap: 6, paddingHorizontal: space[1] }}>
            <View style={{ width: 12, height: 12, borderRadius: 6, backgroundColor: offline ? "#EF4444" : "#22C55E" }} />
            <Text style={{ color: offline ? "#FCA5A5" : "#4ADE80", fontFamily: fonts.semibold, fontSize: 14 }}>
              {offline ? m("offline") : m("online")}
              {waiting ? ` · ${waiting}` : ""}
            </Text>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel={m("pick_job")} onPress={onJob} hitSlop={8} style={{ padding: space[1.5] ?? 6 }}>
            <ArrowLeftRight size={24} color="#ffffff" strokeWidth={2.4} />
          </Pressable>
          <Pressable accessibilityRole="button" accessibilityLabel={m("menu")} onPress={() => setMenu(true)} hitSlop={8} style={{ padding: space[1.5] ?? 6 }}>
            <EllipsisVertical size={24} color="#ffffff" />
          </Pressable>
        </View>
      </SafeAreaView>
      {top}

      {/* The four tiles fill the screen: hit without aiming. */}
      <View style={{ flex: 1, padding: 10, gap: 10 }}>
        {[TILES.slice(0, 2), TILES.slice(2)].map((row) => (
          <View key={row[0].key} style={{ flex: 1, flexDirection: "row", gap: 10 }}>
            {row.map((tile) => (
              <Pressable
                key={tile.key}
                accessibilityRole="button"
                accessibilityLabel={`${q(`action.${tile.key}`)} ${num(counts[tile.key])}`}
                onPress={() => onTap(tile.key)}
                style={({ pressed }) => ({
                  flex: 1,
                  borderRadius: 22,
                  backgroundColor: tile.bg,
                  alignItems: "center",
                  justifyContent: "center",
                  gap: 6,
                  opacity: !ready ? 0.55 : pressed || flash === tile.key ? 0.78 : 1,
                  transform: [{ scale: pressed ? 0.98 : 1 }],
                  shadowColor: "#0f172a",
                  shadowOffset: { width: 0, height: 3 },
                  shadowRadius: 6,
                  shadowOpacity: 0.12,
                  elevation: 3,
                })}
              >
                {tile.ring ? (
                  <View style={{ width: 52, height: 52, borderRadius: 26, backgroundColor: "#ffffff", alignItems: "center", justifyContent: "center" }}>
                    <tile.icon size={30} color={tile.ring} strokeWidth={3.2} />
                  </View>
                ) : (
                  <tile.icon size={52} color="#ffffff" fill="#ffffff" stroke={tile.bg} strokeWidth={2} />
                )}
                <Text style={{ color: "#ffffff", fontFamily: fonts.bold, fontSize: 60, lineHeight: 70, fontVariant: ["tabular-nums"] }} numberOfLines={1} adjustsFontSizeToFit>
                  {num(counts[tile.key])}
                </Text>
                <Text style={{ color: "#ffffff", fontFamily: fonts.semibold, fontSize: 18, letterSpacing: 2 }}>{q(`action.${tile.key}`).toUpperCase()}</Text>
              </Pressable>
            ))}
          </View>
        ))}
      </View>

      {/* The day's figures at the foot. */}
      <SafeAreaView edges={["bottom"]} style={{ backgroundColor: "#ffffff", borderTopWidth: 1, borderTopColor: "#E2E8F0" }}>
        <View style={{ flexDirection: "row", paddingVertical: space[3] }}>
          {(
            foot ?? [
              [q("kpi.checked"), num(s?.checked ?? 0), "#2563EB"],
              [q("kpi.pass"), num(s?.pass ?? 0), "#16A34A"],
              [q("kpi.target"), num(s?.target ?? target?.target_qty ?? null), "#3730A3"],
              [m("dhu_pct"), dhu === null ? "0.0" : Number(dhu).toFixed(1), dhu > 5 ? "#DC2626" : "#16A34A"],
            ]
          ).map(([label, value, color], i) => (
            <View key={label} style={{ flex: 1, alignItems: "center", gap: 2, borderLeftWidth: i ? 1 : 0, borderLeftColor: "#E2E8F0" }}>
              <Text style={{ fontFamily: fonts.bold, fontSize: 24, lineHeight: 30, color }} numberOfLines={1} adjustsFontSizeToFit>
                {value}
              </Text>
              <Text style={{ fontSize: 13, color: "#64748B" }} numberOfLines={1}>
                {label}
              </Text>
            </View>
          ))}
        </View>
      </SafeAreaView>

      {/* The menu: who is tapping, the day's history, logout. */}
      <Modal visible={menu} transparent animationType="fade" onRequestClose={() => setMenu(false)} statusBarTranslucent>
        <Pressable style={{ flex: 1 }} onPress={() => setMenu(false)}>
          <SafeAreaView edges={["top"]} style={{ alignItems: "flex-end", paddingRight: space[2] }}>
            <View style={{ minWidth: 200, marginTop: space[2], borderRadius: 6, backgroundColor: "#ffffff", paddingVertical: space[1], shadowColor: "#0f172a", shadowOffset: { width: 0, height: 6 }, shadowRadius: 14, shadowOpacity: 0.2, elevation: 10 }}>
              <View style={{ paddingHorizontal: space[4], paddingVertical: space[2], borderBottomWidth: 1, borderBottomColor: "#E2E8F0" }}>
                <Text style={{ fontFamily: fonts.bold, fontSize: 16, color: BAR }}>{m("id_of", { id: qc.qc.employee_id })}</Text>
                <Text style={{ fontSize: 13, color: "#64748B" }} numberOfLines={1}>
                  {qc.qc.name}
                </Text>
              </View>
              <Pressable onPress={() => { setMenu(false); setHistory(true); }} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], paddingHorizontal: space[4], paddingVertical: space[3], backgroundColor: pressed ? "#F1F5F9" : "#ffffff" })}>
                <History size={18} color="#334155" />
                <Text style={{ fontSize: 15, color: "#0F172A" }}>{m("history")}</Text>
              </Pressable>
              <Pressable onPress={() => { setMenu(false); onLogout(); }} style={({ pressed }) => ({ flexDirection: "row", alignItems: "center", gap: space[3], paddingHorizontal: space[4], paddingVertical: space[3], backgroundColor: pressed ? "#F1F5F9" : "#ffffff" })}>
                <LogOut size={18} color="#DC2626" />
                <Text style={{ fontSize: 15, color: "#0F172A" }}>{m("logout")}</Text>
              </Pressable>
            </View>
          </SafeAreaView>
        </Pressable>
      </Modal>

      {/* History: the day on this line and job — every figure, this hour, the defects found most. */}
      <Sheet open={history} onClose={() => setHistory(false)} title={m("history")} hint={[line.label, job ? `${job.job_no} · ${job.style}` : null].filter(Boolean).join(" · ")} icon={History}>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
          <Stat label={q("kpi.target")} value={num(s?.target ?? target?.target_qty ?? null)} sub={thisHour ? m("this_hour", { n: num(thisHour.pass) }) : null} />
          <Stat label={q("kpi.checked")} value={num(s?.checked ?? 0)} />
          <Stat label={q("kpi.pass")} value={num(s?.pass ?? 0)} tone="success" />
          <Stat label={q("kpi.achievement")} value={pct(s?.achievement)} />
          <Stat label={q("kpi.dhu")} value={pct(s?.dhu)} tone={s?.dhu > 5 ? "danger" : "foreground"} />
          <Stat label={q("kpi.mine")} value={num(s?.mine ?? 0)} />
        </View>
        {s?.top_defects?.length ? (
          <View style={{ gap: space[1] }}>
            <Text variant="cardTitle">{q("tally.top_defects")}</Text>
            {s.top_defects.map((d) => (
              <DetailRow key={d.id} label={`${d.code} — ${bn && d.name_bn ? d.name_bn : d.name}`} value={num(d.count)} />
            ))}
          </View>
        ) : null}
        <SyncBar offline={offline} />
      </Sheet>
    </View>
  );
}

/** One colour per job in a list (user, 2026-11-28): the jobs told apart at a glance, in the order they first appear. */
const JOB_COLORS = ["#2563EB", "#16A34A", "#D97706", "#9333EA", "#DB2777", "#0891B2", "#DC2626", "#4F46E5", "#059669", "#C2410C"];

/**
 * The table's bundle cards on the phone: one is picked instead of scanned.
 * Each with its job number in the job's own colour and its figures written
 * plainly — size, part, how many checked, the bundle's quantity (user, 2026-11-28).
 */
function BundleList({ bundles, onPick, flat = false }) {
  if (!bundles.length) return null;
  const order = [];
  for (const b of bundles) if (!order.includes(b.job_no ?? "")) order.push(b.job_no ?? "");
  const colorOf = (jobNo) => JOB_COLORS[order.indexOf(jobNo ?? "") % JOB_COLORS.length];
  const fact = (label, value, color = "#0F172A") => (
    <View key={label} style={{ flexDirection: "row", alignItems: "baseline", gap: 4, paddingHorizontal: 8, paddingVertical: 3, borderRadius: 8, backgroundColor: "#F1F5F9" }}>
      <Text style={{ fontSize: 12, color: "#334155" }}>{label}</Text>
      <Text style={{ fontSize: 13, fontFamily: fonts.bold, color }}>{value}</Text>
    </View>
  );
  const list = (
    <>
      <Text variant="cardTitle">{m("table_bundles")}</Text>
      {bundles.slice(0, 40).map((b) => {
        const color = colorOf(b.job_no);
        return (
          <Pressable key={b.id} onPress={() => onPick(b)} style={({ pressed }) => ({ flexDirection: "row", borderRadius: 12, borderWidth: 1, borderColor: "#E2E8F0", backgroundColor: pressed ? "#F8FAFC" : "#ffffff", overflow: "hidden" })}>
            <View style={{ width: 5, backgroundColor: color }} />
            <View style={{ flex: 1, padding: space[2.5] ?? 10, gap: 6 }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], flexWrap: "wrap" }}>
                <Text style={{ fontFamily: fonts.bold, fontSize: 15, color: "#0F172A" }}>{b.bundle_no}</Text>
                {b.job_no ? (
                  <View style={{ paddingHorizontal: 8, paddingVertical: 2, borderRadius: 8, backgroundColor: `${color}1A`, borderWidth: 1, borderColor: `${color}55` }}>
                    <Text style={{ fontFamily: fonts.bold, fontSize: 13, color }}>{b.job_no}</Text>
                  </View>
                ) : null}
              </View>
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
                {b.size ? fact(q("col.size"), b.size) : null}
                {b.panel ? fact(q("col.panel"), b.panel) : null}
                {fact(q("kpi.checked"), num(b.checked), Number(b.checked) > 0 ? "#16A34A" : "#0F172A")}
                {fact(q("col.bundle_qty"), num(b.qty))}
              </View>
            </View>
          </Pressable>
        );
      })}
    </>
  );

  return flat ? <View style={{ gap: space[2] }}>{list}</View> : <Card style={{ padding: space[4], gap: space[2] }}>{list}</Card>;
}
