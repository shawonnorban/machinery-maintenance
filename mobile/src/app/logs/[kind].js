/** A change log opened on its own (Order Summary's or Dev Sample's; the Order tab shows both). */
import { router, useLocalSearchParams } from "expo-router";
import { Header, Screen } from "../../components/ui";
import { ChangeLog, KINDS } from "../../components/change-log";
import { t } from "../../lib/i18n";

export default function ChangeLogScreen() {
  const { kind } = useLocalSearchParams();
  const spec = KINDS[kind] ?? KINDS["order-summary"];

  return (
    <Screen header={<Header title={t("mobile", `ord.${spec.title}`)} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <ChangeLog kind={kind} />
    </Screen>
  );
}
