/**
 * Production (the bottom bar: Home · Production · Tally · Machines · More;
 * the reference screen, 2026-11-27): the blue header with the day's pill,
 * then the chosen department's day — figures, trend, each department, the
 * latest hours — for whoever sees a Quality Tally section.
 */
import { useState } from "react";
import { router } from "expo-router";
import { Factory } from "lucide-react-native";
import { Screen } from "../../components/ui";
import { HeroPill, PageHero, longDay } from "../../components/hero";
import { todayDhaka, useAccess } from "../../components/manage";
import { DateSheet, QtDashboard } from "../../components/qt-dashboard";
import { t } from "../../lib/i18n";

export default function ProductionTab() {
  const access = useAccess();
  const [date, setDate] = useState(todayDhaka());
  const [picking, setPicking] = useState(false);

  return (
    <Screen
      tab
      header={
        <PageHero
          title={t("mobile", "prod.title")}
          icon={Factory}
          image="production"
          onBack={() => router.navigate("/")}
          right={access.length ? <HeroPill label={longDay(date)} onPress={() => setPicking(true)} accessibilityLabel={t("mobile", "prod.pick_day")} /> : null}
        />
      }
    >
      {access.length ? <QtDashboard date={date} /> : null}
      <DateSheet open={picking} onClose={() => setPicking(false)} value={date} onChange={setDate} />
    </Screen>
  );
}
