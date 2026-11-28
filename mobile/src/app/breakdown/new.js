/**
 * Report a breakdown (AnnotechERP 37, phase 4): the machine (scanned, chosen
 * from the list, or already known from the machine's page), what is wrong,
 * and a photo. Everything else — priority from the machine's criticality,
 * the failure code later by maintenance — the server derives, as on the web.
 * With no network the report is kept on the phone, photo and all, and sent
 * when the server answers again.
 */
import { useState } from "react";
import { View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { Camera, Check, QrCode, Send, Trash2 } from "lucide-react-native";
import { Alert, Button, Card, Chip, Header, Input, Screen, Text } from "../../components/ui";
import { Scanner } from "../../components/scanner";
import { Sheet, Toast } from "../../components/tally";
import { PhotoCapture, PickField, PickSheet, bt, lower, mt } from "../../components/machinery";
import { LoadState, useLoad } from "../../components/manage";
import { t } from "../../lib/i18n";
import { readLabel } from "../../lib/machines";
import { keepReport } from "../../lib/reports";
import { useSession } from "../../lib/session";
import { space } from "../../theme/tokens";

const PRIORITIES = ["CRITICAL", "HIGH", "MEDIUM", "LOW"];

export default function ReportBreakdown() {
  const { asset: preset } = useLocalSearchParams();
  const { call } = useSession();
  const options = useLoad("/breakdowns/create-form-options", []);
  const assets = options.data?.assets ?? [];
  const [assetId, setAssetId] = useState(preset ?? null);
  const [picking, setPicking] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [problem, setProblem] = useState("");
  const [priority, setPriority] = useState(null);
  const [reason, setReason] = useState(null);
  const [reasonOpen, setReasonOpen] = useState(false);
  const [photo, setPhoto] = useState(null);
  const [camera, setCamera] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState(null);
  const asset = assets.find((a) => a.id === assetId) ?? null;
  // A machine named by the page that opened this one, before the list has come.
  const label = asset ? `${asset.asset_code} — ${asset.name}` : assetId && !options.data ? "…" : null;

  const scanned = async (text) => {
    const code = readLabel(text).code;
    try {
      const found = await call(`/scan/assets/${encodeURIComponent(code)}`);
      setAssetId(found.asset.id);
      setScanning(false);
    } catch {
      const typed = assets.find((a) => a.asset_code.toUpperCase() === code.toUpperCase());
      if (typed) {
        setAssetId(typed.id);
        setScanning(false);
      } else setNotice({ tone: "danger", text: t("scan", "not_found_hint") });
    }
  };

  const submit = async () => {
    if (!assetId || !problem.trim()) {
      setError(bt("pick_machine_and_describe"));
      return;
    }
    setBusy(true);
    setError(null);
    const body = {
      asset_id: assetId,
      problem_description: problem.trim(),
      ...(priority ? { priority } : {}),
      ...(reason ? { downtime_reason_code_id: reason.id } : {}),
      ...(photo?.base64 ? { photo_base64: photo.base64, photo_filename: `breakdown-${Date.now()}.jpg` } : {}),
    };
    try {
      const made = await call("/breakdowns", { method: "POST", body, deadline: 30000 });
      router.replace({ pathname: "/breakdown/[id]", params: { id: made.id, made: made.breakdown_number } });
    } catch (caught) {
      if (caught.status === 0) {
        // No answer: kept on the phone and sent later, with the time it happened.
        keepReport(body, label ?? assetId);
        router.replace({ pathname: "/breakdowns", params: { kept: "1" } });
        return;
      }
      setError(caught.message);
      setBusy(false);
    }
  };

  return (
    <Screen header={<Header title={bt("report_breakdown")} onBack={() => router.back()} backLabel={t("mobile", "back")} />}>
      <LoadState {...options} />
      {error ? <Alert>{error}</Alert> : null}
      <Card>
        <PickField label={bt("asset")} value={label} placeholder={bt("select_machine")} onPress={() => setPicking(true)} />
        <Button variant="outline" icon={QrCode} onPress={() => setScanning(true)}>
          {mt("scan")}
        </Button>
        <Input label={`${bt("problem_description")} *`} value={problem} onChangeText={setProblem} multiline hint={bt("problem_description_hint")} />
        <View style={{ gap: space[2] }}>
          <Text variant="label">{bt("priority")}</Text>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
            {PRIORITIES.map((p) => (
              <Chip key={p} on={priority === p} onPress={() => setPriority(priority === p ? null : p)}>
                {bt(`priority_${lower(p)}`)}
              </Chip>
            ))}
          </View>
          <Text variant="small" tone="foregroundMuted">
            {mt("priority_hint")}
          </Text>
        </View>
        {options.data?.reason_codes?.length ? <PickField label={bt("reason_code")} value={reason?.name} placeholder={mt("optional")} onPress={() => setReasonOpen(true)} /> : null}
        <View style={{ gap: space[2] }}>
          <Text variant="label">{bt("photo")}</Text>
          {photo ? (
            <View style={{ flexDirection: "row", gap: space[2] }}>
              <Button variant="outline" icon={Check} onPress={() => setCamera(true)} style={{ flex: 1 }}>
                {mt("photo_taken")}
              </Button>
              <Button variant="outline" icon={Trash2} onPress={() => setPhoto(null)}>
                {bt("remove_photo")}
              </Button>
            </View>
          ) : (
            <Button variant="outline" icon={Camera} onPress={() => setCamera(true)}>
              {mt("take_photo")}
            </Button>
          )}
          <Text variant="small" tone="foregroundMuted">
            {bt("photo_hint")}
          </Text>
        </View>
      </Card>
      <Button variant="danger" icon={Send} loading={busy} disabled={!assetId || !problem.trim()} onPress={submit}>
        {bt("report")}
      </Button>

      <PickSheet open={picking} onClose={() => setPicking(false)} title={bt("select_machine")} items={assets.map((a) => ({ id: a.id, name: a.asset_code, sub: a.name }))} selected={assetId} onPick={(a) => setAssetId(a.id)} />
      <PickSheet open={reasonOpen} onClose={() => setReasonOpen(false)} title={bt("reason_code")} items={options.data?.reason_codes ?? []} selected={reason?.id} onPick={setReason} />
      <Sheet open={scanning} onClose={() => setScanning(false)} title={mt("scan")}>
        {scanning ? <Scanner onScan={scanned} words={{ scan: mt("scan"), type: mt("type_code") }} /> : null}
      </Sheet>
      <PhotoCapture
        open={camera}
        base64
        onClose={() => setCamera(false)}
        onPhoto={(shot) => {
          setPhoto(shot);
          setCamera(false);
        }}
      />
      <Toast notice={notice} onClose={() => setNotice(null)} />
    </Screen>
  );
}
