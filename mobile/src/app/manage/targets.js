/**
 * A section's Line Target on the manager's phone (AnnotechERP 37, phase 3).
 * Two lists of the day: the targets given (pass so far, edit, delete), and
 * the pending orders — what is left, what the section before has ready, what
 * is planned — each with "Give target", which opens the target already
 * filled with the server's suggestion. The target is the sewing and quality
 * QC's input: what is not targeted cannot be tapped.
 */
import { useState } from "react";
import { Alert as Ask, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { Lock, Pencil, Plus, Trash2, Truck } from "lucide-react-native";
import { Alert, Badge, Button, Card, Header, Input, Screen, Segmented, Text } from "../../components/ui";
import { Option, Sheet, Toast, num, pct } from "../../components/tally";
import { DateBar, Figures, LoadState, mm, q, todayDhaka, useAccess, useLoad } from "../../components/manage";
import { t } from "../../lib/i18n";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";

const BEFORE = { SEWING: "CUTTING", QUALITY: "SEWING" };

export default function Targets() {
  const { section } = useLocalSearchParams();
  const { call } = useSession();
  const manages = useAccess().some((a) => a.section === section && a.manages);
  const [date, setDate] = useState(todayDhaka());
  const [tab, setTab] = useState("given");
  const [form, setForm] = useState(null); // { id?, line_id, job_id, working_hours, manpower, target_qty, remarks, hint }
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState(null);
  const [pick, setPick] = useState(null); // "line" | "job"

  const lookups = useLoad("/quality-tally/lookups");
  const given = useLoad(`/quality-tally/targets?section=${section}&date=${date}`, [date]);
  const pending = useLoad(tab === "pending" ? `/quality-tally/targets/pending?section=${section}&date=${date}` : null, [date, tab]);
  const lines = lookups.data?.lines?.[section] ?? [];
  const jobs = lookups.data?.jobs ?? [];
  const before = BEFORE[section] ? q(`section.${BEFORE[section]}`) : "";

  const say = (tone, text) => {
    setNotice({ tone, text });
    setTimeout(() => setNotice(null), 4000);
  };

  const open = (start) => {
    setErrors({});
    setForm({ line_id: "", job_id: "", working_hours: "8", manpower: "", target_qty: "", remarks: "", ...start });
  };

  const save = async () => {
    setBusy(true);
    setErrors({});
    const body = {
      target_date: date, section, line_id: form.line_id, job_id: form.job_id, working_hours: Number(form.working_hours) || null,
      manpower: form.manpower === "" ? null : Number(form.manpower), target_qty: Number(form.target_qty) || null, remarks: form.remarks || null,
    };
    try {
      await call(form.id ? `/quality-tally/targets/${form.id}` : "/quality-tally/targets", { method: form.id ? "PATCH" : "POST", body });
      setForm(null);
      say("success", q("toast.target_saved"));
      given.reload();
      pending.reload();
    } catch (e) {
      if (e.errors) setErrors(e.errors);
      else say("danger", e.message);
    } finally {
      setBusy(false);
    }
  };

  const remove = (row) =>
    Ask.alert(q("targets.delete_title"), `${row.line} · ${row.job_no}`, [
      { text: mm("cancel"), style: "cancel" },
      {
        text: q("btn.delete"),
        style: "destructive",
        onPress: async () => {
          try {
            await call(`/quality-tally/targets/${row.id}`, { method: "DELETE" });
            say("success", q("toast.deleted"));
            given.reload();
          } catch (e) {
            say("danger", e.message);
          }
        },
      },
    ]);

  const err = (k) => errors?.[k]?.[0] ?? null;
  const lineLabel = lines.find((l) => l.value === form?.line_id)?.label;
  const jobLabel = jobs.find((j) => j.value === form?.job_id)?.label;

  return (
    <View style={{ flex: 1 }}>
      <Screen header={<Header title={q(`screens.${section.toLowerCase()}-target`)} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
        <DateBar value={date} onChange={setDate} />
        <Segmented value={tab} onChange={setTab} options={[{ value: "given", label: mm("targets_given") }, { value: "pending", label: q("pend.title") }]} />

        {tab === "given" ? (
          <>
            <LoadState {...given} />
            {manages ? (
              <Button icon={Plus} onPress={() => open({})}>
                {q("btn.add_target")}
              </Button>
            ) : null}
            {given.data?.rows.length === 0 ? <Text tone="foregroundMuted">{q("empty.targets")}</Text> : null}
            {(given.data?.rows ?? []).map((r) => (
              <Card key={r.id} style={{ padding: space[4], gap: space[1] }}>
                <View style={{ flexDirection: "row", justifyContent: "space-between", gap: space[2] }}>
                  <Text variant="cardTitle" style={{ flex: 1 }} numberOfLines={1}>
                    {r.line}
                  </Text>
                  <Text variant="cardTitle" tone={r.achievement >= 100 ? "success" : "foreground"}>
                    {pct(r.achievement)}
                  </Text>
                </View>
                <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
                  {[r.job_no, r.buyer_name, r.style].filter(Boolean).join(" · ")}
                </Text>
                <Figures items={[[q("kpi.target"), num(r.target_qty)], [q("kpi.pass"), num(r.pass), "success"], [q("kpi.hourly_target"), num(r.hourly_target)], [q("f.working_hours"), num(r.working_hours)], [q("f.manpower"), num(r.manpower)]]} />
                {manages ? (
                  <View style={{ flexDirection: "row", gap: space[2], marginTop: space[1] }}>
                    <Button
                      variant="outline"
                      icon={Pencil}
                      onPress={() => open({ id: r.id, line_id: r.line_id, job_id: r.job_id, working_hours: String(r.working_hours), manpower: r.manpower ? String(r.manpower) : "", target_qty: String(r.target_qty), remarks: r.remarks ?? "" })}
                      style={{ flex: 1, minHeight: 40 }}
                    >
                      {q("btn.edit")}
                    </Button>
                    <Button variant="outline" icon={Trash2} onPress={() => remove(r)} style={{ flex: 1, minHeight: 40 }}>
                      {q("btn.delete")}
                    </Button>
                  </View>
                ) : null}
              </Card>
            ))}
          </>
        ) : (
          <>
            <LoadState {...pending} />
            {pending.data?.rows.length === 0 ? <Text tone="foregroundMuted">{q("pend.none")}</Text> : null}
            {(pending.data?.rows ?? []).map((r) => (
              <Card key={r.id} style={{ padding: space[4], gap: space[1] }}>
                <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                  <Text variant="cardTitle" numberOfLines={1} style={{ flex: 1 }}>
                    {r.job_no} · {r.style}
                  </Text>
                  {r.stage ? <StageBadge stage={r.stage} /> : null}
                </View>
                <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
                  {[r.buyer_name, r.color, r.due_date ? (r.days_left < 0 ? q("pend.late", { n: -r.days_left }) : q("pend.days_left", { n: r.days_left })) : null].filter(Boolean).join(" · ")}
                </Text>
                <Figures
                  items={[
                    [q("f.order_qty"), num(r.order_qty)],
                    [q("pend.done"), num(r.done), "success"],
                    [q("pend.left"), num(r.left)],
                    ...(r.ready !== null ? [[q("pend.ready", { before }), num(r.ready), r.ready > 0 ? "foreground" : "warning"]] : []),
                    [q("pend.planned"), num(r.planned)],
                  ]}
                />
                {r.planned_lines?.length ? (
                  <Text variant="small" tone="foregroundMuted">
                    {r.planned_lines.join(", ")}
                  </Text>
                ) : null}
                {/* Not this section's yet (user, 2026-11-28): where the job is, that it is coming, and no target until it arrives. */}
                {r.blocked ? (
                  <View style={{ flexDirection: "row", gap: space[2], alignItems: "flex-start", padding: space[2], borderRadius: 10, backgroundColor: "#FFF7E6" }}>
                    <Truck size={16} color="#B45309" />
                    <Text variant="small" style={{ flex: 1, color: "#92400E" }}>
                      {r.blocked}
                    </Text>
                  </View>
                ) : null}
                {manages ? (
                  <Button icon={r.blocked ? Lock : Plus} disabled={Boolean(r.blocked)} onPress={() => open({ job_id: r.id, target_qty: r.suggest ? String(r.suggest) : "", hint: section === "CUTTING" ? q("pend.panels_hint") : null })} style={{ marginTop: space[1], minHeight: 40 }}>
                    {r.blocked ? q("stage.waiting") : q("pend.give")}
                  </Button>
                ) : null}
              </Card>
            ))}
          </>
        )}
      </Screen>

      <Sheet
        open={form !== null && pick === null}
        onClose={() => setForm(null)}
        title={form?.id ? q("targets.edit_title") : q("targets.add_title")}
        hint={form?.hint}
        footer={
          <Button loading={busy} onPress={save} style={{ flex: 1 }}>
            {q("btn.save")}
          </Button>
        }
      >
        {form ? (
          <View style={{ gap: space[3] }}>
            <View style={{ gap: space[1] }}>
              <Text variant="label">{q("f.line")}</Text>
              <Option title={lineLabel ?? q("f.select_line")} on={Boolean(lineLabel)} onPress={() => setPick("line")} />
              {err("line_id") ? <Text variant="small" tone="danger">{err("line_id")}</Text> : null}
            </View>
            <View style={{ gap: space[1] }}>
              <Text variant="label">{q("f.job")}</Text>
              <Option title={jobLabel ?? q("f.select_job")} on={Boolean(jobLabel)} onPress={() => setPick("job")} />
              {err("job_id") ? <Text variant="small" tone="danger">{err("job_id")}</Text> : null}
            </View>
            <View style={{ flexDirection: "row", gap: space[3] }}>
              <Input label={q("f.target_qty")} value={form.target_qty} onChangeText={(v) => setForm({ ...form, target_qty: v.replace(/\D/g, "") })} keyboardType="number-pad" error={err("target_qty")} style={{ flex: 1 }} />
              <Input label={q("f.working_hours")} value={form.working_hours} onChangeText={(v) => setForm({ ...form, working_hours: v.replace(/[^\d.]/g, "") })} keyboardType="decimal-pad" error={err("working_hours")} style={{ flex: 1 }} />
            </View>
            <Input label={q("f.manpower")} value={form.manpower} onChangeText={(v) => setForm({ ...form, manpower: v.replace(/\D/g, "") })} keyboardType="number-pad" error={err("manpower")} />
            <Input label={q("f.remarks")} value={form.remarks} onChangeText={(v) => setForm({ ...form, remarks: v })} error={err("remarks")} />
            {form.target_qty && Number(form.working_hours) > 0 ? (
              <Text variant="small" tone="foregroundMuted">
                {q("kpi.hourly_target")}: {num(Math.round(Number(form.target_qty) / Number(form.working_hours)))}
              </Text>
            ) : null}
          </View>
        ) : null}
      </Sheet>

      <Sheet open={pick !== null} onClose={() => setPick(null)} title={pick === "line" ? q("f.select_line") : q("f.select_job")}>
        {pick === "line" && lines.length === 0 ? <Alert tone="warning">{q("f.no_lines")}</Alert> : null}
        {(pick === "line" ? lines : jobs).map((o) => {
          const blocked = pick === "job" ? (o.blocked?.[section] ?? null) : null;
          return (
            <View key={o.value} style={{ gap: 4, opacity: blocked ? 0.6 : 1 }}>
              <Option
                title={o.label}
                sub={pick === "job" && o.stage ? stageText(o.stage) : undefined}
                on={form?.[pick === "line" ? "line_id" : "job_id"] === o.value}
                onPress={() => {
                  if (blocked) {
                    setNotice({ tone: "warning", text: blocked });
                    return;
                  }
                  setForm({ ...form, [pick === "line" ? "line_id" : "job_id"]: o.value });
                  setPick(null);
                }}
              />
            </View>
          );
        })}
      </Sheet>
      <Toast notice={notice} onClose={() => setNotice(null)} />
    </View>
  );
}

/** Where a job is: at cutting (with its bundles), at sewing, at quality, or done. */
function stageText(stage) {
  const where = q(`stage.${stage.stage}`);
  return stage.stage === "CUTTING" ? `${where} · ${q("stage.bundles", { done: stage.bundles_done, total: stage.bundles })}` : where;
}

function StageBadge({ stage }) {
  const tone = { CUTTING: "info", SEWING: "brand", QUALITY: "warning", DONE: "success" }[stage.stage] ?? "neutral";
  return <Badge tone={tone}>{stage.stage === "CUTTING" ? `${q("stage.CUTTING")} ${stage.bundles_done}/${stage.bundles}` : q(`stage.${stage.stage}`)}</Badge>;
}
