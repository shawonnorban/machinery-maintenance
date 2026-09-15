import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { Alert } from "@/components/ui/alert";
import { RecordPaymentForm } from "@/components/billing/record-payment-form";
import { recordPayment } from "./actions";

const OPEN_STATUSES = ["ISSUED", "PARTIALLY_PAID", "OVERDUE"];

/** Mirrors `BillingController::show`/`invoice.blade.php` — lines, payments, credit notes, and (gated on `billing.payment.manage`) a record-payment form while the invoice is still open. */
export default async function InvoiceDetailPage({ params }) {
  const { invoiceId } = await params;

  const [invoice, permissions] = await Promise.all([
    apiFetch(`/subscription/invoices/${invoiceId}`),
    apiFetch("/auth/permissions"),
  ]);

  const canRecordPayment = permissions.permissions.includes("billing.payment.manage");
  const isOpen = OPEN_STATUSES.includes(invoice.status);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Billing", href: "/billing" }, { label: invoice.invoice_number }]}
        title={invoice.invoice_number}
        description={`Issued ${invoice.issue_date}`}
        actions={
          <Link href="/billing" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> Back
          </Link>
        }
      />

      {invoice.status === "VOID" ? (
        <Alert variant="warning" className="mb-6" title="This invoice was voided.">
          {invoice.void_reason}
        </Alert>
      ) : null}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div className="flex flex-col gap-6 lg:col-span-8">
          <Card>
            <CardHeader>
              <CardTitle>Lines</CardTitle>
            </CardHeader>
            <CardBody className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-xs font-medium text-foreground-muted">
                    <tr>
                      <th className="px-5 py-2.5 text-left">Description</th>
                      <th className="px-5 py-2.5 text-right">Quantity</th>
                      <th className="px-5 py-2.5 text-right">Unit price</th>
                      <th className="px-5 py-2.5 text-right">Amount</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {invoice.lines.map((line) => (
                      <tr key={line.id}>
                        <td className="px-5 py-3">
                          {line.description}
                          {line.period_start ? (
                            <div className="text-xs text-foreground-muted">
                              {line.period_start} — {line.period_end}
                            </div>
                          ) : null}
                        </td>
                        <td className="px-5 py-3 text-right tabular">{Number(line.quantity)}</td>
                        <td className="px-5 py-3 text-right tabular">{Number(line.unit_price).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                        <td className="px-5 py-3 text-right tabular">{Number(line.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot className="divide-y divide-border border-t border-border">
                    <FooterRow label="Subtotal" value={invoice.subtotal} />
                    <FooterRow label="Tax" value={invoice.tax} />
                    <FooterRow label="Total" value={invoice.total} suffix={invoice.currency} strong />
                    <FooterRow label="Paid" value={invoice.paid_amount} />
                    <FooterRow label="Balance due" value={invoice.balance_due} strong danger={Number(invoice.balance_due) > 0} />
                  </tfoot>
                </table>
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Payments</CardTitle>
            </CardHeader>
            <CardBody>
              {invoice.payments.length === 0 ? (
                <p className="text-sm text-foreground-muted">No payments recorded yet.</p>
              ) : (
                <div className="flex flex-col gap-3">
                  {invoice.payments.map((payment) => (
                    <div
                      key={payment.id}
                      className={cn(
                        "flex items-center justify-between border-b border-border pb-3 text-sm last:border-b-0 last:pb-0",
                        payment.status === "REVERSED" && "text-foreground-muted",
                      )}
                    >
                      <div>
                        <span className="text-foreground">{payment.payment_reference ?? "—"}</span>
                        <div className="text-xs text-foreground-muted">{payment.method}</div>
                      </div>
                      <div className="text-right">
                        <span className="tabular font-medium">{Number(payment.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        {payment.status === "REVERSED" ? <div className="text-xs text-danger">Reversed</div> : null}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardBody>
          </Card>

          {invoice.credit_notes.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>Credit notes</CardTitle>
              </CardHeader>
              <CardBody>
                <div className="flex flex-col gap-3">
                  {invoice.credit_notes.map((note) => (
                    <div key={note.id} className="flex items-center justify-between border-b border-border pb-3 text-sm last:border-b-0 last:pb-0">
                      <div>
                        <span className="text-foreground">{note.credit_note_number}</span>
                        <div className="text-xs text-foreground-muted">{note.reason}</div>
                      </div>
                      <span className="tabular font-medium">{Number(note.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          ) : null}
        </div>

        <div className="flex flex-col gap-6 lg:col-span-4">
          {canRecordPayment && isOpen ? (
            <RecordPaymentForm balanceDue={invoice.balance_due} action={recordPayment.bind(null, invoiceId)} />
          ) : null}

          <Card>
            <CardBody>
              <dl className="flex flex-col gap-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-foreground-muted">Status</dt>
                  <dd>
                    <StatusBadge status={invoice.status} />
                  </dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-foreground-muted">Due date</dt>
                  <dd className="text-foreground">{invoice.due_date}</dd>
                </div>
              </dl>
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}

function FooterRow({ label, value, suffix, strong, danger }) {
  return (
    <tr>
      <th colSpan={3} className={cn("px-5 py-2 text-right font-normal text-foreground-muted", strong && "font-semibold text-foreground")}>
        {label}
      </th>
      <td className={cn("px-5 py-2 text-right tabular", strong && "font-semibold", danger && "text-danger")}>
        {Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 })}
        {suffix ? ` ${suffix}` : ""}
      </td>
    </tr>
  );
}
