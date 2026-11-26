"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  ArrowLeft,
  CheckCheck,
  FileText,
  Paperclip,
  Pencil,
  Play,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Send,
  Trash2,
  X,
  XCircle,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { printDocument } from "@/lib/print-document";
import { useT } from "@/lib/i18n";
import {
  PSelect,
  Field,
  StatusChip,
  card,
  dayFmt,
  messagesOf,
} from "@/components/knitting/yarn-common";

/*
 * Colour Lab → Operations (AnnotechERP 16/01), the screens with a shape of
 * their own: the certificate form (with its ✚ for a new type or equipment
 * and its 📎 for a filed document), the Chemical Trial Workbench and the
 * In-House Fabric Testing list and sheet. Words come from `colourlab.*`.
 */

function useCl() {
  const t = useT("colourlab");
  return (k, p) => t(k, p);
}

const BASE = {
  certificates: "/colour-lab/certificate-list",
  trials: "/colour-lab/chemical-trial-lab",
  tests: "/colour-lab/inh-fab-test",
};

const str = (v) => (v === null || v === undefined ? "" : String(v));
const blank = (v) => (v === "" || v === undefined ? null : v);
const numFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 3 });
const fig = (v) =>
  v === null || v === undefined || v === "" ? "—" : numFmt.format(Number(v));
const pick = (list, ph) => [{ value: "", label: ph }, ...list];

const TONE = {
  PENDING: "DRAFT",
  IN_PROGRESS: "PLACED",
  COMPLETED: "APPROVED",
  CANCELLED: "CANCELLED",
  DRAFT: "DRAFT",
  SUBMITTED: "PLACED",
  APPROVED: "APPROVED",
  REJECTED: "REJECTED",
};

/** Brickland's legacy form row: the label right-aligned beside its box, a red star when required. */
function Row({ label, required, children, wide }) {
  return (
    <label
      className={cn(
        "grid items-start gap-2 sm:grid-cols-[10rem_1fr]",
        wide && "sm:col-span-2 sm:grid-cols-[10rem_1fr]",
      )}
    >
      <span className="pt-2 text-[13px] font-medium text-foreground sm:text-right">
        {required ? <span className="text-danger">* </span> : null}
        {label}
      </span>
      <span className="flex min-w-0 items-start gap-1.5">{children}</span>
    </label>
  );
}

// ── Certificate form ─────────────────────────────────────────────────────

/**
 * Brickland's "Dyeing Lab Certification": the certificate's type and the
 * equipment it covers (each with ✚ to add one in place), the document ref,
 * the filed document (typed, or 📎 picked from Company Management's filed
 * documents), the dates, who applied, who is mailed and how many days
 * before Valid Date, the lab, Is Active? and Remarks.
 */
function CertificateForm({ options, initial, canManage, save, saveMaster }) {
  const c = useCl();
  const router = useRouter();
  const toastManager = useToastManager();
  const empty = {
    cert_type_id: "",
    equipment_id: "",
    document_ref: "",
    upload_doc: "",
    company_document_id: "",
    apply_date: "",
    applied_by: "",
    issue_date: "",
    valid_date: "",
    mail_to: "",
    mail_cc: "",
    notify_days: "30",
    lab_floor_id: "",
    active: true,
    remarks: "",
  };
  const start = initial
    ? {
        ...empty,
        ...Object.fromEntries(
          Object.entries(initial).map(([k, v]) => [
            k,
            k === "active" ? Boolean(v) : str(v),
          ]),
        ),
      }
    : empty;
  const [f, setF] = useState(start);
  const [types, setTypes] = useState(options.cert_types);
  const [equipment, setEquipment] = useState(options.equipment);
  const [dialog, setDialog] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));

  function submit() {
    startTransition(async () => {
      const payload = Object.fromEntries(
        Object.entries(f).map(([k, v]) => [k, k === "active" ? v : blank(v)]),
      );
      const res = await save(initial?.id ?? null, payload);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: c("done.certificate", { ref: f.document_ref }),
        type: "success",
      });
      router.push(BASE.certificates);
      router.refresh();
    });
  }

  async function addMaster(kind, payload) {
    const res = await saveMaster(
      kind === "type" ? "lab-cert-types" : "lab-equipment",
      payload,
    );
    if (res?.status !== "success") return messagesOf(res);
    const row = res.data;
    if (kind === "type") {
      setTypes((x) => [
        ...x,
        {
          value: row.id,
          label: row.issuing_body
            ? `${row.name} — ${row.issuing_body}`
            : row.name,
        },
      ]);
      set("cert_type_id", row.id);
    } else {
      setEquipment((x) => [
        ...x,
        {
          value: row.id,
          label: `${row.name} (${row.code})${row.serial_no ? ` · ${row.serial_no}` : ""}`,
        },
      ]);
      set("equipment_id", row.id);
    }
    setDialog(null);
    return null;
  }

  const plus = (kind, label) =>
    canManage ? (
      <Button
        type="button"
        size="icon"
        className="size-9 shrink-0"
        onClick={() => setDialog(kind)}
        aria-label={label}
        title={label}
      >
        <Plus />
      </Button>
    ) : null;

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {c("cert.form_title")}
        </h2>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] text-foreground-muted italic">
            {c("cert.legend")}
          </legend>
          <div className="grid gap-4 lg:grid-cols-2">
            <Row label={c("f.cert_type")} required>
              <PSelect
                value={f.cert_type_id}
                onValueChange={(v) => set("cert_type_id", v ?? "")}
                options={pick(types, c("ph.cert_type"))}
                aria-label={c("f.cert_type")}
              />
              {plus("type", c("cert.add_type"))}
            </Row>
            <Row label={c("f.equipment")} required>
              <PSelect
                value={f.equipment_id}
                onValueChange={(v) => set("equipment_id", v ?? "")}
                options={pick(equipment, c("ph.equipment"))}
                aria-label={c("f.equipment")}
              />
              {plus("equipment", c("cert.add_equipment"))}
            </Row>
            <Row label={c("f.document_ref")} required>
              <Input
                value={f.document_ref}
                onChange={(e) => set("document_ref", e.target.value)}
                aria-label={c("f.document_ref")}
              />
            </Row>
            <Row label={c("f.upload_doc")} required>
              <Input
                value={f.upload_doc}
                onChange={(e) => (
                  set("upload_doc", e.target.value),
                  set("company_document_id", "")
                )}
                aria-label={c("f.upload_doc")}
              />
              <Button
                type="button"
                variant="outline"
                size="icon"
                className="size-9 shrink-0"
                onClick={() => setDialog("doc")}
                aria-label={c("cert.pick_doc")}
                title={c("cert.pick_doc")}
              >
                <Paperclip />
              </Button>
            </Row>
            <Row label={c("f.apply_date")} required>
              <Input
                type="date"
                value={f.apply_date}
                onChange={(e) => set("apply_date", e.target.value)}
                aria-label={c("f.apply_date")}
              />
            </Row>
            <Row label={c("f.applied_by")} required>
              <PSelect
                value={f.applied_by}
                onValueChange={(v) => set("applied_by", v ?? "")}
                options={pick(options.users, c("ph.user"))}
                aria-label={c("f.applied_by")}
              />
            </Row>
            <Row label={c("f.issue_date")} required>
              <Input
                type="date"
                value={f.issue_date}
                onChange={(e) => set("issue_date", e.target.value)}
                aria-label={c("f.issue_date")}
              />
            </Row>
            <Row label={c("f.valid_date")} required>
              <Input
                type="date"
                value={f.valid_date}
                onChange={(e) => set("valid_date", e.target.value)}
                aria-label={c("f.valid_date")}
              />
            </Row>
            <Row label={c("f.mail_to")}>
              <Textarea
                rows={2}
                value={f.mail_to}
                onChange={(e) => set("mail_to", e.target.value)}
                placeholder="compliance@mill.example, lab@mill.example"
                aria-label={c("f.mail_to")}
              />
            </Row>
            <Row label={c("f.mail_cc")}>
              <Textarea
                rows={2}
                value={f.mail_cc}
                onChange={(e) => set("mail_cc", e.target.value)}
                placeholder="gm@mill.example"
                aria-label={c("f.mail_cc")}
              />
            </Row>
            <Row label={c("f.lab_floor")} required>
              <PSelect
                value={f.lab_floor_id}
                onValueChange={(v) => set("lab_floor_id", v ?? "")}
                options={pick(options.floors, c("ph.lab_floor"))}
                aria-label={c("f.lab_floor")}
              />
            </Row>
            <Row label={c("f.notify_days")}>
              <Input
                type="number"
                min={0}
                max={365}
                value={f.notify_days}
                onChange={(e) => set("notify_days", e.target.value)}
                aria-label={c("f.notify_days")}
              />
            </Row>
            <Row label={c("f.is_active")}>
              <Checkbox
                checked={f.active}
                onCheckedChange={(v) => set("active", v === true)}
                aria-label={c("f.is_active")}
                className="mt-2.5"
              />
            </Row>
            <Row label={c("f.remarks")} wide>
              <Textarea
                rows={3}
                value={f.remarks}
                onChange={(e) => set("remarks", e.target.value)}
                aria-label={c("f.remarks")}
              />
            </Row>
          </div>
          <p className="mt-3 text-xs text-foreground-muted">
            {c("cert.notify_hint")}
          </p>
        </fieldset>
        {errors ? (
          <Alert variant="danger" className="mt-4">
            {errors.join(" · ")}
          </Alert>
        ) : null}
        <div className="mt-4 flex flex-wrap gap-2">
          <Link
            href={BASE.certificates}
            className={buttonVariants({ variant: "outline" })}
          >
            <ArrowLeft /> {c("btn.back")}
          </Link>
          {canManage ? (
            <Button onClick={submit} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
          <Button
            variant="outline"
            onClick={() => (setF(start), setErrors(null))}
            disabled={pending}
          >
            <XCircle /> {c("btn.cancel")}
          </Button>
        </div>
      </section>
      <MasterDialog
        kind={dialog === "type" || dialog === "equipment" ? dialog : null}
        onClose={() => setDialog(null)}
        onSave={addMaster}
      />
      <DocPicker
        open={dialog === "doc"}
        documents={options.documents}
        onClose={() => setDialog(null)}
        onPick={(d) => {
          set("upload_doc", d.label);
          set("company_document_id", d.value);
          setDialog(null);
        }}
      />
    </div>
  );
}

/** Brickland's "New Certification Type" (Code, Certification Type, Issuing Body) or "New Equipment" (Code, Equipment, Manufacturer, Serial No). */
function MasterDialog({ kind, onClose, onSave }) {
  const c = useCl();
  const [f, setF] = useState({});
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const fields =
    kind === "type"
      ? ["code", "name", "issuing_body"]
      : ["code", "name", "manufacturer", "serial_no"];
  return (
    <Modal
      open={kind !== null}
      onOpenChange={(open) => !open && (setF({}), setErrors(null), onClose())}
      title={kind === "type" ? c("cert.new_type") : c("cert.new_equipment")}
      className="max-w-lg"
    >
      <div className="flex flex-col gap-3">
        {fields.map((k) => (
          <Field
            key={k}
            label={c(`${kind === "type" ? "type" : "equip"}.${k}`)}
            required={k === "code" || k === "name"}
          >
            <Input
              value={f[k] ?? ""}
              onChange={(e) => setF((x) => ({ ...x, [k]: e.target.value }))}
              aria-label={c(`${kind === "type" ? "type" : "equip"}.${k}`)}
            />
          </Field>
        ))}
        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <div className="flex justify-end gap-2">
          <Button
            variant="outline"
            onClick={() => (setF({}), setErrors(null), onClose())}
            disabled={pending}
          >
            {c("btn.cancel")}
          </Button>
          <Button
            loading={pending}
            onClick={() =>
              startTransition(async () => {
                const problems = await onSave(
                  kind,
                  Object.fromEntries(
                    Object.entries(f).map(([k, v]) => [k, blank(v)]),
                  ),
                );
                if (problems) setErrors(problems);
                else setF({});
              })
            }
          >
            <Plus /> {c("btn.add")}
          </Button>
        </div>
      </div>
    </Modal>
  );
}

/** Upload Doc's 📎: the company's filed documents (Company Management), one picked. */
function DocPicker({ open, documents, onClose, onPick }) {
  const c = useCl();
  const [q, setQ] = useState("");
  const shown = documents.filter(
    (d) => !q || d.label.toLowerCase().includes(q.toLowerCase()),
  );
  return (
    <Modal
      open={open}
      onOpenChange={(o) => !o && onClose()}
      title={c("cert.pick_doc")}
      description={c("cert.pick_doc_hint")}
      className="max-w-xl"
    >
      <div className="flex flex-col gap-3">
        <Input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={c("ph.search_doc")}
          aria-label={c("ph.search_doc")}
        />
        {shown.length === 0 ? (
          <p className="py-6 text-center text-sm text-foreground-muted">
            {c("cert.no_docs")}
          </p>
        ) : (
          <ul className="flex max-h-80 flex-col divide-y divide-border overflow-y-auto rounded-lg border border-border">
            {shown.map((d) => (
              <li key={d.value}>
                <button
                  type="button"
                  onClick={() => onPick(d)}
                  className="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-surface-muted"
                >
                  <span className="font-medium text-foreground">{d.label}</span>
                  <span className="text-xs text-foreground-muted">
                    {[
                      d.type,
                      d.expiry
                        ? c("cert.doc_expiry", { date: dayFmt(d.expiry) })
                        : null,
                    ]
                      .filter(Boolean)
                      .join(" · ")}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Modal>
  );
}

// ── Chemical Trial Workbench ─────────────────────────────────────────────

/** The trial's next step, as its WorkFlow column offers it. */
const NEXT_TRIAL = { PENDING: "start", IN_PROGRESS: "complete" };

/**
 * Brickland's "Trial Operation List": New Trial, Ref / From Date / To Date /
 * Status and Search, then the trials — Ref#, Lab, Trial Date, Trial By,
 * Status, WorkFlow (Start Trial, Complete) and Edit, Print, Cancel, Delete.
 */
function TrialList({ options, initialRows, canManage, act }) {
  const c = useCl();
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initialRows);
  const [f, setF] = useState({ ref: "", from: "", to: "", status: "" });
  const [ask, setAsk] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(Object.entries(next).filter(([, v]) => v));
      const res = await act(`trials?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }

  function run({ row, action }) {
    startTransition(async () => {
      const no = encodeURIComponent(row.trial_no);
      const res =
        action === "delete"
          ? await act(`trials/${no}`, "DELETE")
          : await act(`trials/${no}/${action}`, "POST");
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) =>
        action === "delete"
          ? all.filter((x) => x.trial_no !== row.trial_no)
          : all.map((x) =>
              x.trial_no === row.trial_no
                ? { ...x, status: res.data.status }
                : x,
            ),
      );
      setError(null);
      toastManager.add({
        title: c(`done.trial_${action}`, { no: row.trial_no }),
        type: "success",
      });
    });
  }

  function print(row) {
    startTransition(async () => {
      const res = await act(`trials/${encodeURIComponent(row.trial_no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printTrial(c, res.data);
    });
  }

  const columns = [
    {
      key: "trial_no",
      label: c("col.ref"),
      render: (r) => (
        <Link
          href={`${BASE.trials}?tab=entry&ref=${encodeURIComponent(r.trial_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.trial_no}
        </Link>
      ),
      read: (r) => r.trial_no,
    },
    { key: "lab", label: c("col.lab") },
    { key: "trial_date", label: c("col.trial_date"), type: "date" },
    { key: "trial_by", label: c("col.trial_by") },
    {
      key: "status",
      label: c("col.status"),
      read: (r) => c(`trial_status.${r.status}`),
      render: (r) => (
        <StatusChip
          status={TONE[r.status]}
          label={c(`trial_status.${r.status}`)}
        />
      ),
    },
    {
      key: "workflow",
      label: c("col.workflow"),
      sortable: false,
      read: (r) =>
        NEXT_TRIAL[r.status] ? c(`btn.${NEXT_TRIAL[r.status]}`) : "",
      render: (r) =>
        NEXT_TRIAL[r.status] && canManage ? (
          <Button
            size="sm"
            variant="outline"
            onClick={() => setAsk({ row: r, action: NEXT_TRIAL[r.status] })}
            disabled={pending}
            aria-label={`${c(`btn.${NEXT_TRIAL[r.status]}`)} ${r.trial_no}`}
          >
            {NEXT_TRIAL[r.status] === "start" ? <Play /> : <CheckCheck />}{" "}
            {c(`btn.${NEXT_TRIAL[r.status]}`)}
          </Button>
        ) : (
          <span className="text-foreground-muted">—</span>
        ),
    },
  ];
  const rowActions = [
    {
      key: "edit",
      label: c("btn.edit"),
      icon: <Pencil />,
      onClick: (r) =>
        router.push(
          `${BASE.trials}?tab=entry&ref=${encodeURIComponent(r.trial_no)}`,
        ),
    },
    { key: "print", label: c("btn.print"), icon: <Printer />, onClick: print },
    ...(canManage
      ? [
          {
            key: "cancel",
            label: c("btn.cancel_trial"),
            icon: <XCircle />,
            onClick: (r) => setAsk({ row: r, action: "cancel" }),
            disabled: (r) => !["PENDING", "IN_PROGRESS"].includes(r.status),
          },
          {
            key: "delete",
            label: c("btn.delete"),
            icon: <Trash2 />,
            onClick: (r) => setAsk({ row: r, action: "delete" }),
            disabled: (r) => r.status !== "PENDING",
          },
        ]
      : []),
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-base font-semibold text-foreground">
            {c("trial.list_title")}
          </h2>
          {canManage ? (
            <Link
              href={`${BASE.trials}?tab=entry`}
              className={buttonVariants()}
            >
              <Plus /> {c("btn.new_trial")}
            </Link>
          ) : null}
        </div>
        <div className="flex flex-wrap items-end gap-3">
          <Field label={c("f.ref")} className="min-w-48">
            <Input
              value={f.ref}
              onChange={(e) => setF((x) => ({ ...x, ref: e.target.value }))}
              aria-label={c("f.ref")}
            />
          </Field>
          <Field label={c("f.from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF((x) => ({ ...x, from: e.target.value }))}
              aria-label={c("f.from")}
            />
          </Field>
          <Field label={c("f.to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF((x) => ({ ...x, to: e.target.value }))}
              aria-label={c("f.to")}
            />
          </Field>
          <Field label={c("f.status")} className="min-w-52">
            <PSelect
              value={f.status}
              onValueChange={(v) => setF((x) => ({ ...x, status: v ?? "" }))}
              options={pick(
                options.trial_statuses.map((s) => ({
                  value: s,
                  label: c(`trial_status.${s}`),
                })),
                c("ph.status"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Button onClick={() => search()} loading={pending}>
            <Search /> {c("btn.search")}
          </Button>
          <Button
            variant="outline"
            onClick={() => (
              setF({ ref: "", from: "", to: "", status: "" }),
              search({})
            )}
            disabled={pending}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <RecordsGrid
        columns={columns}
        rows={rows.map((r) => ({ ...r, id: r.trial_no }))}
        rowActions={rowActions}
        exportName="chemical-trials"
        emptyState={{ title: c("trial.empty"), text: c("trial.empty_hint") }}
      />
      <ConfirmDialog
        open={ask !== null}
        onOpenChange={(open) => !open && setAsk(null)}
        title={
          ask ? c(`ask.trial_${ask.action}`, { no: ask.row.trial_no }) : ""
        }
        confirmLabel={
          ask
            ? c(`btn.${ask.action === "cancel" ? "cancel_trial" : ask.action}`)
            : ""
        }
        onConfirm={() => run(ask)}
        loading={pending}
        destructive={ask?.action === "delete" || ask?.action === "cancel"}
        cancelLabel={c("btn.close")}
      />
    </div>
  );
}

const LINE = {
  item_department_id: "",
  item_id: "",
  supplier_id: "",
  brand: "",
  origin_country_id: "",
  price: "",
  claim_dose: "",
  dose_unit: "%",
  claim_cost: "",
  claim_ratio: "",
  cert_type_id: "",
  actual_dose: "",
  actual_cost: "",
  actual_ratio: "",
  result: "PENDING",
  comments: "",
  sample_qty: "",
  required_by: "",
};
let seq = 0;
const lineKey = () => `t${++seq}`;

/**
 * Brickland's "Trial Operation": Trial Ref (auto), Lab, Trial Date, Trial
 * Carried Out By, Print, then one row per dye or chemical — Category, Dye /
 * Chem, Supplier, Brand, Origin, Price, the dose, unit, cost per kg and
 * liquor ratio claimed, Certification, what was found, the result, comments,
 * sample qty and when it is required — Add Row, Back To List and Save. A
 * trial that is completed or cancelled reads only.
 */
function TrialEntry({ options, initial, canManage, act }) {
  const c = useCl();
  const router = useRouter();
  const toastManager = useToastManager();
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Dhaka",
  }).format(new Date());
  const [head, setHead] = useState({
    lab_floor_id: str(initial?.lab_floor_id),
    trial_date: initial?.trial_date ?? today,
    trial_by: str(initial?.trial_by),
  });
  const [lines, setLines] = useState(() =>
    (initial?.lines ?? []).map((l) => ({
      ...LINE,
      ...Object.fromEntries(
        Object.entries(l)
          .filter(([k]) => k in LINE)
          .map(([k, v]) => [k, str(v)]),
      ),
      key: lineKey(),
      item_label: l.item,
    })),
  );
  const [items, setItems] = useState({});
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = !initial || ["PENDING", "IN_PROGRESS"].includes(initial.status);
  const editable = open && canManage;

  async function loadItems(department) {
    if (!department || items[department]) return;
    const res = await act(`items?department=${encodeURIComponent(department)}`);
    if (res?.status === "success")
      setItems((x) => ({ ...x, [department]: res.data }));
  }
  const setLine = (key, patch) =>
    setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  function chooseItem(line, value) {
    const it = (items[line.item_department_id] ?? []).find(
      (i) => i.value === value,
    );
    setLine(line.key, {
      item_id: value ?? "",
      item_label: it?.label,
      brand: line.brand || str(it?.brand),
      supplier_id: line.supplier_id || str(it?.supplier_id),
      origin_country_id: line.origin_country_id || str(it?.origin_country_id),
    });
  }

  function save() {
    startTransition(async () => {
      const payload = {
        ...head,
        lines: lines.map(({ key, item_label, ...l }) =>
          Object.fromEntries(Object.entries(l).map(([k, v]) => [k, blank(v)])),
        ),
      };
      const res = initial
        ? await act(
            `trials/${encodeURIComponent(initial.trial_no)}`,
            "PUT",
            payload,
          )
        : await act("trials", "POST", payload);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setErrors(null);
      toastManager.add({
        title: c("done.trial_saved", { no: res.data.trial_no }),
        type: "success",
      });
      router.push(
        `${BASE.trials}?tab=entry&ref=${encodeURIComponent(res.data.trial_no)}`,
      );
      router.refresh();
    });
  }

  const brands = options.brands.map((b) => ({ value: b, label: b }));
  const cellCls = "px-1.5 py-1.5 align-top";
  const heads = [
    "category",
    "dye_chem",
    "supplier",
    "brand",
    "origin",
    "price",
    "claim_dose",
    "dose_unit",
    "claim_cost",
    "claim_ratio",
    "certification",
    "actual_dose",
    "actual_cost",
    "actual_ratio",
    "result",
    "comments",
    "sample_qty",
    "required_by",
  ];
  const numBox = (l, k, n) => (
    <Input
      type="number"
      min={0}
      step="any"
      value={l[k]}
      onChange={(e) => setLine(l.key, { [k]: e.target.value })}
      disabled={!editable}
      aria-label={`${c(`col.${k}`)} ${n}`}
      className="w-24"
    />
  );
  const textBox = (l, k, n, ph, w = "w-24") => (
    <Input
      value={l[k]}
      onChange={(e) => setLine(l.key, { [k]: e.target.value })}
      disabled={!editable}
      placeholder={ph}
      aria-label={`${c(`col.${k}`)} ${n}`}
      className={w}
    />
  );
  const sel = (l, k, n, list, ph, w = "w-44", onChange) => (
    <div className={w}>
      <PSelect
        value={l[k]}
        onValueChange={(v) =>
          onChange ? onChange(v) : setLine(l.key, { [k]: v ?? "" })
        }
        options={ph === null ? list : pick(list, ph)}
        disabled={!editable}
        aria-label={`${c(`col.${k}`)} ${n}`}
      />
    </div>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-base font-semibold text-foreground">
            {c("trial.form_title")}
          </h2>
          {initial ? (
            <StatusChip
              status={TONE[initial.status]}
              label={c(`trial_status.${initial.status}`)}
            />
          ) : null}
        </div>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Row label={c("f.trial_ref")}>
            <Input
              value={initial?.trial_no ?? c("trial.auto")}
              disabled
              aria-label={c("f.trial_ref")}
            />
          </Row>
          <Row label={c("f.lab")} required>
            <PSelect
              value={head.lab_floor_id}
              onValueChange={(v) =>
                setHead((x) => ({ ...x, lab_floor_id: v ?? "" }))
              }
              options={pick(options.floors, c("ph.floor"))}
              disabled={!editable}
              aria-label={c("f.lab")}
            />
          </Row>
          <Row label={c("f.trial_date")} required>
            <Input
              type="date"
              value={head.trial_date}
              onChange={(e) =>
                setHead((x) => ({ ...x, trial_date: e.target.value }))
              }
              disabled={!editable}
              aria-label={c("f.trial_date")}
            />
          </Row>
          <Row label={c("f.trial_by")} required>
            <PSelect
              value={head.trial_by}
              onValueChange={(v) =>
                setHead((x) => ({ ...x, trial_by: v ?? "" }))
              }
              options={pick(options.users, c("ph.user"))}
              disabled={!editable}
              aria-label={c("f.trial_by")}
            />
          </Row>
        </div>
        <div className="mt-4 flex justify-end">
          <Button
            variant="outline"
            onClick={() =>
              printTrial(c, {
                ...(initial ?? {}),
                ...head,
                lab: options.floors.find((x) => x.value === head.lab_floor_id)
                  ?.label,
                trial_by_name: options.users.find(
                  (x) => x.value === head.trial_by,
                )?.label,
                lines: lines.map((l) => ({
                  ...l,
                  item: l.item_label,
                  department: options.departments.find(
                    (d) => d.value === l.item_department_id,
                  )?.label,
                  supplier: options.suppliers.find(
                    (s) => s.value === l.supplier_id,
                  )?.label,
                  origin: options.countries.find(
                    (x) => x.value === l.origin_country_id,
                  )?.label,
                  certification: options.cert_types.find(
                    (x) => x.value === l.cert_type_id,
                  )?.label,
                })),
              })
            }
          >
            <Printer /> {c("btn.print")}
          </Button>
        </div>
        <div className="mt-3 overflow-x-auto rounded-lg border border-border">
          <table className="w-full min-w-[2600px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className="px-2 py-2.5 text-left text-[13px] font-semibold">
                  #
                </th>
                {heads.map((h) => (
                  <th
                    key={h}
                    className="px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap"
                  >
                    {c(`col.${h}`)}
                  </th>
                ))}
                <th className="px-2 py-2.5" aria-label={c("btn.remove")} />
              </tr>
            </thead>
            <tbody>
              {lines.length === 0 ? (
                <tr>
                  <td
                    colSpan={heads.length + 2}
                    className="px-4 py-6 text-center text-foreground-muted"
                  >
                    {c("trial.no_lines")}
                  </td>
                </tr>
              ) : (
                lines.map((l, i) => {
                  const n = i + 1;
                  const dept = items[l.item_department_id] ?? [];
                  const known = dept.some((x) => x.value === l.item_id);
                  return (
                    <tr key={l.key} className="border-t border-border">
                      <td
                        className={cn(cellCls, "pt-3.5 text-foreground-muted")}
                      >
                        {n}
                      </td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "item_department_id",
                          n,
                          options.departments,
                          c("ph.select"),
                          "w-48",
                          (v) => (
                            setLine(l.key, {
                              item_department_id: v ?? "",
                              item_id: "",
                              item_label: null,
                            }),
                            loadItems(v)
                          ),
                        )}
                      </td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "item_id",
                          n,
                          known || !l.item_id
                            ? dept.map((x) => ({
                                value: x.value,
                                label: x.label,
                              }))
                            : [
                                {
                                  value: l.item_id,
                                  label: l.item_label ?? l.item_id,
                                },
                                ...dept,
                              ],
                          c("ph.select"),
                          "w-60",
                          (v) => chooseItem(l, v),
                        )}
                      </td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "supplier_id",
                          n,
                          options.suppliers,
                          c("ph.select"),
                          "w-52",
                        )}
                      </td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "brand",
                          n,
                          l.brand && !options.brands.includes(l.brand)
                            ? [{ value: l.brand, label: l.brand }, ...brands]
                            : brands,
                          c("ph.select"),
                          "w-36",
                        )}
                      </td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "origin_country_id",
                          n,
                          options.countries,
                          c("ph.select"),
                          "w-36",
                        )}
                      </td>
                      <td className={cellCls}>{numBox(l, "price", n)}</td>
                      <td className={cellCls}>{numBox(l, "claim_dose", n)}</td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "dose_unit",
                          n,
                          options.units.map((u) => ({ value: u, label: u })),
                          null,
                          "w-24",
                        )}
                      </td>
                      <td className={cellCls}>{numBox(l, "claim_cost", n)}</td>
                      <td className={cellCls}>
                        {textBox(l, "claim_ratio", n, "1:8")}
                      </td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "cert_type_id",
                          n,
                          options.cert_types,
                          c("ph.select"),
                          "w-56",
                        )}
                      </td>
                      <td className={cellCls}>{numBox(l, "actual_dose", n)}</td>
                      <td className={cellCls}>{numBox(l, "actual_cost", n)}</td>
                      <td className={cellCls}>
                        {textBox(l, "actual_ratio", n, "1:8")}
                      </td>
                      <td className={cellCls}>
                        {sel(
                          l,
                          "result",
                          n,
                          options.results.map((r) => ({
                            value: r,
                            label: c(`result.${r}`),
                          })),
                          null,
                          "w-32",
                        )}
                      </td>
                      <td className={cellCls}>
                        {textBox(l, "comments", n, "", "w-44")}
                      </td>
                      <td className={cellCls}>{numBox(l, "sample_qty", n)}</td>
                      <td className={cellCls}>
                        <Input
                          type="date"
                          value={l.required_by}
                          onChange={(e) =>
                            setLine(l.key, { required_by: e.target.value })
                          }
                          disabled={!editable}
                          aria-label={`${c("col.required_by")} ${n}`}
                          className="w-40"
                        />
                      </td>
                      <td className={cellCls}>
                        {editable ? (
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() =>
                              setLines((all) =>
                                all.filter((x) => x.key !== l.key),
                              )
                            }
                            aria-label={`${c("btn.remove")} ${n}`}
                          >
                            <X />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
        {editable ? (
          <Button
            variant="outline"
            className="mt-3"
            onClick={() =>
              setLines((all) => [...all, { ...LINE, key: lineKey() }])
            }
          >
            <Plus /> {c("btn.add_row")}
          </Button>
        ) : null}
        {errors ? (
          <Alert variant="danger" className="mt-4">
            {errors.join(" · ")}
          </Alert>
        ) : null}
        <div className="mt-4 flex flex-wrap gap-2">
          <Link
            href={BASE.trials}
            className={buttonVariants({ variant: "outline" })}
          >
            <ArrowLeft /> {c("btn.back")}
          </Link>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/** The trial sheet as the lab files it: the head, every line, claimed against found. */
function printTrial(c, t) {
  const cell = (v) => (v === null || v === undefined || v === "" ? "—" : v);
  printDocument({
    title: c("print.trial_title"),
    docNo: t.trial_no ?? c("trial.auto"),
    orientation: "landscape",
    meta: [
      [c("f.lab"), cell(t.lab)],
      [c("f.trial_date"), dayFmt(t.trial_date)],
      [c("f.trial_by"), cell(t.trial_by_name)],
      [c("col.status"), t.status ? c(`trial_status.${t.status}`) : "—"],
    ],
    sections: [
      {
        title: c("print.lines"),
        columns: [
          { label: "#", align: "right" },
          { label: c("col.category") },
          { label: c("col.dye_chem") },
          { label: c("col.supplier") },
          { label: c("col.brand") },
          { label: c("col.price"), align: "right" },
          { label: c("col.claim_dose"), align: "right" },
          { label: c("col.claim_cost"), align: "right" },
          { label: c("col.claim_ratio") },
          { label: c("col.actual_dose"), align: "right" },
          { label: c("col.actual_cost"), align: "right" },
          { label: c("col.actual_ratio") },
          { label: c("col.result") },
          { label: c("col.comments") },
        ],
        rows: (t.lines ?? []).map((l, i) => [
          i + 1,
          cell(l.department),
          cell(l.item),
          cell(l.supplier),
          cell(l.brand),
          fig(l.price),
          l.claim_dose ? `${fig(l.claim_dose)} ${l.dose_unit ?? ""}` : "—",
          fig(l.claim_cost),
          cell(l.claim_ratio),
          l.actual_dose ? `${fig(l.actual_dose)} ${l.dose_unit ?? ""}` : "—",
          fig(l.actual_cost),
          cell(l.actual_ratio),
          c(`result.${l.result || "PENDING"}`),
          cell(l.comments),
        ]),
        empty: c("trial.no_lines"),
      },
    ],
    signatures: [
      c("print.sign_trial_by"),
      c("print.sign_lab_head"),
      c("print.sign_approved"),
    ],
  });
}

// ── In-House Fabric Testing ──────────────────────────────────────────────

const NEXT_TEST = { DRAFT: "submit", REJECTED: "submit", SUBMITTED: "approve" };

/**
 * Brickland's "Dyeing Lab Fabric Test List": Add New, then every test —
 * Customer, Style No, Order No, Fabric Type, Fib Comp, Color, Lot No,
 * Quantity, Drying Proced, Summary, Std/Act Dia, Std/Act GSM, Coil No,
 * Shrinkage (L, W), SP%, S/L, Wash, Rubbing (Dry, Wet), RH%, Status,
 * WorkFlow (Submit, Approve) and Edit, Reject, Delete.
 */
function FabricTestList({ initialRows, canManage, canApprove, act }) {
  const c = useCl();
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initialRows);
  const [ask, setAsk] = useState(null);
  const [note, setNote] = useState("");
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function run({ row, action }) {
    startTransition(async () => {
      const no = encodeURIComponent(row.test_no);
      const res =
        action === "delete"
          ? await act(`fabric-tests/${no}`, "DELETE")
          : await act(`fabric-tests/${no}/${action}`, "POST", {
              note: note || null,
            });
      setAsk(null);
      setNote("");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) =>
        action === "delete"
          ? all.filter((x) => x.test_no !== row.test_no)
          : all.map((x) => (x.test_no === row.test_no ? res.data : x)),
      );
      setError(null);
      toastManager.add({
        title: c(`done.test_${action}`, { no: row.test_no }),
        type: "success",
      });
    });
  }

  const may = (action) => (action === "submit" ? canManage : canApprove);
  const w = (k) => ({ key: k, label: c(`col.${k}`) });
  const n = (k, group) => ({
    key: k,
    label: c(`col.${k}`),
    type: "number",
    format: (v) => fig(v),
    ...(group ? { group, groupLabel: c(`grp.${group}`) } : {}),
  });
  const columns = [
    {
      key: "test_no",
      label: c("col.test_no"),
      render: (r) => (
        <Link
          href={`${BASE.tests}?tab=entry&ref=${encodeURIComponent(r.test_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.test_no}
        </Link>
      ),
      read: (r) => r.test_no,
    },
    w("customer"),
    w("style_no"),
    w("order_no"),
    w("fabric_type"),
    w("composition"),
    w("colour"),
    w("lot_no"),
    n("qty"),
    {
      key: "drying",
      label: c("col.drying"),
      read: (r) => (r.drying ? c(`drying.${r.drying}`) : ""),
      render: (r) => (r.drying ? c(`drying.${r.drying}`) : "—"),
    },
    {
      key: "summary",
      label: c("col.summary"),
      read: (r) => (r.summary ? c(`summary.${r.summary}`) : ""),
      render: (r) => (r.summary ? c(`summary.${r.summary}`) : "—"),
    },
    n("std_dia"),
    n("act_dia"),
    n("std_gsm"),
    n("act_gsm"),
    w("coil_no"),
    n("shrink_l", "shrinkage"),
    n("shrink_w", "shrinkage"),
    n("spirality"),
    n("stitch_length"),
    w("wash"),
    {
      key: "rub_dry",
      label: c("col.rub_dry"),
      group: "rubbing",
      groupLabel: c("grp.rubbing"),
    },
    {
      key: "rub_wet",
      label: c("col.rub_wet"),
      group: "rubbing",
      groupLabel: c("grp.rubbing"),
    },
    n("rh"),
    {
      key: "status",
      label: c("col.status"),
      read: (r) => c(`test_status.${r.status}`),
      render: (r) => (
        <StatusChip
          status={TONE[r.status]}
          label={c(`test_status.${r.status}`)}
        />
      ),
    },
    {
      key: "workflow",
      label: c("col.workflow"),
      sortable: false,
      read: (r) => (NEXT_TEST[r.status] ? c(`btn.${NEXT_TEST[r.status]}`) : ""),
      render: (r) =>
        NEXT_TEST[r.status] && may(NEXT_TEST[r.status]) ? (
          <Button
            size="sm"
            variant="outline"
            onClick={() => setAsk({ row: r, action: NEXT_TEST[r.status] })}
            disabled={pending}
            aria-label={`${c(`btn.${NEXT_TEST[r.status]}`)} ${r.test_no}`}
          >
            {NEXT_TEST[r.status] === "submit" ? <Send /> : <CheckCheck />}{" "}
            {c(`btn.${NEXT_TEST[r.status]}`)}
          </Button>
        ) : (
          <span className="text-foreground-muted">—</span>
        ),
    },
  ];
  const rowActions = [
    {
      key: "edit",
      label: c("btn.edit"),
      icon: <Pencil />,
      onClick: (r) =>
        router.push(
          `${BASE.tests}?tab=entry&ref=${encodeURIComponent(r.test_no)}`,
        ),
    },
    ...(canApprove
      ? [
          {
            key: "reject",
            label: c("btn.reject"),
            icon: <XCircle />,
            onClick: (r) => setAsk({ row: r, action: "reject" }),
            disabled: (r) => r.status !== "SUBMITTED",
          },
        ]
      : []),
    ...(canManage
      ? [
          {
            key: "delete",
            label: c("btn.delete"),
            icon: <Trash2 />,
            onClick: (r) => setAsk({ row: r, action: "delete" }),
            disabled: (r) => r.status !== "DRAFT",
          },
        ]
      : []),
  ];

  return (
    <div className="flex flex-col gap-4">
      <section
        className={cn(card, "flex items-center justify-between gap-3 p-5")}
      >
        <h2 className="text-base font-semibold text-foreground">
          {c("test.list_title")}
        </h2>
        {canManage ? (
          <Link
            href={`${BASE.tests}?tab=entry`}
            className={buttonVariants({ variant: "success" })}
          >
            <Plus /> {c("btn.add_new")}
          </Link>
        ) : null}
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <RecordsGrid
        columns={columns}
        rows={rows.map((r) => ({ ...r, id: r.test_no }))}
        rowActions={rowActions}
        exportName="fabric-tests"
        emptyState={{ title: c("test.empty"), text: c("test.empty_hint") }}
      />
      <ConfirmDialog
        open={
          ask !== null && (ask.action === "submit" || ask.action === "delete")
        }
        onOpenChange={(open) => !open && setAsk(null)}
        title={ask ? c(`ask.test_${ask.action}`, { no: ask.row.test_no }) : ""}
        confirmLabel={ask ? c(`btn.${ask.action}`) : ""}
        onConfirm={() => run(ask)}
        loading={pending}
        destructive={ask?.action === "delete"}
        cancelLabel={c("btn.close")}
      />
      <Modal
        open={
          ask !== null && (ask.action === "approve" || ask.action === "reject")
        }
        onOpenChange={(open) => !open && (setAsk(null), setNote(""))}
        title={ask ? c(`ask.test_${ask.action}`, { no: ask.row.test_no }) : ""}
        className="max-w-md"
      >
        <div className="flex flex-col gap-3">
          <Field label={c("f.decision_note")}>
            <Input
              value={note}
              onChange={(e) => setNote(e.target.value)}
              aria-label={c("f.decision_note")}
            />
          </Field>
          <div className="flex justify-end gap-2">
            <Button
              variant="outline"
              onClick={() => (setAsk(null), setNote(""))}
              disabled={pending}
            >
              {c("btn.close")}
            </Button>
            <Button
              variant={ask?.action === "reject" ? "danger" : "primary"}
              onClick={() => run(ask)}
              loading={pending}
            >
              {ask?.action === "reject" ? <XCircle /> : <CheckCheck />}{" "}
              {ask ? c(`btn.${ask.action}`) : ""}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

const TEST = {
  test_date: "",
  lab_floor_id: "",
  buyer_id: "",
  order_id: "",
  style_no: "",
  fabric_type_id: "",
  composition_id: "",
  colour: "",
  lot_no: "",
  qty: "",
  drying: "",
  summary: "",
  std_dia: "",
  act_dia: "",
  std_gsm: "",
  act_gsm: "",
  coil_no: "",
  shrink_l: "",
  shrink_w: "",
  spirality: "",
  stitch_length: "",
  wash: "",
  rub_dry: "",
  rub_wet: "",
  light: "",
  perspiration: "",
  rh: "",
  remarks: "",
};

/**
 * The fabric test sheet (Brickland's own Add New answers "Page not found",
 * so it is laid out from the list's columns): the lot and its order, then
 * dia and GSM against standard, shrinkage, spirality, stitch length, the
 * fastness grades and the room's humidity, and a summary. A draft or a
 * rejected test changes; a submitted or approved one reads only.
 */
function FabricTestEntry({ options, initial, canManage, act }) {
  const c = useCl();
  const router = useRouter();
  const toastManager = useToastManager();
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Dhaka",
  }).format(new Date());
  const [f, setF] = useState(() => ({
    ...TEST,
    test_date: today,
    ...(initial
      ? Object.fromEntries(Object.keys(TEST).map((k) => [k, str(initial[k])]))
      : {}),
  }));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable =
    canManage && (!initial || ["DRAFT", "REJECTED"].includes(initial.status));
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const orders = useMemo(
    () =>
      options.orders.filter((o) => !f.buyer_id || o.buyer_id === f.buyer_id),
    [options.orders, f.buyer_id],
  );
  const grades = options.grades.map((g) => ({ value: g, label: g }));

  function save() {
    startTransition(async () => {
      const payload = Object.fromEntries(
        Object.entries(f).map(([k, v]) => [k, blank(v)]),
      );
      const res = initial
        ? await act(
            `fabric-tests/${encodeURIComponent(initial.test_no)}`,
            "PUT",
            payload,
          )
        : await act("fabric-tests", "POST", payload);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setErrors(null);
      toastManager.add({
        title: c("done.test_saved", { no: res.data.test_no }),
        type: "success",
      });
      router.push(BASE.tests);
      router.refresh();
    });
  }

  const box = (k, type = "text", extra = {}) => (
    <Field label={c(`col.${k}`)}>
      <Input
        type={type}
        step={type === "number" ? "any" : undefined}
        value={f[k]}
        onChange={(e) => set(k, e.target.value)}
        disabled={!editable}
        aria-label={c(`col.${k}`)}
        {...extra}
      />
    </Field>
  );
  const choose = (k, list, ph, label = k) => (
    <Field label={c(`col.${label}`)}>
      <PSelect
        value={f[k]}
        onValueChange={(v) => set(k, v ?? "")}
        options={pick(list, ph)}
        disabled={!editable}
        aria-label={c(`col.${label}`)}
      />
    </Field>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-base font-semibold text-foreground">
            {initial
              ? c("test.edit_title", { no: initial.test_no })
              : c("test.form_title")}
          </h2>
          {initial ? (
            <StatusChip
              status={TONE[initial.status]}
              label={c(`test_status.${initial.status}`)}
            />
          ) : null}
        </div>
        {initial?.status === "REJECTED" && initial.decision_note ? (
          <Alert variant="warning" className="mt-3">
            {c("test.rejected_note", { note: initial.decision_note })}
          </Alert>
        ) : null}
        <h3 className="mt-5 text-[13px] font-semibold tracking-wide text-foreground-muted uppercase">
          {c("test.sec_lot")}
        </h3>
        <div className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={c("col.test_date")} required>
            <Input
              type="date"
              value={f.test_date}
              onChange={(e) => set("test_date", e.target.value)}
              disabled={!editable}
              aria-label={c("col.test_date")}
            />
          </Field>
          <Field label={c("col.customer")} required>
            <PSelect
              value={f.buyer_id}
              onValueChange={(v) => (
                set("buyer_id", v ?? ""),
                set("order_id", "")
              )}
              options={pick(options.buyers, c("ph.select"))}
              disabled={!editable}
              aria-label={c("col.customer")}
            />
          </Field>
          <Field label={c("col.order_no")}>
            <PSelect
              value={f.order_id}
              onValueChange={(v) => {
                set("order_id", v ?? "");
                const o = options.orders.find((x) => x.value === v);
                if (o?.style && !f.style_no) set("style_no", o.style);
              }}
              options={pick(orders, c("ph.select"))}
              disabled={!editable}
              aria-label={c("col.order_no")}
            />
          </Field>
          {box("style_no")}
          {choose(
            "fabric_type_id",
            options.fabric_types,
            c("ph.select"),
            "fabric_type",
          )}
          {choose(
            "composition_id",
            options.compositions,
            c("ph.select"),
            "composition",
          )}
          {box("colour")}
          {box("lot_no")}
          {box("qty", "number")}
          {choose(
            "drying",
            options.drying.map((d) => ({ value: d, label: c(`drying.${d}`) })),
            c("ph.select"),
          )}
          {box("coil_no")}
          {choose("lab_floor_id", options.floors, c("ph.floor"), "lab")}
          {choose(
            "summary",
            options.summaries.map((s) => ({
              value: s,
              label: c(`summary.${s}`),
            })),
            c("ph.select"),
          )}
        </div>
        <h3 className="mt-5 text-[13px] font-semibold tracking-wide text-foreground-muted uppercase">
          {c("test.sec_results")}
        </h3>
        <div className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {box("std_dia", "number")}
          {box("act_dia", "number")}
          {box("std_gsm", "number")}
          {box("act_gsm", "number")}
          {box("shrink_l", "number")}
          {box("shrink_w", "number")}
          {box("spirality", "number")}
          {box("stitch_length", "number")}
          {choose("wash", grades, c("ph.grade"))}
          {choose("rub_dry", grades, c("ph.grade"))}
          {choose("rub_wet", grades, c("ph.grade"))}
          {choose("light", grades, c("ph.grade"))}
          {choose("perspiration", grades, c("ph.grade"))}
          {box("rh", "number")}
        </div>
        <div className="mt-4">
          <Field label={c("f.remarks")}>
            <Textarea
              rows={3}
              value={f.remarks}
              onChange={(e) => set("remarks", e.target.value)}
              disabled={!editable}
              aria-label={c("f.remarks")}
            />
          </Field>
        </div>
        <p className="mt-3 text-xs text-foreground-muted">{c("test.hint")}</p>
        {errors ? (
          <Alert variant="danger" className="mt-4">
            {errors.join(" · ")}
          </Alert>
        ) : null}
        <div className="mt-4 flex flex-wrap gap-2">
          <Link
            href={BASE.tests}
            className={buttonVariants({ variant: "outline" })}
          >
            <ArrowLeft /> {c("btn.back")}
          </Link>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

// ── Lab Test Reports ─────────────────────────────────────────────────────

const PLAIN_TYPES = ["text", "date", "number", "int", "pct"];

/**
 * Brickland's "Dyeing Lab Reports": the reports as one list ("Fabric Test —
 * Fabric Test Register" …), Export to Excel? and Preview; below, the report's
 * name and its grid. The chosen report stays in the address (`?report=`)
 * without a reload; ticked for Excel, Preview saves the grid as a file.
 */
function LabReports({ reports, initial, act }) {
  const c = useCl();
  const router = useRouter();
  const toastManager = useToastManager();
  const [report, setReport] = useState(initial?.report ?? "");
  const [excel, setExcel] = useState(false);
  const [result, setResult] = useState(initial);
  const [pending, startTransition] = useTransition();
  const options = reports.map((r) => ({
    value: r.key,
    label: `${c(`rgroup.${r.group}`)} — ${c(`report.${r.key}`)}`,
  }));

  const cellText = (col, v) => {
    if (v === null || v === undefined || v === "") return "";
    if (col.type === "pct") return `${Number(v).toFixed(2)}%`;
    if (col.type === "date") return dayFmt(v);
    if (col.type === "number" || col.type === "int") return fig(v);
    return PLAIN_TYPES.includes(col.type) ? String(v) : c(`${col.type}.${v}`);
  };

  function download(data) {
    const head = data.columns.map((col) => c(`rcol.${col.key}`));
    const body = data.rows.map((r) =>
      data.columns.map((col) => cellText(col, r[col.key])),
    );
    const csv = [head, ...body]
      .map((l) =>
        l.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(","),
      )
      .join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(
      new Blob([`﻿${csv}`], { type: "text/csv;charset=utf-8" }),
    );
    link.download = `${data.report}.csv`;
    link.click();
  }

  function preview() {
    if (!report)
      return toastManager.add({ title: c("rep.pick"), type: "warning" });
    startTransition(async () => {
      const res = await act(`reports/${report}`);
      if (res?.status !== "success")
        return toastManager.add({
          title: messagesOf(res).join(" · "),
          type: "warning",
        });
      router.replace(`/colour-lab/dyeing-lab-reports?report=${report}`, {
        scroll: false,
      });
      if (excel) download(res.data);
      else setResult(res.data);
    });
  }

  const columns = result
    ? result.columns.map((col) => ({
        key: col.key,
        label: c(`rcol.${col.key}`),
        type:
          col.type === "date"
            ? "date"
            : ["number", "int", "pct"].includes(col.type)
              ? "number"
              : "text",
        format: ["number", "int", "pct"].includes(col.type)
          ? (v) => cellText(col, v)
          : undefined,
        render: PLAIN_TYPES.includes(col.type)
          ? undefined
          : (r) => cellText(col, r[col.key]) || "—",
        read: PLAIN_TYPES.includes(col.type)
          ? undefined
          : (r) => cellText(col, r[col.key]),
      }))
    : [];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {c("rep.title")}
        </h2>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] text-foreground-muted italic">
            {c("rep.params")}
          </legend>
          <div className="flex flex-wrap items-center gap-4">
            <div className="min-w-72 flex-1">
              <PSelect
                value={report}
                onValueChange={(v) => setReport(v ?? "")}
                options={pick(options, c("rep.select"))}
                aria-label={c("rep.report")}
              />
            </div>
            <label className="flex items-center gap-2 text-[13px] font-medium text-foreground">
              <Checkbox
                checked={excel}
                onCheckedChange={(v) => setExcel(v === true)}
                aria-label={c("rep.excel")}
              />
              {c("rep.excel")}
            </label>
          </div>
          <div className="mt-4 flex justify-center">
            <Button onClick={preview} loading={pending}>
              <FileText /> {c("rep.preview")}
            </Button>
          </div>
        </fieldset>
      </section>
      {result ? (
        <section
          className="flex flex-col gap-2"
          aria-label={c(`report.${result.report}`)}
        >
          <h3 className="text-sm font-semibold text-foreground">
            {c(`report.${result.report}`)}
          </h3>
          <RecordsGrid
            key={result.report}
            columns={columns}
            rows={result.rows.map((r, i) => ({ ...r, id: `r${i}` }))}
            exportName={result.report}
            plain
            emptyText={c("rep.empty")}
          />
        </section>
      ) : null}
    </div>
  );
}

export {
  LabReports,
  CertificateForm,
  TrialList,
  TrialEntry,
  FabricTestList,
  FabricTestEntry,
};
