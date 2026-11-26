import { cn } from "@/lib/utils";
import { apiFetch } from "@/lib/api-server";
import { findByRef } from "@/lib/row-ref";
import { RecordsGrid } from "@/components/erp/records-grid";
import { BundleEntry, BundleList } from "@/components/cutting/bundles";
import { AdditionalCuttingList, CutReport } from "@/components/cutting/reports";
import * as actions from "./actions";

/*
 * Cutting Floor (AnnotechERP 19/01–19/03), each laid out as Brickland lays
 * it out: the Dashboard (today's cutting, the balance, cards waiting to print
 * and the latest cards), New Cutting and Bundle Entry (the same bench), the
 * Cutting List (every PO / style / colour cut so far), the Bundle Card List,
 * the four bundle reports and the Additional Cutting List (19/04).
 */

const act = { list: actions.listBundles, save: actions.saveBundle, print: actions.printBundles, cancel: actions.cancelBundle, serial: actions.findSerial, context: actions.cuttingContext, inputs: actions.listInputs, saveInput: actions.saveInput, cancelInput: actions.cancelInput, saveOutput: actions.saveOutput, cardsFromOutput: actions.cardsFromOutput };
const dmy = (d) => (d ? d.split("-").reverse().join("-") : "—");
const num = (v) => Number(v ?? 0).toLocaleString("en-US");
const daysAgo = (n, today) => {
  const d = new Date(`${today}T00:00:00`);
  d.setDate(d.getDate() - n);
  return d.toLocaleDateString("en-CA");
};

const dashboard = async ({ w }) => {
  const d = await apiFetch("/cutting/dashboard");
  const tiles = [
    ["dash.today_cut", num(d.today_cut), "bg-brand-subtle text-brand"],
    ["dash.balance", `${num(d.balance)} ${w("card.pcs")}`, "bg-warning/15 text-warning"],
    ["dash.today_bundles", `${num(d.today_bundles)} ${w("dash.bundles")}`, "bg-info-subtle text-info"],
    ["dash.bundle_qty", `${num(d.today_qty)} ${w("card.pcs")}`, "bg-success/15 text-success"],
    ["dash.pending_print", num(d.pending_print), "bg-danger/15 text-danger"],
    ["dash.printed", num(d.printed), "bg-surface-muted text-foreground"],
  ];
  return {
    body: () => (
      <div className="flex flex-col gap-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          {tiles.map(([k, v, tone]) => (
            <div key={k} className="rounded-lg border border-border bg-surface p-4">
              <p className="text-[13px] text-foreground-muted">{w(k)}</p>
              <p className={cn("mt-2 inline-block rounded-md px-2 py-0.5 text-xl font-bold tabular-nums", tone)}>{v}</p>
            </div>
          ))}
        </div>
        <section className="overflow-hidden rounded-lg border border-border bg-surface">
          <header className="flex items-center gap-2.5 bg-brand px-4 py-2.5 text-white">
            <span className="flex size-6 items-center justify-center rounded-full bg-white text-xs font-bold text-foreground">1</span>
            <h2 className="text-[15px] font-semibold">{w("dash.latest")}</h2>
          </header>
          <div className="p-4">
            <RecordsGrid
              exportName="latest-bundle-cards"
              rows={d.latest.map((r) => ({ ...r, status_label: w(`status.${r.status}`), date: dmy(r.cut_date) }))}
              rowKey="bundle_no"
              searchable={false}
              emptyState={{ title: w("list.empty"), text: w("dash.empty_hint") }}
              columns={[
                { key: "bundle_no", label: w("col.bundle_no"), type: "mono" },
                { key: "po_no", label: w("col.po") },
                { key: "style", label: w("col.style") },
                { key: "colour", label: w("col.colour") },
                { key: "size", label: w("col.size") },
                { key: "qty", label: w("col.qty"), type: "number", align: "right" },
                { key: "status_label", label: w("col.status"), type: "badge" },
                { key: "date", label: w("col.date") },
              ]}
            />
          </div>
        </section>
      </div>
    ),
  };
};

// New Cutting starts a whole cutting at once; Bundle Entry adds cards to one already started.
const bench = (mode) => async ({ searchParams: q = {}, canManage }) => {
  const options = await apiFetch("/cutting/options");
  const from = daysAgo(6, options.today);
  // From the Cutting List's Bundle: the PO and colour it was opened on (its readable reference, never an id).
  let prefill = {};
  if (q.pick) {
    const rows = (await apiFetch("/cutting/cutting-list")).map((r) => ({ ...r, ref: `${r.po_no} ${r.colour}` }));
    const row = findByRef(rows, "ref", q.pick, "ref");
    if (row) prefill = { po: row.po_no, colour: row.colour };
  }
  const [list, todays, inputs] = await Promise.all([apiFetch(`/cutting/bundles?from=${from}&to=${options.today}`), apiFetch(`/cutting/bundles?from=${options.today}&to=${options.today}`), apiFetch("/cutting/inputs")]);
  return { body: () => <BundleEntry mode={mode} options={options} initial={list} todays={todays} inputs={inputs} canManage={canManage} act={act} prefill={prefill} /> };
};

const cuttingList = async ({ w }) => {
  const rows = await apiFetch("/cutting/cutting-list");
  return {
    body: () => (
      <section className="overflow-hidden rounded-lg border border-border bg-surface">
        <header className="flex items-center gap-2.5 bg-brand px-4 py-2.5 text-white">
          <span className="flex size-6 items-center justify-center rounded-full bg-white text-xs font-bold text-foreground">1</span>
          <h2 className="text-[15px] font-semibold">{w("cut_list.title")}</h2>
        </header>
        <div className="p-4">
          <RecordsGrid
            exportName="cutting-list"
            rows={rows.map((r, i) => ({ ...r, sl: i + 1, ref: `${r.po_no} ${r.colour}`, last: dmy(r.last_cut), additional_pct_label: `${Number(r.additional_pct).toFixed(2)}%` }))}
            rowKey="ref"
            emptyState={{ title: w("cut_list.empty"), text: w("cut_list.empty_hint") }}
            columns={[
              { key: "sl", label: w("col.sl"), type: "number" },
              { key: "po_no", label: w("col.po") },
              { key: "style", label: w("col.style") },
              { key: "buyer", label: w("col.buyer") },
              { key: "colour", label: w("col.colour") },
              { key: "order_qty", label: w("col.order_qty"), type: "number", align: "right" },
              { key: "already_cut", label: w("col.already_cut"), type: "number", align: "right" },
              { key: "balance", label: w("col.balance"), type: "number", align: "right" },
              { key: "additional", label: w("col.additional"), type: "number", align: "right" },
              { key: "additional_pct_label", label: w("col.additional_pct"), align: "right" },
              { key: "bundles", label: w("col.bundles"), type: "number", align: "right" },
              { key: "last", label: w("col.last_cut") },
            ]}
            rowLink={{ label: w("cut_list.bundle"), href: "/cutting-floor/new-cutting?pick=", refKey: "ref" }}
          />
        </div>
      </section>
    ),
  };
};

const bundleList = async ({ canManage }) => {
  const options = await apiFetch("/cutting/options");
  const list = await apiFetch(`/cutting/bundles?from=${daysAgo(6, options.today)}&to=${options.today}`);
  return { body: () => <BundleList initial={list} today={options.today} canManage={canManage} act={act} /> };
};

const report = (kind, slug) => async ({ w }) => {
  const initial = await apiFetch(`/cutting/reports/${kind}`);
  return { body: () => <CutReport kind={kind} initial={initial} load={actions.cutReport} title={w(`screens.${slug}`)} /> };
};

const additionalCutting = async () => {
  const initial = await apiFetch("/cutting/additional-cutting");
  return { body: () => <AdditionalCuttingList initial={initial} load={actions.additionalCutting} /> };
};

const SCREENS = {
  "cut-dashboard": dashboard,
  "new-cutting": bench("new"),
  "cutting-list": cuttingList,
  "bundle-card-list": bundleList,
  "create-bundle-card": bench("entry"),
  "bundle-report": report("bundle", "bundle-report"),
  "additional-cutting-list": additionalCutting,
  "po-wise-bundle-report": report("po", "po-wise-bundle-report"),
  "style-wise-bundle-report": report("style", "style-wise-bundle-report"),
  "size-wise-bundle-report": report("size", "size-wise-bundle-report"),
};

export { SCREENS };
