import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { TicketsTable } from "@/components/support/tickets-table";
import { getT } from "@/lib/i18n-server";
import { deleteTicket } from "./actions";

/** Mirrors `SupportTicketController::index` — no permission gate beyond being signed in to the company. */
export default async function SupportTicketsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [tickets, t] = await Promise.all([
    apiFetch(`/support/tickets?page=${page}`, { includeMeta: true }),
    getT("support"),
  ]);
  const { current_page: currentPage, last_page: lastPage, total, per_page: perPage } = tickets.meta;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("support") }, { label: t("tickets") }]}
        title={t("page_title")}
        description={t("page_description")}
        actions={
          <Link href="/support/tickets/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_ticket")}
          </Link>
        }
      />

      <TicketsTable tickets={tickets.data} deleteTicket={deleteTicket} />

      {total > perPage ? (
        <div className="mt-3 flex items-center justify-between gap-2 text-xs text-foreground-muted">
          <span>{t("page_summary", { current: currentPage, last: lastPage, total })}</span>
          <div className="flex gap-2">
            <Link
              href={`/support/tickets?page=${currentPage - 1}`}
              aria-disabled={currentPage <= 1}
              className={cn(buttonVariants({ variant: "outline", size: "sm" }), currentPage <= 1 && "pointer-events-none opacity-50")}
            >
              {t("previous")}
            </Link>
            <Link
              href={`/support/tickets?page=${currentPage + 1}`}
              aria-disabled={currentPage >= lastPage}
              className={cn(buttonVariants({ variant: "outline", size: "sm" }), currentPage >= lastPage && "pointer-events-none opacity-50")}
            >
              {t("next")}
            </Link>
          </div>
        </div>
      ) : null}
    </>
  );
}
