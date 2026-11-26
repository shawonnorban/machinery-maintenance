import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { findByRef, refsFor } from "@/lib/row-ref";
import { cn } from "@/lib/utils";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { BuyerPicker } from "@/components/tradeexport/buyer-picker";
import { BankDirectory } from "@/components/tradeexport/bank-directory";
import { BillAdjustmentDesk, BoeDesk, InvoiceDesk, LcDesk, LodgementDesk, MarginClosingDesk, PiDesk, RealisationDesk } from "@/components/tradeexport/operations";
import { CmReceiptDesk } from "@/components/tradeexport/cm-payment";
import { CashIncentiveDesk } from "@/components/tradeexport/cash-incentive";
import { TradeReports } from "@/components/tradeexport/reports";
import { ImportPiDesk, ImportLcDesk, EntryDesk, AcceptanceDesk, UdDesk, UdTransferDesk } from "@/components/tradeexport/imports";
import * as actions from "./actions";

/*
 * Trade & Export → Master Data (AnnotechERP 22/01): Document Reference Types,
 * Buyer-Wise Notify Party, L/C Applicant Master, Currency Exchange Rates and
 * Bank Directory. Brickland lays these out without tabs — the entry form and
 * the list on one page, in its "Select …", "Master Data" and "… List" boxes.
 */

const LISTS = ["INCO_TERM", "PAYMENT_TERM", "DELIVERY_PLACE", "TRANSIT_PORT", "LC_TYPE", "SHIPMENT_MODE", "BUSINESS_ASSOCIATION", "FORWARDER"];
const PURPOSES = ["EXPORT_REALIZATION", "IMPORT_PAYMENT", "LOCAL_SALES", "CASH_INCENTIVE"];

const lookups = () => apiFetch("/tradeexport/lookups");
const all = (m) => ({ value: "", label: m("all") });
const yn = { true: "Y", false: "N" };

/** Brickland's titled box around a part of the page. */
function Box({ title, className, children }) {
  return (
    <div className={cn("rounded-lg border border-border bg-surface", className)}>
      <div className="px-5 pt-4 pb-3 text-[15px] font-semibold">{title}</div>
      {children}
    </div>
  );
}

async function references({ m, tab, canManage, searchParams: query = {} }) {
  const list = LISTS.includes(query.list) ? query.list : "INCO_TERM";
  const rows = (await apiFetch("/erp/tx-reference-entries")).filter((r) => r.list === list);
  const fields = (editing) => [
    { name: "list", type: "hidden", default: list },
    { name: "code", label: m("f.code"), readOnly: true, placeholder: m("generated") },
    { name: "name", label: m("f.name_en"), required: true },
    { name: "name_bn", label: m("f.name_bn") },
    { name: "short_name", label: m("f.short_name"), required: true },
    { name: "display_order", label: m("f.display_order"), type: "number", required: true, default: editing ? undefined : String(rows.length + 1) },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ].filter((f) => !(editing && f.name === "list"));
  return {
    tabs: null,
    body: () => (
      <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
        <Box title={m("ref.select")} className="self-start">
          <ul className="border-t border-border">
            {LISTS.map((l) => (
              <li key={l}>
                <Link href={`/trade-export/reference-type?list=${l}`} className={cn("block px-4 py-2.5 text-sm hover:bg-surface-muted", l === list && "bg-brand-subtle font-semibold text-brand")}>
                  {m(`ref.list.${l}`)}
                </Link>
              </li>
            ))}
          </ul>
        </Box>
        <div className="flex min-w-0 flex-col gap-4">
          {canManage ? <EntryForm key={list} fields={fields(false)} title={m("master_data")} description={m(`ref.list.${list}`)} action={actions.createResource.bind(null, "tx-reference-entries", ["active"])} submitLabel={m("save")} columns={2} /> : null}
          <Box title={m("ref.data_list")}>
            <RecordsGrid
              exportName={`reference-${list.toLowerCase()}`}
              rows={rows.map((r) => ({ ...r, display_order: String(r.display_order), status: r.active }))}
              columns={[
                { key: "code", label: m("f.code"), type: "mono" },
                { key: "name", label: m("f.name_en") },
                { key: "name_bn", label: m("f.name_bn") },
                { key: "short_name", label: m("f.short_name") },
                { key: "display_order", label: m("f.display_order"), type: "number", align: "right" },
                { key: "status", label: m("f.status"), type: "badge", labels: yn },
              ]}
              edit={canManage ? { fields: fields(true), action: actions.updateResource.bind(null, "tx-reference-entries", ["active"]), title: m("ref.edit"), submitLabel: m("save") } : undefined}
              rowAction={canManage ? { label: m("deactivate"), action: actions.toggleActive.bind(null, "tx-reference-entries") } : undefined}
            />
          </Box>
        </div>
      </div>
    ),
  };
}

async function notifyParties({ m, canManage, searchParams: query = {} }) {
  const [parties, look] = await Promise.all([apiFetch("/erp/tx-notify-parties"), lookups()]);
  // A buyer is named in the url by its name (`lib/row-ref`), never its id.
  const buyerRefs = refsFor(look.buyers, "label", "value");
  const buyer = findByRef(look.buyers, "label", query.buyer, "value");
  const rows = buyer ? parties.filter((p) => p.buyer_id === buyer.value) : [];
  const fields = (editing) => [
    ...(editing ? [] : [{ name: "buyer_id", type: "hidden", default: buyer?.value }]),
    { name: "buyer_display", label: m("f.buyer_name"), readOnly: true, default: buyer?.label },
    { name: "code", label: m("f.code"), readOnly: true, placeholder: m("generated") },
    { name: "name", label: m("f.party_name"), required: true },
    { name: "name_bn", label: m("f.party_name_bn") },
    { name: "address", label: m("f.address"), required: true },
    { name: "country_id", label: m("f.country"), type: "select", options: [{ value: "", label: m("select_country") }, ...look.countries], placeholder: m("select_country") },
    { name: "post_code", label: m("f.post_code") },
    { name: "po_box", label: m("f.po_box") },
    { name: "phone", label: m("f.work_phone") },
    { name: "mobile", label: m("f.mobile") },
    { name: "fax", label: m("f.fax") },
    { name: "email", label: m("f.email"), type: "email" },
    { name: "web_url", label: m("f.web_url") },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: null,
    body: () => (
      <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
        <BuyerPicker title={m("np.select")} buyers={look.buyers.map((b) => ({ ...b, value: buyerRefs.get(b.value) }))} active={buyer ? buyerRefs.get(buyer.value) : undefined} hrefFor="/trade-export/buyer-wise-notify-party?buyer=" placeholder={m("search")} empty={m("np.no_buyers")} />
        <div className="flex min-w-0 flex-col gap-4">
          {canManage ? (
            // Brickland shows the form before a buyer is picked; Buyer Name then reads "Pick a buyer on the left".
            <EntryForm key={buyer?.value ?? "none"} fields={fields(false).map((f) => (f.name === "buyer_display" && !buyer ? { ...f, default: undefined, placeholder: m("np.pick") } : f))} title={m("master_data")} action={actions.createResource.bind(null, "tx-notify-parties", ["active"])} submitLabel={m("save")} columns={2} cancelHref="/trade-export/buyer-wise-notify-party" cancelLabel={m("cancel")} />
          ) : (
            <Box title={m("master_data")}>
              <p className="border-t border-border px-5 py-8 text-center text-sm text-foreground-muted">{m("np.pick")}</p>
            </Box>
          )}
          <Box title={m("np.list")}>
            {buyer ? (
              <RecordsGrid
                exportName="notify-parties"
                rows={rows.map((r) => ({ ...r, country_id: r.country_id ?? "", status: r.active }))}
                columns={[
                  { key: "buyer", label: m("f.buyer") },
                  { key: "country", label: m("f.country") },
                  { key: "code", label: m("f.code"), type: "mono" },
                  { key: "name", label: m("f.name") },
                  { key: "phone", label: m("f.phone") },
                  { key: "mobile", label: m("f.mobile") },
                  { key: "fax", label: m("f.fax") },
                  { key: "email", label: m("f.email") },
                  { key: "web_url", label: m("f.web_url") },
                  { key: "status", label: m("f.status"), type: "badge", labels: yn },
                ]}
                edit={canManage ? { fields: fields(true), action: actions.updateResource.bind(null, "tx-notify-parties", ["active"]), title: m("np.edit"), submitLabel: m("save"), columns: 2 } : undefined}
                rowAction={canManage ? { label: m("deactivate"), action: actions.toggleActive.bind(null, "tx-notify-parties") } : undefined}
              />
            ) : (
              <p className="border-t border-border px-5 py-8 text-center text-sm text-foreground-muted">{m("np.pick_list")}</p>
            )}
          </Box>
        </div>
      </div>
    ),
  };
}

async function applicants({ m, canManage }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/tx-lc-applicants"), lookups()]);
  const fields = [
    { name: "buyer_id", label: m("f.buyer_name"), type: "select", required: true, options: look.buyers, placeholder: m("select_buyer") },
    { name: "name", label: m("f.applicant_name"), required: true },
    { name: "name_bn", label: m("f.applicant_name_bn") },
    { name: "address", label: m("f.lc_address"), type: "textarea", required: true, span: 2 },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: null,
    body: () => (
      <div className="flex flex-col gap-4">
        {canManage ? <EntryForm fields={fields} title={m("master_data")} action={actions.createResource.bind(null, "tx-lc-applicants", ["active"])} submitLabel={m("save")} columns={2} /> : null}
        <Box title={m("ap.list")}>
          <RecordsGrid
            exportName="lc-applicants"
            rows={rows.map((r) => ({ ...r, status: r.active }))}
            columns={[
              { key: "buyer", label: m("f.buyer") },
              { key: "name", label: m("f.applicant_name_col") },
              { key: "name_bn", label: m("f.applicant_name_bn_col") },
              { key: "address", label: m("f.address") },
              { key: "status", label: m("f.status"), type: "badge", labels: yn },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "tx-lc-applicants", ["active"]), title: m("ap.edit"), submitLabel: m("save"), columns: 2 } : undefined}
            rowAction={canManage ? { label: m("deactivate"), action: actions.toggleActive.bind(null, "tx-lc-applicants") } : undefined}
          />
        </Box>
      </div>
    ),
  };
}

function monthBounds() {
  const now = new Date();
  const iso = (d) => d.toISOString().slice(0, 10);
  return { from: iso(new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() - 1, 1))), to: iso(new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 0))) };
}

async function exchangeRates({ m, tab, canManage, searchParams: query = {} }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/tx-exchange-rates"), lookups()]);
  const bounds = monthBounds();
  const values = { company: query.company ?? "", purpose: query.purpose ?? "", from: query.from ?? bounds.from, to: query.to ?? bounds.to };
  const fields = [
    { name: "company_display", label: m("f.company"), readOnly: true, default: look.company.label },
    { name: "rate_for", label: m("f.rate_for"), type: "select", options: [{ value: "", label: m("select_rate_for") }, ...PURPOSES.map((p) => ({ value: p, label: m(`opt.purpose.${p}`) }))], placeholder: m("select_rate_for") },
    { name: "rate_display", label: m("f.local_rate"), type: "derived", from: "currency", values: Object.fromEntries(look.currencies.map((c) => [c.value, c.rate ?? ""])) },
    { name: "effective_date", label: m("f.effective_date"), type: "date", required: true, default: new Date().toISOString().slice(0, 10) },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
    { name: "currency", label: m("f.rate_by"), type: "select", required: true, options: look.currencies, placeholder: m("select_currency"), help: m("rate.rate_hint") },
  ];
  const shown = rows.filter((r) => (!values.purpose || r.rate_for === values.purpose) && (!values.from || r.effective_date >= values.from) && (!values.to || r.effective_date <= values.to));
  return {
    tabs: null,
    body: () =>
      tab === "new" && canManage ? (
        <div className="flex flex-col gap-3">
          <div>
            <Link href="/trade-export/exchange-rates" className="text-sm font-medium text-brand hover:underline">← {m("rate.back")}</Link>
          </div>
          <EntryForm fields={fields} title={m("rate.config")} action={actions.createResource.bind(null, "tx-exchange-rates", [])} submitLabel={m("save")} columns={2} successHref="/trade-export/exchange-rates" />
        </div>
      ) : (
        <div className="flex flex-col gap-3">
          {canManage ? (
            <div>
              <Link href="/trade-export/exchange-rates?tab=new" className="inline-flex h-9 items-center gap-1.5 rounded-sm bg-brand px-3 text-sm font-medium text-brand-foreground hover:bg-brand-hover">+ {m("rate.add")}</Link>
            </div>
          ) : null}
          <FilterBar
            basePath="/trade-export/exchange-rates"
            values={values}
            fields={[
              { name: "company", label: m("f.company_name"), type: "select", options: [{ value: "", label: m("select_company") }, look.company] },
              { name: "purpose", label: m("f.rate_for"), type: "select", options: [all(m), ...PURPOSES.map((p) => ({ value: p, label: m(`opt.purpose.${p}`) }))] },
              { name: "from", label: m("f.from_date"), type: "date" },
              { name: "to", label: m("f.to_date"), type: "date" },
            ]}
            labels={{ show: m("search_button"), reset: m("refresh") }}
          />
          <RecordsGrid
            exportName="exchange-rates"
            rows={shown.map((r) => ({ ...r, rate_for: r.rate_for ?? "", rate: String(Number(r.rate)), created_on: r.created_at?.slice(0, 10), purpose_label: r.rate_for ? m(`opt.purpose.${r.rate_for}`) : "" }))}
            columns={[
              { key: "company", label: m("f.company") },
              { key: "currency", label: m("f.rate_by"), type: "mono" },
              { key: "rate", label: m("f.exchange_rate"), type: "number", align: "right" },
              { key: "purpose_label", label: m("f.rate_for") },
              { key: "effective_date", label: m("f.effective_date"), type: "date" },
              { key: "created_on", label: m("f.created_on"), type: "date" },
              { key: "remarks", label: m("f.remarks") },
            ]}
            edit={canManage ? { fields: fields.filter((f) => f.name !== "company_display"), action: actions.updateResource.bind(null, "tx-exchange-rates", []), title: m("rate.edit"), submitLabel: m("save"), columns: 2 } : undefined}
          />
        </div>
      ),
  };
}

async function banks({ m, canManage, searchParams: query = {} }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/tx-banks"), lookups()]);
  const code = (query.code ?? "").trim().toLowerCase();
  const name = (query.name ?? "").trim().toLowerCase();
  const shown = rows.filter((b) => (!code || b.code.toLowerCase().includes(code)) && (!name || b.name.toLowerCase().includes(name)));
  return {
    tabs: null,
    body: () => (
      <BankDirectory
        banks={shown}
        countries={look.countries}
        create={actions.createResource}
        update={actions.updateResource}
        canManage={canManage}
        filters={
          <FilterBar
            basePath="/trade-export/bank-list"
            values={{ code: query.code ?? "", name: query.name ?? "" }}
            fields={[
              { name: "code", label: m("f.bank_code"), type: "text" },
              { name: "name", label: m("f.bank_name_search"), type: "text" },
            ]}
            labels={{ show: m("search_button"), reset: m("refresh") }}
          />
        }
      />
    ),
  };
}

/* Operations (AnnotechERP 22/02): the export papers, each list and its entry on one route. */
const ops = (path, Desk) => async ({ op, key, permissions, searchParams }) => {
  const [rows, options] = await Promise.all([apiFetch(`/tradeexport/ops/${path}`), apiFetch("/tradeexport/ops/options")]);
  return {
    description: op(`descriptions.${key}`),
    body: () => <Desk initial={rows} opt={options} query={searchParams} canManage={permissions.includes("tradeexport.export.manage")} act={{ read: actions.opRead, write: actions.opWrite }} />,
  };
};

/* Import (AnnotechERP 22/03) and the two declarations that read it. */
const imp = (path, Desk, perm) => async ({ op, key, permissions, searchParams }) => {
  const [rows, options] = await Promise.all([apiFetch(`/tradeexport/ops/${path}`), apiFetch("/tradeexport/ops/import-options")]);
  return {
    description: op(`descriptions.${key}`),
    body: () => <Desk initial={rows} opt={options} query={searchParams} canManage={permissions.includes(perm)} act={{ read: actions.opRead, write: actions.opWrite }} />,
  };
};

const SCREENS = {
  "import-lc-pi": imp("imp-pis", ImportPiDesk, "tradeexport.import.manage"),
  "import-lc": imp("imp-lcs", ImportLcDesk, "tradeexport.import.manage"),
  "bill-of-entry": imp("entries", EntryDesk, "tradeexport.import.manage"),
  "acceptance-of-import-bill": imp("acceptances", AcceptanceDesk, "tradeexport.import.manage"),
  "lc-ud": imp("uds", UdDesk, "tradeexport.export.manage"),
  "ud-transfer": imp("ud-transfers", UdTransferDesk, "tradeexport.export.manage"),
  "payment-receives": ops("cm-receipts", CmReceiptDesk),
  "bill-adjustment-not-used": ops("boes", BillAdjustmentDesk),
  "margin-closing-not-used": ops("margin-closings", MarginClosingDesk),
  "cash-incentives": ops("cash-incentives", CashIncentiveDesk),
  "commercial-reports": async ({ op, key }) => {
    const options = await apiFetch("/tradeexport/ops/reports/options");
    return { description: op(`descriptions.${key}`), body: () => <TradeReports options={options} act={{ read: actions.opRead, write: actions.opWrite }} /> };
  },
  "export-lc-sales-contract-entry": ops("lcs", LcDesk),
  "export-lc-pi": ops("pis", PiDesk),
  "commercial-invoice": ops("invoices", InvoiceDesk),
  "bill-of-export": ops("lodgements", LodgementDesk),
  "bill-of-exchange": ops("boes", BoeDesk),
  "bank-bill-collection": ops("realisations", RealisationDesk),
  "reference-type": references,
  "buyer-wise-notify-party": notifyParties,
  "lc-applicant": applicants,
  "exchange-rates": exchangeRates,
  "bank-list": banks,
};

/** No Trade & Export master screen has tabs in Brickland. */
const DEFAULT_TABS = {};

export { SCREENS, DEFAULT_TABS };
