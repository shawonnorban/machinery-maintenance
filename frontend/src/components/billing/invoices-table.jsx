"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";

/** Mirrors `billing/index.blade.php`'s own invoices card — no search/filter, the same as the web page, just with real pagination in place of its hard `limit(24)`. */
function InvoicesTable({ invoices, meta }) {
  const router = useRouter();

  return (
    <DataTable
      columns={[
        {
          key: "invoice_number",
          header: "Invoice",
          render: (invoice) => (
            <Link href={`/billing/invoices/${invoice.id}`} className="font-medium text-brand hover:underline">
              {invoice.invoice_number}
            </Link>
          ),
        },
        { key: "due_date", header: "Due date" },
        { key: "total", header: "Total", align: "right", render: (invoice) => Number(invoice.total).toLocaleString(undefined, { minimumFractionDigits: 2 }) },
        {
          key: "balance_due",
          header: "Balance due",
          align: "right",
          render: (invoice) => (
            <span className={Number(invoice.balance_due) > 0 ? "text-danger" : undefined}>
              {Number(invoice.balance_due).toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </span>
          ),
        },
        { key: "status", header: "Status", render: (invoice) => <StatusBadge status={invoice.status} /> },
      ]}
      rows={invoices}
      rowKey={(invoice) => invoice.id}
      emptyTitle="No invoices yet."
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={(nextPage) => router.push(`/billing?page=${nextPage}`)}
    />
  );
}

export { InvoicesTable };
