/**
 * A section's Tally History on the manager's phone (AnnotechERP 37, phase 3):
 * every tap of a day, newest first — time, line, job, action, defects, QC —
 * with those that arrived late or are kept for a check marked. More pages are
 * read as asked for.
 */
import { useEffect, useState } from "react";
import { View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { ChevronDown } from "lucide-react-native";
import { Badge, Button, Card, Header, Screen, Text } from "../../components/ui";
import { num } from "../../components/tally";
import { DateBar, LoadState, mm, q, todayDhaka } from "../../components/manage";
import { clock12 } from "../../../../frontend/src/lib/clock";
import { t } from "../../lib/i18n";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";

const TONE = { RFT: "success", DEFECT: "warning", RECTIFY: "info", REJECT: "danger" };

export default function History() {
  const { section } = useLocalSearchParams();
  const { call } = useSession();
  const [date, setDate] = useState(todayDhaka());
  const [pages, setPages] = useState(null); // { rows, page, last_page, total }
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  const read = async (page, keep) => {
    setLoading(true);
    try {
      const d = await call(`/quality-tally/history?section=${section}&from=${date}&to=${date}&page=${page}`);
      setPages({ ...d, rows: keep ? [...keep, ...d.rows] : d.rows });
      setError(null);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };
  useEffect(() => {
    setPages(null);
    read(1, null);
  }, [date]);

  return (
    <Screen header={<Header title={q(`screens.${section.toLowerCase()}-history`)} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <DateBar value={date} onChange={setDate} />
      <LoadState data={pages} error={error} loading={loading} reload={() => read(1, null)} />
      {pages ? (
        <Text variant="small" tone="foregroundMuted">
          {mm("taps_count", { n: num(pages.total) })}
        </Text>
      ) : null}
      {pages?.rows.length === 0 ? <Text tone="foregroundMuted">{q("empty.no_taps")}</Text> : null}
      {(pages?.rows ?? []).map((r) => (
        <Card key={r.id} style={{ padding: space[3], gap: space[1] }}>
          <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
            <Text variant="label" style={{ width: 92 }}>
              {clock12(r.time.slice(0, 5))}
            </Text>
            <Badge tone={TONE[r.action]}>{q(`action.${r.action}`)}</Badge>
            <View style={{ flex: 1 }} />
            {r.review ? <Badge tone="danger">{q("history.review")}</Badge> : r.synced_late ? <Badge tone="warning">{q("history.late")}</Badge> : null}
          </View>
          <Text variant="small" numberOfLines={1}>
            {r.line} · {r.job_no} {r.style ?? ""}
          </Text>
          <Text variant="small" tone="foregroundMuted" numberOfLines={2}>
            {[r.user, r.defects.length ? r.defects.join(", ") : null].filter(Boolean).join(" · ")}
          </Text>
        </Card>
      ))}
      {pages && pages.page < pages.last_page ? (
        <Button variant="outline" icon={ChevronDown} loading={loading} onPress={() => read(pages.page + 1, pages.rows)}>
          {mm("more")}
        </Button>
      ) : null}
    </Screen>
  );
}
