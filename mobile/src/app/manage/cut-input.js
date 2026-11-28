/**
 * Cutting Input on the cutting manager's phone (AnnotechERP 37, phase 3; user
 * 2026-11-26: the camera scans). The cutting department's bundle card is
 * scanned; its details and the job it belongs to (matched by the server, as
 * on the web) open at once; "Give to QC" gives it, and the camera opens again
 * for the next card — card after card. A card the camera cannot read, or one
 * not on Cutting Floor, is typed in by hand. No table is picked: the QC table
 * that first scans the bundle takes it.
 */
import { useState } from "react";
import { View } from "react-native";
import { router } from "expo-router";
import { Check, Keyboard, Plus } from "lucide-react-native";
import { Alert, Button, Card, DetailRow, Header, Input, Screen, Text } from "../../components/ui";
import { Scanner } from "../../components/scanner";
import { Option, Sheet, Toast, num } from "../../components/tally";
import { DateBar, Figures, LoadState, dayLabel, mm, q, todayDhaka, useLoad } from "../../components/manage";
import { t } from "../../lib/i18n";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";

const BLANK = { bundle_no: "", job_id: "", garment_type: "", panel_id: "", size: "", qty: "", serial_from: "", serial_to: "", cutting_no: "", batch_no: "" };

export default function CutInput() {
  const { call } = useSession();
  const [date, setDate] = useState(todayDhaka());
  const form = useLoad("/quality-tally/panel-inspections/form");
  const list = useLoad(`/quality-tally/cut-bundles?date=${date}`, [date]);
  const [card, setCard] = useState(null); // a card from Cutting Floor, to give
  const [manual, setManual] = useState(null); // the hand-typed card
  const [v, setV] = useState({});
  const [pick, setPick] = useState(null); // job | garment | panel
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const [reading, setReading] = useState(false);
  const [notice, setNotice] = useState(null);
  const [last, setLast] = useState(null);
  const [scanKey, setScanKey] = useState(0);

  const say = (tone, text) => {
    setNotice({ tone, text });
    setTimeout(() => setNotice(null), 4500);
  };
  const jobsAll = form.data?.jobs ?? [];
  const garments = form.data?.garment_types ?? [];
  const sheetOpen = card !== null || manual !== null;
  const values = card ? v : manual;
  const setValues = (patch) => (card ? setV((x) => ({ ...x, ...patch })) : setManual((x) => ({ ...x, ...patch })));
  const panels = garments.find((g) => g.name === values?.garment_type)?.panels ?? [];
  // The jobs of the card's style first; every open job when none has it.
  const jobs = card?.job_choices?.length ? jobsAll.filter((j) => card.job_choices.includes(j.value) || j.value === v.job_id) : jobsAll;

  const scan = async (code) => {
    setReading(true);
    try {
      const res = await call(`/quality-tally/cut-bundles/scan?code=${encodeURIComponent(code)}`);
      if (res.found === "CARD") {
        setErrors({});
        setV({ job_id: res.card.job_id ?? "", garment_type: res.card.garment_type ?? "", panel_id: res.card.panel_id ?? "" });
        setCard(res.card);
      } else if (res.found === "INPUT") {
        say("warning", res.bundle.line ? q("ci.given_to", { line: res.bundle.line, date: dayLabel(res.bundle.input_date) }) : q("ci.given_free", { date: dayLabel(res.bundle.input_date) }));
      } else {
        say("danger", q("ci.missing", { code }));
        setErrors({});
        setManual({ ...BLANK, bundle_no: code.toUpperCase() });
      }
    } catch (e) {
      say("danger", e.message);
    } finally {
      setReading(false);
    }
  };

  const give = async () => {
    const src = card
      ? { bundle_no: card.bundle_no, barcode: card.barcode, size: card.size ?? "", qty: card.qty, serial_from: card.serial_from, serial_to: card.serial_to, cutting_no: card.cutting_no ?? "", batch_no: card.batch_no ?? "", source_id: card.source_id, ...v }
      : { ...manual, barcode: manual.bundle_no };
    const body = {
      ...src,
      panel_id: src.panel_id || null,
      input_date: date,
      qty: src.qty === "" ? null : Number(src.qty),
      serial_from: src.serial_from === "" || src.serial_from === null ? null : Number(src.serial_from),
      serial_to: src.serial_to === "" || src.serial_to === null ? null : Number(src.serial_to),
    };
    setBusy(true);
    setErrors({});
    try {
      await call("/quality-tally/cut-bundles", { method: "POST", body });
      const job = jobsAll.find((j) => j.value === body.job_id)?.label ?? "";
      setLast({ no: body.bundle_no, job });
      setCard(null);
      setManual(null);
      say("success", q("toast.bundle_given"));
      list.reload();
      // The camera again, for the next card.
      setScanKey((k) => k + 1);
    } catch (e) {
      if (e.errors) setErrors(e.errors);
      else say("danger", e.message);
    } finally {
      setBusy(false);
    }
  };

  const err = (k) => errors?.[k]?.[0] ?? null;
  const rows = list.data?.rows ?? [];
  const tot = rows.reduce((a, r) => ({ qty: a.qty + r.qty, checked: a.checked + r.checked, reject: a.reject + r.reject }), { qty: 0, checked: 0, reject: 0 });

  return (
    <View style={{ flex: 1 }}>
      <Screen header={<Header title={q("screens.cutting-input")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
        {form.error ? <Alert>{form.error}</Alert> : null}
        <Card style={{ padding: space[4] }}>
          <Text variant="sectionTitle">{q("ci.qr_title")}</Text>
          {sheetOpen ? null : <Scanner key={scanKey} onScan={scan} busy={reading} />}
          <Button
            variant="outline"
            icon={Keyboard}
            onPress={() => {
              setErrors({});
              setManual({ ...BLANK });
            }}
          >
            {q("ci.manual")}
          </Button>
          {last ? (
            <View style={{ flexDirection: "row", gap: space[2], alignItems: "center" }}>
              <Check size={16} color="#16a34a" />
              <Text variant="small" tone="success" style={{ flex: 1 }}>
                {q("ci.last_given", last)}
              </Text>
            </View>
          ) : null}
        </Card>

        <DateBar value={date} onChange={setDate} />
        <LoadState {...list} />
        {list.data ? <Figures items={[[q("col.bundles"), num(rows.length)], [q("ci.bundle_pcs"), num(tot.qty)], [q("cpi.checked_panels"), num(tot.checked)], [q("cpi.reject_panels"), num(tot.reject), "danger"]]} /> : null}
        {list.data && rows.length === 0 ? <Text tone="foregroundMuted">{q("empty.bundles")}</Text> : null}
        {rows.map((r) => (
          <Card key={r.id} style={{ padding: space[4], gap: space[1] }}>
            <View style={{ flexDirection: "row", justifyContent: "space-between", gap: space[2] }}>
              <Text variant="cardTitle" style={{ flex: 1 }} numberOfLines={1}>
                {r.bundle_no}
              </Text>
              <Text variant="small" tone={r.line ? "foregroundMuted" : "warning"}>
                {r.line ?? q("ci.no_table")}
              </Text>
            </View>
            <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
              {[r.job_no, r.style, r.size, r.panel ?? q("ci.all_panels")].filter(Boolean).join(" · ")}
            </Text>
            <Figures items={[[q("col.bundle_qty"), num(r.qty)], [q("col.checked"), num(r.checked)], [q("col.pass"), num(r.pass), "success"], [q("action.REJECT"), num(r.reject), r.reject ? "danger" : "foreground"]]} />
          </Card>
        ))}
      </Screen>

      <Sheet
        open={sheetOpen && pick === null}
        onClose={() => {
          setCard(null);
          setManual(null);
          setScanKey((k) => k + 1);
        }}
        title={card ? q("ci.card_title", { no: card.bundle_no }) : q("ci.manual_title")}
        footer={
          <Button icon={Plus} loading={busy} onPress={give} style={{ flex: 1 }}>
            {q("ci.give")}
          </Button>
        }
      >
        {card ? (
          <View style={{ gap: space[2] }}>
            <DetailRow label={q("col.style")} value={[card.buyer, card.style, card.color].filter(Boolean).join(" · ")} />
            <DetailRow label={q("col.panel")} value={card.part || "—"} />
            <DetailRow label={q("col.size")} value={`${card.size || "—"} · ${q("col.bundle_qty")} ${num(card.qty)}`} />
            <DetailRow label={q("col.bundle_serial")} value={`${card.serial_from}-${card.serial_to}`} />
            <DetailRow label={q("col.cutting_no")} value={[card.cutting_no || "—", card.batch_no ? `${q("col.batch_no")} ${card.batch_no}` : null].filter(Boolean).join(" · ")} />
            {card.match ? <Alert tone="success">{q(`ci.match_${card.match}`)}</Alert> : card.job_choices?.length ? <Alert tone="warning">{q("ci.match_none", { style: card.style, color: card.color })}</Alert> : <Alert tone="warning">{q("ci.no_job", { style: card.style })}</Alert>}
          </View>
        ) : manual ? (
          <View style={{ gap: space[3] }}>
            <Input label={q("col.bundle_no")} value={manual.bundle_no} onChangeText={(x) => setValues({ bundle_no: x })} autoCapitalize="characters" error={err("bundle_no") ?? err("barcode")} />
            <View style={{ flexDirection: "row", gap: space[3] }}>
              <Input label={q("col.size")} value={manual.size} onChangeText={(x) => setValues({ size: x })} style={{ flex: 1 }} error={err("size")} />
              <Input label={q("col.bundle_qty")} value={String(manual.qty)} onChangeText={(x) => setValues({ qty: x.replace(/\D/g, "") })} keyboardType="number-pad" style={{ flex: 1 }} error={err("qty")} />
            </View>
            <View style={{ flexDirection: "row", gap: space[3] }}>
              <Input label={mm("serial_from")} value={String(manual.serial_from)} onChangeText={(x) => setValues({ serial_from: x.replace(/\D/g, "") })} keyboardType="number-pad" style={{ flex: 1 }} error={err("serial_from")} />
              <Input label={mm("serial_to")} value={String(manual.serial_to)} onChangeText={(x) => setValues({ serial_to: x.replace(/\D/g, "") })} keyboardType="number-pad" style={{ flex: 1 }} error={err("serial_to")} />
            </View>
            <View style={{ flexDirection: "row", gap: space[3] }}>
              <Input label={q("col.cutting_no")} value={manual.cutting_no} onChangeText={(x) => setValues({ cutting_no: x })} style={{ flex: 1 }} />
              <Input label={q("col.batch_no")} value={manual.batch_no} onChangeText={(x) => setValues({ batch_no: x })} style={{ flex: 1 }} />
            </View>
          </View>
        ) : null}

        {values ? (
          <View style={{ gap: space[3], marginTop: space[2] }}>
            <Picked label={q("f.job")} value={jobsAll.find((j) => j.value === values.job_id)?.label} placeholder={q("f.select_job")} error={err("job_id")} onPress={() => setPick("job")} />
            <Picked label={q("f.garment_type")} value={values.garment_type || null} placeholder={q("f.select_garment")} error={err("garment_type")} onPress={() => setPick("garment")} />
            <Picked label={q("col.panel")} value={panels.find((x) => x.id === values.panel_id)?.name ?? q("ci.all_panels")} placeholder={q("ci.all_panels")} error={err("panel_id")} onPress={() => values.garment_type && setPick("panel")} />
            {err("barcode") && card ? <Text variant="small" tone="danger">{err("barcode")}</Text> : null}
          </View>
        ) : null}
      </Sheet>

      <Sheet open={pick !== null} onClose={() => setPick(null)} title={pick === "job" ? q("f.select_job") : pick === "garment" ? q("f.select_garment") : q("col.panel")}>
        {pick === "job" ? (
          (card ? jobs : jobsAll).map((j) => (
            <Option
              key={j.value}
              title={j.label}
              on={values?.job_id === j.value}
              onPress={() => {
                // A job already cut is one garment: it comes with it.
                setValues({ job_id: j.value, ...(j.garment_type ? { garment_type: j.garment_type, panel_id: "" } : {}) });
                setPick(null);
              }}
            />
          ))
        ) : pick === "garment" ? (
          garments.map((g) => (
            <Option
              key={g.name}
              title={g.name}
              on={values?.garment_type === g.name}
              onPress={() => {
                setValues({ garment_type: g.name, panel_id: "" });
                setPick(null);
              }}
            />
          ))
        ) : (
          <>
            <Option
              title={q("ci.all_panels")}
              sub={q("ci.panels_all")}
              on={!values?.panel_id}
              onPress={() => {
                setValues({ panel_id: "" });
                setPick(null);
              }}
            />
            {panels.map((x) => (
              <Option
                key={x.id}
                title={x.name}
                on={values?.panel_id === x.id}
                onPress={() => {
                  setValues({ panel_id: x.id });
                  setPick(null);
                }}
              />
            ))}
          </>
        )}
      </Sheet>
      <Toast notice={notice} onClose={() => setNotice(null)} />
    </View>
  );
}

function Picked({ label, value, placeholder, error, onPress }) {
  return (
    <View style={{ gap: space[1] }}>
      <Text variant="label">{label}</Text>
      <Option title={value ?? placeholder} on={Boolean(value)} onPress={onPress} />
      {error ? (
        <Text variant="small" tone="danger">
          {error}
        </Text>
      ) : null}
    </View>
  );
}
