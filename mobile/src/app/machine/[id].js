/**
 * A machine on the phone (AnnotechERP 37, phase 4): what and where it is, its
 * open breakdowns, its last work, and "report a breakdown" for whoever may.
 */
import { router, useLocalSearchParams } from "expo-router";
import { View } from "react-native";
import { Siren } from "lucide-react-native";
import { Button, Card, DetailRow, Header, Screen, SectionHeader, Text } from "../../components/ui";
import { AssetStatus, BreakdownStatus, Priority, WorkOrderStatus, at, bt, lower, mt, usePages } from "../../components/machinery";
import { LoadState, useLoad } from "../../components/manage";
import { dateTimeOf } from "../../lib/clock";
import { t } from "../../lib/i18n";
import { useMachineAccess } from "../../lib/machines";
import { space } from "../../theme/tokens";

export default function Machine() {
  const { id } = useLocalSearchParams();
  const access = useMachineAccess();
  const load = useLoad(`/assets/${id}`, [id]);
  const asset = load.data;
  const open = useLoad(access.breakdowns ? `/breakdowns?asset_id=${id}&open=true&per_page=10` : null, [id]);
  const work = usePages(`/assets/${id}/maintenance-history`);
  const terminal = ["RETIRED", "SCRAPPED", "LOST", "DRAFT"].includes(asset?.status);

  return (
    <Screen header={<Header title={asset?.asset_code ?? mt("machine")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <LoadState {...load} />
      {asset ? (
        <>
          <Card>
            <View style={{ flexDirection: "row", alignItems: "flex-start", gap: space[2] }}>
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="sectionTitle">{asset.name}</Text>
                <Text variant="small" tone="foregroundMuted">
                  {asset.asset_code}
                </Text>
              </View>
              <AssetStatus status={asset.status} />
            </View>
            {asset.criticality ? <DetailRow label={at("criticality")} value={at(`criticality_${lower(asset.criticality)}`)} /> : null}
            {asset.type?.name ? <DetailRow label={at("type")} value={asset.type.name} /> : null}
            {asset.manufacturer?.name || asset.model ? <DetailRow label={at("model")} value={[asset.manufacturer?.name, asset.model].filter(Boolean).join(" · ")} /> : null}
            {asset.serial_number ? <DetailRow label={at("serial_number")} value={asset.serial_number} /> : null}
            <DetailRow label={at("factory")} value={asset.factory?.name ?? "—"} />
            <DetailRow label={at("location")} value={asset.location?.name ?? "—"} />
          </Card>

          {access.report && !terminal ? (
            <Button variant="danger" icon={Siren} onPress={() => router.push({ pathname: "/breakdown/new", params: { asset: asset.id } })} >
              {bt("report_breakdown")}
            </Button>
          ) : null}

          {access.breakdowns ? (
            <>
              <SectionHeader title={mt("open_breakdowns")} />
              {open.data?.length === 0 ? <Text tone="foregroundMuted">{mt("none_open")}</Text> : null}
              {(open.data ?? []).map((b) => (
                <Card key={b.id} onPress={() => router.push({ pathname: "/breakdown/[id]", params: { id: b.id } })} style={{ gap: space[1] }}>
                  <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                    <Text variant="cardTitle" style={{ flex: 1 }}>
                      {b.breakdown_number}
                    </Text>
                    <BreakdownStatus status={b.status} />
                  </View>
                  <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                    <Priority priority={b.priority} />
                    <Text variant="small" tone="foregroundMuted">
                      {dateTimeOf(b.reported_at)}
                    </Text>
                  </View>
                </Card>
              ))}
            </>
          ) : null}

          {work.rows?.length ? (
            <>
              <SectionHeader title={at("maintenance_history")} />
              <Card style={{ gap: space[2] }}>
                {work.rows.slice(0, 8).map((w) => (
                  <View key={w.id} style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                    <View style={{ flex: 1 }}>
                      <Text variant="label">{w.work_order_number}</Text>
                      <Text variant="small" tone="foregroundMuted">
                        {[w.type, dateTimeOf(w.completed_at ?? w.scheduled_start)].filter(Boolean).join(" · ")}
                      </Text>
                    </View>
                    <WorkOrderStatus status={w.status} />
                  </View>
                ))}
              </Card>
            </>
          ) : null}
        </>
      ) : null}
    </Screen>
  );
}
