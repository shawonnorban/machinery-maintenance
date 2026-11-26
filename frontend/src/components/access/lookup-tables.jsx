"use client";

import Link from "next/link";
import { ListChecks } from "lucide-react";
import { CompanyGrid } from "@/components/company/company-grid";
import { Card, CardBody } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

/**
 * Lookup Table Master (AnnotechERP 02/01): Brickland's two panes — the tables,
 * and the values of the chosen one (`?tab=tables&table=<code>`). Shared tables
 * stay locked, but every value can be changed or deleted: the first change to a
 * shared list gives the company its own copy of it (shown as "Company copy").
 */
function LookupTables({ tables, selected, values, tableSchema, valueSchema, actions }) {
  const t = useT("access");

  return (
    <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">
      <CompanyGrid
        ns="access"
        tabKey="lookup-table"
        typeKey="lookups"
        rows={tables}
        schema={tableSchema}
        referenceOptions={{}}
        actions={actions}
        readOnlyField="is_system"
        rowActions={(row) => (
          <span className="inline-flex items-center gap-2">
            {row.is_system ? <Badge variant="neutral">{t("system")}</Badge> : null}
            {row.customised ? <Badge variant="info">{t("company_copy")}</Badge> : null}
            <Link
              href={`/access-control/lookup-table?tab=tables&table=${encodeURIComponent(row.code)}`}
              scroll={false}
              className={cn(buttonVariants({ variant: row.code === selected?.code ? "primary" : "outline", size: "sm" }))}
            >
              <ListChecks /> {t("lookup_values")} ({row.values_count})
            </Link>
          </span>
        )}
      />

      {selected ? (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">
            {selected.name} <span className="font-mono text-xs text-foreground-muted">{selected.code}</span>
          </p>
          <p className="px-1 text-xs text-foreground-muted">{t("lookup_copy_hint")}</p>
          <CompanyGrid
            key={selected.id}
            ns="access"
            tabKey="lookup-value"
            typeKey={`values/${selected.id}`}
            rows={values}
            schema={valueSchema}
            referenceOptions={{}}
            actions={actions}
          />
        </div>
      ) : (
        <Card>
          <CardBody className="py-10 text-center text-sm text-foreground-muted">{t("choose_lookup")}</CardBody>
        </Card>
      )}
    </div>
  );
}

export { LookupTables };
