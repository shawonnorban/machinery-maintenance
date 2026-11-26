/**
 * The QC's tally on a phone (AnnotechERP 37, phase 2) — the web's Tally Entry,
 * held in one hand beside a line. A tap is counted and kept on the phone at
 * once and sent behind (lib/taps): no tap ever waits for the server, and none
 * is lost to a dropped Wi-Fi. What the screen needs is kept on the phone too,
 * so it opens and counts with no network.
 *
 *  - Sewing and Quality: the job (only those with a target on this line today —
 *    the target is their input), the running figures, four buttons, Undo.
 *  - Cutting (user, 2026-11-25/26): the bundle card scanned with the camera
 *    (or typed, or picked from the table's list), the panel in hand, four
 *    buttons; no more first checks than the bundle holds.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Alert as Ask, Vibration, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { Layers, RefreshCw, ScanLine, ShieldAlert, Undo2, Wifi, WifiOff } from "lucide-react-native";
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
import { space } from "../theme/tokens";
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
  const { session, sessionEnded, setOnline } = useSession();
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
          <Button icon={ShieldAlert} disabled={!chosen.length} onPress={saveDefect} style={{ flex: 1, minHeight: 56, backgroundColor: colors.warning, borderColor: colors.warning }}>
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
    </Screen>
    <Toast notice={notice} onClose={() => setNotice(null)} />
    </View>
  );
}

/** The table's bundle cards on the phone: one is picked instead of scanned. */
function BundleList({ bundles, onPick }) {
  if (!bundles.length) return null;

  return (
    <Card style={{ padding: space[4], gap: space[2] }}>
      <Text variant="cardTitle">{m("table_bundles")}</Text>
      {bundles.slice(0, 40).map((b) => (
        <Option key={b.id} title={`${b.bundle_no} · ${b.job_no ?? ""}`} sub={[b.size, b.panel, `${num(b.checked)} ${q("kpi.checked").toLowerCase()}`, `${q("col.bundle_qty")} ${num(b.qty)}`].filter(Boolean).join(" · ")} onPress={() => onPick(b)} />
      ))}
    </Card>
  );
}
