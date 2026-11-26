"use client";

import { useEffect, useState } from "react";
import { FileText, Paperclip, ShieldCheck } from "lucide-react";
import { FileInput } from "@/components/ui/file-input";
import { Field } from "@/components/dyehouse/chem-store";
import { blank, firstError, today, useSs } from "./shared";
import { FormDesk, dateRange, useDesk } from "./shipment";

/*
 * Sales & Shipment → Export Documentation (AnnotechERP 21/06), as Brickland
 * lays its screens out — each a list and a plain entry form: EXP / Export
 * Documents, COO / Certificate of Origin, GSP / REX and Bill of Lading / AWB
 * (one register by family: type, number, dates, issuing authority, status
 * moved by Mark Received / Submitted / Approved, the expiry warned inside
 * the settings' certificate window, an attachment), Certificate Management
 * (typed from the Certificate Master, validity) and Shipment Attachments
 * (scans by document type, PDF or image up to 5 MB). The final Document
 * Handover reuses the handover desk (21/05) as its FINAL kind.
 */

const FAMILY = {
  EXPORT: { key: "export", flow: "export-documents", types: ["EXP", "INSPECTION", "INSURANCE", "OTHER"] },
  COO: { key: "coo", flow: null, types: ["COO"] },
  GSP: { key: "gsp", flow: null, types: ["GSP", "REX"] },
  BLAWB: { key: "blawb", flow: null, types: ["BILL_OF_LADING", "AIR_WAYBILL"] },
};
const STATUSES = ["PENDING", "RECEIVED", "SUBMITTED", "APPROVED", "CANCELLED"];

/** A file sent with a paper, through the upload route (streamed past the server-action limit). */
async function upload(path, body) {
  const res = await fetch(`/api/sales-shipment/upload?path=${encodeURIComponent(path)}`, { method: "POST", body });
  const json = await res.json().catch(() => ({}));
  return res.ok ? { status: "success", data: json.data } : { status: "error", message: json.message, errors: json.errors };
}

function useEdOptions(act) {
  const [opt, setOpt] = useState({ shipments: [], certificate_types: [], attachment_types: [], alert_days: 30 });
  useEffect(() => {
    act.read("/sales-shipment/export-documentation/options").then((r) => r?.status === "success" && setOpt(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return opt;
}

const shipOpts = (opt) => opt.shipments.filter((s) => s.open).map((s) => ({ value: s.shipment_no, label: `${s.shipment_no} · ${s.so_no} · ${s.customer}` }));
const expiryText = (w, r) => (r.expiry ? w(`ed.expiry.${r.expiry}`, { n: Math.abs(r.expiry_days) }) : "—");

/** The file box under a form, and what is attached already. */
const fileBox = (w, setFile, required = false) => (form, set, canEdit) => (
  <Field label={w("dh.f.attachment")} required={required && !form.file_label} hint={form.file_label ? w("dh.f.attached", { name: form.file_label }) : w(required ? "sa.f.file_hint" : "dh.f.attachment_hint")}>
    <FileInput onChange={(e) => setFile(e.target.files?.[0] ?? null)} disabled={!canEdit} aria-label={w("dh.f.attachment")} />
  </Field>
);

/* ───────────────────────── Export documents (four screens) ───────────────────────── */

function DocDesk({ family, initial, canManage, act }) {
  const w = useSs();
  const { warn } = useDesk();
  const cfg = FAMILY[family];
  const k = cfg.key;
  const opt = useEdOptions(act);
  const [rows, setRows] = useState(initial);
  const [file, setFile] = useState(null);
  const reload = () => act.read(`/sales-shipment/export-docs/${k}`).then((r) => r?.status === "success" && setRows(r.data));
  const blankForm = () => ({ shipment_no: "", doc_type: cfg.types.length === 1 ? cfg.types[0] : "", document_no: "", document_date: today(), issue_date: "", expiry_date: "", issuing_authority: "", status: "PENDING", remarks: "", file_label: "" });
  const step = (a) => (id) => act.write("POST", `/sales-shipment/export-doc-items/${encodeURIComponent(id)}/${a}`);
  const typeOpts = cfg.types.map((t) => ({ value: t, label: w(`ed.type.${t}`) }));
  return (
    <FormDesk
      w={w}
      flow={cfg.flow}
      listLabel={w(`ed.${k}.list_tab`)}
      entryLabel={w(`ed.${k}.entry_tab`)}
      icon={FileText}
      title={w(`ed.${k}.title`)}
      hint={w(`ed.${k}.hint`)}
      note={w("ed.note")}
      rows={rows.map((r) => ({ ...r, expiry_label: expiryText(w, r) }))}
      reload={reload}
      refKey="id"
      exportName={`export-docs-${k}`}
      emptyTitle={w(`ed.${k}.empty_title`)}
      blankForm={blankForm}
      onLoaded={() => setFile(null)}
      rowToForm={(r) => ({ ...blankForm(), ...Object.fromEntries(Object.entries(r).map(([x, v]) => [x, v ?? ""])), file_label: r.attachment ?? "" })}
      editable={(r) => canManage && (!r || !r.locked)}
      fields={() => [
        { key: "shipment_no", label: w("shp.f.shipment"), type: "select", required: true, options: shipOpts(opt), hint: w("ed.f.shipment_hint"), placeholder: w("od.ed.select") },
        { key: "doc_type", label: w("ed.f.type"), type: "select", required: true, options: typeOpts, placeholder: w("od.ed.select") },
        { key: "document_no", label: w("ed.f.no"), required: true, max: 60 },
        { key: "document_date", label: w("ed.f.date"), type: "date", required: true },
        { key: "issue_date", label: w("ed.f.issue"), type: "date" },
        { key: "expiry_date", label: w("ed.f.expiry"), type: "date", hint: w("ed.f.expiry_hint") },
        { key: "issuing_authority", label: w("ed.f.authority"), max: 160 },
        { key: "status", label: w("ed.f.status"), type: "select", options: STATUSES.map((s) => ({ value: s, label: w(`ed.status.${s}`) })) },
        { key: "remarks", label: w("od.f.remarks"), type: "textarea", wide: true },
      ]}
      extra={fileBox(w, setFile)}
      save={async (form, editing) => {
        const payload = Object.fromEntries(["shipment_no", "doc_type", "document_no", "document_date", "issue_date", "expiry_date", "issuing_authority", "status", "remarks"].map((x) => [x, blank(form[x])]));
        const res = await act.write(editing ? "PUT" : "POST", editing ? `/sales-shipment/export-docs/${k}/${encodeURIComponent(editing.id)}` : `/sales-shipment/export-docs/${k}`, payload);
        if (res?.status !== "success") return res;
        let label = form.file_label;
        if (file) {
          const body = new FormData();
          body.append("upload", file);
          const up = await upload(`/sales-shipment/attachments-of/export-docs/${res.data.id}`, body);
          if (up.status !== "success") warn(firstError(up) ?? w("dh.upload_failed"));
          else label = up.data.attachment;
          setFile(null);
        }
        return { ...res, message: w("ed.saved", { no: res.data.document_no }), row: { id: res.data.id, locked: false }, form: { ...form, status: res.data.status, file_label: label } };
      }}
      filters={[
        ...(cfg.types.length > 1 ? [{ name: "type", label: w("ed.f.type"), type: "select", options: typeOpts, test: (r, v) => !v || r.doc_type === v }] : []),
        { name: "status", label: w("ed.f.status"), type: "select", options: STATUSES.map((s) => ({ value: s, label: w(`ed.status.${s}`) })), test: (r, v) => !v || r.status === v },
        ...dateRange(w, "date", w("ed.f.date"), "document_date"),
      ]}
      tiles={(s) => [
        [w("ed.tile.all"), s.length],
        [w("ed.status.PENDING"), s.filter((r) => r.status === "PENDING").length, "warning"],
        [w("ed.tile.expiring", { n: opt.alert_days }), s.filter((r) => r.expiry === "EXPIRING").length, "warning"],
        [w("ed.tile.expired"), s.filter((r) => r.expiry === "EXPIRED").length, "critical"],
      ]}
      actions={[
        { key: "file", label: w("dh.reg.file"), when: (r) => Boolean(r.file_id), open: (r) => window.open(`/api/files/${r.file_id}/download`, "_blank") },
        ...(canManage
          ? [
              { key: "received", label: w("dh.reg.received"), when: (r) => !r.locked && r.status === "PENDING", done: w("ed.done.received"), run: step("received") },
              { key: "submitted", label: w("ed.reg.submitted"), when: (r) => !r.locked && ["PENDING", "RECEIVED"].includes(r.status), done: w("ed.done.submitted"), run: step("submitted") },
              { key: "approved", label: w("ed.reg.approved"), when: (r) => !r.locked && r.status === "SUBMITTED", done: w("ed.done.approved"), run: step("approved") },
              { key: "delete", label: w("od.reg.delete"), when: (r) => !r.locked, confirm: w("ed.confirm.delete"), done: w("od.done.delete"), run: (id) => act.write("DELETE", `/sales-shipment/export-doc-items/${encodeURIComponent(id)}`), destructive: true },
            ]
          : []),
      ]}
      columns={[
        { key: "doc_type", label: w("ed.col.type"), type: "badge", labels: Object.fromEntries(cfg.types.map((t) => [t, w(`ed.type.${t}`)])) },
        { key: "document_no", label: w("ed.f.no"), type: "mono" },
        { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
        { key: "buyer", label: w("so.col.buyer") },
        { key: "document_date", label: w("ed.f.date"), type: "date" },
        { key: "issue_date", label: w("ed.f.issue"), type: "date" },
        { key: "expiry_date", label: w("ed.f.expiry"), type: "date" },
        { key: "expiry_label", label: w("ed.col.expiry") },
        { key: "issuing_authority", label: w("ed.f.authority") },
        { key: "attachment", label: w("dh.f.attachment") },
        { key: "status", label: w("ed.f.status"), type: "badge", labels: Object.fromEntries(STATUSES.map((s) => [s, w(`ed.status.${s}`)])) },
      ]}
    />
  );
}

/* ───────────────────────── Certificates ───────────────────────── */

function CertificateDesk({ initial, canManage, act }) {
  const w = useSs();
  const { warn } = useDesk();
  const opt = useEdOptions(act);
  const [rows, setRows] = useState(initial);
  const [file, setFile] = useState(null);
  const reload = () => act.read("/sales-shipment/certificates").then((r) => r?.status === "success" && setRows(r.data));
  const blankForm = () => ({ certificate_type_id: "", certificate_name: "", certificate_no: "", shipment_no: "", issue_date: today(), expiry_date: "", issuing_authority: "", status: "ACTIVE", remarks: "", file_label: "" });
  const validity = (r) => (r.status === "CANCELLED" ? null : r.expiry === "EXPIRED" ? "expired" : r.expiry === "EXPIRING" ? "expiring" : "valid");
  return (
    <FormDesk
      w={w}
      listLabel={w("ce.list_tab")}
      entryLabel={w("ce.entry_tab")}
      icon={ShieldCheck}
      title={w("ce.title")}
      hint={w("ce.hint")}
      note={w("ce.note")}
      rows={rows.map((r) => ({ ...r, validity: validity(r), validity_label: r.status === "CANCELLED" ? w("ed.status.CANCELLED") : expiryText(w, r) === "—" ? w("ce.validity.valid") : expiryText(w, r) }))}
      reload={reload}
      refKey="id"
      exportName="certificates"
      emptyTitle={w("ce.empty_title")}
      blankForm={blankForm}
      onLoaded={() => setFile(null)}
      rowToForm={(r) => ({ ...blankForm(), ...Object.fromEntries(Object.entries(r).map(([x, v]) => [x, v ?? ""])), file_label: r.attachment ?? "" })}
      editable={(r) => canManage && (!r || !r.locked)}
      fields={() => [
        { key: "certificate_type_id", label: w("ce.f.type"), type: "select", optional: true, options: opt.certificate_types, hint: w("ce.f.type_hint") },
        { key: "certificate_name", label: w("ce.f.name"), max: 160, hint: w("ce.f.name_hint") },
        { key: "certificate_no", label: w("ce.f.no"), required: true, max: 60 },
        { key: "shipment_no", label: w("shp.f.shipment"), type: "select", required: true, options: shipOpts(opt), placeholder: w("od.ed.select") },
        { key: "issue_date", label: w("ed.f.issue"), type: "date", required: true },
        { key: "expiry_date", label: w("ed.f.expiry"), type: "date", hint: w("ed.f.expiry_hint") },
        { key: "issuing_authority", label: w("ed.f.authority"), max: 160 },
        { key: "status", label: w("ed.f.status"), type: "select", options: ["ACTIVE", "CANCELLED"].map((s) => ({ value: s, label: w(`ce.status.${s}`) })) },
        { key: "remarks", label: w("od.f.remarks"), type: "textarea", wide: true },
      ]}
      extra={fileBox(w, setFile)}
      save={async (form, editing) => {
        const payload = Object.fromEntries(["certificate_type_id", "certificate_name", "certificate_no", "shipment_no", "issue_date", "expiry_date", "issuing_authority", "status", "remarks"].map((x) => [x, blank(form[x])]));
        const res = await act.write(editing ? "PUT" : "POST", editing ? `/sales-shipment/certificates/${encodeURIComponent(editing.id)}` : "/sales-shipment/certificates", payload);
        if (res?.status !== "success") return res;
        let label = form.file_label;
        if (file) {
          const body = new FormData();
          body.append("upload", file);
          const up = await upload(`/sales-shipment/attachments-of/certificates/${res.data.id}`, body);
          if (up.status !== "success") warn(firstError(up) ?? w("dh.upload_failed"));
          else label = up.data.attachment;
          setFile(null);
        }
        return { ...res, message: w("ce.saved", { no: res.data.certificate_no }), row: { id: res.data.id, locked: false },
          form: { ...form, certificate_name: res.data.certificate_name, issuing_authority: res.data.issuing_authority ?? "", file_label: label } };
      }}
      filters={[
        { name: "status", label: w("ed.f.status"), type: "select", options: ["ACTIVE", "CANCELLED"].map((s) => ({ value: s, label: w(`ce.status.${s}`) })), test: (r, v) => !v || r.status === v },
        { name: "validity", label: w("ce.col.validity"), type: "select", options: ["valid", "expiring", "expired"].map((s) => ({ value: s, label: w(`ce.validity.${s}`) })), test: (r, v) => !v || r.validity === v },
        ...dateRange(w, "exp", w("ed.f.expiry"), "expiry_date"),
      ]}
      tiles={(s) => [[w("ce.tile.all"), s.length], [w("ed.tile.expiring", { n: opt.alert_days }), s.filter((r) => r.validity === "expiring").length, "warning"], [w("ed.tile.expired"), s.filter((r) => r.validity === "expired").length, "critical"]]}
      actions={[
        { key: "file", label: w("dh.reg.file"), when: (r) => Boolean(r.file_id), open: (r) => window.open(`/api/files/${r.file_id}/download`, "_blank") },
        ...(canManage ? [{ key: "delete", label: w("od.reg.delete"), when: (r) => !r.locked, confirm: w("ce.confirm.delete"), done: w("od.done.delete"), run: (id) => act.write("DELETE", `/sales-shipment/certificates/${encodeURIComponent(id)}`), destructive: true }] : []),
      ]}
      columns={[
        { key: "certificate_no", label: w("ce.f.no"), type: "mono" },
        { key: "certificate_name", label: w("ce.col.name") },
        { key: "type", label: w("ce.col.type") },
        { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
        { key: "buyer", label: w("so.col.buyer") },
        { key: "so_no", label: w("so.f.so_no"), type: "mono" },
        { key: "issue_date", label: w("ed.f.issue"), type: "date" },
        { key: "expiry_date", label: w("ed.f.expiry"), type: "date" },
        { key: "validity_label", label: w("ce.col.validity") },
        { key: "issuing_authority", label: w("ed.f.authority") },
        { key: "attachment", label: w("dh.f.attachment") },
      ]}
    />
  );
}

/* ───────────────────────── Shipment attachments ───────────────────────── */

function AttachmentDesk({ initial, canManage, act }) {
  const w = useSs();
  const opt = useEdOptions(act);
  const [rows, setRows] = useState(initial);
  const [file, setFile] = useState(null);
  const reload = () => act.read("/sales-shipment/shipment-attachments").then((r) => r?.status === "success" && setRows(r.data));
  const blankForm = () => ({ shipment_no: "", document_type: "", title: "", remarks: "", file_label: "" });
  return (
    <FormDesk
      w={w}
      listLabel={w("sa.list_tab")}
      entryLabel={w("sa.entry_tab")}
      icon={Paperclip}
      title={w("sa.title")}
      hint={w("sa.hint")}
      note={w("sa.note")}
      rows={rows.map((r) => ({ ...r, uploaded_on: r.uploaded_at?.slice(0, 10) }))}
      reload={reload}
      refKey="id"
      exportName="shipment-attachments"
      emptyTitle={w("sa.empty_title")}
      blankForm={blankForm}
      onLoaded={() => setFile(null)}
      rowToForm={(r) => ({ ...blankForm(), shipment_no: r.shipment_no, document_type: r.document_type, title: r.title, remarks: r.remarks ?? "", file_label: r.file ?? "" })}
      editable={() => canManage}
      fields={() => [
        { key: "shipment_no", label: w("shp.f.shipment"), type: "select", required: true, options: shipOpts(opt), placeholder: w("od.ed.select") },
        { key: "document_type", label: w("ed.f.type"), type: "select", required: true, options: opt.attachment_types.map((t) => ({ value: t, label: w(`sa.type.${t.replace(/[^A-Za-z]/g, "")}`) })), placeholder: w("od.ed.select") },
        { key: "title", label: w("sa.f.title"), required: true, max: 160, hint: w("sa.f.title_hint"), placeholder: w("sa.f.title_ph") },
        { key: "remarks", label: w("od.f.remarks"), type: "textarea", wide: true },
      ]}
      extra={fileBox(w, setFile, true)}
      save={async (form, editing) => {
        const body = new FormData();
        for (const x of ["shipment_no", "document_type", "title", "remarks"]) if (form[x]) body.append(x, form[x]);
        if (file) body.append("upload", file);
        const res = await upload(editing ? `/sales-shipment/shipment-attachments/${editing.id}` : "/sales-shipment/shipment-attachments", body);
        if (res.status !== "success") return res;
        setFile(null);
        return { ...res, message: w("sa.saved", { name: res.data.title }), row: { id: res.data.id }, form: { ...form, file_label: res.data.file ?? form.file_label } };
      }}
      filters={[
        { name: "type", label: w("ed.f.type"), type: "select", options: opt.attachment_types.map((t) => ({ value: t, label: w(`sa.type.${t.replace(/[^A-Za-z]/g, "")}`) })), test: (r, v) => !v || r.document_type === v },
        ...dateRange(w, "up", w("sa.col.date"), "uploaded_on"),
      ]}
      tiles={(s) => [[w("sa.tile.all"), s.length], [w("sa.tile.shipments"), new Set(s.map((r) => r.shipment_no)).size]]}
      actions={[
        { key: "file", label: w("sa.reg.open"), when: (r) => Boolean(r.file_id), open: (r) => window.open(`/api/files/${r.file_id}/download`, "_blank") },
        ...(canManage ? [{ key: "delete", label: w("od.reg.delete"), confirm: w("sa.confirm.delete"), done: w("od.done.delete"), run: (id) => act.write("DELETE", `/sales-shipment/shipment-attachments/${encodeURIComponent(id)}`), destructive: true }] : []),
      ]}
      columns={[
        { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
        { key: "so_no", label: w("so.f.so_no"), type: "mono" },
        { key: "document_type", label: w("ed.f.type"), type: "badge", labels: Object.fromEntries(opt.attachment_types.map((t) => [t, w(`sa.type.${t.replace(/[^A-Za-z]/g, "")}`)])) },
        { key: "title", label: w("sa.f.title") },
        { key: "file", label: w("sa.col.file") },
        { key: "uploaded_by", label: w("sa.col.by") },
        { key: "uploaded_on", label: w("sa.col.date"), type: "date" },
        { key: "remarks", label: w("od.f.remarks") },
      ]}
    />
  );
}

export { DocDesk, CertificateDesk, AttachmentDesk };
