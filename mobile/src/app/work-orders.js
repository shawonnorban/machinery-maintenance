/**
 * Work orders (AnnotechERP 37, phase 4): the person's own first — the jobs on
 * their technician record, working ones on top — then every open one, or all.
 */
import { useCallback, useState } from "react";
import { View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { ChevronDown } from "lucide-react-native";
import { Alert, Button, Card, Header, Screen, Segmented, Text } from "../components/ui";
import { Priority, WorkOrderStatus, mt, usePages, wt } from "../components/machinery";
import { dateTimeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { factoryParam, useFactory } from "../lib/factory";
import { useSession } from "../lib/session";
import { space } from "../theme/tokens";

const PATHS = { mine: "/work-orders?assigned_to_me=true", open: "/work-orders?status=IN_PROGRESS", all: "/work-orders" };

export default function WorkOrders() {
  const [show, setShow] = useState("mine");
  const factory = useFactory(useSession().session?.me?.factories ?? []);
  // The factory chosen on Home narrows the list (the work orders on its machines).
  const list = usePages(`${PATHS[show]}${PATHS[show].includes("?") ? "&" : "?"}${factoryParam(factory)}`);

  useFocusEffect(
    useCallback(() => {
      list.reload();
    }, [show]),
  );

  return (
    <Screen header={<Header title={wt("work_orders")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <Segmented
        value={show}
        onChange={setShow}
        options={[
          { value: "mine", label: wt("my_work") },
          { value: "open", label: wt("status_in_progress") },
          { value: "all", label: mt("all") },
        ]}
      />
      {list.error && !list.rows ? <Alert>{list.error}</Alert> : null}
      {list.rows?.length === 0 ? <Text tone="foregroundMuted">{show === "mine" ? wt("no_my_work") : wt("no_work_orders")}</Text> : null}
      {(list.rows ?? []).map((w) => (
        <Card key={w.id} onPress={() => router.push({ pathname: "/work-order/[id]", params: { id: w.id } })} style={{ gap: space[1] }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
            <Text variant="cardTitle" style={{ flex: 1 }}>
              {w.work_order_number}
            </Text>
            <WorkOrderStatus status={w.status} />
          </View>
          {w.title ? <Text numberOfLines={2}>{w.title}</Text> : null}
          <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
            {`${w.asset?.asset_code ?? ""} — ${w.asset?.name ?? ""}`}
          </Text>
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
            <Priority priority={w.priority} />
            {w.scheduled_start ? (
              <Text variant="small" tone="foregroundMuted">
                {dateTimeOf(w.scheduled_start)}
              </Text>
            ) : null}
          </View>
        </Card>
      ))}
      {list.more ? (
        <Button variant="outline" icon={ChevronDown} loading={list.loading} onPress={list.more}>
          {mt("more")}
        </Button>
      ) : null}
    </Screen>
  );
}
