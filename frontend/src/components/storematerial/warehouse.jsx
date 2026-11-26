"use client";

import { useState, useTransition } from "react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { card, dash, day, qty, StoreFilters } from "./common";

/*
 * Store & Material's stock views (AnnotechERP 09/01–09/02), as Brickland
 * draws them: the Fabric Warehouse (Stock List, Roll Wise, Color Wise, Dye
 * Lot Wise, Movement), the Trims & Accessories Warehouse and the Stock
 * Ledger with its running balance. Filters and views switch in place.
 */

const right = [
  "gsm",
  "recv",
  "out",
  "returned",
  "available",
  "rolls",
  "in",
  "balance",
];

function Grid({ cols, rows, empty, cell }) {
  const t = useT("storematerial");
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[760px] text-sm">
        <thead className="bg-brand text-brand-foreground">
          <tr>
            {cols.map((k) => (
              <th
                key={k}
                className={cn(
                  "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                  right.includes(k) && "text-right",
                )}
              >
                {t(`wh.col.${k}`)}
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
          {rows.map((r, i) => (
            <tr key={i} className="border-t border-border">
              {cols.map((k) => (
                <td
                  key={k}
                  className={cn(
                    "px-3 py-2.5",
                    right.includes(k) && "text-right tabular-nums",
                    ["material", "item", "reference"].includes(k) &&
                      "font-medium",
                    k === "available" && "font-semibold",
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
  );
}

const common = (r, k, t) =>
  ({
    material: r.material,
    item: r.item,
    color: dash(r.color),
    category: dash(r.category),
    size: dash(r.size),
    gsm: dash(r.gsm),
    width: dash(r.width),
    unit: r.unit,
    recv: qty(r.recv),
    out: qty(r.out),
    returned: qty(r.returned),
    available: `${qty(r.available)}`,
    rolls: qty(r.rolls),
    roll_no: dash(r.roll_no),
    dye_lot: dash(r.dye_lot),
    buyer_style: [r.buyer, r.style].filter(Boolean).join(" / ") || "—",
    date: day(r.date),
    reference: r.reference,
    type: r.type ? t(`type.${r.type}`) : "—",
    in: qty(r.in),
    balance: `${qty(r.balance)} ${r.unit ?? ""}`,
  })[k];

/** Fabric Warehouse. */
function FabricWarehouse({ data, filters, act }) {
  const t = useT("storematerial");
  const [view, setView] = useState("stock");
  const [f, setF] = useState({});
  const [rows, setRows] = useState(data.rows);
  const [pending, startTransition] = useTransition();
  const load = (v, nf) =>
    startTransition(async () => {
      const result = await act("fabric", { ...nf, view: v });
      if (result?.status === "success") setRows(result.data.rows);
    });
  const cols = {
    stock: [
      "roll_no",
      "material",
      "color",
      "dye_lot",
      "gsm",
      "width",
      "unit",
      "buyer_style",
      "recv",
      "out",
      "available",
    ],
    roll: [
      "roll_no",
      "material",
      "color",
      "dye_lot",
      "gsm",
      "width",
      "unit",
      "recv",
      "out",
      "available",
    ],
    color: ["material", "color", "rolls", "unit", "recv", "out", "available"],
    lot: ["material", "dye_lot", "rolls", "unit", "recv", "out", "available"],
    movement: [
      "date",
      "reference",
      "type",
      "material",
      "in",
      "out",
      "returned",
      "balance",
    ],
  }[view];
  const empty =
    {
      roll: t("wh.no_rolls"),
      lot: t("wh.no_lots"),
      movement: t("wh.no_movement"),
    }[view] ?? t("wh.no_stock");

  return (
    <div className="flex flex-col gap-4">
      <StoreFilters
        fields={["buyer", "style", "material"]}
        options={{
          ...filters,
          materials: filters.materials.filter((m) => m.kind === "FABRIC"),
        }}
        pending={pending}
        onSearch={(nf) => {
          setF(nf);
          load(view, nf);
        }}
      />
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-center justify-between gap-3 p-5">
          <h2 className="text-base font-semibold text-foreground">
            {t("wh.fabric_title")}
          </h2>
          <div
            role="tablist"
            className="flex flex-wrap gap-1 rounded-lg border border-border p-1"
          >
            {["stock", "roll", "color", "lot", "movement"].map((k) => (
              <button
                key={k}
                type="button"
                role="tab"
                aria-selected={view === k}
                onClick={() => {
                  setView(k);
                  load(k, f);
                }}
                className={cn(
                  "rounded-md px-3 py-1 text-[13px] font-medium",
                  view === k
                    ? "bg-brand text-brand-foreground"
                    : "text-foreground hover:bg-surface-muted",
                )}
              >
                {t(`wh.view.${k}`)}
              </button>
            ))}
          </div>
        </div>
        <Grid
          cols={cols}
          rows={rows}
          empty={empty}
          cell={(r, k) => common(r, k, t)}
        />
      </section>
    </div>
  );
}

/** Trims & Accessories Warehouse. */
function TrimsWarehouse({ data, filters, act }) {
  const t = useT("storematerial");
  const [rows, setRows] = useState(data.rows);
  const [pending, startTransition] = useTransition();
  return (
    <div className="flex flex-col gap-4">
      <StoreFilters
        fields={["category", "material"]}
        options={{
          ...filters,
          materials: filters.materials.filter((m) => m.kind === "TRIMS"),
        }}
        pending={pending}
        onSearch={(nf) =>
          startTransition(async () => {
            const result = await act("trims", nf);
            if (result?.status === "success") setRows(result.data.rows);
          })
        }
      />
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <h2 className="p-5 text-base font-semibold text-foreground">
          {t("wh.trims_title")}
        </h2>
        <Grid
          cols={[
            "item",
            "category",
            "color",
            "size",
            "unit",
            "buyer_style",
            "recv",
            "out",
            "returned",
            "available",
          ]}
          rows={rows}
          empty={t("wh.no_trims")}
          cell={(r, k) => common(r, k, t)}
        />
      </section>
    </div>
  );
}

/** Stock Ledger. */
function StockLedger({ data, filters, act }) {
  const t = useT("storematerial");
  const [state, setState] = useState(data);
  const [pending, startTransition] = useTransition();
  return (
    <div className="flex flex-col gap-4">
      <StoreFilters
        fields={["material", "from", "to"]}
        options={filters}
        pending={pending}
        onSearch={(nf) =>
          startTransition(async () => {
            const result = await act("ledger", nf);
            if (result?.status === "success") setState(result.data);
          })
        }
      />
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-baseline justify-between gap-3 p-5">
          <h2 className="text-base font-semibold text-foreground">
            {t("ledger.title")}
          </h2>
          {state.opening ? (
            <p className="text-[13px] text-foreground-muted">
              {t("ledger.opening")}:{" "}
              <span className="font-semibold text-foreground tabular-nums">
                {qty(state.opening)}
              </span>
            </p>
          ) : null}
        </div>
        <Grid
          cols={[
            "date",
            "reference",
            "type",
            "in",
            "out",
            "returned",
            "balance",
          ]}
          rows={state.rows}
          empty={t("ledger.no_rows")}
          cell={(r, k) => (k === "balance" ? qty(r.balance) : common(r, k, t))}
        />
      </section>
    </div>
  );
}

export { FabricWarehouse, TrimsWarehouse, StockLedger };
