"use client";

import { useState, useTransition } from "react";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ProtoList, messagesOf } from "./proto-kit";

/*
 * Prototype Desk → Notifications & Alerts (AnnotechERP 07/02): every alert the
 * desk has raised, its severity, kind, sample, the desk it is for and the
 * document behind it. Mark Read, Resolve and Reopen act on the row in place.
 */

const DESKS = ["DESK", "MERCHANDISING", "SAMPLE_ROOM", "STORE", "PRODUCTION", "QC"];
const view = (a) => ({ ...a, unread: !a.read });

/** @param {{ initial: object[], kinds: string[], severities: string[], canManage: boolean, act: (id: string, action: string) => Promise<object> }} props */
function SampleAlertList({ initial, kinds, severities, canManage, act }) {
  const t = useT("prototype");
  const a = (k, p) => t(`ui.al.${k}`, p);
  const toastManager = useToastManager();
  const [rows, setRows] = useState(() => initial.map(view));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();

  function run(row, action) {
    setErrors(null);
    startTransition(async () => {
      const result = await act(row.id, action);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setRows((all) => all.map((r) => (r.id === row.id ? view(result.data) : r)));
      toastManager.add({ title: a(`done.${action}`), type: "success" });
    });
  }

  return (
    <div className="flex flex-col gap-3">
      {errors ? (
        <Alert variant="danger">
          {errors.map((e) => (
            <p key={e}>{e}</p>
          ))}
        </Alert>
      ) : null}
      <ProtoList
        rows={rows}
        rowKey="id"
        exportName="prototype-alerts"
        title={a("list")}
        subtitle={a("list_hint")}
        emptyText={a("empty")}
        filters={[
          { key: "severity", label: a("f.severity"), field: "severity", options: severities.map((v) => ({ value: v, label: t(`alert_severity.${v}`) })) },
          { key: "kind", label: a("f.kind"), field: "kind", options: kinds.map((v) => ({ value: v, label: t(`alert_kind.${v}`) })) },
          { key: "state", label: a("f.state"), field: "state", options: ["OPEN", "RESOLVED"].map((v) => ({ value: v, label: a(`state.${v}`) })) },
          { key: "raised", label: a("f.raised"), field: "raised_date", range: true },
        ]}
        pills={[
          { key: "critical", label: a("pill.critical"), field: "severity", value: "CRITICAL" },
          { key: "warning", label: a("pill.warning"), field: "severity", value: "WARNING" },
          { key: "open", label: a("pill.open"), field: "state", value: "OPEN" },
          { key: "unread", label: a("pill.unread"), field: "unread", value: true },
          { key: "resolved_today", label: a("pill.resolved_today"), field: "resolved_today", value: true },
        ]}
        columns={[
          { key: "raised_at", label: a("col.raised"), type: "datetime" },
          { key: "kind", label: a("col.kind"), type: "badge", labels: Object.fromEntries(kinds.map((v) => [v, t(`alert_kind.${v}`)])) },
          { key: "title", label: a("col.title") },
          { key: "message", label: a("col.message") },
          { key: "sample_no", label: a("col.sample_no"), type: "mono" },
          { key: "for_desk", label: a("col.for"), type: "badge", labels: Object.fromEntries(DESKS.map((v) => [v, t(`desk.${v}`)])) },
          { key: "doc_no", label: a("col.doc_no"), type: "mono" },
          { key: "read", label: a("col.read"), type: "yesno" },
          { key: "resolved", label: a("col.resolved"), type: "yesno" },
          { key: "severity", label: a("col.status"), type: "badge", labels: Object.fromEntries(severities.map((v) => [v, t(`alert_severity.${v}`)])) },
        ]}
        rowActions={[
          { key: "read", label: a("act.read"), onClick: (r) => run(r, "read"), disabled: (r) => pending || r.read },
          ...(canManage
            ? [
                { key: "resolve", label: a("act.resolve"), onClick: (r) => run(r, "resolve"), disabled: (r) => pending || r.resolved },
                { key: "reopen", label: a("act.reopen"), onClick: (r) => run(r, "reopen"), disabled: (r) => pending || !r.resolved },
              ]
            : []),
        ]}
      />
    </div>
  );
}

export { SampleAlertList };
