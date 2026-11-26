"use client";

import { useEffect, useState } from "react";
import { day, money } from "@/components/sales-shipment/shared";
import { amt, Box, clean, dateF, Desk, finalCol, nulled, ReadGrid, selF, textF, today, useW, withFinal } from "@/components/tradeexport/operations";

/*
 * Trade & Export → CM Payment Receive (AnnotechERP 22/04): CM Payment
 * Receipts — a final bill of exchange picked, what it carries shown, the
 * amount received, its date, rate and DFC margin, and every receipt of the
 * bill in the grid below with their totals.
 */

const P = "/tradeexport/ops";
const TYPES = ["FDBP", "FDBC", "DA", "DP", "IP"];

function CmForm({ w, form, set, patch, opt, act, editing, F }) {
  const [bills, setBills] = useState([]);
  const [bill, setBill] = useState(null);
  const load = async (id, fresh) => {
    const res = await act.read(`${P}/cm-receipts/source/${id}`);
    if (res?.status !== "success") return;
    setBill(res.data);
    if (fresh) patch({ boe_id: id, bank_bill_type: res.data.bank_bill_type, exchange_rate: res.data.rate ?? "" });
  };
  useEffect(() => {
    act.read(`${P}/cm-receipts/bills`).then((r) => r?.status === "success" && setBills(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    if (form.boe_id) load(form.boe_id, false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing?.id, editing?.amount]);
  return (
    <>
      <Box legend={w("box.key_info_short")}>
        {F.sel("boe_id", w("f.boe_no_dot"), bills, { req: true, onPick: (v) => v && load(v, true) })}
        {F.sel("bank_bill_type", w("f.bank_bill_type"), TYPES.map((v) => ({ value: v, label: v })))}
        {F.read(w("f.export_lc_sc_short"), bill?.lc_no)}
        {F.read(w("col.submit_at_bank"), bill?.submit_date ? day(bill.submit_date) : "")}
        {F.read(w("f.buyer"), bill?.buyer)}
        {F.read(w("f.total_bill"), bill ? money(bill.bill_amount) : "")}
        {F.read(w("f.export_lc_value"), bill?.lc_value === null || !bill ? "" : money(bill.lc_value))}
        {F.num("amount", w("f.cm_amount"))}
        {F.read(w("f.bill_date"), bill ? day(bill.bill_date) : "")}
        {F.read(w("f.bank_bill_ref"), bill?.bank_bill_ref_no)}
        {F.read(w("f.export_invoices"), bill?.invoices)}
        {F.date("receive_date", w("f.cm_date"))}
        {F.num("exchange_rate", w("f.rate_usd"))}
        {F.num("dfc_margin", w("f.dfc_margin"))}
      </Box>
      <Box legend={w("box.cm_receipts")} grid={false}>
        <ReadGrid
          w={w}
          empty={w("no_data")}
          rows={bill?.receipts ?? []}
          cols={[{ key: "receipt_no", label: w("col.action") }, { key: "receive_date", label: w("f.cm_date"), render: (r) => day(r.receive_date) }, { key: "amount", label: w("f.cm_amount"), right: true, render: (r) => money(r.amount) }, { key: "dfc_margin", label: w("f.dfc_margin"), right: true, render: (r) => money(r.dfc_margin) }]}
        />
        <p className="mt-2 text-right text-sm font-semibold">{w("cm_total", { amount: money(bill?.received ?? 0), dfc: money(bill?.dfc ?? 0) })}</p>
      </Box>
    </>
  );
}

function CmReceiptDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  return (
    <Desk
      w={w}
      path={`${P}/cm-receipts`}
      initial={initial}
      decorate={(r) => withFinal(w, [r])[0]}
      query={query}
      refKey="receipt_no"
      exportName="cm-payment-receipts"
      opt={opt}
      canManage={canManage}
      act={act}
      Form={CmForm}
      finalAction
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ receive_date: today(opt), dfc_margin: 0 })}
      toForm={(d) => nulled(d)}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/cm-receipts/${e.id}` : `${P}/cm-receipts`, clean(f, ["boe_id", "bank_bill_type", "amount", "receive_date", "exchange_rate", "dfc_margin"]))}
      filters={[textF("no", w("f.boe_no"), (r) => r.bill_no), textF("lc", w("f.lc_no_short"), (r) => r.lc_no), textF("ref", w("f.bank_bill_ref"), (r) => r.bank_bill_ref_no), selF("type", w("f.bank_bill_type"), TYPES.map((v) => ({ value: v, label: v })), (r, v) => r.bank_bill_type === v), dateF("bf", w("f.boe_from"), (r) => r.bill_date, "from"), dateF("bt", w("f.boe_to"), (r) => r.bill_date, "to"), dateF("sf", w("f.submit_from"), (r) => r.submit_date, "from"), dateF("st", w("f.submit_to"), (r) => r.submit_date, "to"), dateF("rf", w("f.receive_from"), (r) => r.receive_date, "from"), dateF("rt", w("f.receive_to"), (r) => r.receive_date, "to")]}
      columns={[finalCol(w), { key: "bill_no", label: w("col.boe_no_full"), type: "mono" }, { key: "bank_bill_type", label: w("col.bank_bill_type") }, { key: "bank_bill_ref_no", label: w("col.bank_bill_ref_long") }, { key: "bill_date", label: w("col.bill_date"), type: "date" }, amt("bill_amount", w("col.bill_amount")), { key: "buyer", label: w("col.buyer_name") }, { key: "lc_no", label: w("col.lc_no"), type: "mono" }, amt("lc_value", w("col.lc_value")), { key: "submit_date", label: w("col.submit_at_bank"), type: "date" }, { key: "receive_date", label: w("col.payment_recv"), type: "date" }, amt("amount", w("f.cm_amount"))]}
    />
  );
}

export { CmReceiptDesk };
