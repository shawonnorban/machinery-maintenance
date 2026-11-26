"use client";

import { useState } from "react";
import { printDocument } from "@/lib/print-document";
import { History, Printer, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { useT } from "@/lib/i18n";
import {
  card,
  day,
  stamp,
  dash,
  messagesOf,
  today,
  dropError,
  Field,
} from "@/components/generalstore/common";

/*
 * What the Gate Pass screens share (AnnotechERP 11/02–11/04): the status and
 * return pills, the gate pass lists as Brickland lays them out (click a row
 * to open it), the open pass — Gate Pass, Taken By, Product / Item Details,
 * Additional Information, Gate IN records and the Audit Trail — the confirm
 * dialogs with their note or reason, and the printed gate pass.
 */

const qty = (n) =>
  n === null || n === undefined || n === ""
    ? "—"
    : Number(n).toLocaleString("en-IN", { maximumFractionDigits: 3 });

const STATUS_TONE = {
  DRAFT: "bg-surface-muted text-foreground-muted",
  PENDING: "bg-warning-subtle text-warning",
  APPROVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  CANCELLED: "bg-danger-subtle text-danger",
  OUT: "bg-info-subtle text-info",
  PARTIAL: "bg-warning-subtle text-warning",
  SHORT: "bg-danger-subtle text-danger",
  CLOSED: "bg-success-subtle text-success",
  COMPLETED: "bg-success-subtle text-success",
};
const RETURN_TONE = {
  PENDING: "bg-info-subtle text-info",
  PARTIAL: "bg-warning-subtle text-warning",
  FULL: "bg-success-subtle text-success",
  SHORT: "bg-danger-subtle text-danger",
};

function useGp() {
  const t = useT("gatepass");
  const g = (k, p) => t(`gp.${k}`, p);
  // "3 Pcs" — a bare number when the lines' units differ.
  const withUnit = (n, unit) =>
    unit ? `${qty(n)} ${t(`master.opt.unit.${unit}`)}` : qty(n);
  const unitName = (unit) => (unit ? t(`master.opt.unit.${unit}`) : "—");
  return { t, g, withUnit, unitName };
}

function StatusPill({ status }) {
  const { g } = useGp();
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap",
        STATUS_TONE[status],
      )}
    >
      {g(`status.${status}`)}
    </span>
  );
}

function ReturnPill({ state }) {
  const { g } = useGp();
  if (!state) return "—";
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap",
        RETURN_TONE[state],
      )}
    >
      {g(`return.${state}`)}
    </span>
  );
}

const RIGHT = ["qty", "out", "returned", "short", "balance"];

/**
 * A gate pass list as Brickland lays it out: the title with its count, a
 * hint, the columns asked for, and a click on a row to open the pass.
 *
 * @param {{ title: string, hint?: string, rows: object[], cols: string[], empty: string, onOpen?: Function, openNo?: string, action?: React.ReactNode }} props
 */
function PassTable({ title, hint, rows, cols, empty, onOpen, openNo, action }) {
  const { g, withUnit } = useGp();
  const cell = (r, k) => {
    // A Main Gate Pass carries no items: no quantities.
    if (
      r.kind === "MAIN" &&
      ["qty", "out", "balance", "returned", "short"].includes(k)
    )
      return "—";
    switch (k) {
      case "gp_no":
        return <span className="font-semibold">{r.gp_no}</span>;
      case "date":
        return day(r.date);
      case "category":
        return g(`kind.${r.kind}`);
      case "type":
        return g(`type.${r.pass_type}`);
      case "items":
        return dash(r.items.join(", "));
      case "qty":
        return withUnit(r.out, r.unit);
      case "status":
        return <StatusPill status={r.status} />;
      case "return":
        return <ReturnPill state={r.return} />;
      case "out_time":
        return stamp(r.out_at);
      case "in_time":
        return stamp(r.in_at);
      case "balance":
        return withUnit(r.balance, r.unit);
      case "expected_return":
        return dash(r.expected_return);
      case "expected_return_day":
        return r.expected_return ? day(r.expected_return) : "—";
      case "out":
        return withUnit(r.out, r.unit);
      case "returned":
      case "short":
        return qty(r[k]);
      default:
        return dash(r[k]);
    }
  };
  const label = (k) =>
    g(`col.${k === "expected_return_day" ? "expected_return" : k}`);

  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">{title}</h2>
          {hint ? (
            <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
          ) : null}
        </div>
        {action}
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[960px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {cols.map((k) => (
                <th
                  key={k}
                  className={cn(
                    "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                    RIGHT.includes(k) && "text-right",
                  )}
                >
                  {label(k)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td
                  colSpan={cols.length}
                  className="px-3 py-10 text-center text-foreground-muted"
                >
                  {empty}
                </td>
              </tr>
            ) : null}
            {rows.map((r) => (
              <tr
                key={r.gp_no}
                onClick={onOpen ? () => onOpen(r.gp_no) : undefined}
                onKeyDown={
                  onOpen
                    ? (ev) => {
                        if (ev.key === "Enter") onOpen(r.gp_no);
                      }
                    : undefined
                }
                tabIndex={onOpen ? 0 : undefined}
                className={cn(
                  "border-t border-border align-top",
                  onOpen && "cursor-pointer hover:bg-surface-muted",
                  openNo === r.gp_no && "bg-brand-subtle",
                )}
              >
                {cols.map((k) => (
                  <td
                    key={k}
                    className={cn(
                      "px-3 py-2.5",
                      RIGHT.includes(k) && "text-right tabular-nums",
                      ["gp_no", "date", "out_time", "in_time"].includes(k) &&
                        "whitespace-nowrap",
                    )}
                  >
                    {cell(r, k)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

/** A coloured section of the open pass. */
function Section({ title, tone, children, className }) {
  const head = {
    brand: "bg-brand-subtle text-brand",
    success: "bg-success-subtle text-success",
    warning: "bg-warning-subtle text-warning",
    info: "bg-info-subtle text-info",
  }[tone];
  return (
    <section className={cn(card, "min-w-0 overflow-hidden", className)}>
      <h3
        className={cn(
          "flex items-center gap-2 px-4 py-2.5 text-sm font-semibold",
          head,
        )}
      >
        {title}
      </h3>
      <div className="p-4">{children}</div>
    </section>
  );
}

function Facts({ rows }) {
  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
      {rows.map(([k, v]) => (
        <div key={k} className="contents">
          <dt className="text-foreground-muted">{k}</dt>
          <dd className="font-semibold text-foreground">{dash(v)}</dd>
        </div>
      ))}
    </dl>
  );
}

/**
 * The open gate pass, as Brickland shows it on every screen: its number,
 * status and type, Print and close, then the sections.
 *
 * @param {{ pass: object, buttons?: React.ReactNode, notice?: React.ReactNode, onClose?: Function, voucher?: string, children?: React.ReactNode }} props
 */
function PassView({ pass, buttons, notice, onClose, children }) {
  const { g, withUnit, unitName } = useGp();
  const p = (k) => g(`panel.${k}`);
  const employee = pass.employee;
  const contact = [pass.contact_person, pass.contact_no]
    .filter(Boolean)
    .join(" · ");

  return (
    <section className={cn(card, "flex min-w-0 flex-col gap-4 p-5")}>
      {buttons || onClose ? (
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="flex flex-wrap gap-2">{buttons}</div>
          {onClose ? (
            <Button
              variant="outline"
              size="sm"
              onClick={onClose}
              aria-label={g("btn.close")}
            >
              <X className="size-4" aria-hidden />
            </Button>
          ) : null}
        </div>
      ) : null}
      {notice}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-lg font-semibold text-foreground">
            {pass.gp_no}
          </h2>
          <StatusPill status={pass.status} />
          <span className="inline-flex items-center rounded-full bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">
            {g(`type_long.${pass.pass_type}`)}
          </span>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => printPass(pass, g, unitName)}
        >
          <Printer className="size-4" aria-hidden />
          {g("btn.print")}
        </Button>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Section title={p("pass")} tone="brand">
          <Facts
            rows={[
              [p("date"), day(pass.date)],
              [p("category"), pass.category],
              [p("purpose"), pass.purpose],
              [p("destination"), pass.destination],
              [p("location_type"), pass.location_type],
              [p("address"), pass.address],
              [p("contact"), contact],
              ...(pass.kind === "ITEM" && pass.pass_type === "RETURNABLE"
                ? [
                    [
                      p("expected_return"),
                      pass.expected_return ? day(pass.expected_return) : null,
                    ],
                  ]
                : []),
              ...(pass.kind === "ITEM"
                ? [
                    [p("buyer"), pass.buyer],
                    [
                      p("style_po"),
                      [pass.style, pass.po_no].filter(Boolean).join(" / "),
                    ],
                  ]
                : []),
            ]}
          />
        </Section>
        <Section title={p("taken_by")} tone="success">
          <Facts
            rows={
              employee
                ? [
                    [p("carrier"), g(`carrier.${pass.carrier_type}`)],
                    [p("card"), employee.card_no],
                    [p("name"), employee.name],
                    [g("f.employee_id"), employee.employee_code],
                    [p("department"), employee.department],
                    [p("designation"), employee.designation],
                    [p("mobile"), employee.mobile],
                    ...(pass.kind === "ITEM"
                      ? [[p("vehicle"), pass.vehicle_no]]
                      : []),
                  ]
                : [
                    [p("carrier"), g(`carrier.${pass.carrier_type}`)],
                    [p("name"), pass.carrier_name],
                    [p("company"), pass.carrier_company],
                    [p("mobile"), pass.carrier_mobile],
                    [p("nid"), pass.carrier_nid],
                    ...(pass.vehicle_no
                      ? [[p("vehicle"), pass.vehicle_no]]
                      : []),
                  ]
            }
          />
        </Section>
      </div>

      {pass.kind === "ITEM" ? (
        <Section title={p("items")} tone="brand">
          <div className="-m-4 overflow-x-auto">
            <table className="w-full min-w-[760px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "sl",
                    "item_code",
                    "item_name",
                    "unit",
                    "out_qty",
                    "returned",
                    "short",
                    "balance",
                    "remarks",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                        ["out_qty", "returned", "short", "balance"].includes(
                          k,
                        ) && "text-right",
                      )}
                    >
                      {g(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {pass.lines.map((l) => (
                  <tr key={l.id} className="border-t border-border">
                    <td className="px-3 py-2.5">{l.sl}</td>
                    <td className="px-3 py-2.5">{dash(l.item_code)}</td>
                    <td className="px-3 py-2.5 font-semibold">
                      {dash(l.item)}
                    </td>
                    <td className="px-3 py-2.5">{unitName(l.unit)}</td>
                    <td className="px-3 py-2.5 text-right font-semibold tabular-nums">
                      {qty(l.out)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {qty(l.returned)}
                    </td>
                    <td
                      className={cn(
                        "px-3 py-2.5 text-right tabular-nums",
                        l.short > 0 && "text-danger",
                      )}
                    >
                      {qty(l.short)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {qty(l.balance)}
                    </td>
                    <td className="px-3 py-2.5">{dash(l.remarks)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Section>
      ) : null}

      {children}

      <Section title={p("additional")} tone="warning">
        <Facts
          rows={[
            [p("remarks"), pass.remarks],
            [p("documents"), pass.documents],
            ...(pass.out_at
              ? [
                  [
                    p("out"),
                    `${stamp(pass.out_at)}${pass.out_by ? ` · ${pass.out_by}` : ""}${pass.out_remarks ? ` · ${pass.out_remarks}` : ""}`,
                  ],
                ]
              : []),
          ]}
        />
      </Section>

      {pass.ins.length ? (
        <Section title={p("ins")} tone="info">
          <div className="-m-4 overflow-x-auto">
            <table className="w-full min-w-[640px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "gpi_no",
                    "in_time",
                    "in_items",
                    "entry",
                    "received_by",
                  ].map((k) => (
                    <th
                      key={k}
                      className="px-3 py-2.5 text-left text-xs font-semibold uppercase"
                    >
                      {g(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {pass.ins.map((i) => (
                  <tr key={i.gpi_no} className="border-t border-border">
                    <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                      {i.gpi_no}
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {stamp(i.in_at)}
                    </td>
                    <td className="px-3 py-2.5">
                      {i.items.length
                        ? i.items
                            .map(
                              (x) =>
                                `${x.item}: ${withUnit(x.quantity, x.unit)}`,
                            )
                            .join(", ")
                        : "—"}
                    </td>
                    <td className="px-3 py-2.5">
                      {i.final
                        ? i.short_reason
                          ? g("in.final_short", {
                              reason: g(`short_reason.${i.short_reason}`),
                            })
                          : g("in.final")
                        : g("in.partial")}
                    </td>
                    <td className="px-3 py-2.5">{dash(i.received_by)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Section>
      ) : null}

      <Section
        title={
          <>
            <History className="size-4" aria-hidden />
            {p("audit")}
          </>
        }
        tone="info"
      >
        <ol className="flex flex-col gap-3">
          {pass.events.map((e, i) => (
            <li key={i} className="border-l-2 border-brand pl-3">
              <p className="text-sm font-semibold text-foreground">
                {g(`event.${e.action}`)}
                <span className="font-normal text-foreground-muted">
                  {" → "}
                  {g(`status.${e.to_status}`)}
                </span>
              </p>
              {e.note ? (
                <p className="text-sm text-foreground">{e.note}</p>
              ) : null}
              <p className="text-xs text-foreground-muted">
                {p("by_at")
                  .replace(":by", e.by ?? "—")
                  .replace(":at", stamp(e.at))}
              </p>
            </li>
          ))}
        </ol>
      </Section>
    </section>
  );
}

/** The printed gate pass: its facts, carrier, items and the signature boxes. */
/** A gate pass on paper: its facts, the items going out, and the four signatures the gate asks for. */
function printPass(pass, g, unitName) {
  const p = (k) => g(`panel.${k}`);
  printDocument({
    title: g("print.title"),
    docNo: pass.gp_no,
    subtitle: g(`type_long.${pass.pass_type}`),
    meta: [
      [p("date"), day(pass.date)],
      [p("category"), pass.category],
      [p("purpose"), pass.purpose],
      [
        g("col.status"),
        pass.status ? { v: g(`status.${pass.status}`), tone: "info" } : null,
      ],
      [p("destination"), pass.destination],
      [p("address"), pass.address],
      [
        p("expected_return"),
        pass.expected_return ? day(pass.expected_return) : null,
      ],
      [
        p("carrier"),
        pass.employee
          ? `${pass.employee.name} (${pass.employee.card_no})`
          : pass.carrier_name,
      ],
      [p("mobile"), pass.carrier_mobile],
      [p("vehicle"), pass.vehicle_no],
    ],
    sections: pass.lines.length
      ? [
          {
            columns: [
              { label: g("col.sl"), align: "center", width: "7%" },
              { label: g("col.item_code"), nowrap: true },
              { label: g("col.item_name") },
              { label: g("col.unit") },
              { label: g("col.out_qty"), align: "right" },
              { label: g("col.remarks") },
            ],
            rows: pass.lines.map((l) => [
              l.sl,
              l.item_code,
              l.item,
              unitName(l.unit),
              qty(l.out),
              l.remarks,
            ]),
          },
        ]
      : [],
    notes: [pass.remarks],
    signatures: ["prepared", "approved", "security", "receiver"].map((k) =>
      g(`print.${k}`),
    ),
  });
}

/**
 * A confirm dialog: its question, what follows, an optional or required
 * note (or reason), Cancel and the action.
 *
 * @param {{ title: string, body?: string, field?: { label: string, required?: boolean, input?: boolean }, confirm: string, variant?: string, pending?: boolean, error?: string, onConfirm: Function, onClose: Function }} props
 */
function ConfirmDialog({
  title,
  body,
  field,
  confirm,
  variant = "primary",
  pending,
  error,
  onConfirm,
  onClose,
}) {
  const { g } = useGp();
  const [text, setText] = useState("");
  return (
    <Modal
      open
      onOpenChange={(o) => !o && onClose()}
      title={title}
      description={body}
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            {g("btn.cancel")}
          </Button>
          <Button
            variant={variant}
            loading={pending}
            disabled={field?.required && !text.trim()}
            onClick={() => onConfirm(text.trim())}
          >
            {confirm}
          </Button>
        </>
      }
    >
      {field ? (
        <Field label={field.label} required={field.required} error={error}>
          {field.input ? (
            <Input
              value={text}
              onChange={(ev) => setText(ev.target.value)}
              aria-label={field.label}
            />
          ) : (
            <Textarea
              rows={3}
              value={text}
              onChange={(ev) => setText(ev.target.value)}
              aria-label={field.label}
            />
          )}
        </Field>
      ) : error ? (
        <p className="text-sm text-danger">{error}</p>
      ) : null}
    </Modal>
  );
}

/** The first message of a failed call. */
const firstMessage = (result) =>
  Object.values(messagesOf(result))[0]?.[0] ?? "";

export {
  card,
  day,
  stamp,
  dash,
  qty,
  today,
  dropError,
  messagesOf,
  firstMessage,
  Field,
  useGp,
  StatusPill,
  ReturnPill,
  PassTable,
  PassView,
  Section,
  ConfirmDialog,
};
