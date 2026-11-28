/**
 * Orders (user, 2026-11-27; off the bottom bar since the five-tab design —
 * opened from Home and More): Order Summary and Dev Sample as tabs at the
 * top, each its change log — every save, cell by cell. A tab shows only to
 * whoever may read it.
 */
import { useState } from "react";
import { FileClock, FlaskConical } from "lucide-react-native";
import { router } from "expo-router";
import { View } from "react-native";
import { FileClock as OrdersIcon } from "lucide-react-native";
import { PillTabs, Screen } from "../components/ui";
import { HeroBell, PageHero } from "../components/hero";
import { ChangeLog, LogFilters } from "../components/change-log";
import { t } from "../lib/i18n";
import { useSession } from "../lib/session";

const ot = (key) => t("mobile", `ord.${key}`);

export default function Orders() {
  const { session } = useSession();
  const has = (p) => (session?.permissions ?? []).includes(p);
  const tabs = [
    ...(has("ordersummary.view") ? [{ value: "order-summary", label: ot("os"), icon: FileClock }] : []),
    ...(has("devsample.view") ? [{ value: "dev-sample", label: ot("ds"), icon: FlaskConical }] : []),
  ];
  const [tab, setTab] = useState(tabs[0]?.value);
  const [period, setPeriod] = useState("7d");
  const [action, setAction] = useState("");
  const shown = tabs.find((x) => x.value === tab)?.value ?? tabs[0]?.value;

  return (
    <Screen bleed header={<PageHero title={ot("orders_title")} icon={OrdersIcon} image="order" onBack={() => (router.canGoBack() ? router.back() : router.replace("/"))} right={<HeroBell />} />}>
      {/* The tabs on top, the filters under them (redesigned 2026-11-28). */}
      <PillTabs value={shown} onChange={setTab} options={tabs} />
      {shown ? <LogFilters kind={shown} period={period} action={action} onPeriod={setPeriod} onAction={setAction} /> : null}
      {shown ? <ChangeLog key={shown} kind={shown} period={period} action={action} /> : null}
    </Screen>
  );
}
