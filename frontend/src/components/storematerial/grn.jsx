"use client";

import { useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import {
  CheckCircle2,
  Download,
  FileText,
  Plus,
  Printer,
  RotateCcw,
  Search,
  Trash2,
  Undo2,
  Upload,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import {
  card,
  dash,
  day,
  messagesOf,
  qty,
  EmployeeField,
  RollEntry,
  rollPayload,
  rollsOff,
} from "./common";

/*
 * Store & Material → In-House / GRN (AnnotechERP 09/01), as Brickland lays
 * it out. Against PR: the PR number fetches the approved requisition's
 * information and items, the actual receive quantity goes against each
 * (never past its pending), and Confirm In-House asks once more. Direct
 * Receive: whom the stock is for (optional), the supplier and currency, and
 * material lines from the Fabric / Accessories master. Either way the
 * confirmed GRN opens in place — Print, Reverse (the Store Manager, with a
 * reason), its items and its attachments. A fabric line may be received
 * roll by roll — roll number, dye lot, GSM, width and quantity — the rolls
 * adding up to the line. Nothing reloads.
 */

/** The first message on any of a line's rolls. */
const rollError = (errors, key) =>
  Object.entries(errors).find(([k]) =>
    k.startsWith(`lines.${key}.rolls`),
  )?.[1]?.[0];

const blankDirect = () => ({
  buyer: "",
  po_number: "",
  style: "",
  order_no: "",
  supplier: "",
  currency: "BDT",
  lines: [{ kind: "FABRIC", item_id: "", qty: "", unit_price: "", rolls: [] }],
});

function Facts({ rows, columns = 1 }) {
  return (
    <dl
      className={cn(
        "grid gap-x-6 gap-y-2 text-sm",
        columns === 2 && "sm:grid-cols-2",
      )}
    >
      {rows.map(([label, value]) => (
        <div key={label} className="grid grid-cols-[9rem_1fr] gap-2">
          <dt className="text-foreground-muted">{label}</dt>
          <dd className="font-medium text-foreground">{dash(value)}</dd>
        </div>
      ))}
    </dl>
  );
}

/** A confirmed GRN: its facts, Print and Reverse, its items and attachments. */
function GrnView({ grn, canManage, canReceive, act, onChange, onNew }) {
  const t = useT("storematerial");
  const te = useT("erp");
  const g = (k, p) => t(`grn.${k}`, p);
  const toastManager = useToastManager();
  const [tab, setTab] = useState("items");
  const [reverse, setReverse] = useState(null);
  const [upload, setUpload] = useState({ name: "", file: null });
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const fromPr = grn.kind === "PR";

  function doReverse() {
    setErrors({});
    startTransition(async () => {
      const result = await act("reverse", grn.grn_no, {
        reason: reverse,
      });
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setReverse(null);
      toastManager.add({
        title: g("reversed_msg", { no: grn.grn_no }),
        type: "success",
      });
      onChange(result.data);
    });
  }

  function attach(ev) {
    ev.preventDefault();
    setErrors({});
    if (!upload.file) return setErrors({ file: [g("file_required")] });
    const body = new FormData();
    body.set("file", upload.file);
    if (upload.name) body.set("name", upload.name);
    startTransition(async () => {
      const response = await fetch(
        `/api/store-material/grn-attachment/${encodeURIComponent(grn.grn_no)}`,
        { method: "POST", body },
      );
      const json = await response.json().catch(() => ({}));
      if (!response.ok)
        return setErrors(json?.errors ?? { file: [json?.message ?? ""] });
      setUpload({ name: "", file: null });
      ev.target.reset();
      toastManager.add({ title: g("attached"), type: "success" });
      onChange(json.data);
    });
  }

  const cols = fromPr
    ? [
        "sl",
        "material",
        "unit",
        "pr_qty",
        "prev",
        "current",
        "total",
        "pending",
        "warehouse",
      ]
    : ["sl", "type", "material", "unit", "current", "unit_price", "warehouse"];
  const right = ["pr_qty", "prev", "current", "total", "pending", "unit_price"];
  // The GRN on paper: the document and its supplier, each line (its rolls under it), and the receiving signatures.
  const print = () =>
    printDocument({
      title: g("note"),
      docNo: grn.grn_no,
      orientation: fromPr ? "landscape" : "portrait",
      meta: [
        [g("d.grn_date"), grn.grn_date],
        [g("d.pr_no"), fromPr ? grn.pr_no : null],
        [g("d.merchandiser"), fromPr ? grn.merchandiser : null],
        [g("d.buyer"), grn.buyer],
        [g("d.po_number"), grn.po_number],
        [g("d.style"), grn.style],
        [g("d.order_no"), grn.order_no],
        [g("d.supplier"), grn.supplier],
        [
          g("d.warehouse"),
          grn.warehouse.map((w) => t(`warehouse.${w}`)).join(", "),
        ],
        [g("d.currency"), fromPr ? null : grn.currency],
        [g("d.received_by"), grn.received_by],
        [g("d.confirmed_by"), grn.confirmed_by],
        [
          te("status"),
          {
            v: g(`status.${grn.status}`),
            tone: grn.status === "CONFIRMED" ? "ok" : "bad",
          },
        ],
        [
          g("d.reversed"),
          grn.status === "REVERSED"
            ? `${grn.reversed_by ?? ""} · ${grn.reverse_reason ?? ""}`
            : null,
        ],
      ],
      sections: [
        {
          columns: cols.map((k) => ({
            label: g(`col.${k}`),
            align: right.includes(k)
              ? "right"
              : k === "sl"
                ? "center"
                : undefined,
          })),
          rows: grn.lines.map((l) =>
            cols.map(
              (k) =>
                ({
                  sl: l.sl,
                  type: t(`kind.${l.kind}`),
                  material: {
                    v: l.material,
                    bold: true,
                    sub: l.rolls?.length
                      ? `${t("roll.on_line", { n: l.rolls.length })}: ${l.rolls.map((r) => [r.roll_no, r.dye_lot ? `${t("roll.lot")} ${r.dye_lot}` : null, r.gsm ? `${r.gsm} GSM` : null, r.width, `${qty(r.qty)} ${l.uom}`].filter(Boolean).join(" · ")).join("; ")}`
                      : undefined,
                  },
                  unit: l.uom,
                  pr_qty: qty(l.pr_qty),
                  prev: qty(l.prev),
                  current: { v: qty(l.qty), bold: true },
                  total: qty(l.total),
                  pending: qty(l.pending),
                  unit_price: l.unit_price == null ? null : qty(l.unit_price),
                  warehouse: t(`warehouse.${l.warehouse}_short`),
                })[k],
            ),
          ),
        },
      ],
      notes: [grn.remarks],
      signatures: [
        te("print_sign.received"),
        te("print_sign.store"),
        te("print_sign.approved"),
      ],
    });

  return (
    <section
      className={cn(card, "min-w-0 overflow-hidden print:border-0")}
      id="grn-print"
    >
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <h2 className="text-base font-semibold text-foreground">{g("note")}</h2>
        <div className="flex flex-wrap items-center gap-2 print:hidden">
          <span
            className={cn(
              "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium",
              grn.status === "CONFIRMED"
                ? "bg-success-subtle text-success"
                : "bg-danger-subtle text-danger",
            )}
          >
            <CheckCircle2 className="size-3.5" aria-hidden />
            {g(`status.${grn.status}`)}
          </span>
          <Button size="sm" variant="outline" onClick={print}>
            <Printer className="size-4" aria-hidden />
            {g("print")}
          </Button>
          {canManage && grn.status === "CONFIRMED" ? (
            <Button
              size="sm"
              variant="outline"
              className="border-danger text-danger"
              onClick={() => (setErrors({}), setReverse(""))}
            >
              <Undo2 className="size-4" aria-hidden />
              {g("reverse")}
            </Button>
          ) : null}
          {onNew ? (
            <Button size="sm" onClick={onNew}>
              <Plus className="size-4" aria-hidden />
              {g("new")}
            </Button>
          ) : null}
        </div>
      </div>
      {/* Brickland's two columns: the document and whom it is for on the left; supplier, warehouse and people on the right. */}
      <div className="grid gap-4 border-t border-border p-5 md:grid-cols-2">
        <Facts
          rows={[
            [g("d.grn_no"), grn.grn_no],
            [g("d.grn_date"), day(grn.grn_date)],
            ...(fromPr
              ? [
                  [g("d.pr_no"), grn.pr_no],
                  [g("d.merchandiser"), grn.merchandiser],
                ]
              : []),
            [g("d.buyer"), grn.buyer],
            [g("d.po_number"), grn.po_number],
            [g("d.style"), grn.style],
            [g("d.order_no"), grn.order_no],
          ]}
        />
        <Facts
          rows={[
            [g("d.supplier"), grn.supplier],
            [
              g("d.warehouse"),
              grn.warehouse.map((w) => t(`warehouse.${w}`)).join(", "),
            ],
            ...(fromPr ? [] : [[g("d.currency"), grn.currency]]),
            [g("d.received_by"), grn.received_by],
            [g("d.confirmed_by"), grn.confirmed_by],
            [g("d.remarks"), grn.remarks],
            ...(grn.status === "REVERSED"
              ? [
                  [
                    g("d.reversed"),
                    `${grn.reversed_by ?? ""} · ${grn.reverse_reason ?? ""}`,
                  ],
                ]
              : []),
          ]}
        />
      </div>
      <div
        role="tablist"
        className="flex gap-1 border-t border-border px-5 pt-3 print:hidden"
      >
        {["items", "attachments"].map((k) => (
          <button
            key={k}
            type="button"
            role="tab"
            aria-selected={tab === k}
            onClick={() => setTab(k)}
            className={cn(
              "border-b-2 px-3 py-2 text-sm font-medium",
              tab === k
                ? "border-brand text-brand"
                : "border-transparent text-foreground-muted hover:text-foreground",
            )}
          >
            {g(`tab_${k}`)}
          </button>
        ))}
      </div>
      {tab === "items" ? (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {cols.map((k) => (
                  <th
                    key={k}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      right.includes(k) && "text-right",
                    )}
                  >
                    {g(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {grn.lines.flatMap((l) => [
                <tr key={l.sl} className="border-t border-border">
                  {cols.map((k) => (
                    <td
                      key={k}
                      className={cn(
                        "px-3 py-2.5",
                        right.includes(k) && "text-right tabular-nums",
                        k === "material" && "font-semibold",
                        k === "current" && "font-semibold",
                      )}
                    >
                      {
                        {
                          sl: l.sl,
                          type: t(`kind.${l.kind}`),
                          material: l.material,
                          unit: l.uom,
                          pr_qty: qty(l.pr_qty),
                          prev: qty(l.prev),
                          current: qty(l.qty),
                          total: qty(l.total),
                          pending: qty(l.pending),
                          unit_price:
                            l.unit_price == null ? "—" : qty(l.unit_price),
                          warehouse: t(`warehouse.${l.warehouse}_short`),
                        }[k]
                      }
                    </td>
                  ))}
                </tr>,
                l.rolls?.length ? (
                  <tr key={`${l.sl}-rolls`} className="bg-surface-muted/40">
                    <td />
                    <td colSpan={cols.length - 1} className="px-3 pb-2.5 pt-0">
                      <p className="mb-1 text-xs font-semibold text-foreground-muted">
                        {t("roll.on_line", {
                          n: l.rolls.length,
                        })}
                      </p>
                      <ul className="flex flex-wrap gap-1.5">
                        {l.rolls.map((r) => (
                          <li
                            key={r.roll_no}
                            className="rounded-md border border-border bg-surface px-2 py-0.5 text-xs tabular-nums"
                          >
                            <span className="font-semibold">{r.roll_no}</span>
                            {r.dye_lot
                              ? ` · ${t("roll.lot")} ${r.dye_lot}`
                              : ""}
                            {r.gsm ? ` · ${r.gsm} GSM` : ""}
                            {r.width ? ` · ${r.width}` : ""} · {qty(r.qty)}{" "}
                            {l.uom}
                          </li>
                        ))}
                      </ul>
                    </td>
                  </tr>
                ) : null,
              ])}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="flex flex-col gap-4 p-5">
          {grn.attachments.length === 0 ? (
            <p className="text-sm text-foreground-muted">
              {g("no_attachments")}
            </p>
          ) : null}
          <ul className="flex flex-col divide-y divide-border">
            {grn.attachments.map((a) => (
              <li
                key={a.id}
                className="flex items-center justify-between gap-3 py-2"
              >
                <span className="flex items-center gap-2 text-sm text-foreground">
                  <FileText
                    className="size-4 text-foreground-muted"
                    aria-hidden
                  />
                  {a.original_name}
                  <span className="text-xs text-foreground-muted">
                    {a.human_size}
                  </span>
                </span>
                {a.downloadable ? (
                  <a
                    href={`/api/files/${a.id}/download`}
                    className="inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline"
                  >
                    <Download className="size-4" aria-hidden />
                    {g("download")}
                  </a>
                ) : null}
              </li>
            ))}
          </ul>
          {canReceive ? (
            <form
              onSubmit={attach}
              className="grid gap-3 sm:grid-cols-[1fr_1.4fr_auto] sm:items-end"
            >
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-medium">{g("doc_name")}</span>
                <Input
                  value={upload.name}
                  onChange={(ev) =>
                    setUpload((u) => ({
                      ...u,
                      name: ev.target.value,
                    }))
                  }
                  aria-label={g("doc_name")}
                />
              </label>
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-medium">{g("file")}</span>
                <input
                  type="file"
                  accept="application/pdf,image/*"
                  onChange={(ev) =>
                    setUpload((u) => ({
                      ...u,
                      file: ev.target.files?.[0] ?? null,
                    }))
                  }
                  aria-label={g("file")}
                  className="rounded-sm border border-border px-3 py-2 text-sm"
                />
                {errors.file ? (
                  <span className="text-xs text-danger">{errors.file[0]}</span>
                ) : null}
              </label>
              <Button type="submit" loading={pending}>
                <Upload className="size-4" aria-hidden />
                {g("attach")}
              </Button>
            </form>
          ) : null}
        </div>
      )}
      {reverse !== null ? (
        <Modal
          open
          onOpenChange={(v) => !v && setReverse(null)}
          title={g("reverse_title", { no: grn.grn_no })}
          description={g("reverse_body")}
          footer={
            <>
              <Button
                variant="outline"
                onClick={() => setReverse(null)}
                disabled={pending}
              >
                {g("cancel")}
              </Button>
              <Button variant="danger" onClick={doReverse} loading={pending}>
                {g("reverse_confirm")}
              </Button>
            </>
          }
        >
          <div className="flex flex-col gap-2">
            {errors.grn ? (
              <Alert variant="danger">{errors.grn[0]}</Alert>
            ) : null}
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-medium">
                {g("reason")}
                <span className="text-danger"> *</span>
              </span>
              <Textarea
                rows={3}
                value={reverse}
                onChange={(ev) => setReverse(ev.target.value)}
                aria-label={g("reason")}
              />
              {errors.reason ? (
                <span className="text-xs text-danger">{errors.reason[0]}</span>
              ) : null}
            </label>
          </div>
        </Modal>
      ) : null}
    </section>
  );
}

/** Against PR. */
function AgainstPr({ act, onDone }) {
  const t = useT("storematerial");
  const g = (k, p) => t(`grn.${k}`, p);
  const [prNo, setPrNo] = useState("");
  const [pr, setPr] = useState(null);
  const [qtys, setQtys] = useState({});
  const [rolls, setRolls] = useState({});
  const [receivedBy, setReceivedBy] = useState("");
  const [remarks, setRemarks] = useState("");
  const [errors, setErrors] = useState({});
  const [confirming, setConfirming] = useState(false);
  const [pending, startTransition] = useTransition();
  const filled = pr
    ? pr.items.filter((i) => Number(qtys[i.requisition_line_id]) > 0)
    : [];
  const over = pr
    ? pr.items.some(
        (i) =>
          Number(qtys[i.requisition_line_id] || 0) > i.pending ||
          rollsOff(rolls[i.requisition_line_id], qtys[i.requisition_line_id]),
      )
    : false;

  function find(ev) {
    ev?.preventDefault();
    setErrors({});
    setPr(null);
    startTransition(async () => {
      const result = await act("requisition", prNo.trim());
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setPr(result.data);
      setQtys({});
      setRolls({});
    });
  }

  function confirm() {
    setErrors({});
    startTransition(async () => {
      const result = await act("againstPr", {
        pr_no: pr.pr_no,
        received_by: receivedBy || null,
        remarks: remarks || null,
        lines: pr.items.map((i) => ({
          requisition_line_id: i.requisition_line_id,
          qty: qtys[i.requisition_line_id] || 0,
          rolls: rollPayload(rolls[i.requisition_line_id]),
        })),
      });
      setConfirming(false);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      onDone(result.data);
    });
  }

  const info = pr
    ? [
        [g("info.merchandiser"), pr.merchandiser],
        [g("info.pr_date"), day(pr.pr_date)],
        [g("info.buyer"), pr.buyer],
        [g("info.supplier"), pr.supplier],
        [g("info.po_number"), pr.po_number],
        [g("info.department"), pr.department],
        [g("info.style"), pr.style],
        [g("info.required_date"), day(pr.required_date)],
        [g("info.order_no"), pr.order_no],
        [g("info.purchase_ref"), pr.purchase_ref],
      ]
    : [];
  const general = Object.entries(errors)
    .filter(
      ([k]) => !k.startsWith("lines.") && !["pr_no", "received_by"].includes(k),
    )
    .flatMap(([, v]) => v);

  return (
    <section className={cn(card, "flex min-w-0 flex-col gap-4 p-5")}>
      <h2 className="text-base font-semibold text-foreground">{g("title")}</h2>
      <form onSubmit={find} className="flex flex-wrap items-end gap-3">
        <label className="flex min-w-64 flex-1 flex-col gap-1.5">
          <span className="text-[13px] font-medium">
            {g("pr_no")}
            <span className="text-danger"> *</span>
          </span>
          <Input
            value={prNo}
            onChange={(ev) => setPrNo(ev.target.value)}
            placeholder={g("pr_ph")}
            aria-label={g("pr_no")}
          />
        </label>
        <Button type="submit" loading={pending} disabled={!prNo.trim()}>
          <Search className="size-4" aria-hidden />
          {g("search")}
        </Button>
        {pr ? (
          <span className="ml-auto flex items-center gap-2 text-sm text-foreground">
            {g("pr_status")}
            <span className="rounded-full bg-info-subtle px-2 py-0.5 text-xs font-medium text-info">
              {g(`pr_state.${pr.status}`)}
            </span>
          </span>
        ) : null}
      </form>
      {errors.pr_no ? <Alert variant="danger">{errors.pr_no[0]}</Alert> : null}
      {pr ? (
        <>
          <div className="rounded-lg border border-border p-4">
            <p className="mb-3 text-sm font-semibold text-foreground">
              {g("pr_info")}{" "}
              <span className="font-normal text-foreground-muted">
                {g("auto_fetch")}
              </span>
            </p>
            <Facts rows={info} columns={2} />
          </div>
          <p className="text-sm font-semibold text-foreground">
            {g("items")}{" "}
            <span className="font-normal text-foreground-muted">
              {g("enter_qty")}
            </span>
          </p>
          <div className="-mx-5 overflow-x-auto">
            <table className="w-full min-w-[820px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "sl",
                    "type",
                    "category",
                    "material",
                    "unit",
                    "pr_qty",
                    "received",
                    "pending",
                    "receive_qty",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                        [
                          "pr_qty",
                          "received",
                          "pending",
                          "receive_qty",
                        ].includes(k) && "text-right",
                      )}
                    >
                      {g(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {pr.items.map((i) => {
                  const v = qtys[i.requisition_line_id] ?? "";
                  const tooMuch = Number(v || 0) > i.pending;
                  return [
                    <tr
                      key={i.requisition_line_id}
                      className="border-t border-border align-top"
                    >
                      <td className="px-3 py-2.5">{i.sl}</td>
                      <td className="px-3 py-2.5">{t(`kind.${i.kind}`)}</td>
                      <td className="px-3 py-2.5">{dash(i.category)}</td>
                      <td className="px-3 py-2.5 font-semibold">
                        {i.material}
                      </td>
                      <td className="px-3 py-2.5">{i.uom}</td>
                      <td className="px-3 py-2.5 text-right tabular-nums">
                        {qty(i.pr_qty)}
                      </td>
                      <td className="px-3 py-2.5 text-right tabular-nums">
                        {qty(i.received)}
                      </td>
                      <td className="px-3 py-2.5 text-right font-semibold tabular-nums">
                        {qty(i.pending)}
                      </td>
                      <td className="px-3 py-2.5">
                        <div className="ml-auto flex w-36 flex-col items-end gap-1">
                          <Input
                            type="number"
                            min="0"
                            step="any"
                            max={i.pending}
                            value={v}
                            onChange={(ev) =>
                              setQtys((all) => ({
                                ...all,
                                [i.requisition_line_id]: ev.target.value,
                              }))
                            }
                            placeholder="0"
                            disabled={i.pending <= 0}
                            aria-label={`${g("col.receive_qty")} ${i.sl}`}
                            className={cn(
                              "text-right",
                              tooMuch && "border-danger",
                            )}
                          />
                          {tooMuch ||
                          errors[`lines.${i.requisition_line_id}`] ? (
                            <span className="text-xs text-danger">
                              {errors[`lines.${i.requisition_line_id}`]?.[0] ??
                                g("max", {
                                  n: qty(i.pending),
                                })}
                            </span>
                          ) : null}
                        </div>
                      </td>
                    </tr>,
                    // A fabric line being received may be entered roll by roll.
                    i.kind === "FABRIC" && Number(v) > 0 ? (
                      <tr key={`${i.requisition_line_id}-rolls`}>
                        <td />
                        <td colSpan={8} className="px-3 pb-3">
                          <RollEntry
                            rolls={rolls[i.requisition_line_id] ?? []}
                            onChange={(list) =>
                              setRolls((all) => ({
                                ...all,
                                [i.requisition_line_id]: list,
                              }))
                            }
                            uom={i.uom}
                            lineQty={v}
                            label={i.material}
                            error={rollError(errors, i.requisition_line_id)}
                          />
                        </td>
                      </tr>
                    ) : null,
                  ];
                })}
              </tbody>
            </table>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <EmployeeField
              label={g("received_by")}
              value={receivedBy}
              onChange={setReceivedBy}
              error={errors.received_by?.[0]}
              lookup={(id) => act("employee", id)}
            />
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-medium">{g("remarks")}</span>
              <Input
                value={remarks}
                onChange={(ev) => setRemarks(ev.target.value)}
                aria-label={g("remarks")}
              />
            </label>
          </div>
          {general.length ? <Alert variant="danger">{general[0]}</Alert> : null}
          <div className="flex justify-end gap-2 border-t border-border pt-4">
            <Button
              onClick={() => setConfirming(true)}
              disabled={!filled.length || over || pending}
            >
              {g("confirm")}
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setPr(null);
                setPrNo("");
                setQtys({});
                setRolls({});
                setErrors({});
              }}
            >
              <RotateCcw className="size-4" aria-hidden />
              {g("reset")}
            </Button>
          </div>
        </>
      ) : null}
      {confirming ? (
        <Modal
          open
          onOpenChange={(v) => !v && setConfirming(false)}
          title={g("confirm_title")}
          description={g("confirm_body", {
            n: filled.length,
            pr: pr.pr_no,
          })}
          footer={
            <>
              <Button
                variant="outline"
                onClick={() => setConfirming(false)}
                disabled={pending}
              >
                {g("cancel")}
              </Button>
              <Button onClick={confirm} loading={pending}>
                {g("confirm")}
              </Button>
            </>
          }
        />
      ) : null}
    </section>
  );
}

/** Direct Receive. */
function DirectReceive({ options, act, onDone }) {
  const t = useT("storematerial");
  const g = (k, p) => t(`grn.${k}`, p);
  const [form, setForm] = useState(blankDirect());
  const [receivedBy, setReceivedBy] = useState("");
  const [remarks, setRemarks] = useState("");
  const [errors, setErrors] = useState({});
  const [confirming, setConfirming] = useState(false);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setForm((all) => ({ ...all, [k]: v ?? "" }));
  const setLine = (i, k) => (v) =>
    setForm((all) => ({
      ...all,
      lines: all.lines.map((l, j) =>
        j === i
          ? {
              ...l,
              [k]: v ?? "",
              ...(k === "kind" ? { item_id: "", rolls: [] } : {}),
            }
          : l,
      ),
    }));
  const ready = form.lines.filter((l) => l.item_id && Number(l.qty) > 0).length;
  const rollsWrong = form.lines.some((l) => rollsOff(l.rolls, l.qty));

  function confirm() {
    setErrors({});
    startTransition(async () => {
      const payload = {
        ...form,
        received_by: receivedBy || null,
        remarks: remarks || null,
        lines: form.lines.map((l) => ({
          item_id: l.item_id || null,
          qty: l.qty === "" ? null : l.qty,
          unit_price: l.unit_price === "" ? null : l.unit_price,
          rolls: l.kind === "FABRIC" ? rollPayload(l.rolls) : [],
        })),
      };
      const result = await act("direct", payload);
      setConfirming(false);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      onDone(result.data);
    });
  }

  return (
    <section className={cn(card, "flex min-w-0 flex-col gap-4 p-5")}>
      <div>
        <h2 className="text-base font-semibold text-foreground">
          {g("direct_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {g("direct_hint")}
        </p>
      </div>
      <div className="flex flex-col gap-3 rounded-lg border border-border p-4">
        <p className="text-sm font-semibold text-foreground">
          {g("stock_for")}{" "}
          <span className="font-normal text-foreground-muted">
            {g("stock_for_hint")}
          </span>
        </p>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["buyer", g("info.buyer")],
            ["po_number", g("info.po_number")],
            ["style", g("info.style")],
            ["order_no", g("info.order_no")],
          ].map(([k, label]) => (
            <label key={k} className="flex flex-col gap-1.5">
              <span className="text-[13px] font-medium">{label}</span>
              <Input
                value={form[k]}
                onChange={(ev) => set(k)(ev.target.value)}
                aria-label={label}
              />
            </label>
          ))}
          <label className="flex flex-col gap-1.5 sm:col-span-2">
            <span className="text-[13px] font-medium">{g("supplier")}</span>
            <Input
              value={form.supplier}
              onChange={(ev) => set("supplier")(ev.target.value)}
              aria-label={g("supplier")}
            />
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-medium">{g("currency")}</span>
            <Select
              value={form.currency}
              onValueChange={set("currency")}
              options={options.currencies.map((c) => ({
                value: c,
                label: c,
              }))}
              aria-label={g("currency")}
            />
          </label>
        </div>
      </div>
      <p className="text-sm font-semibold text-foreground">
        {g("materials")}{" "}
        <span className="font-normal text-foreground-muted">
          {g("ready", { n: ready })}
        </span>
      </p>
      <div className="flex flex-col gap-3">
        {form.lines.map((l, i) => (
          <div
            key={i}
            className="flex flex-col gap-3 rounded-lg border border-border p-3"
          >
            <div className="grid items-end gap-3 sm:grid-cols-[10rem_1fr_9rem_9rem_auto]">
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-medium">
                  {g("line_type", { n: i + 1 })}
                </span>
                <Select
                  value={l.kind}
                  onValueChange={setLine(i, "kind")}
                  options={["FABRIC", "TRIMS"].map((k) => ({
                    value: k,
                    label: t(`kind.${k}`),
                  }))}
                  aria-label={g("line_type", { n: i + 1 })}
                />
              </label>
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-medium">
                  {g("material")}
                  <span className="text-danger"> *</span>
                </span>
                <Select
                  value={l.item_id}
                  onValueChange={setLine(i, "item_id")}
                  options={options.items
                    .filter((o) => o.kind === l.kind)
                    .map((o) => ({
                      value: o.value,
                      label: o.label,
                    }))}
                  placeholder={g("select_material")}
                  aria-label={`${g("material")} ${i + 1}`}
                />
                {errors[`lines.${i}.item_id`] ? (
                  <span className="text-xs text-danger">
                    {errors[`lines.${i}.item_id`][0]}
                  </span>
                ) : null}
              </label>
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-medium">
                  {g("receive_qty")}
                  <span className="text-danger"> *</span>
                </span>
                <Input
                  type="number"
                  min="0"
                  step="any"
                  value={l.qty}
                  onChange={(ev) => setLine(i, "qty")(ev.target.value)}
                  placeholder="0"
                  aria-label={`${g("receive_qty")} ${i + 1}`}
                />
                {errors[`lines.${i}.qty`] ? (
                  <span className="text-xs text-danger">
                    {errors[`lines.${i}.qty`][0]}
                  </span>
                ) : null}
              </label>
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-medium">
                  {g("unit_price", {
                    currency: form.currency,
                  })}
                </span>
                <Input
                  type="number"
                  min="0"
                  step="any"
                  value={l.unit_price}
                  onChange={(ev) => setLine(i, "unit_price")(ev.target.value)}
                  placeholder={g("optional")}
                  aria-label={`${g("unit_price", { currency: form.currency })} ${i + 1}`}
                />
              </label>
              <Button
                variant="outline"
                size="icon"
                onClick={() =>
                  setForm((all) => ({
                    ...all,
                    lines:
                      all.lines.length > 1
                        ? all.lines.filter((_, j) => j !== i)
                        : all.lines,
                  }))
                }
                aria-label={`${g("cancel")} ${i + 1}`}
                disabled={form.lines.length === 1}
              >
                <Trash2 className="size-4" aria-hidden />
              </Button>
            </div>
            {l.kind === "FABRIC" && l.item_id ? (
              <RollEntry
                rolls={l.rolls ?? []}
                onChange={(list) => setLine(i, "rolls")(list)}
                uom={
                  options.items.find((o) => o.value === l.item_id)?.uom ?? ""
                }
                lineQty={l.qty}
                label={`${g("material")} ${i + 1}`}
                error={rollError(errors, i)}
              />
            ) : null}
          </div>
        ))}
      </div>
      <div className="flex flex-wrap gap-2">
        {[
          ["FABRIC", "add_fabric"],
          ["TRIMS", "add_trims"],
        ].map(([kind, label]) => (
          <Button
            key={kind}
            variant="outline"
            size="sm"
            onClick={() =>
              setForm((all) => ({
                ...all,
                lines: [
                  ...all.lines,
                  {
                    kind,
                    item_id: "",
                    qty: "",
                    unit_price: "",
                    rolls: [],
                  },
                ],
              }))
            }
          >
            <Plus className="size-4" aria-hidden />
            {g(label)}
          </Button>
        ))}
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <EmployeeField
          label={g("received_by")}
          value={receivedBy}
          onChange={setReceivedBy}
          error={errors.received_by?.[0]}
          lookup={(id) => act("employee", id)}
        />
        <label className="flex flex-col gap-1.5">
          <span className="text-[13px] font-medium">{g("remarks")}</span>
          <Input
            value={remarks}
            onChange={(ev) => setRemarks(ev.target.value)}
            aria-label={g("remarks")}
          />
        </label>
      </div>
      {errors.lines ? <Alert variant="danger">{errors.lines[0]}</Alert> : null}
      <div className="flex justify-end gap-2 border-t border-border pt-4">
        <Button
          onClick={() => setConfirming(true)}
          disabled={!ready || rollsWrong || pending}
        >
          {g("confirm_receive")}
        </Button>
        <Button
          variant="outline"
          onClick={() => {
            setForm(blankDirect());
            setErrors({});
          }}
        >
          <RotateCcw className="size-4" aria-hidden />
          {g("reset")}
        </Button>
      </div>
      {confirming ? (
        <Modal
          open
          onOpenChange={(v) => !v && setConfirming(false)}
          title={g("confirm_title")}
          description={g("direct_confirm_body", { n: ready })}
          footer={
            <>
              <Button
                variant="outline"
                onClick={() => setConfirming(false)}
                disabled={pending}
              >
                {g("cancel")}
              </Button>
              <Button onClick={confirm} loading={pending}>
                {g("confirm_receive")}
              </Button>
            </>
          }
        />
      ) : null}
    </section>
  );
}

/**
 * In-House / GRN.
 *
 * @param {{ options: object, initial: object|null, canReceive: boolean, canManage: boolean, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function InHouseGrn({ options, initial, canReceive, canManage, act }) {
  const t = useT("storematerial");
  const toastManager = useToastManager();
  const [tab, setTab] = useState("pr");
  const [grn, setGrn] = useState(initial);
  const done = (g) => {
    toastManager.add({
      title: t("grn.created_msg", { no: g.grn_no }),
      type: "success",
    });
    setGrn(g);
  };

  return (
    <div className="flex flex-col gap-4">
      {!grn ? (
        <div role="tablist" className={cn(card, "flex w-fit gap-1 p-1")}>
          {[
            ["pr", "against_pr"],
            ["direct", "direct"],
          ].map(([k, label]) => (
            <button
              key={k}
              type="button"
              role="tab"
              aria-selected={tab === k}
              onClick={() => setTab(k)}
              className={cn(
                "rounded-lg px-3 py-1.5 text-sm font-medium",
                tab === k
                  ? "bg-brand text-brand-foreground"
                  : "text-foreground hover:bg-surface-muted",
              )}
            >
              {t(`grn.${label}`)}
            </button>
          ))}
        </div>
      ) : null}
      {grn ? (
        <GrnView
          grn={grn}
          canManage={canManage}
          canReceive={canReceive}
          act={act}
          onChange={setGrn}
          onNew={canReceive ? () => setGrn(null) : null}
        />
      ) : !canReceive ? null : tab === "pr" ? (
        <AgainstPr act={act} onDone={done} />
      ) : (
        <DirectReceive options={options} act={act} onDone={done} />
      )}
    </div>
  );
}

export { InHouseGrn };
