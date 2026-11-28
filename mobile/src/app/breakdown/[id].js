/**
 * One breakdown on the phone (AnnotechERP 37, phase 4), from report to close:
 * where it stands, the next steps this person may take (the web's own rules —
 * BreakdownActions — with each step's permission), its repair work order and
 * checklist, the times so far, and its photos.
 */
import { useCallback } from "react";
import { View } from "react-native";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { ChevronRight } from "lucide-react-native";
import { Alert, Card, DetailRow, Header, Screen, SectionHeader, Text } from "../../components/ui";
import { BreakdownStatus, Checklist, Photos, Priority, WorkOrderStatus, bt, lower, mt, wt } from "../../components/machinery";
import { LoadState, useLoad } from "../../components/manage";
import { StepButtons } from "../../components/steps";
import { dateTimeOf } from "../../lib/clock";
import { t } from "../../lib/i18n";
import { useMachineAccess } from "../../lib/machines";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";
import { useColors } from "../../theme/theme";

const CHAIN = ["failure_at", "reported_at", "acknowledged_at", "technician_arrival_at", "repair_started_at", "repair_completed_at", "production_resumed_at"];
const WO_DONE = ["CLOSED", "CANCELLED"];

export default function BreakdownScreen() {
  const { id, made } = useLocalSearchParams();
  const { call } = useSession();
  const { can } = useMachineAccess();
  const colors = useColors();
  const load = useLoad(`/breakdowns/${id}`, [id]);
  const options = useLoad(`/breakdowns/${id}/form-options`, [id]);
  const downtime = useLoad(`/breakdowns/${id}/downtime`, [id]);
  const b = load.data;
  const wo = b?.work_order ?? null;

  const reload = useCallback(() => {
    load.reload();
    downtime.reload();
  }, [load.reload, downtime.reload]);

  useFocusEffect(
    useCallback(() => {
      reload();
    }, [reload]),
  );

  const post = (path, body) => call(path, { method: "POST", body });
  const step = (key, label, path, extra = {}) => ({ key, label, send: (values) => post(`/breakdowns/${id}/${path}`, values), ...extra });

  const steps = [];
  if (b) {
    const o = options.data ?? {};
    const s = b.status;
    const pick = (items, sub) => (items ?? []).map((i) => ({ id: i.id, name: i.name, sub: sub?.(i) }));
    const reasonField = (key) => [{ key, label: bt("rejection_reason"), kind: "text", required: true, multiline: false }];

    if (s === "REPORTED" && can("breakdown.breakdown.acknowledge")) steps.push(step("acknowledge", bt("acknowledge"), "acknowledge"));
    if (["ACKNOWLEDGED", "ASSIGNED"].includes(s) && can("breakdown.breakdown.assign")) {
      steps.push(step("assign", bt("assign"), "assign", { variant: "outline", fields: [{ key: "technician_id", label: bt("technician"), kind: "pick", required: true, items: pick(o.technicians, (i) => i.employee_id), placeholder: bt("select_technician") }] }));
    }
    if (b.is_open && !b.timestamps?.technician_arrival_at && s !== "REPORTED" && can("breakdown.breakdown.repair")) steps.push(step("arrive", bt("record_arrival"), "arrive", { variant: "outline" }));
    if (wo && wo.status === "ASSIGNED" && can("work_order.work_order.start")) {
      steps.push({ key: "start_wo", label: bt("start_work_order"), ask: bt("start_work_order_description"), send: () => post(`/work-orders/${wo.id}/start`) });
    }
    if (s === "REPAIRED" && can("breakdown.breakdown.repair")) steps.push(step("reopen", bt("reopen_repair"), "start-repair", { variant: "outline" }));
    if (s === "IN_REPAIR" && can("breakdown.breakdown.repair")) {
      steps.push(
        step("complete", bt("complete_repair"), "complete-repair", {
          fields: [
            { key: "failure_code_id", label: bt("failure_code"), kind: "pick", required: true, items: pick(o.failure_codes), placeholder: bt("select_failure_code") },
            { key: "root_cause_id", label: bt("root_cause"), kind: "pick", required: true, items: pick(o.root_causes), placeholder: bt("select_root_cause") },
            { key: "corrective_action", label: bt("corrective_action"), kind: "text" },
            { key: "preventive_action", label: bt("preventive_action"), kind: "text" },
          ],
        }),
      );
      steps.push(
        step("hold", bt("hold"), "hold", {
          variant: "outline",
          fields: [
            { key: "reason_code", label: bt("hold_reason"), kind: "chips", required: true, options: (o.hold_reasons ?? []).map((r) => ({ value: r, label: bt(`hold_reason_${lower(r)}`) })) },
            { key: "notes", label: bt("notes"), kind: "text" },
          ],
        }),
      );
    }
    if (s === "ON_HOLD" && can("breakdown.breakdown.repair")) steps.push(step("resume", bt("resume"), "resume"));
    if (s === "REPAIRED" && can("breakdown.breakdown.repair")) steps.push(step("resume_production", bt("resume_production"), "resume-production"));
    if (["REPAIRED", "PRODUCTION_RESUMED"].includes(s) && can("breakdown.breakdown.verify")) {
      steps.push(step("verify", bt("verify"), "verify", { ask: bt("verify_description"), fields: [{ key: "notes", label: bt("notes"), kind: "text" }] }));
      steps.push(step("reject_verification", bt("reject"), "reject-verification", { variant: "outline", fields: reasonField("rejection_reason") }));
    }
    if (s === "VERIFIED" && can("breakdown.breakdown.verify")) steps.push(step("submit", bt("submit_for_approval"), "submit-for-approval"));
    if (s === "PENDING_APPROVAL" && can("breakdown.breakdown.close")) {
      steps.push(step("close", bt("close"), "close", { ask: bt("close_description"), fields: [{ key: "closure_notes", label: bt("closure_notes"), kind: "text" }] }));
      steps.push(step("reject", bt("reject"), "reject", { variant: "outline", ask: bt("reject_description"), fields: reasonField("rejection_reason") }));
    }
    if (!b.is_terminal && (!wo || WO_DONE.includes(wo.status)) && can("work_order.work_order.create")) {
      steps.push(step("raise", bt("raise_work_order"), "work-order", { variant: "outline", ask: bt("raise_work_order_description") }));
    }
    if (["REPORTED", "ACKNOWLEDGED", "ASSIGNED", "IN_REPAIR", "ON_HOLD"].includes(s) && can("breakdown.breakdown.close")) {
      steps.push(step("cancel", bt("cancel_breakdown"), "cancel", { variant: "danger", ask: bt("cancel_reason_hint"), fields: [{ key: "cancellation_reason", label: bt("cancellation_reason"), kind: "text", required: true, multiline: false }] }));
    }
  }

  const minutes = (n) => (n === null || n === undefined ? "—" : `${n} ${bt("minutes")}`);

  return (
    <Screen header={<Header title={b?.breakdown_number ?? bt("breakdown")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      {made ? <Alert tone="success">{bt("reported_message", { number: made })}</Alert> : null}
      <LoadState {...load} />
      {b ? (
        <>
          <Card>
            <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], flexWrap: "wrap" }}>
              <BreakdownStatus status={b.status} />
              <Priority priority={b.priority} />
            </View>
            <Card onPress={() => router.push({ pathname: "/machine/[id]", params: { id: b.asset.id } })} style={{ padding: space[3], flexDirection: "row", alignItems: "center", gap: space[2], backgroundColor: colors.surfaceMuted, shadowOpacity: 0, elevation: 0 }}>
              <View style={{ flex: 1 }}>
                <Text variant="cardTitle">{b.asset?.asset_code}</Text>
                <Text variant="small" tone="foregroundMuted">
                  {b.asset?.name}
                </Text>
              </View>
              <ChevronRight size={20} color={colors.foregroundSubtle} />
            </Card>
            <Text>{b.problem_description}</Text>
            <DetailRow label={bt("reported_by")} value={b.reported_by?.name ?? "—"} />
            <DetailRow label={bt("reported_at")} value={dateTimeOf(b.reported_at)} />
            {b.production_line?.name ? <DetailRow label={bt("production_line")} value={b.production_line.name} /> : null}
            <DetailRow label={bt("technician")} value={b.assigned_technician ?? bt("unassigned_placeholder")} />
            {b.downtime_reason ? <DetailRow label={bt("reason_code")} value={b.downtime_reason} /> : null}
            {b.failure_code ? <DetailRow label={bt("failure_code")} value={b.failure_code} /> : null}
            {b.root_cause ? <DetailRow label={bt("root_cause")} value={b.root_cause} /> : null}
            {b.corrective_action ? <DetailRow label={bt("corrective_action")} value={b.corrective_action} /> : null}
          </Card>

          <StepButtons steps={steps} onDone={reload} />

          {wo ? (
            <Card onPress={() => router.push({ pathname: "/work-order/[id]", params: { id: wo.id } })} style={{ flexDirection: "row", alignItems: "center", gap: space[3] }}>
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="small" tone="foregroundMuted">
                  {bt("repair_work_order")}
                </Text>
                <Text variant="cardTitle">{wo.work_order_number}</Text>
              </View>
              <WorkOrderStatus status={wo.status} />
              <ChevronRight size={20} color={colors.foregroundSubtle} />
            </Card>
          ) : null}
          {wo ? <Checklist key={`${wo.id}-${wo.status}`} workOrderId={wo.id} canRecord={wo.status === "IN_PROGRESS" && can("work_order.work_order.start")} /> : null}

          <Photos path={`/breakdowns/${id}/attachments`} canAdd={can("breakdown.breakdown.repair")} />

          <SectionHeader title={bt("timeline")} />
          <Card style={{ gap: space[2] }}>
            {CHAIN.map((field) => (
              <DetailRow key={field} label={bt(field)} value={b.timestamps?.[field] ? dateTimeOf(b.timestamps[field]) : bt("not_recorded")} />
            ))}
            {downtime.data ? (
              <>
                <DetailRow label={bt("response_time")} value={minutes(downtime.data.response_minutes)} />
                <DetailRow label={bt("repair_time")} value={minutes(downtime.data.repair_minutes)} />
                <DetailRow label={bt("total_downtime")} value={minutes(downtime.data.total_downtime_minutes)} />
              </>
            ) : null}
          </Card>
        </>
      ) : null}
    </Screen>
  );
}
