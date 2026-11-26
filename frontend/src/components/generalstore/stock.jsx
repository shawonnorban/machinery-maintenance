"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Download, Printer, RotateCcw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useT } from "@/lib/i18n";
import {
  card,
  day,
  num,
  money,
  dash,
  Field,
  Pill,
  Tile,
  printWindow,
} from "./common";

/*
 * General Store → Current Stock and Stock Ledger (AnnotechERP 10/03), as
 * Brickland draws them. Current Stock: the filters, six tiles, and every
 * item's quantity, price, value, minimum, stock by store, rack and status —
 * an item's name opens its ledger. Stock Ledger: one item's opening, every
 * movement in its own column with the balance after it, and the closing
 * balance. Filters apply in place.
 */

const filled = (f) =>
  Object.fromEntries(Object.entries(f).filter(([, x]) => x));
const query = (f) =>
  Object.keys(f).length ? `?${new URLSearchParams(f)}` : "";

function FilterBar({ children, onSubmit, onReset, pending, v }) {
  return (
    <form
      className={cn(
        card,
        "grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]",
      )}
      onSubmit={(ev) => {
        ev.preventDefault();
        onSubmit();
      }}
    >
      {children}
      <div className="flex items-end gap-2">
        <Button type="submit" loading={pending}>
          <Search className="size-4" aria-hidden />
          {v("btn.search")}
        </Button>
        <Button type="button" variant="outline" onClick={onReset}>
          <RotateCcw className="size-4" aria-hidden />
          {v("btn.reset")}
        </Button>
      </div>
    </form>
  );
}

/**
 * Current Stock.
 *
 * @param {{ data: { tiles: object, rows: object[] }, options: object, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function CurrentStock({ data, options, act }) {
  const t = useT("store");
  const v = (k, p) => t(`inv.${k}`, p);
  const blank = { q: "", store: "", category: "", status: "" };
  const [f, setF] = useState(blank);
  const [shown, setShown] = useState({});
  const [result, setResult] = useState(data);
  const [pending, startTransition] = useTransition();
  const set = (k) => (x) => setF((all) => ({ ...all, [k]: x ?? "" }));
  const load = (nf) =>
    startTransition(async () => {
      const res = await act("currentStock", filled(nf));
      if (res?.status === "success") {
        setResult(res.data);
        setShown(nf);
      }
    });
  const cols = [
    "item_code",
    "item",
    "category",
    "unit",
    "qty",
    "price",
    "value",
    "min",
    "by_store",
    "rack",
    "status",
  ];
  const right = ["qty", "price", "value", "min"];
  const byStore = (r) =>
    r.by_store.map((s) => `${s.store}: ${num(s.qty)}`).join(", ") || "—";
  const tiles = result.tiles;

  return (
    <div className="flex flex-col gap-4">
      <FilterBar
        v={v}
        pending={pending}
        onSubmit={() => load(f)}
        onReset={() => {
          setF(blank);
          load(blank);
        }}
      >
        <Field label={v("filter.search")}>
          <Input
            value={f.q}
            onChange={(ev) => set("q")(ev.target.value)}
            placeholder={v("filter.search_ph")}
            aria-label={v("filter.search")}
          />
        </Field>
        <Field label={v("filter.store")}>
          <Select
            value={f.store}
            onValueChange={set("store")}
            options={[
              { value: "", label: v("filter.all_stores") },
              ...options.store_filter,
            ]}
            placeholder={v("filter.all_stores")}
            aria-label={v("filter.store")}
          />
        </Field>
        <Field label={v("stock.category")}>
          <Select
            value={f.category}
            onValueChange={set("category")}
            options={[
              { value: "", label: v("line.all_categories") },
              ...options.categories,
            ]}
            placeholder={v("line.all_categories")}
            aria-label={v("stock.category")}
          />
        </Field>
        <Field label={v("stock.stock_status")}>
          <Select
            value={f.status}
            onValueChange={set("status")}
            options={[
              { value: "", label: v("filter.any_status") },
              ...["IN_STOCK", "LOW", "OUT"].map((s) => ({
                value: s,
                label: v(`stock_status.${s}`),
              })),
            ]}
            placeholder={v("filter.any_status")}
            aria-label={v("stock.stock_status")}
          />
        </Field>
      </FilterBar>

      <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <Tile label={v("stock.tiles.items")} value={num(tiles.items)} />
        <Tile label={v("stock.tiles.qty")} value={num(tiles.qty)} />
        <Tile label={v("stock.tiles.value")} value={money(tiles.value)} />
        <Tile
          label={v("stock.tiles.priced")}
          value={`${num(tiles.priced)} / ${num(tiles.items)}`}
        />
        <Tile
          label={v("stock.tiles.low")}
          value={num(tiles.low)}
          tone="warning"
        />
        <Tile
          label={v("stock.tiles.out")}
          value={num(tiles.out)}
          tone="danger"
        />
      </div>

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {v("stock.title", { n: result.rows.length })}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {v("stock.hint")}
            </p>
          </div>
          <div className="flex gap-2">
            <a
              href={`/api/general-store/excel/current-stock${query(filled(shown))}`}
              className={buttonVariants({ variant: "outline", size: "sm" })}
            >
              <Download className="size-4" aria-hidden />
              {v("btn.excel")}
            </a>
            <Button
              size="sm"
              variant="outline"
              onClick={() =>
                printWindow({
                  title: v("stock.title", { n: result.rows.length }),
                  signatures: false,
                  columns: cols.map((k) => ({
                    label: v(`stock.col.${k}`),
                    right: right.includes(k),
                    nowrap: k === "item_code",
                  })),
                  rows: result.rows.map((r) => [
                    r.item_code,
                    r.item,
                    dash(r.category),
                    dash(r.unit),
                    num(r.qty),
                    num(r.price),
                    num(r.value),
                    num(r.min),
                    byStore(r),
                    dash(r.rack),
                    {
                      v: v(`stock_status.${r.status}`),
                      tone: { IN_STOCK: "ok", LOW: "warn", OUT: "bad" }[
                        r.status
                      ],
                    },
                  ]),
                })
              }
            >
              <Printer className="size-4" aria-hidden />
              {v("stock.print")}
            </Button>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1000px] text-sm">
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
                    {v(`stock.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {result.rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={cols.length}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {v("list.empty")}
                  </td>
                </tr>
              ) : null}
              {result.rows.map((r) => (
                <tr key={r.item_code} className="border-t border-border">
                  <td className="px-3 py-2.5 whitespace-nowrap">
                    {r.item_code}
                  </td>
                  <td className="px-3 py-2.5">
                    <Link
                      href={`/general-store/gs-ledger?item=${encodeURIComponent(r.item_code)}`}
                      className="font-semibold text-foreground hover:text-brand hover:underline"
                    >
                      {r.item}
                    </Link>
                  </td>
                  <td className="px-3 py-2.5">{dash(r.category)}</td>
                  <td className="px-3 py-2.5">{dash(r.unit)}</td>
                  <td
                    className={cn(
                      "px-3 py-2.5 text-right font-semibold tabular-nums",
                      r.status === "OUT" && "text-danger",
                      r.status === "LOW" && "text-warning",
                    )}
                  >
                    {num(r.qty)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(r.price)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(r.value)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(r.min)}
                  </td>
                  <td className="px-3 py-2.5">{byStore(r)}</td>
                  <td className="px-3 py-2.5">{dash(r.rack)}</td>
                  <td className="px-3 py-2.5">
                    <Pill tone={r.status}>{v(`stock_status.${r.status}`)}</Pill>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

const MOVES = ["receive", "issue", "return", "adjustment", "transfer"];

/**
 * Stock Ledger.
 *
 * @param {{ data: object, options: object, initialItem?: string, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function StockLedger({ data, options, initialItem = "", act }) {
  const t = useT("store");
  const v = (k, p) => t(`inv.${k}`, p);
  const start = options.items.find(
    (i) => i.code === initialItem || i.value === initialItem,
  );
  const blank = { from: "", to: "", store: "", item: "" };
  const [f, setF] = useState({ ...blank, item: start?.value ?? "" });
  const [shown, setShown] = useState(start ? { item: start.value } : {});
  const [result, setResult] = useState(data);
  const [pending, startTransition] = useTransition();
  const set = (k) => (x) => setF((all) => ({ ...all, [k]: x ?? "" }));
  const load = (nf) =>
    startTransition(async () => {
      const res = await act("ledger", filled(nf));
      if (res?.status === "success") {
        setResult(res.data);
        setShown(filled(nf));
      }
    });
  const item = result.item;
  const cols = [
    "date",
    "type",
    "doc_no",
    "store",
    "party",
    ...MOVES,
    "balance",
    "user",
  ];
  const right = [...MOVES, "balance"];
  const typeOf = (r) =>
    r.reversal
      ? v("ledger.reversal", { type: v(`badge.${r.type}`) })
      : v(`badge.${r.type}`);

  return (
    <div className="flex flex-col gap-4">
      <FilterBar
        v={v}
        pending={pending}
        onSubmit={() => load(f)}
        onReset={() => {
          setF(blank);
          setResult({ item: null, rows: [] });
          setShown({});
        }}
      >
        {["from", "to"].map((k) => (
          <Field key={k} label={v(`filter.${k}`)}>
            <Input
              type="date"
              value={f[k]}
              onChange={(ev) => set(k)(ev.target.value)}
              aria-label={v(`filter.${k}`)}
            />
          </Field>
        ))}
        <Field label={v("filter.store")}>
          <Select
            value={f.store}
            onValueChange={set("store")}
            options={[
              { value: "", label: v("filter.all_stores") },
              ...options.store_filter,
            ]}
            placeholder={v("filter.all_stores")}
            aria-label={v("filter.store")}
          />
        </Field>
        <Field label={v("filter.item")}>
          <Select
            value={f.item}
            onValueChange={set("item")}
            options={[
              { value: "", label: v("filter.all_items") },
              ...options.items.map((i) => ({ value: i.value, label: i.label })),
            ]}
            placeholder={v("filter.all_items")}
            aria-label={v("filter.item")}
          />
        </Field>
      </FilterBar>

      {!item ? (
        <p className={cn(card, "px-5 py-4 text-sm text-foreground")}>
          {v("ledger.pick")}
        </p>
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <Tile label={v("ledger.tiles.item")} value={item.name} />
            <Tile
              label={v("ledger.tiles.opening")}
              value={`${num(result.opening)} ${item.unit ?? ""}`}
            />
            <Tile
              label={v("ledger.tiles.total_in")}
              value={num(result.total_in)}
              tone="success"
            />
            <Tile
              label={v("ledger.tiles.total_out")}
              value={num(result.total_out)}
              tone="warning"
            />
            <Tile
              label={v("ledger.tiles.closing")}
              value={`${num(result.closing)} ${item.unit ?? ""}`}
            />
          </div>
          <section className={cn(card, "min-w-0 overflow-hidden")}>
            <div className="flex flex-wrap items-start justify-between gap-3 p-5">
              <div>
                <h2 className="text-base font-semibold text-foreground">
                  {v("ledger.title", { item: item.name, code: item.code })}
                </h2>
                <p className="mt-0.5 text-[13px] text-foreground-muted">
                  {v("ledger.sub", {
                    category: dash(item.category),
                    unit: dash(item.unit),
                    price: money(item.price),
                    store: item.store ?? v("ledger.all_stores"),
                  })}
                </p>
              </div>
              <div className="flex gap-2">
                <a
                  href={`/api/general-store/excel/ledger${query(shown)}`}
                  className={buttonVariants({ variant: "outline", size: "sm" })}
                >
                  <Download className="size-4" aria-hidden />
                  {v("btn.excel")}
                </a>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() =>
                    printWindow({
                      title: v("ledger.title", {
                        item: item.name,
                        code: item.code,
                      }),
                      signatures: false,
                      columns: cols.map((k) => ({
                        label: v(`ledger.col.${k}`),
                        right: right.includes(k),
                        nowrap: k === "date" || k === "doc_no",
                      })),
                      rows: [
                        [
                          "",
                          { v: v("ledger.opening_row"), bold: true },
                          "",
                          "",
                          "",
                          "",
                          "",
                          "",
                          "",
                          "",
                          num(result.opening),
                          "",
                        ],
                        ...result.rows.map((r) => [
                          day(r.date),
                          typeOf(r),
                          r.doc_no,
                          dash(r.store),
                          dash(r.party),
                          ...MOVES.map((m) =>
                            r.column === m ? num(r.qty) : null,
                          ),
                          num(r.balance),
                          dash(r.user),
                        ]),
                      ],
                    })
                  }
                >
                  <Printer className="size-4" aria-hidden />
                  {v("stock.print")}
                </Button>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[1080px] text-sm">
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
                        {v(`ledger.col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-t border-border">
                    <td />
                    <td className="px-3 py-2.5 font-semibold" colSpan={9}>
                      {v("ledger.opening_row")}
                    </td>
                    <td className="px-3 py-2.5 text-right font-semibold tabular-nums">
                      {num(result.opening)}
                    </td>
                    <td />
                  </tr>
                  {result.rows.map((r, i) => (
                    <tr key={i} className="border-t border-border">
                      <td className="px-3 py-2.5 whitespace-nowrap">
                        {day(r.date)}
                      </td>
                      <td
                        className={cn(
                          "px-3 py-2.5 whitespace-nowrap",
                          r.reversal && "text-foreground-muted line-through",
                        )}
                      >
                        {typeOf(r)}
                      </td>
                      <td className="px-3 py-2.5 whitespace-nowrap">
                        {r.doc_no}
                      </td>
                      <td className="px-3 py-2.5">{dash(r.store)}</td>
                      <td className="px-3 py-2.5">{dash(r.party)}</td>
                      {MOVES.map((m) => (
                        <td
                          key={m}
                          className="px-3 py-2.5 text-right tabular-nums"
                        >
                          {r.column === m ? num(r.qty) : "-"}
                        </td>
                      ))}
                      <td className="px-3 py-2.5 text-right font-semibold tabular-nums">
                        {num(r.balance)}
                      </td>
                      <td className="px-3 py-2.5">{dash(r.user)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </div>
  );
}

export { CurrentStock, StockLedger };
