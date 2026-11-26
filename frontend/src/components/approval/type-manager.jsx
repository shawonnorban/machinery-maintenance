"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { useT } from "@/lib/i18n";

/**
 * Approval Center → Request Types & Routing (AnnotechERP 04/03): the types
 * the request form offers, each with its default priority, deadline, the chain
 * that follows Request To, and whom an overdue request escalates to.
 */
function TypeManager({ rows, options, create, update, remove }) {
  const t = useT("approval");
  const [creating, setCreating] = useState(false);
  const priorities = options.priorities.map((p) => ({ value: p, label: t(`center.priorities.${p}`) }));

  const fields = (editing) => [
    { name: "code", label: t("center.type.code"), required: true, placeholder: "FABRIC-BOOKING", readOnly: editing },
    { name: "name", label: t("center.type.name"), required: true },
    { name: "module", label: t("center.type.module"), required: true, placeholder: "Material Planning" },
    { name: "default_priority", label: t("center.type.default_priority"), type: "select", options: priorities, default: "MEDIUM" },
    { name: "due_days", label: t("center.type.due_days"), type: "number", default: "0", help: t("center.type.due_days_hint") },
    { name: "sort_order", label: t("center.type.sort_order"), type: "number", default: "0" },
    {
      name: "workflow_id",
      label: t("center.type.chain"),
      type: "select",
      options: [{ value: "", label: t("center.type.no_chain") }, ...options.workflows.map((w) => ({ value: w.id, label: w.label }))],
      help: t("center.type.chain_hint"),
      span: 3,
    },
    {
      name: "escalate_to_user_id",
      label: t("center.type.escalate_to"),
      type: "select",
      options: [{ value: "", label: t("center.type.no_escalation") }, ...options.users.map((u) => ({ value: u.id, label: u.name }))],
    },
    { name: "escalate_after_hours", label: t("center.type.escalate_after"), type: "number", default: "24" },
    { name: "description", label: t("center.type.description"), type: "textarea" },
    { name: "active", label: t("center.type.active"), type: "checkbox", default: true },
  ];

  const gridRows = rows.map((r) => ({
    ...r,
    type_name: `${r.name} · ${r.code}`,
    priority_label: t(`center.priorities.${r.default_priority}`),
    chain: r.workflow ?? t("center.type.no_chain"),
    escalation: r.escalate_to ? t("center.type.escalation_text", { name: r.escalate_to, hours: r.escalate_after_hours }) : t("center.type.no_escalation"),
    workflow_id: r.workflow_id ?? "",
    escalate_to_user_id: r.escalate_to_user_id ?? "",
    due_days: String(r.due_days ?? 0),
    sort_order: String(r.sort_order ?? 0),
    escalate_after_hours: String(r.escalate_after_hours ?? 24),
  }));

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <CardTitle>{t("center.type.title")}</CardTitle>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{t("center.type.hint")}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus /> {t("center.type.new")}
        </Button>
      </CardHeader>
      <CardBody>
        <RecordsGrid
          exportName="request-types"
          rows={gridRows}
          columns={[
            { key: "type_name", label: t("center.type.request_type") },
            { key: "module", label: t("center.type.module") },
            { key: "priority_label", label: t("center.type.default_priority") },
            { key: "due_days", label: t("center.type.due_days"), type: "number" },
            { key: "chain", label: t("center.type.chain") },
            { key: "escalation", label: t("center.type.escalation") },
            { key: "requests", label: t("center.type.requests"), type: "number" },
            { key: "active", label: t("center.type.status"), type: "status" },
          ]}
          edit={{ fields: fields(true), action: update, title: t("center.type.edit"), submitLabel: t("center.type.save") }}
          remove={remove}
          deleteDescription={t("center.type.delete_hint")}
        />
      </CardBody>
      {creating ? (
        <Modal open onOpenChange={(o) => !o && setCreating(false)} title={t("center.type.new_title")} className="max-w-3xl">
          <EntryForm dialog fields={fields(false)} action={create} submitLabel={t("center.type.save")} onDone={() => setCreating(false)} />
        </Modal>
      ) : null}
    </Card>
  );
}

export { TypeManager };
