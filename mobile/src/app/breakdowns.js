/**
 * Breakdowns (AnnotechERP 37, phase 4): the open ones first, or all; the
 * reports still on this phone above them, each with its state — waiting for
 * the network, or refused and why.
 */
import { useCallback, useEffect, useRef, useState } from "react";
import { View } from "react-native";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { ChevronDown, CloudOff, RefreshCw, Siren, Trash2 } from "lucide-react-native";
import { Alert, Button, Card, Header, Screen, Segmented, Text } from "../components/ui";
import { BreakdownStatus, Priority, bt, mt, usePages } from "../components/machinery";
import { dateTimeOf } from "../lib/clock";
import { t } from "../lib/i18n";
import { useMachineAccess } from "../lib/machines";
import { factoryParam, useFactory } from "../lib/factory";
import { dropReport, retryReport, sendReports, useReports } from "../lib/reports";
import { useSession } from "../lib/session";
import { space } from "../theme/tokens";

export default function Breakdowns() {
  const { kept } = useLocalSearchParams();
  const { call } = useSession();
  const access = useMachineAccess();
  const [show, setShow] = useState("open");
  const factory = useFactory(useSession().session?.me?.factories ?? []);
  // The factory chosen on Home narrows the list.
  const list = usePages(access.breakdowns ? `/breakdowns?${show === "open" ? "open=true&" : ""}${factoryParam(factory)}` : null);
  const reports = useReports();
  const [sending, setSending] = useState(false);
  const before = useRef(reports.length);

  // A report sent from the phone's queue (here, or by the half-minute round) shows in the list at once.
  useEffect(() => {
    if (reports.length < before.current) list.reload();
    before.current = reports.length;
  }, [reports.length]);

  useFocusEffect(
    useCallback(() => {
      list.reload();
    }, [show]),
  );

  const sendNow = async () => {
    setSending(true);
    const sent = await sendReports(call).catch(() => 0);
    setSending(false);
    if (sent) list.reload();
  };

  return (
    <Screen header={<Header title={bt("breakdowns")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      {kept ? <Alert tone="warning">{bt("queued_offline")}</Alert> : null}
      {reports.length ? (
        <Card>
          <Text variant="cardTitle">{mt("on_phone", { n: reports.length })}</Text>
          {reports.map((r) => (
            <View key={r.key} style={{ gap: space[1], paddingTop: space[2] }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                <CloudOff size={18} color={r.error ? "#dc2626" : "#d97706"} />
                <Text variant="label" style={{ flex: 1 }} numberOfLines={1}>
                  {r.label}
                </Text>
                <Text variant="small" tone="foregroundMuted">
                  {dateTimeOf(r.at)}
                </Text>
              </View>
              <Text variant="small" numberOfLines={2}>
                {r.body.problem_description}
              </Text>
              {r.error ? (
                <>
                  <Alert>{r.error}</Alert>
                  <View style={{ flexDirection: "row", gap: space[2] }}>
                    <Button variant="outline" icon={RefreshCw} onPress={() => retryReport(r.key)} style={{ flex: 1 }}>
                      {mt("try_again")}
                    </Button>
                    <Button variant="outline" icon={Trash2} onPress={() => dropReport(r.key)} style={{ flex: 1 }}>
                      {mt("delete")}
                    </Button>
                  </View>
                </>
              ) : null}
            </View>
          ))}
          <Button icon={RefreshCw} loading={sending} onPress={sendNow}>
            {mt("send_now")}
          </Button>
        </Card>
      ) : null}

      {access.report ? (
        <Button variant="danger" icon={Siren} onPress={() => router.push("/breakdown/new")}>
          {bt("report_breakdown")}
        </Button>
      ) : null}

      {access.breakdowns ? (
        <>
          <Segmented
            value={show}
            onChange={setShow}
            options={[
              { value: "open", label: mt("open") },
              { value: "all", label: mt("all") },
            ]}
          />
          {list.error && !list.rows ? <Alert>{list.error}</Alert> : null}
          {list.rows?.length === 0 ? <Text tone="foregroundMuted">{bt("no_breakdowns")}</Text> : null}
          {(list.rows ?? []).map((b) => (
            <Card key={b.id} onPress={() => router.push({ pathname: "/breakdown/[id]", params: { id: b.id } })} style={{ gap: space[1] }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                <Text variant="cardTitle" style={{ flex: 1 }}>
                  {b.breakdown_number}
                </Text>
                <BreakdownStatus status={b.status} />
              </View>
              <Text numberOfLines={1}>{`${b.asset?.asset_code ?? ""} — ${b.asset?.name ?? ""}`}</Text>
              <View style={{ flexDirection: "row", alignItems: "center", gap: space[2], flexWrap: "wrap" }}>
                <Priority priority={b.priority} />
                <Text variant="small" tone="foregroundMuted">
                  {[b.production_line?.name, dateTimeOf(b.reported_at)].filter(Boolean).join(" · ")}
                </Text>
              </View>
            </Card>
          ))}
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
