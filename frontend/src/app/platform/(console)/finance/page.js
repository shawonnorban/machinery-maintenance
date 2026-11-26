import Link from "next/link";
import { Building2, TriangleAlert, Wallet } from "lucide-react";
import { platformApiFetch } from "@/lib/platform-api-server";
import { formatCurrency } from "@/lib/format";
import { PlatformHeader, Panel, PTable, Kpi, fmtDate } from "@/components/platform/ui";
import { ExpensesCard } from "@/components/platform/finance/expenses-card";
import { storeExpense, removeExpense } from "./actions";

/** What the business took, is owed and spent. */
export default async function PlatformFinancePage() {
  const [summary, overdue, expenses] = await Promise.all([
    platformApiFetch("/finance/summary"),
    platformApiFetch("/finance/invoices/overdue", { includeMeta: true }),
    platformApiFetch("/finance/expenses?per_page=20", { includeMeta: true }),
  ]);
  const totals = Object.entries(summary.totals);
  const overdueCount = overdue.meta?.total ?? overdue.data.length;

  return (
    <>
      <PlatformHeader
        breadcrumb={[{ label: "Finance" }]}
        title="Finance"
        description="What the business took, is owed and spent — by currency, by customer, and what is overdue."
        icon={<Wallet />}
        tone="success"
      />

      {totals.map(([currency, f]) => (
        <div key={currency} className="mb-4 grid grid-cols-2 gap-3 xl:grid-cols-5">
          <Kpi label={`Invoiced (${currency})`} value={formatCurrency(f.invoiced, currency)} tone="brand" />
          <Kpi label={`Received (${currency})`} value={formatCurrency(f.received, currency)} tone="success" sub={Number(f.refunded) > 0 ? `${formatCurrency(f.refunded, currency)} refunded` : null} />
          <Kpi label={`Due (${currency})`} value={formatCurrency(f.due, currency)} tone={Number(f.due) > 0 ? "danger" : "muted"} sub={`${overdueCount} overdue invoice(s)`} />
          <Kpi label={`Spent (${currency})`} value={formatCurrency(f.spent, currency)} tone="warning" />
          <Kpi label={`Net (${currency})`} value={formatCurrency(f.net, currency)} tone={Number(f.net) >= 0 ? "success" : "danger"} sub="received − refunds − spent" />
        </div>
      ))}

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Panel title="By customer" subtitle="Invoiced and still due, customer by customer" icon={Building2}>
          <PTable
            rows={summary.customers}
            rowKey={(r) => `${r.company.id}-${r.currency}`}
            empty="Nothing billed yet."
            columns={[
              {
                key: "company",
                label: "Customer",
                render: (r) => (
                  <Link href={`/platform/tenants/${r.company.id}?tab=billing`} className="font-semibold text-brand hover:underline">
                    {r.company.name}
                  </Link>
                ),
              },
              { key: "invoiced", label: "Invoiced", align: "right", render: (r) => formatCurrency(r.invoiced, r.currency) },
              { key: "due", label: "Due", align: "right", render: (r) => (Number(r.due) > 0 ? <b className="text-danger">{formatCurrency(r.due, r.currency)}</b> : <span className="text-foreground-muted">—</span>) },
            ]}
          />
        </Panel>
        <Panel title="Overdue invoices" subtitle="Past their due date with a balance left" icon={TriangleAlert}>
          <PTable
            rows={overdue.data}
            rowKey={(inv) => inv.id}
            empty="Nothing overdue."
            columns={[
              {
                key: "invoice_number",
                label: "Invoice No",
                render: (inv) => (
                  <Link href={`/platform/tenants/${inv.company_id}?tab=billing`} className="font-mono font-semibold text-brand hover:underline">
                    {inv.invoice_number}
                  </Link>
                ),
              },
              { key: "due_date", label: "Due", render: (inv) => fmtDate(inv.due_date) },
              { key: "balance_due", label: "Balance", align: "right", render: (inv) => <b className="text-danger">{formatCurrency(inv.balance_due, inv.currency)}</b> },
            ]}
          />
        </Panel>
      </div>

      <div className="mt-4">
        <ExpensesCard expenses={expenses.data} meta={expenses.meta} storeAction={storeExpense} removeAction={removeExpense} />
      </div>
    </>
  );
}
