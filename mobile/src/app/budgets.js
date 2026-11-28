/** The Budget List opened on its own (from Home's tiles or the side menu): the Budget tab's page with a way back. */
import { router } from "expo-router";
import { SafeAreaView } from "react-native-safe-area-context";
import { FileText } from "lucide-react-native";
import { HeroBell, PageHero } from "../components/hero";
import { BudgetList } from "../components/budget-list";
import { t } from "../lib/i18n";
import { useColors } from "../theme/theme";

export default function Budgets() {
  const colors = useColors();

  return (
    <SafeAreaView edges={["bottom"]} style={{ flex: 1, backgroundColor: colors.page }}>
      <PageHero title={t("mobile", "blist.title")} icon={FileText} image="budget" onBack={() => router.back()} right={<HeroBell />} fill={colors.page} />
      <BudgetList />
    </SafeAreaView>
  );
}
