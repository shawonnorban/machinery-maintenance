/**
 * Tally (the bottom bar; 2026-11-27): a QC's lines — one press opens a
 * line's tally — with what is still to send; for a section's people, the
 * section's tally screens (Line Target, Cutting Input, History, Review) as
 * the reference screens' tiles. The day's production is its own tab.
 */
import { router } from "expo-router";
import { ClipboardCheck } from "lucide-react-native";
import { FeatureTile, Screen } from "../../components/ui";
import { PageHero } from "../../components/hero";
import { HomeSection, TilesCard } from "../../components/home-premium";
import { SyncBar } from "../../components/tally";
import { Lines, SectionPicker } from "../../components/home";
import { mm, useAccess } from "../../components/manage";
import { tallyFeatures } from "../../lib/features";
import { t } from "../../lib/i18n";
import { useSection } from "../../lib/section";
import { qcOf, useSession } from "../../lib/session";

export default function TallyTab() {
  const { session } = useSession();
  const qc = qcOf(session);
  const access = useAccess();
  const section = useSection(access);
  // The section's own screens; the dashboard and the QC's tally are tabs of their own.
  const tiles = tallyFeatures({ session, section }).filter((f) => f.key !== "dashboard" && f.key !== "tally");

  return (
    <Screen tab header={<PageHero title={t("mobile", "nav.tally")} subtitle={qc?.qc?.name} icon={ClipboardCheck} image="quality" onBack={() => router.navigate("/")} />}>
      {qc ? (
        <>
          <Lines lines={qc.lines} />
          <SyncBar />
        </>
      ) : null}
      {tiles.length ? (
        <>
          <HomeSection title={mm("title")} />
          <SectionPicker access={access} value={section?.section} />
          <TilesCard>
            {tiles.map((tile) => (
              <FeatureTile square key={tile.key} icon={tile.icon} image={tile.image} label={tile.label} color={tile.color} onPress={() => router.push(tile.href)} />
            ))}
          </TilesCard>
        </>
      ) : null}
    </Screen>
  );
}
