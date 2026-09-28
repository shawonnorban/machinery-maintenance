"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { EmptyState } from "@/components/ui/empty-state";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { FormDialog } from "@/components/procurement/form-dialog";
import { Field } from "@/components/procurement/requisition-detail";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

const MODES = ["SEA", "AIR", "ROAD", "COURIER"];
const SHIPMENT_STATUSES = ["PLANNED", "SHIPPED", "IN_TRANSIT", "ARRIVED", "CLEARED"];
const DOCUMENT_STATUSES = ["AWAITED", "RECEIVED", "AT_BANK", "RELEASED"];

/**
 * The paper file behind an imported order (Procurement spec §10).
 *
 * Three stacked cards in the order the chain actually happens — invoice,
 * letter of credit, shipment — because a merchandiser reading this page is
 * checking one thing: does each document still match the one before it.
 * That is why the LC card shows its variance against the order as a number
 * rather than leaving it to be worked out on paper, and why a revised
 * invoice appears as a new version beside the old rather than in place of
 * it.
 */
function ImportDocumentsPanel({ file, actions }) {
  const t = useT("procurement");
  const tc = useT("common");
  const toastManager = useToastManager();
  const [dialog, setDialog] = useState(null);
  const canManage = file.can_manage;

  /** Runs a server action, toasts either way, and closes the dialog on success. */
  async function run(fn, successTitle) {
    const result = await fn();

    if (result?.status === "error") {
      toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
      return false;
    }

    toastManager.add({ type: "success", title: successTitle });
    setDialog(null);
    return true;
  }

  const live = file.proforma_invoices.filter((pi) => ["DRAFT", "CONFIRMED"].includes(pi.status));
  const openLetters = file.letters_of_credit.filter((lc) => !["CLOSED", "CANCELLED"].includes(lc.status));

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>{t("proforma_invoices")}</CardTitle>
          {canManage ? (
            <Button size="sm" variant="outline" onClick={() => setDialog({ kind: "pi" })}>
              <Plus /> {t("proforma_invoice")}
            </Button>
          ) : null}
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {file.proforma_invoices.length === 0 ? (
            <EmptyState title={t("no_proforma_invoices")} description={t("import_documents_hint")} />
          ) : (
            file.proforma_invoices.map((pi) => (
              <div key={pi.id} className="rounded-sm border border-border p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{pi.pi_no}</span>
                    <Badge variant="neutral">v{pi.version}</Badge>
                    <StatusBadge status={pi.status} label={t(`pi_statuses.${pi.status}`)} />
                  </div>
                  <span className="text-sm tabular-nums">
                    {formatNumber(pi.amount, { maximumFractionDigits: 2 })} {pi.currency}
                  </span>
                </div>

                <div className="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                  <Field label={t("pi_date")}><FormattedDateTime value={pi.pi_date} mode="date" /></Field>
                  <Field label={t("incoterm")}>{pi.incoterm ?? "—"}</Field>
                  <Field label={t("port_of_loading")}>{pi.port_of_loading ?? "—"}</Field>
                  <Field label={t("port_of_discharge")}>{pi.port_of_discharge ?? "—"}</Field>
                </div>

                {canManage && ["DRAFT", "CONFIRMED"].includes(pi.status) ? (
                  <div className="mt-3 flex flex-wrap gap-2">
                    {pi.status === "DRAFT" ? (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => run(() => actions.confirmInvoice(pi.id), t("pi_confirmed", { number: pi.pi_no }))}
                      >
                        {tc("confirm")}
                      </Button>
                    ) : null}
                    <Button size="sm" variant="outline" onClick={() => setDialog({ kind: "pi-revise", pi })}>
                      {t("revise")}
                    </Button>
                  </div>
                ) : null}
              </div>
            ))
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("letters_of_credit")}</CardTitle>
          {canManage ? (
            <Button size="sm" variant="outline" onClick={() => setDialog({ kind: "lc" })}>
              <Plus /> {t("letter_of_credit")}
            </Button>
          ) : null}
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {file.letters_of_credit.length === 0 ? (
            <EmptyState title={t("no_letters_of_credit")} description={t("lc_variance_hint")} />
          ) : (
            file.letters_of_credit.map((lc) => (
              <LetterCard
                key={lc.id}
                lc={lc}
                t={t}
                tc={tc}
                canManage={canManage}
                onOpen={() => run(() => actions.openLetter(lc.id), t("lc_opened", { number: lc.lc_no }))}
                onClose={() => run(() => actions.closeLetter(lc.id), t("lc_closed", { number: lc.lc_no }))}
                onAmend={() => setDialog({ kind: "lc-amend", lc })}
              />
            ))
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("shipments")}</CardTitle>
          {canManage ? (
            <Button size="sm" variant="outline" onClick={() => setDialog({ kind: "shipment" })}>
              <Plus /> {t("shipment")}
            </Button>
          ) : null}
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {file.shipments.length === 0 ? (
            <EmptyState title={t("shipments_empty")} description={t("eta_hint")} />
          ) : (
            file.shipments.map((shipment) => (
              <ShipmentCard
                key={shipment.id}
                shipment={shipment}
                t={t}
                tc={tc}
                canManage={canManage}
                onUpdate={() => setDialog({ kind: "shipment-update", shipment })}
                onCancel={() => setDialog({ kind: "shipment-cancel", shipment })}
              />
            ))
          )}
        </CardBody>
      </Card>

      {dialog?.kind === "pi" ? (
        <FormDialog
          title={t("proforma_invoice")}
          description={t("import_documents_hint")}
          fields={[
            { name: "pi_no", label: t("pi_no"), required: true },
            { name: "pi_date", label: t("pi_date"), type: "date" },
            { name: "amount", label: t("pi_amount"), type: "number", required: true },
            { name: "incoterm", label: t("incoterm") },
            { name: "port_of_loading", label: t("port_of_loading") },
            { name: "port_of_discharge", label: t("port_of_discharge") },
            { name: "notes", label: t("notes"), type: "textarea" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) => run(() => actions.createInvoice(values), t("pi_created", { number: values.pi_no }))}
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "pi-revise" ? (
        <FormDialog
          title={t("revise")}
          description={t("import_documents_hint")}
          initial={{ pi_no: dialog.pi.pi_no, amount: dialog.pi.amount, incoterm: dialog.pi.incoterm ?? "" }}
          fields={[
            { name: "pi_no", label: t("pi_no") },
            { name: "pi_date", label: t("pi_date"), type: "date" },
            { name: "amount", label: t("pi_amount"), type: "number" },
            { name: "incoterm", label: t("incoterm") },
            { name: "notes", label: t("notes"), type: "textarea" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () => actions.reviseInvoice(dialog.pi.id, values),
              t("pi_revised", { version: dialog.pi.version + 1 }),
            )
          }
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "lc" ? (
        <FormDialog
          title={t("letter_of_credit")}
          description={t("lc_variance_hint")}
          fields={[
            { name: "lc_no", label: t("lc_no"), required: true },
            { name: "bank_name", label: t("lc_bank"), required: true },
            { name: "branch", label: t("lc_branch") },
            { name: "amount", label: t("lc_amount"), type: "number", required: true },
            {
              name: "proforma_invoice_id",
              label: t("proforma_invoice"),
              type: "select",
              options: [
                { value: "", label: "—" },
                ...live.map((pi) => ({ value: pi.id, label: `${pi.pi_no} (v${pi.version})` })),
              ],
            },
            { name: "lc_date", label: t("lc_date"), type: "date" },
            { name: "expiry_date", label: t("lc_expiry"), type: "date" },
            { name: "last_shipment_date", label: t("lc_last_shipment"), type: "date" },
            { name: "tenor_days", label: t("lc_tenor"), type: "number" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) => run(() => actions.createLetter(values), t("lc_created", { number: values.lc_no }))}
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "lc-amend" ? (
        <FormDialog
          title={t("lc_amendments")}
          description={t("lc_amendment_needs_reason")}
          initial={{ new_amount: dialog.lc.amount, new_expiry_date: dialog.lc.expiry_date ?? "" }}
          fields={[
            { name: "new_amount", label: t("lc_new_amount"), type: "number" },
            { name: "new_expiry_date", label: t("lc_new_expiry"), type: "date" },
            { name: "bank_charge", label: t("lc_bank_charge"), type: "number" },
            { name: "reason", label: t("lc_amendment_reason"), type: "textarea", required: true },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () => actions.amendLetter(dialog.lc.id, values),
              t("lc_amended", { no: dialog.lc.amendments.length + 1, number: dialog.lc.lc_no }),
            )
          }
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "shipment" ? (
        <FormDialog
          title={t("shipment")}
          description={t("eta_hint")}
          initial={{ mode: "SEA" }}
          fields={[
            {
              name: "mode",
              label: t("shipment_mode"),
              type: "select",
              options: MODES.map((value) => ({ value, label: t(`shipment_modes.${value}`) })),
            },
            {
              name: "letter_of_credit_id",
              label: t("letter_of_credit"),
              type: "select",
              options: [{ value: "", label: "—" }, ...openLetters.map((lc) => ({ value: lc.id, label: lc.lc_no }))],
            },
            { name: "bl_awb_no", label: t("bl_awb_no") },
            { name: "container_no", label: t("container_no") },
            { name: "vessel_or_flight", label: t("vessel_or_flight") },
            { name: "shipping_date", label: t("shipping_date"), type: "date" },
            { name: "eta", label: t("eta"), type: "date" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) => run(() => actions.createShipment(values), t("shipment_created", { number: "" }))}
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "shipment-update" ? (
        <FormDialog
          title={dialog.shipment.shipment_no}
          description={t("document_status_hint")}
          initial={{
            status: dialog.shipment.status,
            document_status: dialog.shipment.document_status,
            eta: dialog.shipment.eta ?? "",
          }}
          fields={[
            {
              name: "status",
              label: t("status"),
              type: "select",
              options: SHIPMENT_STATUSES.map((value) => ({ value, label: t(`shipment_statuses.${value}`) })),
            },
            {
              name: "document_status",
              label: t("document_status"),
              type: "select",
              options: DOCUMENT_STATUSES.map((value) => ({ value, label: t(`document_statuses.${value}`) })),
            },
            { name: "eta", label: t("eta"), type: "date", helperText: t("eta_hint") },
            { name: "actual_arrival_date", label: t("actual_arrival_date"), type: "date" },
            { name: "bl_awb_no", label: t("bl_awb_no") },
            { name: "container_no", label: t("container_no") },
            { name: "notes", label: t("notes"), type: "textarea" },
          ]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () => actions.updateShipment(dialog.shipment.id, values),
              t("shipment_updated", { number: dialog.shipment.shipment_no }),
            )
          }
          submitLabel={tc("save")}
        />
      ) : null}

      {dialog?.kind === "shipment-cancel" ? (
        <FormDialog
          title={t("cancel_document")}
          description={dialog.shipment.shipment_no}
          fields={[{ name: "reason", label: t("cancel_reason"), type: "textarea" }]}
          onClose={() => setDialog(null)}
          onSubmit={(values) =>
            run(
              () => actions.cancelShipment(dialog.shipment.id, values.reason),
              t("shipment_cancelled", { number: dialog.shipment.shipment_no }),
            )
          }
          submitLabel={tc("confirm")}
        />
      ) : null}
    </div>
  );
}

function LetterCard({ lc, t, tc, canManage, onOpen, onClose, onAmend }) {
  const variance = Number(lc.variance_against_order ?? 0);

  return (
    <div className="rounded-sm border border-border p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-medium">{lc.lc_no}</span>
          <StatusBadge status={lc.status} label={t(`lc_statuses.${lc.status}`)} />
          {lc.days_to_expiry !== null && lc.days_to_expiry <= 15 && !["CLOSED", "CANCELLED"].includes(lc.status) ? (
            <Badge variant={lc.days_to_expiry < 0 ? "danger" : "warning"}>
              {t("lc_expiry")}: {lc.days_to_expiry}
            </Badge>
          ) : null}
        </div>
        <span className="text-sm tabular-nums">
          {formatNumber(lc.amount, { maximumFractionDigits: 2 })} {lc.currency}
        </span>
      </div>

      <div className="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
        <Field label={t("lc_bank")}>{lc.bank_name}</Field>
        <Field label={t("lc_date")}><FormattedDateTime value={lc.lc_date} mode="date" /></Field>
        <Field label={t("lc_expiry")}><FormattedDateTime value={lc.expiry_date} mode="date" /></Field>
        <Field label={t("lc_variance")}>
          {/* The one number the bank and the order have to agree on. */}
          <span className={variance === 0 ? "text-success" : "text-danger"}>
            {formatNumber(lc.variance_against_order, { maximumFractionDigits: 2 })}
          </span>
        </Field>
      </div>

      {lc.amendments.length > 0 ? (
        <div className="mt-3 flex flex-col gap-1 border-t border-border pt-2 text-sm">
          <span className="text-xs font-medium text-foreground-muted">{t("lc_amendments")}</span>
          {lc.amendments.map((row) => (
            <div key={row.id} className="flex flex-wrap items-baseline justify-between gap-2">
              <span>
                #{row.amendment_no} · {row.reason}
              </span>
              <span className="text-xs tabular-nums text-foreground-muted">
                {formatNumber(row.amount_change, { maximumFractionDigits: 2 })} · {t("lc_bank_charge")}{" "}
                {formatNumber(row.bank_charge, { maximumFractionDigits: 2 })}
              </span>
            </div>
          ))}
        </div>
      ) : null}

      {canManage ? (
        <div className="mt-3 flex flex-wrap gap-2">
          {lc.status === "DRAFT" ? (
            <Button size="sm" variant="outline" onClick={onOpen}>
              {t("lc_statuses.OPENED")}
            </Button>
          ) : null}
          {["OPENED", "SHIPPED"].includes(lc.status) ? (
            <>
              <Button size="sm" variant="outline" onClick={onAmend}>
                {t("lc_amendments")}
              </Button>
              <Button size="sm" variant="outline" onClick={onClose}>
                {tc("close")}
              </Button>
            </>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

function ShipmentCard({ shipment, t, tc, canManage, onUpdate, onCancel }) {
  return (
    <div className="rounded-sm border border-border p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-medium">{shipment.shipment_no}</span>
          <StatusBadge status={shipment.status} label={t(`shipment_statuses.${shipment.status}`)} />
          <Badge variant="neutral">{t(`document_statuses.${shipment.document_status}`)}</Badge>
          {shipment.overdue ? <Badge variant="danger">{t("shipment_overdue")}</Badge> : null}
        </div>
        <span className="text-sm text-foreground-muted">{t(`shipment_modes.${shipment.mode}`)}</span>
      </div>

      <div className="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
        <Field label={t("bl_awb_no")}>{shipment.bl_awb_no ?? "—"}</Field>
        <Field label={t("container_no")}>{shipment.container_no ?? "—"}</Field>
        <Field label={t("shipping_date")}><FormattedDateTime value={shipment.shipping_date} mode="date" /></Field>
        <Field label={t("eta")}>
          <span className="flex flex-col">
            <FormattedDateTime value={shipment.eta} mode="date" />
            {/* Kept beside the current one: the gap is the supplier's record. */}
            {shipment.original_eta && shipment.original_eta !== shipment.eta ? (
              <span className="text-xs text-foreground-muted line-through">
                <FormattedDateTime value={shipment.original_eta} mode="date" />
              </span>
            ) : null}
          </span>
        </Field>
        {shipment.actual_arrival_date ? (
          <Field label={t("actual_arrival_date")}>
            <span className="flex flex-col">
              <FormattedDateTime value={shipment.actual_arrival_date} mode="date" />
              {shipment.delay_days !== null ? (
                <span className="text-xs text-foreground-muted">
                  {shipment.delay_days > 0
                    ? t("shipment_delay", { days: shipment.delay_days })
                    : shipment.delay_days < 0
                      ? t("shipment_early", { days: Math.abs(shipment.delay_days) })
                      : t("shipment_on_time")}
                </span>
              ) : null}
            </span>
          </Field>
        ) : null}
        {shipment.letter_of_credit ? (
          <Field label={t("letter_of_credit")}>{shipment.letter_of_credit.lc_no}</Field>
        ) : null}
      </div>

      {canManage && !["CLEARED", "CANCELLED"].includes(shipment.status) ? (
        <div className="mt-3 flex flex-wrap gap-2">
          <Button size="sm" variant="outline" onClick={onUpdate}>
            {tc("edit")}
          </Button>
          {shipment.status !== "ARRIVED" ? (
            <Button size="sm" variant="outline" onClick={onCancel}>
              {tc("cancel")}
            </Button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

export { ImportDocumentsPanel };
