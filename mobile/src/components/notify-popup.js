/**
 * The bell's popup (user, 2026-11-28): the latest ten notifications in a
 * centred modal — a tap opens the record (the popup closes first) — and
 * "View All" goes to the notifications page; all can be marked read here.
 */
import { useCallback, useEffect, useState } from "react";
import { View } from "react-native";
import { router } from "expo-router";
import { ArrowRight, Bell, CheckCheck } from "lucide-react-native";
import { ActivityTimeline } from "./activity";
import { Alert, Badge, Button, Popup, Text } from "./ui";
import { t } from "../lib/i18n";
import { useSession } from "../lib/session";
import { space } from "../theme/tokens";

const at = (key, params) => t("mobile", `act.${key}`, params);

export function NotificationsPopup({ open, onClose, onChanged }) {
  const { call } = useSession();
  const [list, setList] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    call("/notifications?per_page=10", { whole: true }).then(
      (a) => {
        setList({ items: a.data ?? [], unread: a.meta?.unread_count ?? 0 });
        setError(null);
      },
      (e) => setError(e.message),
    );
  }, [call]);

  useEffect(() => {
    if (open) load();
  }, [open, load]);

  const readAll = async () => {
    setBusy(true);
    await call("/notifications/read-all", { method: "POST" }).catch(() => {});
    setBusy(false);
    load();
    onChanged?.();
  };

  const viewAll = () => {
    onClose();
    router.push("/notifications");
  };

  return (
    <Popup
      open={open}
      onClose={onClose}
      icon={Bell}
      title={at("title")}
      hint={t("mobile", "home.latest")}
      closeLabel={at("close")}
      right={list?.unread ? <Badge tone="danger">{list.unread > 99 ? "99+" : list.unread}</Badge> : null}
      footer={
        <>
          {list?.unread ? (
            <View style={{ flex: 1 }}>
              <Button variant="outline" icon={CheckCheck} loading={busy} onPress={readAll}>
                {at("read_all", { n: list.unread })}
              </Button>
            </View>
          ) : null}
          <View style={{ flex: 1 }}>
            <Button icon={ArrowRight} onPress={viewAll}>
              {t("mobile", "home.view_all")}
            </Button>
          </View>
        </>
      }
    >
      {error && !list ? <Alert tone="warning">{error}</Alert> : null}
      {list === null && !error ? <Text tone="foregroundMuted">{t("mobile", "mgr.loading")}</Text> : null}
      {list && list.items.length === 0 ? <Text tone="foregroundMuted">{at("empty")}</Text> : null}
      {list?.items.length ? (
        <View style={{ paddingTop: space[1] }}>
          <ActivityTimeline
            items={list.items}
            onGo={onClose}
            onRead={(id) => {
              setList((l) => ({ items: l.items.map((n) => (n.id === id ? { ...n, is_read: true } : n)), unread: Math.max(0, l.unread - 1) }));
              onChanged?.();
            }}
          />
        </View>
      ) : null}
    </Popup>
  );
}
