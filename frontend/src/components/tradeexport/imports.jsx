"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Plus, RefreshCw } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { day, EditGrid, has, money, qty } from "@/components/sales-shipment/shared";
import { amt, Box, clean, companyF, dateF, Desk, finalCol, finalF, n, nulled, ReadGrid, refs, refShort, Row, selF, textF, today, uid, useW, withFinal } from "@/components/tradeexport/operations";

/*
 * Trade & Export → Import (AnnotechERP 22/03), and the two declarations of
 * Operations (22/02) that read it: Import L/C Proforma Invoice (the
 * supplier's POs on the left, their lines into the PI), Import L/C Opening,
 * Customs Bill of Entry (the goods received, taken line by line), Import
 * Bill Acceptance, Utilisation Declaration and its Transfer.
 */

const P = "/tradeexport/ops";
const opts = (list) => list.map((v) => ({ value: v, label: v }));
const lc = (act) => ({ read: act.read, write: act.write });

async function upload(id, file) {
  const body = new FormData();
  body.append("upload", file);
  const r = await fetch(`/api/trade-export/bill-attachment?kind=entry&id=${encodeURIComponent(id)}`, { method: "POST", body });
  return r.ok ? { status: "success" } : { status: "error", message: (await r.json().catch(() => ({})))?.message };
}

/* ───────────────────────── Import L/C Proforma Invoice ───────────────────────── */

function ImportPiForm({ w, form, set, patch, locked, opt, act, editing, F }) {
  const [pos, setPos] = useState([]);
  const [search, setSearch] = useState("");
  const except = editing ? `?except=${editing.id}` : "";
  const loadPos = (supplier) => act.read(`${P}/imp-pis/pos?supplier=${encodeURIComponent(supplier)}`).then((r) => r?.status === "success" && setPos(r.data));
  useEffect(() => {
    if (form.supplier_id) loadPos(form.supplier_id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pickPo = async (po) => {
    if (locked) return;
    const res = await act.read(`${P}/imp-pis/po-lines/${po.kind}/${po.id}${except}`);
    if (res?.status !== "success") return;
    const fresh = res.data.filter((l) => l.balance > 0 && !form.lines.some((x) => x.po_line_id === l.po_line_id)).map((l) => ({ _k: uid(), po_kind: l.po_kind, po_line_id: l.po_line_id, po_no: l.po_no, item: l.item, qty: l.balance, uom: l.uom, unit_price: l.unit_price }));
    set("lines", [...form.lines, ...fresh]);
  };
  const net = form.lines.reduce((a, l) => a + n(l.qty) * n(l.unit_price), 0);
  const shown = pos.filter((p) => has(p.po_no, search));
  return (
    <>
      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
        <Box legend={w("box.po_list")} grid={false}>
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={w("search")} aria-label={w("search")} className="mb-2" />
          <ReadGrid
            w={w}
            empty={w("no_items")}
            rows={shown}
            cols={[
              { key: "po_no", label: w("col.po_no"), render: (r) => <button type="button" className="font-mono text-xs text-brand hover:underline" onClick={() => pickPo(r)}>{r.po_no}</button> },
              { key: "po_date", label: w("col.issue_date"), render: (r) => day(r.po_date) },
              { key: "inco_term", label: w("f.inco_term") },
              { key: "payment_term", label: w("f.payment_term") },
            ]}
          />
        </Box>
        <Box legend={w("box.master_information")}>
          {F.sel("supplier_id", w("f.supplier"), opt.suppliers, { req: true, onPick: (v) => { patch({ supplier_id: v, lines: [] }); if (v) loadPos(v); else setPos([]); } })}
          {F.sel("export_lc_id", w("f.export_lc_contract"), opt.export_lcs)}
          {F.text("pi_no", w("f.import_pi_no"), { req: true })}
          {F.date("pi_date", w("f.pi_date"), { req: true })}
          {F.num("pi_amount", w("f.pi_amount"), { req: true })}
          {F.read(w("f.pi_tracking"), form.tracking_no || w("generated_on_save"))}
          {F.area("remarks", w("f.terms_description"))}
        </Box>
      </div>
      <EditGrid
        w={w}
        title={w("box.po_details")}
        editable={!locked}
        addLabel={w("add_to_pi")}
        onAdd={() => set("lines", [...form.lines, { _k: uid(), po_no: "", item: "", qty: "", uom: "", unit_price: "" }])}
        rows={form.lines}
        setRows={(v) => set("lines", v)}
        emptyNote={w("no_items")}
        summaries={[[w("f.net_pi"), money(net)]]}
        cols={[
          { key: "po_no", label: w("col.po_hash"), width: 160, lockWhen: (r) => Boolean(r.po_line_id) },
          { key: "item", label: w("col.item"), width: 280, lockWhen: (r) => Boolean(r.po_line_id) },
          { key: "qty", label: w("col.pi_qty"), type: "number", align: "right" },
          { key: "uom", label: w("col.uom"), type: "select", options: opt.uoms.map((u) => ({ value: u.code, label: u.code })), width: 100, lockWhen: (r) => Boolean(r.po_line_id) },
          { key: "unit_price", label: w("col.unit_price"), type: "number", align: "right" },
          { key: "a", label: w("col.amount"), align: "right", read: (r) => money(n(r.qty) * n(r.unit_price)) },
        ]}
      />
      <Box legend={w("box.action_status")}>{F.read(w("f.action_status"), w(`istatus.${form.status || "DRAFT"}`), { req: true })}</Box>
    </>
  );
}

function ImportPiDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  return (
    <Desk
      w={w}
      path={`${P}/imp-pis`}
      initial={initial}
      decorate={(r) => ({ ...r, status_label: w(`istatus.${r.status}`), pi_info: `${r.pi_no} · ${r.tracking_no}` })}
      query={query}
      refKey="pi_no"
      exportName="import-pi"
      opt={opt}
      canManage={canManage}
      act={lc(act)}
      Form={ImportPiForm}
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ pi_date: today(opt), status: "DRAFT", lines: [] })}
      toForm={(d) => ({ ...nulled(d), lines: d.lines.map((l) => ({ ...nulled(l), _k: uid() })) })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/imp-pis/${e.id}` : `${P}/imp-pis`, { ...clean(f, ["supplier_id", "pi_no", "export_lc_id", "pi_date", "pi_amount", "remarks"]), lines: f.lines.map(({ _k, ...l }) => ({ ...l, po_kind: l.po_kind || null, po_line_id: l.po_line_id || null })) })}
      extraActions={canManage ? [{ key: "approve", label: w("approve"), when: (r) => !r.approved, confirm: w("confirm.approve"), done: w("done.approve"), run: (_no, _e, r) => act.write("POST", `${P}/imp-pis/${r.id}/approve`) }] : []}
      filters={[textF("no", w("col.pi_no"), (r) => r.pi_no), textF("tr", w("f.pi_tracking_no"), (r) => r.tracking_no), textF("po", w("col.po_no"), (r) => r.po_no), dateF("af", w("f.approved_from"), (r) => r.approved_on, "from"), dateF("at", w("f.approved_to"), (r) => r.approved_on, "to"), dateF("cf", w("f.creation_from"), (r) => r.created_on, "from"), dateF("ct", w("f.creation_to"), (r) => r.created_on, "to"), dateF("pf", w("f.pi_from"), (r) => r.pi_date, "from"), dateF("pt", w("f.pi_to"), (r) => r.pi_date, "to"), selF("sup", w("f.supplier"), opt.suppliers, (r, v) => r.supplier_id === v), selF("exp", w("f.export_lc"), opt.export_lcs, (r, v) => r.export_lc_id === v)]}
      columns={[{ key: "status_label", label: w("col.status"), type: "badge" }, { key: "pi_info", label: w("col.pi_info") }, { key: "po_no", label: w("col.po_no") }, { key: "created_on", label: w("col.pi_create"), type: "date" }, { key: "approved_on", label: w("col.pi_approved"), type: "date" }, { key: "supplier", label: w("col.supplier_name") }, { key: "pi_date", label: w("col.pi_date"), type: "date" }, amt("pi_amount", w("col.pi_amount")), { key: "lc_info", label: w("col.lc_info") }]}
    />
  );
}

/* ───────────────────────── Import L/C Opening ───────────────────────── */

const ILC_FIELDS = ["ref_no", "lc_type", "commodity_type", "supplier_id", "buyer_id", "export_lc_id", "sender_branch_id", "receiving_branch_id", "lean_branch_id", "issue_date", "expire_date", "ref_date", "currency", "inco_term", "delivery_place", "payment_term_description", "lcaf_no", "insurance", "credit_tolerance_pct", "btma_cert_no", "btma_cert_date", "port_of_loading", "port_of_discharge", "last_shipment_date", "time_of_shipment", "country_of_origin_id", "bank_ref_no", "lc_bank_code", "lc_year", "lc_code", "lc_sl_no", "place_of_expiry_id", "place_of_issue_id", "lc_value", "payment_term", "tenor_days", "irc_no", "shipment_mode", "freight_cost", "lc_status", "bin_no", "status"];
const LC_STATUS = ["APPROVED", "APPLIED", "AMENDED", "CLOSED"];

function ImportLcForm({ w, form, set, patch, locked, opt, act, F }) {
  const [pick, setPick] = useState("");
  const [piLc, setPiLc] = useState("");
  const pis = opt.import_pis.filter((p) => (!form.supplier_id || p.supplier_id === form.supplier_id) && (!piLc || p.export_lc_id === piLc) && (!p.lc_id || p.lc_id === form.id) && !form.pis.includes(p.value));
  const addPi = async () => {
    if (!pick) return;
    const res = await act.read(`${P}/imp-pis/${pick}/row`);
    if (res?.status === "success") {
      patch({ pis: [...form.pis, pick], pi_rows: [...form.pi_rows, res.data] });
      setPick("");
    }
  };
  const part = (k, ph) => <Input value={form[k] ?? ""} disabled={locked} placeholder={ph} onChange={(e) => set(k, e.target.value)} aria-label={ph} />;
  return (
    <>
      <Box legend={w("box.lc_head")}>
        {F.sel("lc_type", w("f.lc_type"), refs(opt, "LC_TYPE"), { req: true })}
        {F.sel("commodity_type", w("f.commodity"), opts(opt.commodities), { req: true })}
        {F.chk("is_cash", w("f.is_cash"))}
      </Box>
      <Box legend={w("box.master_information")}>
        {F.sel("_c", w("f.applicant"), [opt.company], { req: true, readOnly: true, placeholder: opt.company.label })}
        {F.text("ref_no", w("f.imp_ref_no"), { req: true })}
        {F.sel("supplier_id", w("f.benef_supplier"), opt.suppliers, { req: true })}
        {F.sel("country_of_origin_id", w("f.country_origin"), opt.countries)}
        {F.sel("buyer_id", w("f.export_buyer"), opt.buyers, { req: true })}
        {F.sel("export_lc_id", w("f.export_master_lc"), opt.export_lcs.filter((e) => !form.buyer_id || e.buyer_id === form.buyer_id), { req: !form.is_cash })}
        {F.sel("sender_branch_id", w("f.sender_bank"), opt.branches, { req: true })}
        {F.text("bank_ref_no", w("f.bank_ref"))}
        {F.date("issue_date", w("f.date_of_issue"), { req: true })}
        <Row label={w("f.import_lc_no")}>
          <div className="grid grid-cols-4 gap-2">
            {part("lc_bank_code", w("ph.bank_code"))}
            {part("lc_year", w("ph.year"))}
            {part("lc_code", w("ph.lc_code"))}
            {part("lc_sl_no", w("ph.lc_sl"))}
          </div>
        </Row>
        {F.date("expire_date", w("f.date_of_expiry"), { req: true })}
        {F.sel("place_of_expiry_id", w("f.place_of_expiry"), opt.countries)}
        {F.sel("currency", w("f.currency"), opt.currencies, { req: true })}
        {F.num("lc_value", w("f.lc_amount"), { req: true })}
        {F.date("ref_date", w("f.imp_ref_date"))}
        {F.sel("place_of_issue_id", w("f.place_of_issue"), opt.countries)}
        {F.sel("receiving_branch_id", w("f.receiving_bank"), opt.branches)}
        <Row label={w("f.tenor")} req>
          <div className="grid grid-cols-[1fr_6rem] gap-2">
            <Select value={form.payment_term ?? ""} disabled={locked} onValueChange={(v) => set("payment_term", v ?? "")} options={refs(opt, "PAYMENT_TERM")} placeholder={w("select")} aria-label={w("f.tenor")} />
            <Input type="number" value={form.tenor_days ?? ""} disabled={locked} placeholder={w("ph.tenor")} onChange={(e) => set("tenor_days", e.target.value)} aria-label={w("ph.tenor")} />
          </div>
        </Row>
        <Row label={w("f.lean_bank")}>
          <div className="flex items-center gap-3">
            <div className="min-w-0 flex-1">
              <Select value={form.lean_branch_id ?? ""} disabled={locked} onValueChange={(v) => patch(form.same_receiver_bank ? { lean_branch_id: v ?? "", receiving_branch_id: v ?? "" } : { lean_branch_id: v ?? "" })} options={opt.branches} placeholder={w("select")} aria-label={w("f.lean_bank")} />
            </div>
            <label className="flex shrink-0 items-center gap-2 text-sm">
              <Checkbox checked={Boolean(form.same_receiver_bank)} disabled={locked} onCheckedChange={(v) => patch(v === true ? { same_receiver_bank: true, receiving_branch_id: form.lean_branch_id } : { same_receiver_bank: false })} aria-label={w("f.same_receiver")} /> {w("f.same_receiver")}
            </label>
          </div>
        </Row>
        {F.text("irc_no", w("f.irc_no"))}
        <Row label={w("f.inco_delivery")} req>
          <div className="grid grid-cols-2 gap-2">
            <Select value={form.inco_term ?? ""} disabled={locked} onValueChange={(v) => set("inco_term", v ?? "")} options={refs(opt, "INCO_TERM")} placeholder={w("select")} aria-label={w("f.inco_term")} />
            <Select value={form.delivery_place ?? ""} disabled={locked} onValueChange={(v) => set("delivery_place", v ?? "")} options={refs(opt, "DELIVERY_PLACE")} placeholder={w("select")} aria-label={w("f.delivery_place")} />
          </div>
        </Row>
        {F.sel("shipment_mode", w("f.shipping_mode"), refs(opt, "SHIPMENT_MODE"))}
        {F.area("payment_term_description", w("f.payment_desc"), { wide: false })}
        {F.num("freight_cost", w("f.freight_cost"))}
        {F.text("lcaf_no", w("f.lcaf"))}
        {F.date("btma_cert_date", w("f.btma_date"))}
        {F.text("insurance", w("f.insurance_company"))}
        {F.sel("port_of_discharge", w("f.port_of_discharge"), refs(opt, "DELIVERY_PLACE"), { req: true })}
        {F.num("credit_tolerance_pct", w("f.credit_tolerance"))}
        {F.sel("lc_status", w("f.lc_status"), LC_STATUS.map((v) => ({ value: v, label: w(`lcstatus.${v}`) })), { req: true })}
        <Row label={w("f.shipment")}>
          <div className="flex gap-5">
            {["partial_shipment", "trans_shipment"].map((k) => (
              <label key={k} className="flex items-center gap-2 text-sm">
                <Checkbox checked={Boolean(form[k])} disabled={locked} onCheckedChange={(v) => set(k, v === true)} aria-label={w(`f.${k}`)} /> {w(`f.${k}`)}
              </label>
            ))}
          </div>
        </Row>
        {F.text("bin_no", w("f.bin_no"))}
        {F.text("btma_cert_no", w("f.btma_no"))}
        {F.sel("port_of_loading", w("f.port_of_loading"), refs(opt, "DELIVERY_PLACE"), { req: true })}
        {F.date("last_shipment_date", w("f.last_shipment"), { req: true })}
        {F.text("time_of_shipment", w("f.time_shipment"))}
      </Box>
      <EditGrid
        w={w}
        title={w("box.commodity")}
        editable={!locked}
        addLabel={w("add")}
        onAdd={() => set("commodities", [...form.commodities, { _k: uid(), name: "", qty: "", unit: "" }])}
        rows={form.commodities}
        setRows={(v) => set("commodities", v)}
        emptyNote={w("no_items")}
        cols={[{ key: "name", label: w("f.name"), width: 320 }, { key: "qty", label: w("col.qty"), type: "number", align: "right" }, { key: "unit", label: w("col.unit"), type: "select", options: opt.uoms.map((u) => ({ value: u.code, label: u.code })), width: 120 }]}
      />
      <Box legend={w("box.pi_product")} grid={false}>
        {!locked ? (
          <div className="mb-3 grid gap-3 lg:grid-cols-2">
            <Row label={w("f.export_lc_short")}>
              <Select value={piLc} onValueChange={(v) => setPiLc(v ?? "")} options={[{ value: "", label: w("all") }, ...opt.export_lcs]} placeholder={w("select")} aria-label={w("f.export_lc_short")} />
            </Row>
            <Row label={w("f.import_pi")} req>
              <div className="flex items-center gap-2">
                <div className="min-w-0 flex-1">
                  <Select value={pick} onValueChange={(v) => setPick(v ?? "")} options={pis} placeholder={w("select")} aria-label={w("f.import_pi")} />
                </div>
                <Button type="button" variant="outline" className="shrink-0" onClick={addPi}>
                  <Plus /> {w("add_pi")}
                </Button>
              </div>
            </Row>
          </div>
        ) : null}
        <ReadGrid
          w={w}
          empty={w("no_pi_yet")}
          rows={form.pi_rows}
          onRemove={locked ? null : (r) => patch({ pis: form.pis.filter((p) => p !== r.id), pi_rows: form.pi_rows.filter((x) => x.id !== r.id) })}
          cols={[{ key: "pi_no", label: w("col.import_pi") }, { key: "tracking_no", label: w("col.pi_tracking") }, { key: "pi_date", label: w("col.pi_date"), render: (r) => day(r.pi_date) }, { key: "item", label: w("col.item_description") }, { key: "qty", label: w("col.pi_qty"), right: true, render: (r) => qty(r.qty) }, { key: "value", label: w("col.pi_value"), right: true, render: (r) => money(r.value) }]}
        />
        <div className="mt-3 flex justify-end">
          <Link href="/trade-export/lc-ud" className="inline-flex h-9 items-center rounded-lg border border-border px-4 text-sm font-medium hover:bg-surface-muted">
            {w("go_to_ud")}
          </Link>
        </div>
      </Box>
    </>
  );
}

function ImportLcDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  return (
    <Desk
      w={w}
      path={`${P}/imp-lcs`}
      initial={initial}
      decorate={(r) => ({ ...withFinal(w, [r])[0], status_label: w(`action.${r.status}`) })}
      query={query}
      refKey="ref_no"
      exportName="import-lc"
      opt={opt}
      canManage={canManage}
      act={lc(act)}
      Form={ImportLcForm}
      takeAction={["DRAFT", "SUBMITTED", "COMPLETE"]}
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ status: "DRAFT", lc_status: "APPROVED", currency: "USD", credit_tolerance_pct: 5, issue_date: today(opt), tenor_days: "", pis: [], pi_rows: [], commodities: [] })}
      toForm={(d) => ({ ...nulled(d), pi_rows: d.pi_rows, commodities: d.commodities.map((c) => ({ ...c, _k: uid() })) })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/imp-lcs/${e.id}` : `${P}/imp-lcs`, { ...clean(f, ILC_FIELDS), is_cash: Boolean(f.is_cash), same_receiver_bank: Boolean(f.same_receiver_bank), partial_shipment: Boolean(f.partial_shipment), trans_shipment: Boolean(f.trans_shipment), pis: f.pis, commodities: f.commodities.map(({ _k, ...c }) => c) })}
      filters={[textF("no", w("col.import_lc_no"), (r) => `${r.lc_no} ${r.ref_no}`), textF("exp", w("f.exp_lc_no"), (r) => r.export_lc), dateF("ex", w("f.expire_date"), (r) => r.expire_date, "to"), textF("pi", w("col.pi_no"), (r) => r.pi_no), companyF(w, opt), selF("sup", w("f.supplier"), opt.suppliers, (r, v) => r.supplier_id === v), selF("type", w("f.lc_type"), refShort(opt, "LC_TYPE"), (r, v) => r.lc_type === v), dateF("if", w("f.issue_from"), (r) => r.issue_date, "from"), dateF("it", w("f.issue_to"), (r) => r.issue_date, "to"), selF("bank", w("f.bank"), opt.banks, (r, v) => r.bank_id === v)]}
      columns={[finalCol(w), { key: "status_label", label: w("col.status"), type: "badge" }, { key: "lc_no", label: w("col.imp_lc_no"), type: "mono" }, { key: "export_lc", label: w("col.exp_lc_no"), type: "mono" }, amt("lc_value", w("col.lc_value")), { key: "bank_ref_no", label: w("col.bank_ref") }, amt("ifdbc_amount", w("col.ifdbc_amt")), { key: "issue_date", label: w("col.issue_date"), type: "date" }, { key: "expire_date", label: w("col.expire_date"), type: "date" }, { key: "pi_no", label: w("col.pi_no") }, { key: "applicant", label: w("col.applicant") }, { key: "commodity", label: w("col.commodity") }, { key: "supplier", label: w("col.supplier") }]}
    />
  );
}

/* ───────────────────────── Customs Bill of Entry ───────────────────────── */

function EntryForm({ w, form, set, patch, locked, opt, act, editing, F }) {
  const [source, setSource] = useState(null);
  const [ticked, setTicked] = useState([]);
  const except = editing ? `?except=${editing.id}` : "";
  const load = async (id) => {
    const res = await act.read(`${P}/entries/source/${id}${except}`);
    if (res?.status === "success") setSource(res.data);
  };
  useEffect(() => {
    if (form.lc_id) load(form.lc_id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing?.id]);
  const avail = (source?.lines ?? []).filter((l) => l.balance_qty > 0 && !form.lines.some((x) => x.key === l.key));
  const heads = [{ key: "pi_no", label: w("col.pi_no") }, { key: "po_no", label: w("col.po_no") }, { key: "mrr_no", label: w("col.mrr_no") }, { key: "challan_no", label: w("col.challan_no") }, { key: "item", label: w("col.item_details") }, { key: "hs_code", label: w("col.hs_code") }, { key: "uom", label: w("col.mou") }];
  return (
    <>
      <Box legend={w("box.key_info_short")}>
        {F.sel("lc_id", w("f.bb_lc_no"), opt.import_lcs, { req: true, onPick: (v) => { patch({ lc_id: v, lines: [] }); if (v) load(v); else setSource(null); } })}
        {F.read(w("f.bb_lc_amount"), source ? money(source.amount) : "", { req: true })}
        {F.read(w("f.benef_supplier"), source?.supplier)}
        {F.read(w("f.date_of_issue"), source ? day(source.issue_date) : "", { req: true })}
        {F.read(w("f.applicant_importer"), source?.company)}
        {F.read(w("f.export_lc_sc"), source?.export_lc)}
        {F.sel("entry_by", w("f.entry_by"), opt.users)}
        {F.read(w("f.entry_date"), day(editing?.created_at ?? today(opt)), { req: true })}
      </Box>
      <Box legend={w("box.bill_of_entry")}>
        {F.sel("custom_code", w("f.custom_code"), opts(opt.customs))}
        {F.text("custom_name", w("f.custom_name"))}
        {F.text("be_no", w("f.be_no"), { req: true })}
        {F.date("be_date", w("f.be_date"), { req: true })}
        <Row label={w("f.bill_amount")} req>
          <div className="grid grid-cols-[1fr_6rem] gap-2">
            <Input type="number" step="any" value={form.bill_amount ?? ""} disabled={locked} onChange={(e) => set("bill_amount", e.target.value)} aria-label={w("f.bill_amount")} />
            <Select value={form.currency ?? ""} disabled={locked} onValueChange={(v) => patch({ currency: v ?? "", exchange_rate: opt.rates[v] ?? form.exchange_rate })} options={opt.currencies} aria-label={w("f.currency")} />
          </div>
        </Row>
        <Row label={w("f.exchange_rate")} req>
          <div className="grid grid-cols-[1fr_auto_6rem] items-center gap-2">
            <Input type="number" step="any" value={form.exchange_rate ?? ""} disabled={locked} onChange={(e) => set("exchange_rate", e.target.value)} aria-label={w("f.exchange_rate")} />
            <Button type="button" size="sm" variant="ghost" disabled={locked} onClick={() => set("exchange_rate", opt.rates[form.currency] ?? form.exchange_rate)} aria-label={w("recalc_rate")}>
              <RefreshCw />
            </Button>
            <Select value={form.local_currency ?? "BDT"} disabled={locked} onValueChange={(v) => set("local_currency", v ?? "BDT")} options={opt.currencies} aria-label={w("f.local_currency")} />
          </div>
        </Row>
        {F.read(w("f.equivalent"), money(n(form.bill_amount) * n(form.exchange_rate)), { req: true })}
        <Row label={w("f.attached_doc")} hint={form.file_name ? w("attached", { name: form.file_name }) : undefined}>
          <input type="file" disabled={locked} accept=".pdf,.png,.jpg,.jpeg,.webp" onChange={(e) => set("_file", e.target.files?.[0] ?? null)} aria-label={w("f.attached_doc")} className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-subtle file:px-3 file:py-2 file:text-brand" />
        </Row>
        {F.area("remarks", w("f.remarks"))}
      </Box>
      <Box legend={w("box.lc_lines")} grid={false}>
        <ReadGrid
          w={w}
          empty={w("pick_bb")}
          rows={avail}
          cols={[...heads, { key: "pi_qty", label: w("col.pi_quantity"), right: true, render: (r) => qty(r.pi_qty) }, { key: "unit_price", label: w("col.unit_price"), right: true, render: (r) => money(r.unit_price) }, { key: "pi_amount", label: w("col.pi_amount"), right: true, render: (r) => money(r.pi_amount) }, { key: "receive_qty", label: w("col.receive_qty"), right: true, render: (r) => qty(r.receive_qty) }, { key: "receive_amount", label: w("col.receive_amount"), right: true, render: (r) => money(r.receive_amount) }, { key: "balance_qty", label: w("col.balance_qty"), right: true, render: (r) => qty(r.balance_qty) }, { key: "balance_amount", label: w("col.balance_amount"), right: true, render: (r) => money(r.balance_amount) }, { key: "s", label: w("col.select"), render: (r) => <Checkbox checked={ticked.includes(r.key)} disabled={locked} onCheckedChange={(v) => setTicked(v === true ? [...ticked, r.key] : ticked.filter((x) => x !== r.key))} aria-label={`${w("tick")} ${r.mrr_no}`} /> }]}
        />
        {!locked ? (
          <div className="mt-3 flex justify-end">
            <Button
              variant="outline"
              onClick={() => {
                set("lines", [...form.lines, ...avail.filter((a) => ticked.includes(a.key)).map((a) => ({ ...a, receive_qty: a.balance_qty }))]);
                setTicked([]);
              }}
            >
              <Plus /> {w("add_to_grid")}
            </Button>
          </div>
        ) : null}
      </Box>
      <Box legend={w("box.be_details")} grid={false}>
        <ReadGrid w={w} empty={w("no_line_taken")} rows={form.lines.map((l) => ({ ...l, _k: l.key }))} onRemove={locked ? null : (r) => set("lines", form.lines.filter((x) => x.key !== r.key))} cols={[...heads, { key: "receive_qty", label: w("col.receive_qty"), right: true, render: (r) => qty(r.receive_qty) }]} />
      </Box>
    </>
  );
}

function EntryDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  return (
    <Desk
      w={w}
      path={`${P}/entries`}
      initial={initial}
      decorate={(r) => withFinal(w, [r])[0]}
      query={query}
      refKey="be_no"
      exportName="bill-of-entry"
      opt={opt}
      canManage={canManage}
      act={lc(act)}
      Form={EntryForm}
      finalAction
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ be_date: today(opt), currency: "USD", local_currency: "BDT", exchange_rate: opt.rates.USD ?? "", entry_by: opt.me, lines: [] })}
      toForm={(d) => ({ ...nulled(d), lines: d.lines })}
      save={async (f, e) => {
        const res = await act.write(e ? "PUT" : "POST", e ? `${P}/entries/${e.id}` : `${P}/entries`, { ...clean(f, ["lc_id", "be_no", "be_date", "entry_by", "custom_code", "custom_name", "bill_amount", "currency", "exchange_rate", "local_currency", "remarks"]), lines: f.lines.map((l) => ({ pi_line_id: l.pi_line_id, mrr_no: l.mrr_no, receive_qty: l.receive_qty })) });
        if (res?.status !== "success" || !f._file) return res;
        const up = await upload(res.data.id, f._file);
        if (up.status !== "success") return up;
        return act.read(`${P}/entries/${res.data.id}`);
      }}
      extraActions={[{ key: "doc", label: w("doc"), when: (r) => Boolean(r.file_id), open: (r) => window.open(`/api/files/${r.file_id}/download`, "_blank") }]}
      filters={[textF("no", w("f.be_no"), (r) => r.be_no), dateF("from", w("f.from"), (r) => r.be_date, "from"), dateF("to", w("f.to"), (r) => r.be_date, "to"), selF("lc", w("f.import_lc_select"), opt.import_lcs, (r, v) => r.lc_id === v), selF("by", w("f.entry_by"), opt.users, (r, v) => r.entry_by === v)]}
      columns={[finalCol(w), { key: "be_no", label: w("f.be_no"), type: "mono" }, { key: "be_date", label: w("f.be_date"), type: "date" }, amt("bill_amount", w("col.amount")), { key: "custom_name", label: w("col.custom_officer") }, { key: "lc_no", label: w("col.import_lc"), type: "mono" }, { key: "entry_by_name", label: w("f.entry_by") }, { key: "remarks", label: w("col.remarks") }]}
    />
  );
}

/* ───────────────────────── Import Bill Acceptance ───────────────────────── */

const PAY = ["NA", "PMT", "TT", "ADJ"];

function AcceptanceForm({ w, form, set, patch, locked, opt, act, editing, F }) {
  const [source, setSource] = useState(null);
  const load = async (id) => {
    const res = await act.read(`${P}/acceptances/source/${id}`);
    if (res?.status === "success") setSource(res.data);
  };
  useEffect(() => {
    if (form.lc_id) load(form.lc_id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing?.id, editing?.invoiced_amount]);
  return (
    <>
      <Box legend={w("box.key_info_short")}>
        {F.sel("lc_id", w("f.import_lc_no"), opt.import_lcs, { req: true, onPick: (v) => { set("lc_id", v); if (v) load(v); else setSource(null); } })}
        {F.read(w("f.lc_type"), source?.lc_type)}
        {F.read(w("f.import_lc_amount"), source ? money(source.amount) : "")}
        {F.read(w("f.tolerance_amt"), source ? money(source.tolerance_amount) : "")}
        {F.read(w("f.import_issue"), source ? day(source.issue_date) : "", { req: true })}
        {F.read(w("f.export_lc_sc"), source?.export_lc)}
        {F.read(w("f.benef_supplier"), source?.supplier)}
        {F.read(w("f.applicant_importer"), source?.company)}
        {F.read(w("f.tenor"), source?.tenor)}
        {F.read(w("f.is_deferred"), source ? w(`yn.${source.is_deferred ? "YES" : "NO"}`) : "N/A")}
        {F.read(w("f.commodity"), source?.commodity)}
        <div className="lg:col-span-2">
          <Row label={w("f.pi_details")} wide>
            <ReadGrid w={w} empty={w("pick_credit_pi")} rows={source?.pis ?? []} cols={[{ key: "pi_no", label: w("col.pi_no") }, { key: "qty", label: w("col.pi_qty"), right: true, render: (r) => qty(r.qty) }, { key: "amount", label: w("col.pi_amount"), right: true, render: (r) => money(r.amount) }, { key: "pi_date", label: w("col.pi_date"), render: (r) => day(r.pi_date) }]} />
          </Row>
        </div>
      </Box>
      <Box legend={w("box.consignment")}>
        {F.read(w("f.bex_no"), form.boe_no || w("generated_on_save"))}
        {F.date("acceptance_date", w("f.acceptance"), { req: true })}
        <Row label={w("f.invoiced_amount")} req>
          <div className="grid grid-cols-[1fr_6rem] gap-2">
            <Input type="number" step="any" value={form.invoiced_amount ?? ""} disabled={locked} onChange={(e) => set("invoiced_amount", e.target.value)} aria-label={w("f.invoiced_amount")} />
            <Select value={form.currency ?? ""} disabled={locked} onValueChange={(v) => set("currency", v ?? "")} options={opt.currencies} aria-label={w("f.currency")} />
          </div>
        </Row>
      </Box>
      <Box legend={w("box.bank_bill")}>
        {F.read(w("f.sender_bank"), source?.sender_bank, { req: true })}
        {F.read(w("f.lc_lean_bank"), source?.lean_bank)}
        {F.text("ifdbc_no", w("f.ifdbc_no"), { req: true })}
        <Row label={w("f.ifdbc_amount")} req>
          <div className="flex items-center gap-3">
            <Input type="number" step="any" value={form.ifdbc_amount ?? ""} disabled={locked} onChange={(e) => set("ifdbc_amount", e.target.value)} aria-label={w("f.ifdbc_amount")} />
            <label className="flex shrink-0 items-center gap-2 text-sm">
              <Checkbox checked={Boolean(form.is_pending)} disabled={locked} onCheckedChange={(v) => set("is_pending", v === true)} aria-label={w("f.pending")} /> {w("f.pending")}
            </label>
          </div>
        </Row>
        {F.date("open_date", w("f.open_date"), { req: true })}
        {F.date("maturity_date", w("f.maturity"), { req: true })}
        {F.num("b2b_qty", w("f.b2b_qty"))}
        {F.text("attached_document", w("f.attached_doc"))}
        {F.date("ext_maturity_date", w("f.ext_maturity"))}
        {F.sel("payment_type", w("f.payment_type"), PAY.map((v) => ({ value: v, label: w(`pay.${v}`) })))}
        <Row label={w("f.exchange_rate")}>
          <div className="grid grid-cols-[1fr_6rem] gap-2">
            <Input type="number" step="any" value={form.exchange_rate ?? ""} disabled={locked} onChange={(e) => set("exchange_rate", e.target.value)} aria-label={w("f.exchange_rate")} />
            <Select value={form.local_currency ?? "BDT"} disabled={locked} onValueChange={(v) => set("local_currency", v ?? "BDT")} options={opt.currencies} aria-label={w("f.local_currency")} />
          </div>
        </Row>
        {F.area("remarks", w("f.remarks"))}
      </Box>
      <Box legend={w("box.bill_list")} grid={false}>
        <ReadGrid w={w} empty={w("pick_credit_bills")} rows={source?.bills ?? []} cols={[{ key: "boe_no", label: w("col.boe_short") }, { key: "acceptance_date", label: w("col.accept_date"), render: (r) => day(r.acceptance_date) }, { key: "ifdbc_no", label: w("col.ifdbc_no") }, { key: "open_date", label: w("col.ifdbc_open"), render: (r) => day(r.open_date) }, { key: "maturity_date", label: w("col.maturity"), render: (r) => day(r.maturity_date) }, { key: "ifdbc_amount", label: w("col.ifdbc_amount"), right: true, render: (r) => money(r.ifdbc_amount) }, { key: "paid_amount", label: w("col.payment_amt"), right: true, render: (r) => money(r.paid_amount) }, { key: "paid_date", label: w("col.payment_date"), render: (r) => (r.paid_date ? day(r.paid_date) : "—") }, { key: "final", label: w("col.final"), render: (r) => w(`yn.${r.final ? "YES" : "NO"}`) }]} />
        <div className="mt-3 ml-auto grid max-w-md gap-3">
          {F.read(w("f.total_bill"), money(source?.total_bill ?? 0))}
          {F.read(w("f.total_lc_due"), money(source?.lc_due ?? 0))}
        </div>
      </Box>
    </>
  );
}

function AcceptanceDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const yn = ["YES", "NO"].map((v) => ({ value: v, label: w(`yn.${v}`) }));
  const test = (val, v) => val === (v === "YES");
  return (
    <Desk
      w={w}
      path={`${P}/acceptances`}
      initial={initial}
      decorate={(r) => ({ ...withFinal(w, [r])[0], accounting_label: w(`yn.${r.accounting_done ? "YES" : "NO"}`), pay_label: w(`pay.${r.payment_type}`) })}
      query={query}
      refKey="boe_no"
      exportName="import-bill-acceptance"
      opt={opt}
      canManage={canManage}
      act={lc(act)}
      Form={AcceptanceForm}
      takeAction={["DRAFT", "FINAL"]}
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ acceptance_date: today(opt), open_date: today(opt), currency: "BDT", local_currency: "BDT", payment_type: "NA", b2b_qty: 0, status: "DRAFT" })}
      toForm={(d) => ({ ...nulled(d), status: d.final ? "FINAL" : "DRAFT" })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/acceptances/${e.id}` : `${P}/acceptances`, { ...clean(f, ["lc_id", "invoiced_amount", "currency", "acceptance_date", "ifdbc_no", "ifdbc_amount", "open_date", "maturity_date", "ext_maturity_date", "b2b_qty", "attached_document", "payment_type", "exchange_rate", "local_currency", "remarks"]), is_pending: Boolean(f.is_pending), final: f.status === "FINAL" })}
      filters={[textF("no", w("col.boe_short"), (r) => r.boe_no), textF("if", w("col.ifdbc_no"), (r) => r.ifdbc_no), textF("lc", w("f.import_lc_no"), (r) => r.lc_no), dateF("af", w("f.accept_from"), (r) => r.acceptance_date, "from"), dateF("at", w("f.accept_to"), (r) => r.acceptance_date, "to"), dateF("mf", w("f.maturity_from"), (r) => r.maturity_date, "from"), dateF("mt", w("f.maturity_to"), (r) => r.maturity_date, "to"), dateF("pf", w("f.paid_from"), (r) => r.paid_date, "from"), dateF("pt", w("f.paid_to"), (r) => r.paid_date, "to"), selF("paid", w("f.is_paid"), yn, (r, v) => test(r.paid_amount > 0, v)), selF("ifdc", w("f.is_ifdc"), yn, (r, v) => test(Boolean(r.ifdbc_no) && !r.is_pending, v)), selF("pay", w("f.is_payment_done"), yn, (r, v) => test(r.paid_amount > 0 && r.paid_amount + 0.005 >= r.ifdbc_amount, v)), selF("pad", w("f.pad_final"), yn, (r, v) => test(r.final, v)), selF("acc", w("f.accounting_done"), yn, (r, v) => test(r.accounting_done, v)), selF("ptype", w("f.payment_type"), PAY.map((v) => ({ value: v, label: w(`pay.${v}`) })), (r, v) => r.payment_type === v), selF("bank", w("f.bank"), opt.banks, (r, v) => r.bank_id === v)]}
      columns={[finalCol(w), { key: "boe_no", label: w("col.boe_no_full"), type: "mono" }, { key: "lc_no", label: w("col.imp_lc_upper"), type: "mono" }, { key: "accounting_label", label: w("f.accounting_done") }, { key: "pay_label", label: w("f.payment_type") }, amt("invoiced_amount", w("col.bex_amt")), { key: "ifdbc_no", label: w("col.ifdbc_no") }, { key: "maturity_date", label: w("col.maturity"), type: "date" }, { key: "ext_maturity_date", label: w("f.ext_maturity"), type: "date" }, amt("ifdbc_amount", w("col.ifdbc_amt")), amt("paid_amount", w("col.ifdbc_paid")), { key: "open_date", label: w("col.ifdbc_open"), type: "date" }, { key: "paid_date", label: w("col.ifdbc_paid_date"), type: "date" }, amt("b2b_qty", w("f.b2b_qty"))]}
    />
  );
}

/* ───────────────────────── Utilisation Declaration ───────────────────────── */

function UdForm({ w, form, patch, locked, opt, act, editing, F }) {
  const [pool, setPool] = useState([]);
  useEffect(() => {
    act.read(`${P}/uds/lcs${editing ? `?except=${editing.id}` : ""}`).then((r) => r?.status === "success" && setPool(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const left = pool.filter((p) => !form.lc_rows.some((r) => r.id === p.id)).map((p) => ({ value: p.id, label: p.b2b }));
  return (
    <>
      <Box legend={w("box.ud_master")}>
        {F.text("ud_no", w("f.ud_no"), { req: true })}
        {F.date("ud_date", w("f.ud_date"), { req: true })}
        {F.sel("authority", w("f.ud_authority"), opts(opt.authorities), { req: true })}
        <Row label={w("f.b2b_lc")}>
          <Select value="" disabled={locked} onValueChange={(v) => { const hit = pool.find((p) => p.id === v); if (hit) patch({ lc_rows: [...form.lc_rows, hit] }); }} options={left} placeholder={w("select")} aria-label={w("f.b2b_lc")} />
        </Row>
      </Box>
      <Box legend={w("box.ud_lines")} grid={false}>
        <ReadGrid w={w} empty={w("no_data")} rows={form.lc_rows} onRemove={locked ? null : (r) => patch({ lc_rows: form.lc_rows.filter((x) => x.id !== r.id) })} cols={[{ key: "company", label: w("f.company") }, { key: "buyer", label: w("col.buyer") }, { key: "export_lc", label: w("col.exp_lc_sc") }, { key: "export_date", label: w("col.issue_date"), render: (r) => (r.export_date ? day(r.export_date) : "—") }, { key: "export_value", label: w("col.exp_lc_amount"), right: true, render: (r) => (r.export_value === null ? "—" : money(r.export_value)) }, { key: "b2b", label: w("col.b2b_lc") }, { key: "b2b_date", label: w("col.b2b_issue"), render: (r) => day(r.b2b_date) }, { key: "b2b_value", label: w("col.b2b_amount"), right: true, render: (r) => money(r.b2b_value) }]} />
      </Box>
    </>
  );
}

function UdDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  return (
    <Desk
      w={w}
      path={`${P}/uds`}
      initial={initial}
      decorate={(r) => withFinal(w, [r])[0]}
      query={query}
      refKey="ud_no"
      exportName="utilisation-declaration"
      opt={opt}
      canManage={canManage}
      act={lc(act)}
      Form={UdForm}
      finalAction
      saveLabel={w("submit")}
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ ud_date: today(opt), authority: "BKMEA", lc_rows: [] })}
      toForm={(d) => ({ ...nulled(d), lc_rows: d.lc_rows })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/uds/${e.id}` : `${P}/uds`, { ...clean(f, ["ud_no", "ud_date", "authority"]), lcs: f.lc_rows.map((r) => r.id) })}
      filters={[textF("no", w("f.ud_no_dot"), (r) => r.ud_no), textF("exp", w("f.export_lc"), (r) => r.export_lc), textF("b2b", w("f.b2b_lc"), (r) => r.b2b), selF("auth", w("col.authority"), opts(opt.authorities), (r, v) => r.authority === v), dateF("from", w("f.from"), (r) => r.ud_date, "from"), dateF("to", w("f.to"), (r) => r.ud_date, "to"), finalF(w)]}
      columns={[finalCol(w), { key: "export_lc", label: w("col.exp_lc_dot"), type: "mono" }, { key: "ud_no", label: w("col.ud_no"), type: "mono" }, { key: "ud_date", label: w("col.ud_date"), type: "date" }, { key: "authority", label: w("col.authority") }, { key: "amendment_no", label: w("col.amendment_no"), type: "number", align: "right" }]}
    />
  );
}

/* ───────────────────────── Utilisation Declaration Transfer ───────────────────────── */

function UdTransferForm({ w, form, set, locked, opt, act, editing, F }) {
  const [uds, setUds] = useState([]);
  const [has2, setHas2] = useState(null);
  useEffect(() => {
    act.read(`${P}/uds`).then((r) => r?.status === "success" && setUds(r.data.map((u) => ({ value: u.id, label: u.ud_no }))));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    if (!form.material || !form.from_ud_id) return setHas2(null);
    act.read(`${P}/ud-transfers/source?material=${encodeURIComponent(form.material)}&from=${form.from_ud_id}${editing ? `&except=${editing.id}` : ""}`).then((r) => r?.status === "success" && setHas2(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.material, form.from_ud_id]);
  const name = (id) => uds.find((u) => u.value === id)?.label ?? "—";
  const loss = (n(form.export_qty) * n(form.loss_pct)) / 100;
  const balance = has2 ? has2.receive_qty - has2.transferred - n(form.export_qty) - loss : 0;
  const ready = has2 && form.to_ud_id;
  const box = (k, label) => <Input type="number" step="any" className="w-28 text-right" value={form[k] ?? ""} disabled={locked} onChange={(e) => set(k, e.target.value)} aria-label={label} />;
  return (
    <Box legend={w("box.ud_transfer")} grid={false}>
      <div className="mb-3 grid gap-3 lg:grid-cols-3">
        {F.sel("material", w("f.commodity"), opts(opt.materials), { req: true })}
        {F.sel("from_ud_id", w("f.from_ud"), uds, { req: true })}
        {F.sel("to_ud_id", w("f.to_ud"), uds.filter((u) => u.value !== form.from_ud_id), { req: true })}
      </div>
      <ReadGrid
        w={w}
        empty={w("pick_ud")}
        rows={ready ? [{ _k: "x" }] : []}
        cols={[
          { key: "f", label: w("col.from_ud"), render: () => name(form.from_ud_id) },
          { key: "t", label: w("col.to_ud"), render: () => name(form.to_ud_id) },
          { key: "r", label: w("col.receive_qty_short"), right: true, render: () => qty(has2?.receive_qty) },
          { key: "e", label: w("col.export_qty"), right: true, render: () => box("export_qty", w("col.export_qty")) },
          { key: "l", label: w("col.loss_pct"), right: true, render: () => box("loss_pct", w("col.loss_pct")) },
          { key: "lq", label: w("col.loss_qty"), right: true, render: () => qty(loss) },
          { key: "b", label: w("col.balance_short"), right: true, render: () => qty(balance) },
          { key: "x", label: w("col.transfer_qty"), right: true, render: () => box("transfer_qty", w("col.transfer_qty")) },
        ]}
      />
    </Box>
  );
}

function UdTransferDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  return (
    <Desk
      w={w}
      path={`${P}/ud-transfers`}
      initial={initial}
      decorate={(r) => ({ ...r, status_label: w(`istatus.${r.status}`) })}
      query={query}
      refKey="ref"
      exportName="ud-transfer"
      opt={opt}
      canManage={canManage}
      act={lc(act)}
      Form={UdTransferForm}
      takeAction={["DRAFT", "APPROVED"]}
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ status: "DRAFT", export_qty: "", loss_pct: "", transfer_qty: "" })}
      toForm={(d) => nulled(d)}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/ud-transfers/${e.id}` : `${P}/ud-transfers`, clean(f, ["material", "from_ud_id", "to_ud_id", "export_qty", "loss_pct", "transfer_qty", "status"]))}
      filters={[textF("from", w("f.from_ud_no"), (r) => r.from_ud), textF("to", w("f.to_ud_no"), (r) => r.to_ud), dateF("df", w("f.from"), (r) => r.created_on, "from"), dateF("dt", w("f.to"), (r) => r.created_on, "to")]}
      columns={[{ key: "from_ud", label: w("col.from_ud_cap"), type: "mono" }, { key: "to_ud", label: w("col.to_ud_cap"), type: "mono" }, { key: "material", label: w("col.material") }, amt("transfer_qty", w("col.transfer_qty_cap")), { key: "status_label", label: w("col.status"), type: "badge" }]}
    />
  );
}

export { ImportPiDesk, ImportLcDesk, EntryDesk, AcceptanceDesk, UdDesk, UdTransferDesk };
