/**
 * One work order on the phone (AnnotechERP 37, phase 4): the job, who is on
 * it, its next steps (start, hold, resume, complete, verify, close — each with
 * the permission its API checks), the checklist and the photos.
 */
import { useCallback } from "react";
import { View } from "react-native";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { ChevronRight } from "lucide-react-native";
import { Card, DetailRow, Header, Screen, Text } from "../../components/ui";
import { Checklist, Photos, Priority, WorkOrderStatus, bt, lower, wt } from "../../components/machinery";
import { LoadState, useLoad } from "../../components/manage";
import { StepButtons } from "../../components/steps";
import { dateTimeOf } from "../../lib/clock";
import { t } from "../../lib/i18n";
import { useMachineAccess } from "../../lib/machines";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";
import { useColors } from "../../theme/theme";

const HOLD_REASONS = ["AWAITING_PARTS", "AWAITING_APPROVAL", "AWAITING_VENDOR", "PRODUCTION_RUNNING", "SHIFT_END", "OTHER"];

export default function WorkOrderScreen() {
  const { id } = useLocalSearchParams();
  const { call } = useSession();
  const { can } = useMachineAccess();
  const colors = useColors();
  const load = useLoad(`/work-orders/${id}`, [id]);
  const w = load.data;

  useFocusEffect(
    useCallback(() => {
      load.reload();
    }, [load.reload]),
  );

  const step = (key, label, path, extra = {}) => ({ key, label, send: (values) => call(`/work-orders/${id}/${path}`, { method: "POST", body: values }), ...extra });
  const steps = [];
  if (w) {
    const s = w.status;
    if (s === "ASSIGNED" && can("work_order.work_order.start")) steps.push(step("start", wt("start"), "start"));
    if (s === "IN_PROGRESS" && can("work_order.work_order.complete")) steps.push(step("complete", wt("complete"), "complete"));
    if (s === "IN_PROGRESS" && can("work_order.work_order.start")) {
      steps.push(
        step("hold", wt("hold"), "hold", {
          variant: "outline",
          fields: [
            { key: "reason_code", label: wt("hold_reason"), kind: "chips", required: true, options: HOLD_REASONS.map((r) => ({ value: r, label: wt(`hold_reason_${lower(r)}`) })) },
            { key: "notes", label: wt("notes"), kind: "text" },
          ],
        }),
      );
    }
    if (s === "ON_HOLD" && can("work_order.work_order.start")) steps.push(step("resume", wt("resume"), "resume"));
    if (s === "COMPLETED" && w.requires_verification && can("work_order.work_order.verify")) steps.push(step("verify", wt("verify"), "verify"));
    if ((s === "VERIFIED" || (s === "COMPLETED" && !w.requires_verification)) && can("work_order.work_order.close")) steps.push(step("close", wt("close"), "close"));
  }

  return (
    <Screen header={<Header title={w?.work_order_number ?? wt("work_order")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <LoadState {...load} />
      {w ? (
        <>
          <Card>
            <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], flexWrap: "wrap" }}>
              <WorkOrderStatus status={w.status} />
              <Priority priority={w.priority} />
            </View>
            {w.title ? <Text variant="cardTitle">{w.title}</Text> : null}
            <Card onPress={() => router.push({ pathname: "/machine/[id]", params: { id: w.asset.id } })} style={{ padding: space[3], flexDirection: "row", alignItems: "center", gap: space[2], backgroundColor: colors.surfaceMuted, shadowOpacity: 0, elevation: 0 }}>
              <View style={{ flex: 1 }}>
                <Text variant="cardTitle">{w.asset?.asset_code}</Text>
                <Text variant="small" tone="foregroundMuted">
                  {w.asset?.name}
                </Text>
              </View>
              <ChevronRight size={20} color={colors.foregroundSubtle} />
            </Card>
            {w.description ? <Text>{w.description}</Text> : null}
            {w.maintenance_type ? <DetailRow label={wt("maintenance_type")} value={w.maintenance_type} /> : null}
            <DetailRow label={wt("source")} value={wt(`source_${lower(w.source)}`)} />
            <DetailRow label={wt("assigned_to")} value={(w.assignments ?? []).map((a) => a.technician_name).join(", ") || wt("nobody_assigned")} />
            {w.scheduled_start ? <DetailRow label={wt("scheduled_start")} value={dateTimeOf(w.scheduled_start)} /> : null}
          </Card>

          {w.breakdown_id ? (
            <Card onPress={() => router.push({ pathname: "/breakdown/[id]", params: { id: w.breakdown_id } })} style={{ flexDirection: "row", alignItems: "center", gap: space[3] }}>
              <Text variant="cardTitle" style={{ flex: 1 }}>
                {bt("breakdown")}
              </Text>
              <ChevronRight size={20} color={colors.foregroundSubtle} />
            </Card>
          ) : null}

          <StepButtons steps={steps} onDone={load.reload} />
          <Checklist key={`${w.id}-${w.status}`} workOrderId={w.id} canRecord={w.status === "IN_PROGRESS" && can("work_order.work_order.start")} />
          <Photos path={`/work-orders/${id}/attachments`} canAdd={can("work_order.work_order.update")} />
        </>
      ) : null}
    </Screen>
  );
}
