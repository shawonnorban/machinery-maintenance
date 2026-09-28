import Link from "next/link";
import { platformApiFetch } from "@/lib/platform-api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatCard } from "@/components/ui/stat-card";
import { Wallet, TriangleAlert } from "lucide-react";
import { formatCurrency } from "@/lib/format";
import { ExpensesCard } from "@/components/platform/finance/expenses-card";
import { storeExpense, removeExpense } from "./actions";

/** What the business took, is owed, and spent (mirrors `desk/finance.blade.php`). */
export default async function PlatformFinancePage() {
  const [summary, overdue, expenses] = await Promise.all([
    platformApiFetch("/finance/summary"),
    platformApiFetch("/finance/invoices/overdue", { includeMeta: true }),
    platformApiFetch("/finance/expenses?per_page=20", { includeMeta: true }),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Finance" }]} title="Finance" description="What the business took, is owed, and spent." />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Object.entries(summary.totals).map(([currency, figures]) => (
          <StatCard
            key={currency}
            label={`Net (${currency})`}
            value={formatCurrency(figures.net, currency)}
            icon={<Wallet />}
            tone={Number(figures.net) >= 0 ? "success" : "danger"}
            supportingText={`Invoiced ${formatCurrency(figures.invoiced, currency)} · Due ${formatCurrency(figures.due, currency)}`}
          />
        ))}
      </div>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>By customer</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col divide-y divide-border p-0">
            {summary.customers.length === 0 ? (
              <p className="p-5 text-sm text-foreground-muted">Nothing billed yet.</p>
            ) : (
              summary.customers.map((row) => (
                <div key={row.company.id} className="flex items-center justify-between gap-3 p-4">
                  <Link href={`/platform/tenants/${row.company.id}`} className="text-sm font-medium text-brand hover:underline">
                    {row.company.name}
                  </Link>
                  <div className="text-right text-xs text-foreground-muted">
                    <p>{formatCurrency(row.invoiced, row.currency)} invoiced</p>
                    {Number(row.due) > 0 ? <p className="text-danger">{formatCurrency(row.due, row.currency)} due</p> : null}
                  </div>
                </div>
              ))
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TriangleAlert className="size-4 text-danger" /> Overdue invoices
            </CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col divide-y divide-border p-0">
            {overdue.data.length === 0 ? (
              <p className="p-5 text-sm text-foreground-muted">Nothing overdue.</p>
            ) : (
              overdue.data.map((invoice) => (
                <div key={invoice.id} className="flex items-center justify-between gap-3 p-4">
                  <Link href={`/platform/tenants/${invoice.company_id}?tab=billing`} className="text-sm font-medium text-brand hover:underline">
                    {invoice.invoice_number}
                  </Link>
                  <span className="text-xs text-danger">
                    {formatCurrency(invoice.balance_due, invoice.currency)} · due {invoice.due_date}
                  </span>
                </div>
              ))
            )}
          </CardBody>
        </Card>
      </div>

      <div className="mt-5">
        <ExpensesCard expenses={expenses.data} meta={expenses.meta} storeAction={storeExpense} removeAction={removeExpense} />
      </div>
    </>
  );
}
