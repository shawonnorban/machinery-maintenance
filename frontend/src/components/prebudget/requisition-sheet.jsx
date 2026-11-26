"use client";

import { useMemo } from "react";
import { OrderSheet } from "@/components/ordersummary/sheet";
import { useT } from "@/lib/i18n";
import { OrderPicker } from "./order-picker";

/*
 * Pre-Budget → All / Add Requisition (AnnotechERP 33/34): Order Summary's
 * sheet on the requisition rows. Knit and Woven share the sheet; a column of
 * the other Entry Type stays empty and closed on a row (a Woven row has no
 * Yarn Count). Add Requisition keeps itself as a draft the Norban way —
 * saved every two minutes while there are changes, on Save Draft and as the
 * page closes, opened again from Draft Requisition — and Complete saves the
 * requisitions and drops the draft.
 */

const TYPE = { Knit: "KNIT", Woven: "WOVEN" };
/** A style's quantity and price: on its first row only (Order Qty and FOB Value follow from them). */
const QTY_KEYS = ["quantity_ticket", "pac", "fob_price"];

/** The columns View All starts with: Norban's own default view (plus Entry Type, the sheet holding both kinds). */
const DEFAULT_COLUMNS = [
  "serial_no", "entry_type", "buyer", "delivery_date", "style", "artwork", "order_color", "fabric_type", "fabrication", "parts", "yarn_type", "yarn_count",
  "composition", "order_qty", "fob_price", "fob_value", "cutting_percent", "cad_consumption", "process_loss_percent", "cad_with_loss", "feeding_percent", "total_req",
];
const applies = (column, row) => !column.applies_to || !TYPE[row.values?.entry_type] || column.applies_to === TYPE[row.values.entry_type];

function RequisitionSheet({ mode, meta, data, queryString, act, draft = null, orders = null }) {
  const t = useT("prebudget");
  const config = useMemo(
    () => ({
      // ".v2": a view saved before the default columns were set starts again from them.
      viewKey: "prebudget.v2",
      listPath: "/pre-budget/all-requisition",
      artworkUrl: "/api/pre-budget/artwork",
      titleKeys: ["style", "buyer"],
      defaultColumns: DEFAULT_COLUMNS,
      rowHeight: 50,
      // Job No in the company's form (26NCL00001): the year and the row's Factory fill themselves.
      jobNo: { column: "job_no", factory: "factory", url: "/api/job-numbers?module=pb" },
      applies,
      saveModes:
        mode === "new"
          ? [
              { status: "DRAFT", draft: true, label: t("req.save_draft") },
              { status: "COMPLETE", label: t("req.complete"), icon: "check", done: (n) => t("req.completed", { n }) },
            ]
          : null,
      // All Requisition: the ticked requisitions (one buyer) straight into a new budget.
      excelUrl: null, // Order Summary's book-layout Excel is not the requisition's.
      printUrl: null, // the requisition has its own Print (Norban's layout), below.
      // Norban's Print: the filtered rows (only the ticked ones when some are), the shown columns.
      print:
        mode === "list"
          ? {
              label: t("print.btn.print"),
              title: t("print.title.requisitions"),
              href: (queryString, keys, ids) => {
                const q = new URLSearchParams(queryString);
                q.delete("page");
                q.delete("per_page");
                q.set("cols", keys.join(","));
                if (ids.length) q.set("selected", ids.join(","));
                return `/print/pre-budget/requisitions?${q.toString()}`;
              },
            }
          : null,
      listActions: mode === "list" && meta.can.create ? [{ action: "createBudget", label: t("budget.create"), done: (d) => t("budget.created", { no: d.budget_no }), redirect: (d) => `/pre-budget/create-budget?budget=${encodeURIComponent(d.budget_no)}` }] : null,
      // A row made from an order (Order Summary): the cells the order fills stay as the order has them.
      lockedKeys: meta.order_keys ?? [],
      // A style's Quantity (Ticket), PAC and FOB Price sit on its first row: a copied row (another part) does not take them.
      copySkip: QTY_KEYS,
      lockedTitle: (c, row) => (row.order?.ssl ?? row.order_ssl ? `${t("order.locked", { column: c.label })} · ${t("order.from", { ssl: row.order?.ssl ?? row.order_ssl })}` : t("order.locked", { column: c.label })),
      orderMark: (order) => t("order.from", { ssl: order.ssl }),
      newToolbar: mode === "new" && orders ? ({ addPicked, rows }) => <OrderPicker orders={orders} rows={rows} act={act} addPicked={addPicked} skipAgain={QTY_KEYS} /> : null,
      draft:
        mode === "new"
          ? {
              no: draft?.draft_no ?? null,
              rows: draft?.rows ?? null,
              savedAt: draft?.saved_at ?? null,
              url: "/api/pre-budget/drafts",
              words: {
                saving: t("draft.saving"),
                saved: (no, at) => t("draft.saved", { no, at }),
                saved_toast: (no) => t("draft.saved_toast", { no }),
                failed: t("draft.failed"),
                gone: t("draft.gone"),
                nothing: t("draft.nothing"),
                hint: t("draft.hint"),
              },
            }
          : null,
    }),
    [mode, draft, t, meta.can.create, meta.order_keys, orders, act],
  );

  return <OrderSheet mode={mode} meta={meta} data={data} queryString={queryString} act={act} config={config} />;
}

export { RequisitionSheet };
