/** Every notification as the timeline, newest first, a page at a time; all can be marked read. */
import { useCallback, useRef, useState } from "react";
import { router, useFocusEffect } from "expo-router";
import { CheckCheck, ChevronDown } from "lucide-react-native";
import { Alert, Button, Card, Header, Screen, Text } from "../components/ui";
import { ActivityTimeline } from "../components/activity";
import { t } from "../lib/i18n";
import { useSession } from "../lib/session";

const at = (key, params) => t("mobile", `act.${key}`, params);

export default function Notifications() {
  const { call } = useSession();
  const [pages, setPages] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const asked = useRef(0);

  const read = useCallback(
    async (page, keep) => {
      const turn = ++asked.current;
      try {
        const answer = await call(`/notifications?per_page=20&page=${page}`, { whole: true });
        if (turn !== asked.current) return;
        setPages({ items: [...(keep ?? []), ...answer.data], meta: answer.meta });
        setError(null);
      } catch (caught) {
        if (turn === asked.current) setError(caught.message);
      }
    },
    [call],
  );

  useFocusEffect(
    useCallback(() => {
      read(1, null);
    }, [read]),
  );

  const readAll = async () => {
    setBusy(true);
    await call("/notifications/read-all", { method: "POST" }).catch(() => {});
    setBusy(false);
    read(1, null);
  };

  return (
    <Screen header={<Header title={at("title")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      {error && !pages ? <Alert>{error}</Alert> : null}
      {pages?.meta?.unread_count ? (
        <Button variant="outline" icon={CheckCheck} loading={busy} onPress={readAll}>
          {at("read_all", { n: pages.meta.unread_count })}
        </Button>
      ) : null}
      {pages && pages.items.length === 0 ? <Text tone="foregroundMuted">{at("empty")}</Text> : null}
      {pages?.items.length ? (
        <Card>
          <ActivityTimeline items={pages.items} onRead={(id) => setPages((p) => ({ ...p, items: p.items.map((n) => (n.id === id ? { ...n, is_read: true } : n)) }))} />
        </Card>
      ) : null}
      {pages && pages.meta.current_page < pages.meta.last_page ? (
        <Button variant="outline" icon={ChevronDown} onPress={() => read(pages.meta.current_page + 1, pages.items)}>
          {t("mobile", "mach.more")}
        </Button>
      ) : null}
    </Screen>
  );
}
