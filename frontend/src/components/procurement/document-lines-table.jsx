"use client";

import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { formatNumber } from "@/lib/format";
import { cn } from "@/lib/utils";

/**
 * A document's lines, read-only (detail pages). The caller names the
 * columns; this only lays them out with numerics right-aligned and an
 * optional totals row, the way every lines table in the spec is drawn.
 *
 * @param {{ title: string, lines: any[], columns: { key: string, header: string, render?: (line) => React.ReactNode, align?: "left"|"right", total?: string }[] }} props
 */
function DocumentLinesTable({ title, lines, columns, footer }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardBody className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-foreground-muted">
                {columns.map((column) => (
                  <th key={column.key} className={cn("px-5 pt-4 pb-2", column.align === "right" && "text-right")}>
                    {column.header}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((line, index) => (
                <tr key={line.id ?? index} className="border-t border-border">
                  {columns.map((column) => (
                    <td key={column.key} className={cn("px-5 py-2 align-top", column.align === "right" && "text-right tabular-nums")}>
                      {column.render ? column.render(line) : line[column.key] ?? "—"}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
            {footer ? (
              <tfoot>
                <tr className="border-t border-border font-semibold">
                  {columns.map((column) => (
                    <td key={column.key} className={cn("px-5 py-2", column.align === "right" && "text-right tabular-nums")}>
                      {footer[column.key] ?? ""}
                    </td>
                  ))}
                </tr>
              </tfoot>
            ) : null}
          </table>
        </div>
      </CardBody>
    </Card>
  );
}

/**
 * The two-line item cell every lines table starts with: the name is what
 * people know the item by, the code is the library's internal key.
 */
function ItemCell({ line }) {
  return (
    <>
      <span className="font-medium">{line.item_name}</span>
      <div className="font-mono text-xs text-foreground-muted">{line.item_code}</div>
    </>
  );
}

function qty(value, unit) {
  if (value === null || value === undefined) return "—";
  return `${formatNumber(value)}${unit ? ` ${unit}` : ""}`;
}

export { DocumentLinesTable, ItemCell, qty };
