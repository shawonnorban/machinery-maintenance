/**
 * A section's Review on the manager's phone (AnnotechERP 37, phase 3): taps
 * the server refused, with the reason, and taps made with no network kept
 * with a Check mark. The section's manager marks them seen; the web's Review
 * screen is the same list.
 */
import { useState } from "react";
import { View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { CheckCheck } from "lucide-react-native";
import { Badge, Button, Card, Header, Screen, Segmented, Text } from "../../components/ui";
import { Stat, Toast, num } from "../../components/tally";
import { LoadState, mm, q, useAccess, useLoad } from "../../components/manage";
import { t } from "../../lib/i18n";
import { timeOf } from "../../lib/clock";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";

const when = (iso) => (iso ? `${iso.slice(8, 10)}/${iso.slice(5, 7)} ${timeOf(iso)}` : "—");

export default function Review() {
  const { section } = useLocalSearchParams();
  const { call } = useSession();
  const manages = useAccess().some((a) => a.section === section && a.manages);
  const [status, setStatus] = useState("open");
  const load = useLoad(`/quality-tally/review?section=${section}&status=${status}`, [status]);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState(null);
  const rows = load.data?.rows ?? [];
  const open = rows.filter((r) => !r.seen_at);

  const mark = async (items) => {
    setBusy(true);
    try {
      const res = await call("/quality-tally/review/seen", { method: "POST", body: { section, items: items.map((r) => ({ kind: r.kind, id: r.id })) } });
      setNotice({ tone: "success", text: q("rv.marked", { n: res.seen }) });
      setTimeout(() => setNotice(null), 3500);
      load.reload();
    } catch (e) {
      setNotice({ tone: "danger", text: e.message });
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={{ flex: 1 }}>
      <Screen header={<Header title={q(`screens.${section.toLowerCase()}-review`)} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
        <Text variant="small" tone="foregroundMuted">
          {q(`descriptions.${section.toLowerCase()}-review`)}
        </Text>
        <Segmented value={status} onChange={setStatus} options={[{ value: "open", label: q("rv.open") }, { value: "all", label: q("rv.all") }]} />
        <LoadState {...load} />
        {load.data ? (
          <View style={{ flexDirection: "row", gap: space[2] }}>
            <Stat label={q("rv.open_refused")} value={num(load.data.open.refused)} tone="danger" />
            <Stat label={q("rv.open_kept")} value={num(load.data.open.kept)} tone="warning" />
          </View>
        ) : null}
        {manages && open.length > 1 ? (
          <Button variant="outline" icon={CheckCheck} loading={busy} onPress={() => mark(open)}>
            {q("rv.seen_all", { n: open.length })}
          </Button>
        ) : null}
        {load.data && rows.length === 0 ? <Text tone="foregroundMuted">{q("rv.empty")}</Text> : null}
        {rows.map((r) => (
          <Card key={`${r.kind}:${r.id}`} style={{ padding: space[4], gap: space[1] }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
              <Badge tone={r.kind === "REFUSED" ? "danger" : "warning"}>{q(`rv.kind_${r.kind}`)}</Badge>
              {r.action ? <Text variant="label">{q(`action.${r.action}`)}</Text> : null}
              <View style={{ flex: 1 }} />
              <Text variant="small" tone="foregroundMuted">
                {when(r.occurred_at)}
              </Text>
            </View>
            <Text variant="small" numberOfLines={1}>
              {r.line} · {r.job_no ?? "—"} {r.style ?? ""}
            </Text>
            <Text variant="small" tone="foregroundMuted">
              {[r.qc, r.phone, q("rv.arrived", { at: when(r.arrived_at) })].filter(Boolean).join(" · ")}
            </Text>
            <Text tone={r.kind === "REFUSED" ? "danger" : "warning"}>{r.reason}</Text>
            {r.seen_at ? (
              <Text variant="small" tone="foregroundMuted">
                {q("rv.seen_by", { name: r.seen_by ?? "—", at: when(r.seen_at) })}
              </Text>
            ) : manages ? (
              <Button variant="outline" icon={CheckCheck} disabled={busy} onPress={() => mark([r])} style={{ minHeight: 40, marginTop: space[1] }}>
                {q("rv.seen")}
              </Button>
            ) : null}
          </Card>
        ))}
      </Screen>
      <Toast notice={notice} onClose={() => setNotice(null)} />
    </View>
  );
}
