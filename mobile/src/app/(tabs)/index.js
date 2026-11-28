/**
 * Home (the reference screen and the mobile design standard, 2026-11-27): the
 * welcome header with the factory, the factory choice, the modules as one
 * card of tiles, today's overview in the chosen section and the recent
 * activities. A QC signed in by PIN sees who they are, where, and their lines.
 */
import { useState } from "react";
import { View } from "react-native";
import { router } from "expo-router";
import { KeyRound, Lock, RefreshCw, UserRoundCog } from "lucide-react-native";
import { Alert, Button, Card, DetailRow, FeatureTile, Screen, Text } from "../../components/ui";
import { AppBar } from "../../components/appbar";
import { FactoryCard, HERO_SCROLLED, HomeHero, HomeTopInset, RecentActivities, TilesCard, TodayOverview } from "../../components/home-premium";
import { SyncBar } from "../../components/tally";
import { QcDashboard } from "../../components/qc-home";
import { Lines, greeting } from "../../components/home";
import { useAccess } from "../../components/manage";
import { timeOf } from "../../lib/clock";
import { quickAccess } from "../../lib/features";
import { t } from "../../lib/i18n";
import { useMachineAccess } from "../../lib/machines";
import { useSection } from "../../lib/section";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";
import { clock12 } from "../../../../frontend/src/lib/clock";

const m = (key, params) => t("mobile", key, params);

export default function Home() {
  const { session } = useSession();

  return session.kind === "PIN" ? <QcHome /> : <PersonHome />;
}

function Hello({ name, company }) {
  return (
    <View style={{ gap: 2, paddingTop: space[1] }}>
      <Text variant="sectionTitle" numberOfLines={1}>
        {greeting(name)}
      </Text>
      <Text variant="small" tone="foregroundMuted" numberOfLines={1}>
        {company}
      </Text>
    </View>
  );
}

/** The QC's dashboard (redesigned 2026-11-28, components/qc-home). */
function QcHome() {
  return <QcDashboard />;
}

function PersonHome() {
  const { session, attachQc } = useSession();
  const { user } = session.me;
  const refused = session.qcRefused;
  const access = useAccess();
  const section = useSection(access);
  const machines = useMachineAccess();
  const [scrolled, setScrolled] = useState(false);
  const tiles = quickAccess({ session, access, machines });

  return (
    <Screen tab header={<HomeTopInset shown={scrolled} />} onScroll={(e) => setScrolled(e.nativeEvent.contentOffset.y > HERO_SCROLLED)}>
      <HomeHero />
      <FactoryCard />
      {user?.must_change_password ? <Alert tone="warning">{m("home.must_change")}</Alert> : null}

      {/* The modules, one card of tiles (the reference screen): at most eight, the rest under More. */}
      {tiles.length ? (
        <TilesCard>
          <Tiles tiles={tiles} />
        </TilesCard>
      ) : (
        <Card>
          <Text tone="foregroundMuted">{m("home.nothing_yet")}</Text>
        </Card>
      )}

      {refused?.reason === "PHONE_PENDING" || refused?.reason === "OTHER_LINE" || refused?.reason === "PHONE_BLOCKED" ? (
        <View style={{ gap: space[2] }}>
          <Alert tone="warning">{refused.reason === "PHONE_PENDING" ? m("tally.qc_pending", { code: refused.code ?? "" }) : refused.message}</Alert>
          <Button variant="outline" icon={RefreshCw} onPress={() => attachQc().catch(() => {})}>
            {m("tally.qc_check")}
          </Button>
        </View>
      ) : null}

      {section ? <TodayOverview section={section.section} /> : null}
      {session.qc ? <SyncBar /> : null}
      <RecentActivities />
    </Screen>
  );
}

/** Tiles, four to a row: a tab opens in the bar, a screen over it. */
function Tiles({ tiles }) {
  return tiles.map((tile) => <FeatureTile square key={tile.key} icon={tile.icon} image={tile.image} label={tile.label} color={tile.color} onPress={() => (typeof tile.href === "string" ? router.navigate(tile.href) : router.push(tile.href))} />);
}
