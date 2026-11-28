/**
 * The next steps of a breakdown or a work order, as buttons: a plain step
 * asks "sure?" and is done; a step that needs words or a choice (a reason, a
 * technician, the failure code) opens a sheet with its fields. Which steps
 * show is the screen's call — the web's own rules, with the permission each
 * step's API checks.
 */
import { useState } from "react";
import { Alert as Ask, View } from "react-native";
import { Alert, Button, Chip, Input, Text } from "./ui";
import { Sheet, Toast } from "./tally";
import { PickField, PickSheet } from "./machinery";
import { t } from "../lib/i18n";
import { space } from "../theme/tokens";

/**
 * steps: [{ key, label, variant?, icon?, fields?, send(values) }]
 * field: { key, label, kind: "text" | "pick" | "chips", required?, items?, options?, multiline?, hint? }
 */
export function StepButtons({ steps, onDone }) {
  const [open, setOpen] = useState(null);
  const [busy, setBusy] = useState(null);
  const [notice, setNotice] = useState(null);

  const done = (step) => {
    setNotice({ tone: "success", text: t("breakdown", "action_recorded", { action: step.label }) });
    onDone?.();
  };

  const plain = (step) =>
    Ask.alert(`${step.label}?`, step.ask, [
      { text: t("mobile", "cancel"), style: "cancel" },
      {
        text: step.label,
        onPress: async () => {
          setBusy(step.key);
          try {
            await step.send({});
            done(step);
          } catch (caught) {
            setNotice({ tone: "danger", text: caught.message });
          } finally {
            setBusy(null);
          }
        },
      },
    ]);

  if (steps.length === 0) return null;

  return (
    <View style={{ gap: space[2] }}>
      {steps.map((step) => (
        <Button key={step.key} variant={step.variant ?? "primary"} icon={step.icon} loading={busy === step.key} disabled={Boolean(busy)} onPress={() => (step.fields ? setOpen(step) : plain(step))}>
          {step.label}
        </Button>
      ))}
      <StepSheet
        step={open}
        onClose={() => setOpen(null)}
        onDone={(step) => {
          setOpen(null);
          done(step);
        }}
      />
      <Toast notice={notice} onClose={() => setNotice(null)} />
    </View>
  );
}

function StepSheet({ step, onClose, onDone }) {
  const [values, setValues] = useState({});
  const [picking, setPicking] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [shown, setShown] = useState(null);

  // A new step starts empty.
  if (step && shown !== step.key) {
    setShown(step.key);
    setValues({});
    setError(null);
  }
  if (!step) return null;

  const set = (key, value) => setValues((v) => ({ ...v, [key]: value }));
  const missing = step.fields.some((f) => f.required && !String(values[f.key] ?? "").trim());

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const body = Object.fromEntries(Object.entries(values).filter(([, v]) => String(v ?? "").trim() !== "").map(([k, v]) => [k, typeof v === "string" ? v.trim() : v]));
      await step.send(body);
      onDone(step);
    } catch (caught) {
      setError(caught.message);
    } finally {
      setBusy(false);
    }
  };

  const pickField = step.fields.find((f) => f.key === picking);

  return (
    <Sheet
      open
      onClose={onClose}
      title={step.label}
      hint={step.ask}
      footer={
        <>
          <Button variant="outline" onPress={onClose} style={{ flex: 1 }}>
            {t("mobile", "cancel")}
          </Button>
          <Button variant={step.variant === "danger" ? "danger" : "primary"} loading={busy} disabled={missing} onPress={save} style={{ flex: 1 }}>
            {step.label}
          </Button>
        </>
      }
    >
      {error ? <Alert>{error}</Alert> : null}
      {step.fields.map((f) =>
        f.kind === "pick" ? (
          <PickField key={f.key} label={`${f.label}${f.required ? " *" : ""}`} value={f.items.find((i) => i.id === values[f.key])?.name} placeholder={f.placeholder ?? "—"} onPress={() => setPicking(f.key)} />
        ) : f.kind === "chips" ? (
          <View key={f.key} style={{ gap: space[2] }}>
            <Text variant="label">{`${f.label}${f.required ? " *" : ""}`}</Text>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: space[2] }}>
              {f.options.map((o) => (
                <Chip key={o.value} on={values[f.key] === o.value} onPress={() => set(f.key, o.value)}>
                  {o.label}
                </Chip>
              ))}
            </View>
          </View>
        ) : (
          <Input key={f.key} label={`${f.label}${f.required ? " *" : ""}`} value={values[f.key] ?? ""} onChangeText={(v) => set(f.key, v)} multiline={f.multiline !== false} hint={f.hint} />
        ),
      )}
      <PickSheet open={Boolean(pickField)} onClose={() => setPicking(null)} title={pickField?.label ?? ""} items={pickField?.items ?? []} selected={values[picking]} onPick={(i) => set(picking, i.id)} />
    </Sheet>
  );
}
