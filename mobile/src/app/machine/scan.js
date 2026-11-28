/**
 * Scan a machine's label (AnnotechERP 37, phase 4). The label's QR holds the
 * web's scan link (…/scan/{code}, or …/scan/location/{code} on a place's
 * label); the code alone and a typed machine code work too. A machine opens
 * its own page; a place lists the machines there.
 */
import { useState } from "react";
import { router } from "expo-router";
import { View } from "react-native";
import { Alert, Card, Header, Screen, Text } from "../../components/ui";
import { Scanner } from "../../components/scanner";
import { AssetStatus, mt } from "../../components/machinery";
import { t } from "../../lib/i18n";
import { readLabel } from "../../lib/machines";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";

export default function ScanMachine() {
  const { call } = useSession();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [place, setPlace] = useState(null);

  const scan = async (text) => {
    const label = readLabel(text);
    setBusy(true);
    setError(null);
    setPlace(null);
    try {
      if (label.kind === "location") {
        setPlace(await call(`/scan/locations/${encodeURIComponent(label.code)}`));
        return;
      }
      try {
        const found = await call(`/scan/assets/${encodeURIComponent(label.code)}`);
        router.replace({ pathname: "/machine/[id]", params: { id: found.asset.id } });
        return;
      } catch (caught) {
        if (caught.status !== 404) throw caught;
      }
      // Not a label's code: a machine code typed by hand.
      const typed = await call(`/assets?search=${encodeURIComponent(label.code)}&per_page=5`);
      const exact = typed.find((a) => a.asset_code.toUpperCase() === label.code.toUpperCase()) ?? (typed.length === 1 ? typed[0] : null);
      if (exact) router.replace({ pathname: "/machine/[id]", params: { id: exact.id } });
      else setError(t("scan", "not_found_hint"));
    } catch (caught) {
      setError(caught.status === 404 ? t("scan", "not_found_hint") : caught.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen header={<Header title={mt("scan")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <Text tone="foregroundMuted">{mt("scan_hint")}</Text>
      {error ? <Alert>{error}</Alert> : null}
      <Scanner onScan={scan} busy={busy} words={{ scan: mt("scan"), type: mt("type_code") }} />
      {place ? (
        <Card>
          <Text variant="cardTitle">{place.location.full_path ?? place.location.name}</Text>
          <Text variant="small" tone="foregroundMuted">
            {place.location.factory}
          </Text>
          {place.assets.length === 0 ? <Text tone="foregroundMuted">{t("scan", "no_assets_here")}</Text> : null}
          {place.assets.map((a) => (
            <Card key={a.id} onPress={() => router.push({ pathname: "/machine/[id]", params: { id: a.id } })} style={{ gap: space[1] }}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: space[2] }}>
                <Text variant="cardTitle" style={{ flex: 1 }}>
                  {a.asset_code}
                </Text>
                <AssetStatus status={a.status} />
              </View>
              <Text variant="small">{a.name}</Text>
            </Card>
          ))}
        </Card>
      ) : null}
    </Screen>
  );
}
