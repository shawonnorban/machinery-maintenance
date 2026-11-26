import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { SideGrid } from "@/components/workstudy/side-grid";
import { DraftVoucherDesk, PostingDesk, StatutoryRegister, VoucherEntryDesk } from "@/components/finance/vouchers";
import { MrrBillDesk } from "@/components/finance/mrr-bills";
import { BankRecDesk, FyCloseDesk, StockClosingDesk } from "@/components/finance/period-close";
import { ChequeCancelDesk, ChequeIssueDesk } from "@/components/finance/cheques";
import { VoucherAuditRegister } from "@/components/finance/voucher-audit";
import { FinanceReportDesk } from "@/components/finance/reports";
import * as actions from "./actions";

/*
 * Finance & Ledger → Master Data (AnnotechERP 23/01): Payment Method Master,
 * Maker-Checker Setup, Chart of Accounts Master, Cost Centre Grouping, Cost
 * Centre Master, Cheque Book Register, Bank Account Master, Fiscal Year Setup,
 * Account Type Configuration and Statutory Ledger Rules — each with
 * Brickland's tabs.
 */

const VOUCHER_TYPES = ["BPV", "BRV", "CPV", "CRV", "CV", "BV", "JV"];
const ACCOUNT_TYPES = ["ASSET", "LIABILITY", "EQUITY", "REVENUE", "EXPENSE"];
const REF_TYPES = ["BUYER", "SUPPLIER", "EMPLOYEE", "BANK", "BANK_ACCOUNT"];
const BANK_ACCOUNT_TYPES = ["CURRENT", "SAVINGS", "STD", "SND", "FC", "CC", "OD"];
const CLASS_TYPES = ["CASH", "BANK", "RECEIVABLE", "PAYABLE", "INVENTORY", "GENERAL"];
const LAYERS = ["MAIN_CLASS", "SUB_CLASS", "CONTROL", "LEDGER"];

const lookups = () => apiFetch("/accounting/lookups");
const vo = { read: actions.voRead, write: actions.voWrite };
const options = (m, group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const all = (m) => ({ value: "", label: m("all") });
const recordsTab = (m, count, key = "records") => ({ key: "records", label: m(`${key}`), icon: "Table2", count });
const newTab = (m, key) => ({ key: "new", label: m(key), icon: "Plus" });
const statusCol = (m) => ({ key: "active", label: m("f.status"), type: "status" });
const yn = (m) => ({ true: m("yes"), false: m("no") });

/** The usual Records + New screen over one resource. */
function simpleScreen({ resource, screen, booleans, fields, columns, decorate = (r) => r, newKey, editKey, saveKey, description, filters, filter, deletable }) {
  return async function render({ m, tab, canManage, searchParams: query = {} }) {
    const look = fields.length || filters ? await lookups() : null;
    const rows = (await apiFetch(`/erp/${resource}`)).map((r) => decorate(r, m, look));
    const f = typeof fields === "function" ? fields(m, look) : fields;
    const shown = filter ? rows.filter((r) => filter(r, query)) : rows;
    return {
      tabs: [recordsTab(m, rows.length), ...(canManage ? [newTab(m, newKey)] : [])],
      body: () =>
        tab === "new" && canManage ? (
          <EntryForm fields={f} title={m(newKey)} description={description ? m(description) : undefined} action={actions.createResource.bind(null, resource, booleans)} submitLabel={m(saveKey)} columns={3} successHref={`/finance-ledger/${screen}?tab=records`} />
        ) : (
          <div className="flex flex-col gap-3">
            {filters ? <FilterBar basePath={`/finance-ledger/${screen}`} keep={{ tab: "records" }} values={query} fields={filters(m, look)} labels={{ show: m("search"), reset: m("refresh") }} /> : null}
            <RecordsGrid
              exportName={screen}
              rows={shown}
              columns={columns(m)}
              edit={canManage ? { fields: f, action: actions.updateResource.bind(null, resource, booleans), title: m(editKey), submitLabel: m("save"), columns: 3 } : undefined}
              remove={canManage && deletable ? actions.deleteResource.bind(null, resource) : undefined}
            />
          </div>
        ),
    };
  };
}

const paymentModes = simpleScreen({
  resource: "acc-payment-modes",
  screen: "payment-mode",
  deletable: true,
  booleans: ["active"],
  newKey: "pm.new",
  editKey: "pm.edit",
  saveKey: "pm.save",
  description: "pm.hint",
  fields: (m) => [
    // Brickland's one text box of codes ("BPV, BRV, CV"); the server checks each against the voucher types.
    { name: "voucher_types", label: m("f.voucher_type"), span: 3, help: m("pm.voucher_hint") },
    { name: "name", label: m("f.name"), required: true },
    { name: "short_name", label: m("f.short_name") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r, m) => ({ ...r, voucher_label: r.voucher_types ? r.voucher_types.split(",").map((v) => m(`opt.voucher.${v}`)).join(", ") : "" }),
  filters: (m) => [
    { name: "name", label: m("f.mode_name"), type: "text", placeholder: "Cheque" },
    { name: "voucher", label: m("f.voucher_type"), type: "select", options: [all(m), ...options(m, "voucher", VOUCHER_TYPES)] },
  ],
  filter: (r, q) => (!q.name || r.name.toLowerCase().includes(q.name.toLowerCase())) && (!q.voucher || (r.voucher_types ?? "").split(",").includes(q.voucher)),
  columns: (m) => [
    { key: "name", label: m("f.name") },
    { key: "short_name", label: m("f.short") },
    { key: "voucher_label", label: m("f.voucher_type") },
    { key: "remarks", label: m("f.remarks") },
    statusCol(m),
  ],
});

async function makerChecker({ m, canManage }) {
  const [types, look] = await Promise.all([apiFetch("/accounting/voucher-types"), lookups()]);
  return {
    tabs: null,
    body: () => (
      <SideGrid
        ns="accounting"
        listTitle={m("mc.types")}
        items={types.map((t) => ({ key: t.key, label: m(`opt.voucher.${t.key}`), hint: t.key, count: t.count }))}
        title={m("mc.title")}
        hint={m("mc.hint")}
        emptyTitle={m("mc.empty_title")}
        empty={m("mc.empty")}
        gridEmpty={{ title: m("mc.none_title"), text: m("mc.none") }}
        footerKey="master.mc.footer"
        columns={[
          { key: "user_id", label: m("f.user"), type: "select", options: look.users, width: "w-64" },
          { key: "can_make", label: m("f.maker"), type: "check" },
          { key: "can_check", label: m("f.checker"), type: "check" },
          { key: "_remove", label: m("f.action"), type: "action" },
        ]}
        blank={{ user_id: "", can_make: true, can_check: false }}
        load={actions.loadGrants}
        save={actions.saveGrants}
        canManage={canManage}
        exportName="maker-checker"
      />
    ),
  };
}

async function chartOfAccounts({ m, tab, canManage }) {
  const [rows, tree, look] = await Promise.all([apiFetch("/erp/acc-accounts"), apiFetch("/accounting/tree"), lookups()]);
  const fields = [
    { name: "parent_id", label: m("f.parent"), type: "select", options: [{ value: "", label: m("coa.top_level") }, ...look.parents], placeholder: m("coa.top_level"), help: m("coa.parent_hint") },
    { name: "code", label: m("f.code"), help: m("coa.code_hint") },
    { name: "name", label: m("f.name"), required: true, span: 3 },
    { name: "voucher_ref_type", label: m("f.voucher_ref"), type: "select", options: [{ value: "", label: m("coa.select_type") }, ...options(m, "ref", REF_TYPES)], placeholder: m("coa.select_type") },
    { name: "voucher_ref_id", label: m("f.voucher_for"), type: "select", optionsFrom: { field: "voucher_ref_type", map: look.references }, placeholder: m("coa.select_for"), help: m("coa.ref_hint") },
    { name: "type", label: m("f.type"), type: "select", required: true, options: options(m, "type", ACCOUNT_TYPES), default: "ASSET" },
    { name: "postable", label: m("f.postable"), type: "checkbox", default: true, help: m("coa.postable_hint") },
    { name: "display_order", label: m("f.display_order"), type: "number", default: "0" },
  ];
  return {
    tabs: [recordsTab(m, rows.length), { key: "tree", label: m("coa.tree"), icon: "ListChecks" }, ...(canManage ? [newTab(m, "coa.new")] : [])],
    body: () => {
      if (tab === "new" && canManage) {
        return <EntryForm fields={fields} title={m("coa.new")} description={m("coa.hint")} action={actions.createResource.bind(null, "acc-accounts", ["postable", "active"])} submitLabel={m("coa.save")} columns={3} successHref="/finance-ledger/chart-of-account?tab=records" />;
      }
      if (tab === "tree") {
        return (
          <SideGrid
            ns="accounting"
            listTitle={m("coa.tree")}
            items={tree}
            title={m("coa.entry_section")}
            hint={m("coa.tree_hint")}
            emptyTitle={m("coa.empty_title")}
            empty={m("coa.empty")}
            gridEmpty={{ title: m("coa.no_children_title"), text: m("coa.no_children") }}
            footerKey="master.coa.footer"
            columns={[
              { key: "code", label: m("f.code"), type: "text", mono: true },
              { key: "name", label: m("f.name"), type: "text" },
              { key: "type", label: m("f.type"), type: "text" },
              { key: "postable", label: m("f.postable_short"), type: "check" },
              { key: "_status", from: "active", label: m("f.status"), type: "status", labels: { on: m("active_label"), off: m("inactive_label") } },
            ]}
            load={actions.loadChildren}
            save={actions.noSave}
            canManage={false}
            exportName="chart-of-accounts"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="chart-of-accounts"
          rows={rows.map((r) => ({ ...r, parent_id: r.parent_id ?? "", voucher_ref_type: r.voucher_ref_type ?? "", voucher_ref_id: r.voucher_ref_id ?? "", display_order: String(r.display_order), children: String(r.children), kind: r.postable ? m("coa.ledger_head") : m("coa.group"), ref_label: r.voucher_ref_type ? m(`opt.ref.${r.voucher_ref_type}`) : "" }))}
          columns={[
            { key: "code", label: m("f.code"), type: "mono" },
            { key: "name", label: m("f.name") },
            { key: "parent", label: m("f.parent") },
            { key: "type", label: m("f.type") },
            { key: "postable", label: m("f.postable_short"), type: "yesno" },
            { key: "ref_label", label: m("f.voucher_ref") },
            { key: "children", label: m("f.children"), type: "number", align: "right" },
            { key: "kind", label: m("f.status") },
          ]}
          edit={canManage ? { fields, action: actions.updateResource.bind(null, "acc-accounts", ["postable", "active"]), title: m("coa.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          remove={canManage ? actions.deleteResource.bind(null, "acc-accounts") : undefined}
        />
      );
    },
  };
}

const costCenterGroups = simpleScreen({
  resource: "acc-cost-center-groups",
  screen: "cost-center-group",
  booleans: ["active"],
  newKey: "ccg.new",
  editKey: "ccg.edit",
  saveKey: "ccg.save",
  fields: (m) => [
    { name: "name", label: m("f.cost_center"), required: true },
    { name: "display_order", label: m("f.display_order"), type: "number" },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r) => ({ ...r, display_order: String(r.display_order) }),
  columns: (m) => [
    { key: "name", label: m("f.cost_center") },
    { key: "display_order", label: m("f.display_order"), type: "number", align: "right" },
    { key: "remarks", label: m("f.remarks") },
    statusCol(m),
  ],
});

const costCenters = simpleScreen({
  resource: "acc-cost-centers",
  screen: "cost-center",
  booleans: ["active"],
  newKey: "cc.new",
  editKey: "cc.edit",
  saveKey: "cc.save",
  description: "cc.hint",
  fields: (m, look) => [
    { name: "name", label: m("f.name"), required: true },
    { name: "company_display", label: m("f.company"), type: "select", default: look.company.value, options: [look.company] },
    { name: "factory_id", label: m("f.office"), type: "select", options: [{ value: "", label: m("select") }, ...look.factories] },
    { name: "group_id", label: m("f.cc_group"), type: "select", options: [{ value: "", label: m("select") }, ...look.groups] },
    { name: "dept_section", label: m("f.dept_sec") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r) => ({ ...r, factory_id: r.factory_id ?? "", group_id: r.group_id ?? "" }),
  columns: (m) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "company", label: m("f.company") },
    { key: "office", label: m("f.office") },
    { key: "group", label: m("f.cc_group") },
    { key: "dept_section", label: m("f.dept_sec") },
    statusCol(m),
  ],
});

async function chequeBooks({ m, tab, canManage, searchParams: query = {} }) {
  const [rows, books, look] = await Promise.all([apiFetch("/erp/acc-cheque-books"), apiFetch("/accounting/cheque-books"), lookups()]);
  const byAccount = (key) => Object.fromEntries(look.bank_accounts.map((a) => [a.value, key === "type" ? (a.type ? m(`opt.bank_account.${a.type}`) : "") : a[key]]));
  const fields = [
    { name: "bank_account_id", label: m("f.account_no"), type: "select", required: true, options: look.bank_accounts, placeholder: m("cb.search_account"), help: m("cb.account_hint") },
    { name: "company_display", label: m("f.company_name"), type: "select", default: look.company.value, options: [look.company] },
    { name: "book_no", label: m("f.book_no"), required: true },
    { name: "required_leaf", label: m("f.required_leaf"), type: "number", required: true, default: "50" },
    { name: "prefix", label: m("f.prefix"), required: true, default: "CHQ" },
    { name: "no_from", label: m("f.no_from"), type: "number", required: true },
    { name: "no_to", label: m("f.no_to"), type: "number", required: true, help: m("cb.count_hint") },
    // Brickland shows the leaf count the range gives, read-only.
    { name: "leaf_count", label: m("f.no_of_leaf"), type: "count", range: ["no_from", "no_to"] },
    { name: "type_display", label: m("f.account_type"), type: "derived", from: "bank_account_id", values: byAccount("type") },
    { name: "bank_display", label: m("f.bank_name"), type: "derived", from: "bank_account_id", values: byAccount("bank") },
    { name: "branch_display", label: m("f.branch_name"), type: "derived", from: "bank_account_id", values: byAccount("branch") },
    { name: "requisition_date", label: m("f.requisition_date"), type: "date", required: true },
    { name: "est_receive_date", label: m("f.est_receive_date"), type: "date" },
    { name: "receive_date", label: m("f.receive_date"), type: "date", required: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea", span: 3 },
    { name: "finalized", label: m("f.finalize"), type: "checkbox", help: m("cb.finalize_hint") },
  ];
  const shown = rows.filter((r) => (!query.bank || r.bank_id === query.bank) && (!query.branch || r.branch_id === query.branch) && (!query.account || (r.account_no ?? "").includes(query.account))
    && (!query.account_type || (r.account_type ? m(`opt.bank_account.${r.account_type}`) : "").toLowerCase().includes(query.account_type.toLowerCase()))
    && (!query.cheque || (Number(query.cheque) >= r.no_from && Number(query.cheque) <= r.no_to)) && (!query.finalized || String(r.finalized) === query.finalized)
    && (!query.from || r.receive_date >= query.from) && (!query.to || r.receive_date <= query.to));
  return {
    tabs: [recordsTab(m, rows.length), { key: "leaves", label: m("cb.leaf_info"), icon: "ListChecks" }, ...(canManage ? [newTab(m, "cb.new")] : [])],
    body: () => {
      if (tab === "new" && canManage) {
        return <EntryForm fields={fields} title={m("cb.new")} description={m("cb.hint")} action={actions.createResource.bind(null, "acc-cheque-books", ["finalized"])} submitLabel={m("cb.save")} columns={3} successHref="/finance-ledger/cheque-book-registers?tab=records" />;
      }
      if (tab === "leaves") {
        return (
          <SideGrid
            ns="accounting"
            listTitle={m("cb.books")}
            items={books}
            title={m("cb.leaf_info")}
            hint={m("cb.leaf_hint")}
            emptyTitle={m("cb.empty_title")}
            empty={m("cb.empty")}
            gridEmpty={{ title: m("cb.no_leaves_title"), text: m("cb.no_leaves") }}
            footerKey="master.cb.footer"
            columns={[
              { key: "cheque_no", label: m("f.cheque_no"), type: "text", mono: true },
              { key: "status", label: m("f.leaf_status"), type: "text" },
              { key: "active", label: m("f.active"), type: "check" },
            ]}
            load={actions.loadLeaves}
            save={actions.saveLeaves}
            canManage={canManage}
            exportName="cheque-leaves"
          />
        );
      }
      return (
        <div className="flex flex-col gap-3">
          <FilterBar
            basePath="/finance-ledger/cheque-book-registers"
            keep={{ tab: "records" }}
            values={query}
            fields={[
              { name: "company", label: m("f.company"), type: "select", options: [look.company] },
              { name: "bank", label: m("f.bank_name"), type: "select", options: [all(m), ...look.banks] },
              { name: "branch", label: m("f.branch_name"), type: "select", options: [all(m), ...look.all_branches] },
              { name: "account", label: m("f.account_no_short"), type: "text" },
              { name: "account_type", label: m("f.account_type"), type: "text" },
              { name: "cheque", label: m("f.cheque_no"), type: "text" },
              { name: "finalized", label: m("f.with_finalized"), type: "select", options: [all(m), { value: "true", label: m("yes") }, { value: "false", label: m("no") }] },
              { name: "from", label: m("f.from_date"), type: "date" },
              { name: "to", label: m("f.to_date"), type: "date" },
            ]}
            labels={{ show: m("search"), reset: m("refresh") }}
          />
          <RecordsGrid
            exportName="cheque-books"
            rows={shown.map((r) => ({ ...r, est_receive_date: r.est_receive_date ?? "", type_label: r.account_type ? m(`opt.bank_account.${r.account_type}`) : "", range: `${r.prefix}${r.no_from} – ${r.prefix}${r.no_to}`, required_leaf: String(r.required_leaf), no_from: String(r.no_from), no_to: String(r.no_to), leaves_label: `${r.used} / ${r.leaves}` }))}
            columns={[
              { key: "book_no", label: m("f.book_no"), type: "mono" },
              { key: "account_no", label: m("f.account_no"), type: "mono" },
              { key: "bank", label: m("f.bank_name") },
              { key: "branch", label: m("f.branch_name") },
              { key: "type_label", label: m("f.account_type") },
              { key: "range", label: m("f.leaf_range") },
              { key: "leaves_label", label: m("f.leaves_used"), align: "right" },
              { key: "receive_date", label: m("f.receive_date"), type: "date" },
              { key: "finalized", label: m("f.finalized"), type: "yesno" },
            ]}
            edit={canManage ? { fields, action: actions.updateResource.bind(null, "acc-cheque-books", ["finalized"]), title: m("cb.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          />
        </div>
      );
    },
  };
}

async function banks({ m, tab, canManage }) {
  const [rows, bankList, accounts, look] = await Promise.all([apiFetch("/erp/acc-banks"), apiFetch("/accounting/banks"), apiFetch("/erp/acc-bank-accounts"), lookups()]);
  const fields = [
    { name: "name", label: m("f.bank_name_en"), required: true },
    { name: "name_bn", label: m("f.bank_name_bn") },
    { name: "prefix", label: m("f.bank_prefix"), required: true },
    { name: "country_id", label: m("f.country"), type: "select", required: true, options: look.countries },
    { name: "swift_code", label: m("f.swift") },
    { name: "iban", label: m("f.iban") },
    { name: "call_center", label: m("f.call_center") },
    { name: "web_url", label: m("f.web_url") },
    { name: "is_local", label: m("f.is_local"), type: "checkbox", default: true },
    { name: "is_treasury", label: m("f.is_treasury"), type: "checkbox" },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  const accountFields = [
    { name: "bank_id", label: m("f.bank_name"), type: "select", required: true, options: look.banks },
    { name: "branch_id", label: m("f.branch_name"), type: "select", optionsFrom: { field: "bank_id", map: look.branches } },
    { name: "account_no", label: m("f.account_no"), required: true },
    { name: "account_name", label: m("f.account_name"), required: true },
    { name: "account_type", label: m("f.account_type"), type: "select", required: true, options: options(m, "bank_account", BANK_ACCOUNT_TYPES) },
    { name: "currency", label: m("f.currency"), type: "select", required: true, options: look.currencies, default: "BDT" },
    { name: "ledger_id", label: m("f.ledger"), type: "select", options: [{ value: "", label: m("select") }, ...look.ledgers] },
    { name: "active", label: m("f.is_active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [recordsTab(m, rows.length), { key: "branches", label: m("bank.branches"), icon: "ListChecks" }, { key: "accounts", label: m("bank.accounts"), icon: "Wallet", count: accounts.length }, ...(canManage ? [newTab(m, "bank.new")] : [])],
    body: () => {
      if (tab === "new" && canManage) {
        return <EntryForm fields={fields} title={m("bank.new")} description={m("bank.hint")} action={actions.createResource.bind(null, "acc-banks", ["is_local", "is_treasury", "active"])} submitLabel={m("bank.save")} columns={3} successHref="/finance-ledger/manage-bank?tab=records" />;
      }
      if (tab === "branches") {
        return (
          <SideGrid
            ns="accounting"
            listTitle={m("bank.list")}
            items={bankList}
            title={m("bank.branch_title")}
            hint={m("bank.branch_hint")}
            emptyTitle={m("bank.empty_title")}
            empty={m("bank.empty")}
            gridEmpty={{ title: m("bank.no_branches_title"), text: m("bank.no_branches") }}
            footerKey="master.bank.footer"
            addLabel={m("bank.add_branch")}
            columns={[
              { key: "code", label: m("f.branch_code"), type: "input", width: "w-28" },
              { key: "name", label: m("f.branch_name"), type: "input", width: "w-44" },
              { key: "routing_no", label: m("f.routing_no"), type: "input", width: "w-32" },
              { key: "swift_code", label: m("f.swift"), type: "input", width: "w-32" },
              { key: "address", label: m("f.address"), type: "input", width: "w-48" },
              { key: "contact_person", label: m("f.contact_person"), type: "input", width: "w-40" },
              { key: "phone", label: m("f.phone"), type: "input", width: "w-32" },
              { key: "active", label: m("f.active"), type: "check" },
              { key: "_remove", label: m("f.action"), type: "action", blockedBy: "accounts" },
            ]}
            blank={{ code: "", name: "", routing_no: "", swift_code: "", address: "", contact_person: "", phone: "", email: "", active: true, accounts: 0 }}
            load={actions.loadBranches}
            save={actions.saveBranches}
            canManage={canManage}
            exportName="bank-branches"
          />
        );
      }
      if (tab === "accounts") {
        return (
          <div className="flex flex-col gap-3">
            {canManage ? (
              <div className="flex justify-end">
                <DialogFormButton variant="primary" label={m("bank.new_account")} title={m("bank.new_account")} description={m("bank.account_hint")} fields={accountFields} action={actions.createResource.bind(null, "acc-bank-accounts", ["active"])} submitLabel={m("save")} />
              </div>
            ) : null}
            <RecordsGrid
              exportName="bank-accounts"
              rows={accounts.map((a) => ({ ...a, branch_id: a.branch_id ?? "", ledger_id: a.ledger_id ?? "", type_label: m(`opt.bank_account.${a.account_type}`) }))}
              columns={[
                { key: "account_no", label: m("f.account_no"), type: "mono" },
                { key: "account_name", label: m("f.account_name") },
                { key: "bank", label: m("f.bank_name") },
                { key: "branch", label: m("f.branch_name") },
                { key: "type_label", label: m("f.account_type") },
                { key: "currency", label: m("f.currency"), type: "mono" },
                { key: "ledger", label: m("f.ledger") },
                statusCol(m),
              ]}
              edit={canManage ? { fields: accountFields, action: actions.updateResource.bind(null, "acc-bank-accounts", ["active"]), title: m("bank.edit_account"), submitLabel: m("save"), columns: 2 } : undefined}
            />
          </div>
        );
      }
      return (
        <RecordsGrid
          exportName="banks"
          rows={rows.map((r) => ({ ...r, country_id: r.country_id ?? "", branches: String(r.branches), active_yn: r.active }))}
          columns={[
            { key: "code", label: m("f.bank_code"), type: "mono" },
            { key: "name", label: m("f.bank_name_en") },
            { key: "swift_code", label: m("f.swift"), type: "mono" },
            { key: "country", label: m("f.country") },
            { key: "is_local", label: m("f.is_local"), type: "yesno" },
            { key: "branches", label: m("bank.branches"), type: "number", align: "right" },
            { key: "active_yn", label: m("f.active"), type: "badge", labels: yn(m) },
            statusCol(m),
          ]}
          edit={canManage ? { fields, action: actions.updateResource.bind(null, "acc-banks", ["is_local", "is_treasury", "active"]), title: m("bank.edit"), submitLabel: m("save"), columns: 3 } : undefined}
          remove={canManage ? actions.deleteResource.bind(null, "acc-banks") : undefined}
        />
      );
    },
  };
}

const fiscalYears = simpleScreen({
  resource: "acc-fiscal-years",
  screen: "fiscal-year",
  deletable: true,
  booleans: ["closed"],
  newKey: "fy.new",
  editKey: "fy.edit",
  saveKey: "fy.save",
  description: "fy.hint",
  fields: (m, look) => [
    { name: "company_display", label: m("f.company"), type: "select", default: look.company.value, options: [look.company] },
    { name: "name", label: m("f.fy_name"), required: true, placeholder: "FY2028-29" },
    { name: "fy_start", label: m("f.fy_start"), type: "date", required: true },
    { name: "fy_end", label: m("f.fy_end"), type: "date", required: true },
    { name: "cal_start", label: m("f.cal_start"), type: "date", required: true, help: m("fy.cal_hint") },
    { name: "cal_end", label: m("f.cal_end"), type: "date", required: true },
    { name: "closed", label: m("f.closed"), type: "checkbox" },
    { name: "remarks", label: m("f.remarks"), type: "textarea", span: 3 },
  ],
  decorate: (r, m) => ({ ...r, state: r.closed ? m("fy.closed") : m("fy.open") }),
  columns: (m) => [
    { key: "company", label: m("f.company_name") },
    { key: "name", label: m("f.fy_name") },
    { key: "fy_start", label: m("f.fy_start_col"), type: "date" },
    { key: "fy_end", label: m("f.fy_end_col"), type: "date" },
    { key: "cal_start", label: m("f.cal_start_col"), type: "date" },
    { key: "cal_end", label: m("f.cal_end_col"), type: "date" },
    { key: "closed", label: m("f.closed"), type: "yesno" },
    { key: "created_by", label: m("f.created_by") },
    { key: "state", label: m("f.status") },
  ],
});

const accountClasses = simpleScreen({
  resource: "acc-account-classes",
  screen: "coa-type-configuration",
  booleans: [],
  newKey: "ct.new",
  editKey: "ct.edit",
  saveKey: "ct.save",
  description: "ct.hint",
  deletable: true,
  fields: (m, look) => [
    { name: "account_type", label: m("f.account_class"), type: "select", required: true, options: options(m, "class", CLASS_TYPES) },
    { name: "layer", label: m("f.layer"), type: "select", required: true, options: options(m, "layer", LAYERS) },
    { name: "account_id", label: m("f.coa"), type: "select", required: true, options: look.accounts },
  ],
  decorate: (r, m) => ({ ...r, type_label: m(`opt.class.${r.account_type}`), layer_label: m(`opt.layer.${r.layer}`) }),
  columns: (m) => [
    { key: "type_label", label: m("f.account_class") },
    { key: "layer_label", label: m("f.layer") },
    { key: "account", label: m("f.coa_col") },
  ],
});

const complianceRules = simpleScreen({
  resource: "acc-compliance-rules",
  screen: "compliance-ledger-rules",
  booleans: [],
  newKey: "cr.new",
  editKey: "cr.edit",
  saveKey: "cr.save",
  description: "cr.hint",
  deletable: true,
  fields: (m, look) => [
    { name: "account_id", label: m("f.ledger_head"), type: "select", required: true, options: look.ledgers },
    { name: "pct", label: m("f.pct"), type: "number", required: true, step: "0.01", suffix: "%" },
  ],
  decorate: (r) => ({ ...r, pct: String(Number(r.pct)) }),
  columns: (m) => [
    { key: "account", label: m("f.ledger_head") },
    { key: "pct", label: m("f.pct"), type: "number", align: "right" },
  ],
});

const SCREENS = {
  "payment-mode": paymentModes,
  "checker-maker": makerChecker,
  "chart-of-account": chartOfAccounts,
  "cost-center-group": costCenterGroups,
  "cost-center": costCenters,
  "cheque-book-registers": chequeBooks,
  "manage-bank": banks,
  "fiscal-year": fiscalYears,
  "coa-type-configuration": accountClasses,
  "compliance-ledger-rules": complianceRules,
  "draft-voucher": voucherScreen(async () => ({ initial: await apiFetch("/accounting/vouchers?scope=draft") }), DraftVoucherDesk, "accounting.voucher.manage"),
  voucher: voucherScreen(async () => ({ initial: await apiFetch("/accounting/vouchers") }), VoucherEntryDesk, "accounting.voucher.manage"),
  "voucher-post-unpost": voucherScreen(async () => ({ initial: await apiFetch("/accounting/vouchers"), board: await apiFetch("/accounting/vouchers/posting") }), PostingDesk, "accounting.voucher.post", "canPost"),
  "mrr-combine-bill": async ({ permissions, searchParams }) => {
    const [initial, opt] = await Promise.all([apiFetch("/accounting/mrr-bills"), apiFetch("/accounting/mrr-bills/options")]);
    return { tabs: null, body: () => <MrrBillDesk initial={initial} opt={opt} canManage={permissions.includes("accounting.bill.manage")} act={vo} query={searchParams} /> };
  },
  "stock-closing": periodScreen(async () => {
    const [initial, opt] = await Promise.all([apiFetch("/accounting/period/stock"), apiFetch("/accounting/period/stock/options")]);
    return { initial, opt };
  }, StockClosingDesk),
  "bank-reconciliation": periodScreen(async () => {
    const [initial, board, opt] = await Promise.all([apiFetch("/accounting/period/bank"), apiFetch("/accounting/period/bank/board"), apiFetch("/accounting/period/bank/options")]);
    return { initial, board, opt };
  }, BankRecDesk),
  "fiscal-year-close": periodScreen(async () => {
    const [initial, opt] = await Promise.all([apiFetch("/accounting/period/fiscal-years"), apiFetch("/accounting/period/fiscal-years/options")]);
    return { initial, opt };
  }, FyCloseDesk),
  "cheque-issue": async ({ permissions, searchParams }) => {
    const [initial, opt] = await Promise.all([apiFetch("/accounting/cheques"), apiFetch("/accounting/cheques/options")]);
    return { tabs: null, body: () => <ChequeIssueDesk initial={initial} opt={opt} canManage={permissions.includes("accounting.cheque.manage")} act={vo} query={searchParams} /> };
  },
  "cheque-cancellation": async ({ permissions }) => {
    const [initial, opt] = await Promise.all([apiFetch("/accounting/cheques/cancellations"), apiFetch("/accounting/cheques/options")]);
    return { tabs: null, body: () => <ChequeCancelDesk initial={initial} opt={opt} canManage={permissions.includes("accounting.cheque.manage")} act={vo} /> };
  },
  "voucher-activity": auditScreen("activity", "voucher-activity"),
  "voucher-delete-history": auditScreen("deleted", "voucher-delete-history"),
  "voucher-change-logs": auditScreen("amended", "voucher-change-logs"),
  "accounting-reports": reportScreen("statements"),
  "ledger-wise-cost-center-summary": reportScreen("ledger-cc"),
  "cost-center-wise-ledger-summary": reportScreen("cc-ledger"),
  "receive-payment": reportScreen("receive-payment"),
  "accounting-misc-report": reportScreen("misc"),
  "compliance-vouchers": async () => {
    const initial = await apiFetch("/accounting/vouchers/statutory");
    return { tabs: null, body: () => <StatutoryRegister initial={initial} /> };
  },
};

/** Voucher Entry (23/02) screens: their words live under "vo". */
const VO_SCREENS = ["draft-voucher", "voucher", "voucher-post-unpost", "mrr-combine-bill", "compliance-vouchers", "stock-closing", "bank-reconciliation", "fiscal-year-close", "cheque-issue", "cheque-cancellation", "voucher-activity", "voucher-delete-history", "voucher-change-logs", "accounting-reports", "ledger-wise-cost-center-summary", "cost-center-wise-ledger-summary", "receive-payment", "accounting-misc-report"];

/** Voucher Entry (23/02): the desk draws its own tabs, switching in place. */
function voucherScreen(load, Desk, permission, prop = "canManage") {
  return async function render({ permissions, searchParams }) {
    const [data, opt] = await Promise.all([load(), apiFetch("/accounting/vouchers/options")]);
    return { tabs: null, body: () => <Desk {...data} opt={opt} {...{ [prop]: permissions.includes(permission) }} act={vo} query={searchParams} /> };
  };
}

/** Voucher Audit (23/06): one read-only register over the voucher log. */
function auditScreen(kind, key) {
  return async function render() {
    const [initial, opt] = await Promise.all([apiFetch(`/accounting/voucher-audit?kind=${kind}`), apiFetch("/accounting/vouchers/options")]);
    return { tabs: null, body: () => <VoucherAuditRegister kind={key} initial={initial} opt={opt} /> };
  };
}

/** Reports (23/07): Run History, (Selection of Parameters,) New report run — switching in place. */
function reportScreen(screen) {
  return async function render({ permissions }) {
    const [initial, opt] = await Promise.all([apiFetch(`/accounting/reports/runs?screen=${screen}`), apiFetch(`/accounting/reports/options?screen=${screen}`)]);
    return { tabs: null, body: () => <FinanceReportDesk screen={screen} initial={initial} opt={opt} canManage={permissions.includes("accounting.report.manage")} act={vo} /> };
  };
}

/** Period Close (23/04): the desk draws its own tabs. */
function periodScreen(load, Desk) {
  return async function render({ permissions }) {
    const data = await load();
    return { tabs: null, body: () => <Desk {...data} canManage={permissions.includes("accounting.period.manage")} act={vo} /> };
  };
}

/** Every Finance & Ledger master opens on Records, as Brickland's do. */
const DEFAULT_TABS = {};

export { SCREENS, DEFAULT_TABS, VO_SCREENS };
