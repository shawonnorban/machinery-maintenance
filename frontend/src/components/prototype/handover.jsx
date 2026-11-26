"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { DocFoot, DocHead, Field, ProtoList, ReadOnly, Section, blank, card, messagesOf, str, todayIso, useDocForm } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Handover / Delivery (AnnotechERP 07/03): an
 * approved sample handed over or couriered and tracked to the receiver —
 * within its quantity, each status asking for what proves it. A received
 * handover is locked; only a Ready one is removed from the list.
 */

const FIELDS = ["sample_id", "qty", "handover_date", "handover_from", "handover_to", "department_id", "status", "courier", "tracking_no", "expected_delivery", "actual_delivery", "received_by", "received_date", "remarks", "attachment"];
const WAITING = ["READY", "HANDOVER_PENDING"];
const ON_WAY = ["HANDED_OVER", "COURIERED", "IN_TRANSIT"];
const ARRIVED = ["DELIVERED", "RECEIVED"];

function HandoverForm({ options, initial, existing, canManage, actions }) {
  const t = useT("prototype");
  const formOf = (row) => ({ ...Object.fromEntries(FIELDS.map((k) => [k, str(row?.[k])])), handover_date: row?.handover_date ?? todayIso(), status: row?.status ?? "READY" });
  const payloadOf = (form) => Object.fromEntries(FIELDS.map((k) => [k, blank(form[k])]));
  const { row, form, set, errors, pending, load, save } = useDocForm({ kind: "handovers", tab: "handover", param: "ho", noKey: "handover_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.ho.saved", { no }) });
  const locked = !canManage || Boolean(row?.locked);
  const sample = row ?? options.samples.find((s) => s.value === form.sample_id);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const date = (key) => <Input type="date" value={form[key]} onChange={(e) => set(key)(e.target.value)} disabled={locked} />;

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.ho.form")}
          hint={t("ui.ho.form_hint")}
          badge={row ? t(`ho_status.${row.status}`) : t("ui.new")}
          existingLabel={t("ui.ho.existing")}
          newOption={t("ui.ho.new_option")}
          existing={existing}
          value={row?.handover_no ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.ho.s.sample")}>
          <Field label={t("ui.ho.f.handover_no")}>
            <ReadOnly value={row?.handover_no} placeholder={t("ui.issued_on_save")} />
          </Field>
          <Field label={t("ui.ho.f.sample_no")} required hint={t("ui.ho.h.sample_no")}>
            <Select value={form.sample_id} onValueChange={set("sample_id")} options={opt(row ? [{ value: row.sample_id, label: row.sample_no }] : options.samples)} disabled={locked || Boolean(row)} aria-label={t("ui.ho.f.sample_no")} />
          </Field>
          <Field label={t("ui.ho.f.buyer")}>
            <ReadOnly value={sample?.buyer} />
          </Field>
          <Field label={t("ui.ho.f.style_no")}>
            <ReadOnly value={sample?.style_ref} />
          </Field>
          <Field label={t("ui.ho.f.sample_type")}>
            <ReadOnly value={sample?.sample_type} />
          </Field>
          <Field label={t("ui.ho.f.sample_qty")}>
            <ReadOnly value={sample?.sample_qty} />
          </Field>
        </Section>
        <Section title={t("ui.ho.s.handover")}>
          <Field label={t("ui.ho.f.qty")} required hint={t("ui.ho.h.qty")}>
            <Input type="number" min={1} value={form.qty} onChange={(e) => set("qty")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.ho.f.handover_date")} required>
            {date("handover_date")}
          </Field>
          <Field label={t("ui.ho.f.handover_from")} hint={t("ui.left_blank_me")}>
            <Select value={form.handover_from} onValueChange={set("handover_from")} options={opt(options.users)} disabled={locked} aria-label={t("ui.ho.f.handover_from")} />
          </Field>
          <Field label={t("ui.ho.f.handover_to")} required>
            <Input value={form.handover_to} onChange={(e) => set("handover_to")(e.target.value)} placeholder={t("ui.ho.h.handover_to")} disabled={locked} maxLength={255} />
          </Field>
          <Field label={t("ui.ho.f.department")}>
            <Select value={form.department_id} onValueChange={set("department_id")} options={opt(options.departments)} disabled={locked} aria-label={t("ui.ho.f.department")} />
          </Field>
          <Field label={t("ui.ho.f.status")}>
            <Select value={form.status} onValueChange={set("status")} options={opt(options.statuses.map((s) => ({ value: s, label: t(`ho_status.${s}`) })))} disabled={locked} aria-label={t("ui.ho.f.status")} />
          </Field>
        </Section>
        <Section title={t("ui.ho.s.courier")}>
          <Field label={t("ui.ho.f.courier")}>
            <Input value={form.courier} onChange={(e) => set("courier")(e.target.value)} placeholder={t("ui.ho.h.courier")} disabled={locked} maxLength={120} />
          </Field>
          <Field label={t("ui.ho.f.tracking_no")}>
            <Input value={form.tracking_no} onChange={(e) => set("tracking_no")(e.target.value)} disabled={locked} maxLength={120} />
          </Field>
          <Field label={t("ui.ho.f.expected_delivery")}>{date("expected_delivery")}</Field>
          <Field label={t("ui.ho.f.actual_delivery")}>{date("actual_delivery")}</Field>
        </Section>
        <Section title={t("ui.ho.s.receipt")}>
          <Field label={t("ui.ho.f.received_by")}>
            <Input value={form.received_by} onChange={(e) => set("received_by")(e.target.value)} disabled={locked} maxLength={255} />
          </Field>
          <Field label={t("ui.ho.f.received_date")}>{date("received_date")}</Field>
          <Field label={t("ui.ho.f.remarks")} wide>
            <Textarea rows={3} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={locked} />
          </Field>
          <Field label={t("ui.ho.f.attachment")} wide>
            <Input value={form.attachment} onChange={(e) => set("attachment")(e.target.value)} placeholder={t("ui.attachment_placeholder")} disabled={locked} maxLength={500} />
          </Field>
        </Section>
      </section>
      <DocFoot note={t("ui.ho.note")} onClear={() => load("")} onSave={locked ? undefined : save} saveLabel={t("ui.ho.save")} pending={pending} />
    </div>
  );
}

/** The handover list: Brickland's pills and filters, Open, and Remove on a Ready one. */
function HandoverList({ rows, canManage, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const unique = (field) => [...new Set(rows.map((r) => r[field]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));
  const data = rows.map((r) => ({
    ...r,
    courier_label: [r.courier, r.tracking_no].filter(Boolean).join(" / "),
    stage: WAITING.includes(r.status) ? "WAITING" : ON_WAY.includes(r.status) ? "ON_WAY" : ARRIVED.includes(r.status) ? "ARRIVED" : "CANCELLED",
    late: (r.delay_days ?? 0) > 0 && !ARRIVED.includes(r.status),
  }));
  function remove() {
    startTransition(async () => {
      const result = await actions.remove("handovers", confirm.handover_no);
      if (result?.status !== "success") return toastManager.add({ title: messagesOf(result).join(" "), type: "danger" });
      toastManager.add({ title: t("ui.ho.removed", { no: confirm.handover_no }), type: "success" });
      setConfirm(null);
      router.refresh();
    });
  }
  return (
    <>
      <ProtoList
        rows={data}
        rowKey="handover_no"
        exportName="sample-handovers"
        title={t("ui.ho.list")}
        subtitle={t("ui.ho.list_hint")}
        emptyText={t("ui.ho.empty")}
        addHref={canManage ? "?tab=handover" : undefined}
        addLabel={t("ui.ho.new")}
        openHref="?tab=handover&ho="
        filters={[
          { key: "date", label: t("ui.ho.f.handover_date"), field: "handover_date", range: true },
          { key: "buyer", label: t("ui.ho.f.buyer"), field: "buyer", options: unique("buyer") },
          { key: "status", label: t("ui.ho.f.status"), field: "status", options: ["READY", "HANDOVER_PENDING", ...ON_WAY, ...ARRIVED, "CANCELLED"].map((s) => ({ value: s, label: t(`ho_status.${s}`) })) },
          { key: "courier", label: t("ui.ho.f.courier"), field: "courier", options: unique("courier") },
          { key: "sample", label: t("ui.ho.f.sample_no"), field: "sample_no", options: unique("sample_no") },
        ]}
        pills={[
          { key: "waiting", label: t("ui.ho.pill.waiting"), field: "stage", value: "WAITING" },
          { key: "on_way", label: t("ui.ho.pill.on_way"), field: "stage", value: "ON_WAY" },
          { key: "arrived", label: t("ui.ho.pill.arrived"), field: "stage", value: "ARRIVED" },
          { key: "late", label: t("ui.ho.pill.late"), field: "late", value: true },
        ]}
        columns={[
          { key: "handover_no", label: t("ui.ho.col.handover_no"), type: "mono" },
          { key: "handover_date", label: t("ui.ho.col.handover_date"), type: "date" },
          { key: "sample_no", label: t("ui.ho.col.sample_no"), type: "mono" },
          { key: "buyer", label: t("ui.ho.col.buyer") },
          { key: "qty", label: t("ui.ho.col.qty"), type: "number" },
          { key: "handover_to", label: t("ui.ho.col.handover_to") },
          { key: "courier_label", label: t("ui.ho.col.courier") },
          { key: "expected_delivery", label: t("ui.ho.col.expected"), type: "date" },
          { key: "actual_delivery", label: t("ui.ho.col.actual"), type: "date" },
          { key: "delay_days", label: t("ui.ho.col.delay"), type: "number" },
          { key: "received_by", label: t("ui.ho.col.received_by") },
        ]}
        rowActions={canManage ? [{ key: "remove", label: t("ui.ho.remove"), icon: <Trash2 />, onClick: (row) => setConfirm(row), disabled: (row) => !row.removable }] : []}
      />
      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={t("ui.ho.remove_title")}
        description={confirm ? t("ui.ho.remove_hint", { no: confirm.handover_no }) : ""}
        confirmLabel={t("ui.ho.remove")}
        cancelLabel={t("ui.ho.keep")}
        destructive
        loading={pending}
        onConfirm={remove}
      />
    </>
  );
}

export { HandoverForm, HandoverList };
