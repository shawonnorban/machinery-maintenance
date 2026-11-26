"use client";

import Link from "next/link";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  AlertTriangle,
  ArrowLeftRight,
  Boxes,
  CircleX,
  Coins,
  FilePlus2,
  Layers,
  PackageMinus,
  PackagePlus,
  Search,
  Tags,
  Undo2,
  Warehouse,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { StatusDonut } from "@/components/dashboard/status-donut";
import { useT } from "@/lib/i18n";
import { card, day, num, money, dash, Pill } from "./common";

/*
 * General Store → Dashboard (AnnotechERP 10/02), as Brickland draws it: the
 * quick links to Receive, Issue, Return and the stock list; the stock tiles,
 * today's documents; the last thirty days' movement, stock by category and
 * stock value by category; low and out of stock, the recent transactions and
 * the largest stocks.
 */

const TONE = {
  info: ["border-t-info", "bg-info-subtle text-info"],
  danger: ["border-t-danger", "bg-danger-subtle text-danger"],
  warning: ["border-t-warning", "bg-warning-subtle text-warning"],
  success: ["border-t-success", "bg-success-subtle text-success"],
  brand: ["border-t-brand", "bg-brand-subtle text-brand"],
};

function BigTile({ label, value, Icon, tone }) {
  return (
    <div className={cn(card, "border-t-4 p-4", TONE[tone][0])}>
      <div className="flex items-start justify-between gap-2">
        <p className="text-[13px] font-semibold text-foreground">{label}</p>
        <span className={cn("rounded-md p-1.5", TONE[tone][1])}>
          <Icon className="size-4" aria-hidden />
        </span>
      </div>
      <p className="mt-2 text-2xl font-semibold tabular-nums text-foreground">
        {value}
      </p>
    </div>
  );
}

function Table({ title, sub, cols, rows, cell, right = [], v }) {
  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="p-5 pb-3">
        <h2 className="text-base font-semibold text-foreground">{title}</h2>
        {sub ? (
          <p className="text-[13px] text-foreground-muted">{sub}</p>
        ) : null}
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[560px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {cols.map((k) => (
                <th
                  key={k}
                  className={cn(
                    "px-3 py-2 text-left text-xs font-semibold uppercase",
                    right.includes(k) && "text-right",
                  )}
                >
                  {v(`dash.col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td
                  colSpan={cols.length}
                  className="px-3 py-8 text-center text-foreground-muted"
                >
                  {v("dash.none")}
                </td>
              </tr>
            ) : null}
            {rows.map((r, i) => (
              <tr key={i} className="border-t border-border">
                {cols.map((k) => (
                  <td
                    key={k}
                    className={cn(
                      "px-3 py-2",
                      right.includes(k) && "text-right tabular-nums",
                    )}
                  >
                    {cell(r, k, i)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

/**
 * Dashboard.
 *
 * @param {{ data: object }} props
 */
function GsDashboard({ data }) {
  const t = useT("store");
  const v = (k, p) => t(`inv.${k}`, p);
  const tiles = data.tiles;
  const totalQty = data.by_category.reduce((s, c) => s + c.qty, 0);
  // Three series slots in fixed order; the rest fold into Others, as Brickland's donut does.
  const SERIES = ["var(--series-1)", "var(--series-2)", "var(--series-3)"];
  const top = data.by_category.filter((c) => c.qty > 0).slice(0, 3);
  const rest = data.by_category
    .slice(top.length)
    .reduce((s, c) => s + c.qty, 0);
  const segments = [
    ...top.map((c, i) => ({
      key: c.category,
      label: c.category,
      value: c.qty,
      color: SERIES[i],
    })),
    ...(data.by_category.length > top.length
      ? [
          {
            key: "others",
            label: v("dash.others"),
            value: rest,
            color: "var(--foreground-subtle)",
          },
        ]
      : []),
  ];
  const valued = data.by_category
    .filter((c) => c.value > 0)
    .sort((a, b) => b.value - a.value);
  const maxValue = Math.max(1, ...valued.map((c) => c.value));
  const quick = [
    ["gs-receive", "quick_receive", PackagePlus, "primary"],
    ["gs-issue", "quick_issue", PackageMinus, "outline"],
    ["gs-return", "quick_return", Undo2, "outline"],
    ["gs-current-stock", "stock_search", Search, "outline"],
    ["gs-req-new", "new_req", FilePlus2, "outline"],
  ];
  const req = (k, p) => t(`req.${k}`, p);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap justify-end gap-2">
        {quick.map(([screen, label, Icon, variant]) => (
          <Link
            key={screen}
            href={`/general-store/${screen}`}
            className={buttonVariants({ variant, size: "sm" })}
          >
            <Icon className="size-4" aria-hidden />
            {label === "new_req" ? req("dash.new") : v(`dash.${label}`)}
          </Link>
        ))}
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <BigTile
          label={v("dash.tiles.items")}
          value={num(tiles.items)}
          Icon={Boxes}
          tone="info"
        />
        <BigTile
          label={v("dash.tiles.qty")}
          value={num(tiles.qty)}
          Icon={Layers}
          tone="danger"
        />
        <BigTile
          label={v("dash.tiles.value")}
          value={money(tiles.value)}
          Icon={Coins}
          tone="warning"
        />
        <BigTile
          label={v("dash.tiles.received_value")}
          value={money(tiles.received_value)}
          Icon={PackagePlus}
          tone="success"
        />
        <BigTile
          label={v("dash.tiles.issued_value")}
          value={money(tiles.issued_value)}
          Icon={PackageMinus}
          tone="info"
        />
        <BigTile
          label={v("dash.tiles.in_house_value")}
          value={money(tiles.in_house_value)}
          Icon={Warehouse}
          tone="danger"
        />
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <BigTile
          label={v("dash.tiles.categories")}
          value={num(tiles.categories)}
          Icon={Tags}
          tone="success"
        />
        <BigTile
          label={v("dash.tiles.low")}
          value={num(tiles.low)}
          Icon={AlertTriangle}
          tone="danger"
        />
        <BigTile
          label={v("dash.tiles.out")}
          value={num(tiles.out)}
          Icon={CircleX}
          tone="danger"
        />
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BigTile
          label={v("dash.tiles.RECEIVE")}
          value={num(tiles.today.RECEIVE)}
          Icon={PackagePlus}
          tone="success"
        />
        <BigTile
          label={v("dash.tiles.ISSUE")}
          value={num(tiles.today.ISSUE)}
          Icon={PackageMinus}
          tone="info"
        />
        <BigTile
          label={v("dash.tiles.RETURN")}
          value={num(tiles.today.RETURN)}
          Icon={Undo2}
          tone="brand"
        />
        <BigTile
          label={v("dash.tiles.ADJUST")}
          value={num(tiles.today.ADJUST)}
          Icon={ArrowLeftRight}
          tone="warning"
        />
      </div>

      {data.requisitions?.pending || data.requisitions?.to_issue ? (
        <div className="grid gap-3 md:grid-cols-2">
          {data.requisitions.pending ? (
            <Link
              href="/general-store/gs-req-pending"
              className="rounded-xl border border-warning/30 bg-warning-subtle px-4 py-3 text-sm font-semibold text-warning underline-offset-2 hover:underline"
            >
              {req("dash.pending", { n: data.requisitions.pending })}
            </Link>
          ) : (
            <span />
          )}
          {data.requisitions.to_issue ? (
            <Link
              href="/general-store/gs-req-approved"
              className="rounded-xl border border-info/30 bg-info-subtle px-4 py-3 text-sm font-semibold text-info underline-offset-2 hover:underline"
            >
              {req("dash.to_issue", { n: data.requisitions.to_issue })}
            </Link>
          ) : null}
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr_1fr]">
        <section className={cn(card, "min-w-0 p-5")}>
          <h2 className="text-base font-semibold text-foreground">
            {v("dash.movement")}
          </h2>
          <p className="text-[13px] text-foreground-muted">
            {v("dash.movement_sub")}
          </p>
          <div className="mt-3 h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data.movement} barGap={2}>
                <CartesianGrid
                  strokeDasharray="3 3"
                  stroke="var(--border)"
                  vertical={false}
                />
                <XAxis
                  dataKey="date"
                  tickFormatter={(d) => day(d).slice(0, 6)}
                  tick={{ fontSize: 11, fill: "var(--foreground-muted)" }}
                  interval={6}
                  axisLine={false}
                  tickLine={false}
                />
                <YAxis
                  tick={{ fontSize: 11, fill: "var(--foreground-muted)" }}
                  axisLine={false}
                  tickLine={false}
                  width={36}
                />
                <Tooltip
                  labelFormatter={(d) => day(d)}
                  formatter={(value, name) => [num(value), name]}
                  contentStyle={{
                    borderRadius: 8,
                    borderColor: "var(--border)",
                    fontSize: 12,
                  }}
                />
                <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                <Bar
                  dataKey="in"
                  name={v("dash.in")}
                  fill="var(--series-3)"
                  radius={[4, 4, 0, 0]}
                  isAnimationActive={false}
                />
                <Bar
                  dataKey="out"
                  name={v("dash.out")}
                  fill="var(--series-2)"
                  radius={[4, 4, 0, 0]}
                  isAnimationActive={false}
                />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </section>

        <section className={cn(card, "min-w-0 p-5")}>
          <h2 className="text-base font-semibold text-foreground">
            {v("dash.by_category")}
          </h2>
          <p className="text-[13px] text-foreground-muted">
            {v("dash.by_category_sub")}
          </p>
          <div className="mt-3">
            <StatusDonut
              segments={segments}
              total={num(totalQty)}
              totalLabel={v("dash.total_qty")}
            />
          </div>
          <ul className="mt-4 flex flex-col gap-1.5 text-sm">
            {segments.map((s) => (
              <li
                key={s.key}
                className="flex items-center justify-between gap-2"
              >
                <span className="flex items-center gap-2 text-foreground">
                  <span
                    className="size-2.5 rounded-full"
                    style={{ background: s.color }}
                    aria-hidden
                  />
                  {s.label}
                </span>
                <span className="tabular-nums text-foreground">
                  <b>{num(s.value)}</b>{" "}
                  <span className="text-foreground-muted">
                    ({totalQty ? Math.round((s.value / totalQty) * 100) : 0}%)
                  </span>
                </span>
              </li>
            ))}
          </ul>
        </section>

        <section className={cn(card, "min-w-0 p-5")}>
          <h2 className="text-base font-semibold text-foreground">
            {v("dash.value_by_category")}
          </h2>
          <p className="text-[13px] text-foreground-muted">
            {v("dash.value_sub")}
          </p>
          <ul className="mt-4 flex flex-col gap-3 text-sm">
            {valued.length === 0 ? (
              <li className="text-foreground-muted">{v("dash.none")}</li>
            ) : null}
            {valued.map((c) => (
              <li
                key={c.category}
                className="grid grid-cols-[7rem_1fr_auto] items-center gap-3"
              >
                <span className="truncate text-foreground">{c.category}</span>
                <span className="h-4 rounded-sm bg-surface-muted">
                  <span
                    className="block h-full rounded-sm"
                    style={{
                      width: `${(c.value / maxValue) * 100}%`,
                      background: "var(--series-1)",
                    }}
                  />
                </span>
                <span className="text-right font-semibold tabular-nums text-foreground">
                  {num(c.value)}
                </span>
              </li>
            ))}
          </ul>
        </section>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Table
          v={v}
          title={v("dash.low")}
          cols={[
            "sl",
            "item_code",
            "item_name",
            "unit",
            "current",
            "min",
            "status",
          ]}
          right={["current", "min"]}
          rows={data.low}
          cell={(r, k, i) =>
            ({
              sl: i + 1,
              item_code: r.item_code,
              item_name: r.item,
              unit: dash(r.unit),
              current: num(r.qty),
              min: num(r.min),
              status: (
                <Pill tone={r.status}>{v(`stock_status.${r.status}`)}</Pill>
              ),
            })[k]
          }
        />
        <Table
          v={v}
          title={v("dash.out_of_stock")}
          cols={[
            "sl",
            "item_code",
            "item_name",
            "unit",
            "current",
            "min",
            "status",
          ]}
          right={["current", "min"]}
          rows={data.out_of_stock}
          cell={(r, k, i) =>
            ({
              sl: i + 1,
              item_code: r.item_code,
              item_name: r.item,
              unit: dash(r.unit),
              current: num(r.qty),
              min: num(r.min),
              status: (
                <Pill tone={r.status}>{v(`stock_status.${r.status}`)}</Pill>
              ),
            })[k]
          }
        />
        <Table
          v={v}
          title={v("dash.recent")}
          cols={["date", "type", "ref", "item", "party", "qty", "user"]}
          right={["qty"]}
          rows={data.recent}
          cell={(r, k) =>
            ({
              date: day(r.date),
              type: v(`badge.${r.type}`),
              ref: r.doc_no,
              item: r.item,
              party: dash(r.party),
              qty: `${num(r.qty)} ${r.unit ?? ""}`,
              user: dash(r.user),
            })[k]
          }
        />
        <Table
          v={v}
          title={v("dash.highest")}
          sub={v("dash.highest_sub")}
          cols={[
            "item_code",
            "item_name",
            "category",
            "stock_qty",
            "price",
            "stock_value",
            "status",
          ]}
          right={["stock_qty", "price", "stock_value"]}
          rows={data.highest}
          cell={(r, k) =>
            ({
              item_code: r.item_code,
              item_name: r.item,
              category: dash(r.category),
              stock_qty: num(r.qty),
              price: num(r.price),
              stock_value: num(r.value),
              status: (
                <Pill tone={r.status}>{v(`stock_status.${r.status}`)}</Pill>
              ),
            })[k]
          }
        />
      </div>
    </div>
  );
}

export { GsDashboard };
