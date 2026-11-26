"use client";

import { useState, useTransition } from "react";
import { Plus, Save, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const today = () => new Date().toISOString().slice(0, 10);
const str = (v) => (v === null || v === undefined ? "" : String(v));

function blankForm(style, rows) {
  return {
    id: null,
    order_type: rows.length ? "REPEAT" : "BULK_NEW",
    job_no: "",
    inquiry_id: style.inquiry_id ?? "",
    sales_contract_no: "",
    po_no: "",
    sub_style: style.style_no,
    conf_date: today(),
    projection_only: false,
    is_multi: false,
    ship_date: "",
    total_qty: "",
    unit: style.qty_unit,
    pcs_per_unit: str(style.pcs_per_unit),
    unit_price: "",
    currency: "USD",
    exchange_rate: "",
    factory_id: "",
    status: "CONFIRMED",
    tna_template_id: "",
    ship_country_id: style.origin_country_id ?? "",
  };
}

const asForm = (o) => ({
  id: o.id,
  order_no: o.order_no,
  order_type: o.order_type,
  job_no: str(o.job_no),
  inquiry_id: o.inquiry_id ?? "",
  sales_contract_no: str(o.sales_contract_no),
  po_no: str(o.po_no),
  sub_style: str(o.sub_style),
  conf_date: str(o.conf_date),
  projection_only: Boolean(o.projection_only),
  is_multi: Boolean(o.is_multi),
  ship_date: str(o.ship_date),
  total_qty: str(o.total_qty),
  unit: o.unit ?? "PCS",
  pcs_per_unit: str(o.pcs_per_unit ?? 1),
  unit_price: str(o.unit_price),
  currency: o.currency ?? "USD",
  exchange_rate: str(o.exchange_rate),
  factory_id: o.factory_id ?? "",
  status: o.status,
  tna_template_id: o.tna_template_id ?? "",
  ship_country_id: o.ship_country_id ?? "",
});

/**
 * Item/Combo/Model → Order Confirmation (AnnotechERP 06/07): Brickland's
 * Working Style list — the orders confirmed against this style, by type,
 * job/working style, order number and sales contract — and, from
 * New/Repeat/Development or a row, the Order Information form: job, inquiry,
 * sales contract, PO, sub style, the confirmed and shipment dates (lead time
 * worked out), quantity and unit, price and currency (order value worked
 * out), the rate to local currency, company and production unit, status,
 * TNA template and where it is delivered.
 *
 * @param {{ styleId: string, data: object, create: Function, update: Function, canManage: boolean }} props
 */
function StyleOrders({ styleId, data, create, update, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.oc.${k}`, p);
  const mo = (k, p) => t(`master.ord.${k}`, p);
  const toastManager = useToastManager();
  const { style, options: o } = data;
  const [rows, setRows] = useState(data.rows);
  const [form, setForm] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));
  const pick = (list, placeholder) => [{ value: "", label: placeholder }, ...list];
  const lead = form?.conf_date && form?.ship_date ? Math.round((new Date(form.ship_date) - new Date(form.conf_date)) / 86400000) : null;
  const value = form && form.total_qty !== "" && form.unit_price !== "" ? (Number(form.total_qty) * Number(form.unit_price)).toFixed(2) : "";

  function submit() {
    startTransition(async () => {
      const payload = {
        ...form,
        inquiry_id: form.inquiry_id || null,
        factory_id: form.factory_id || null,
        tna_template_id: form.tna_template_id || null,
        ship_country_id: form.ship_country_id || null,
        total_qty: form.total_qty === "" ? null : Number(form.total_qty),
        pcs_per_unit: Number(form.pcs_per_unit) || 1,
        unit_price: form.unit_price === "" ? null : Number(form.unit_price),
        exchange_rate: form.exchange_rate === "" ? null : Number(form.exchange_rate),
        ship_date: form.ship_date || null,
        conf_date: form.conf_date || null,
      };
      delete payload.id;
      delete payload.order_no;
      const result = form.id ? await update(form.id, payload) : await create(styleId, payload);
      if (result?.status === "success") {
        setRows(result.data);
        setForm(null);
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";

  return (
    <div className="grid gap-5 p-5 xl:grid-cols-[1fr_1fr]">
      <fieldset className="flex min-w-0 flex-col gap-3 rounded-lg border border-border px-4 pt-2 pb-4">
        <legend className="px-1 text-[13px] font-semibold text-brand italic">{m("working")}</legend>
        {canManage ? (
          <div className="flex justify-end">
            <Button onClick={() => { setForm(blankForm(style, rows)); setErrors(null); }}>
              <Plus /> {m("new_entry")}
            </Button>
          </div>
        ) : null}
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                {["type", "job", "list", "sc"].map((k) => (
                  <th key={k} className={th}>
                    {m(`dcol.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-14 text-center text-foreground-muted">
                    {m("none")}
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr
                    key={r.id}
                    onClick={canManage ? () => { setForm(asForm(r)); setErrors(null); } : undefined}
                    className={cn("border-b border-border align-middle last:border-0", canManage && "cursor-pointer hover:bg-surface-muted", form?.id === r.id && "bg-brand-subtle")}
                  >
                    <td className="px-3 py-2.5">{mo(`type.${r.order_type}`)}</td>
                    <td className="px-3 py-2.5 font-medium text-foreground">{r.job_no}</td>
                    <td className="px-3 py-2.5">{r.order_no}</td>
                    <td className="px-3 py-2.5">{r.sales_contract_no ?? "—"}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </fieldset>

      {form ? (
        <fieldset className="flex flex-col gap-3 rounded-lg border border-border px-4 pt-2 pb-4">
          <legend className="px-1 text-[13px] font-semibold text-brand italic">{m("info")}</legend>
          <div className="flex items-center justify-between gap-3">
            <span className="text-[13px] font-semibold text-foreground-muted">{form.id ? m("editing", { no: form.order_no }) : null}</span>
            <Button variant="outline" size="sm" onClick={() => setForm(null)} aria-label={m("close_form")}>
              <X />
            </Button>
          </div>
          {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
          <Row label={m("df.type")} required>
            <Select value={form.order_type} onValueChange={(v) => v && set("order_type")(v)} options={o.types.map((v) => ({ value: v, label: mo(`type.${v}`) }))} />
          </Row>
          <Row label={m("df.job")} required>
            <Input value={form.job_no} onChange={(e) => set("job_no")(e.target.value)} placeholder={`${style.style_no}…`} />
          </Row>
          <Row label={m("df.inquiry")}>
            <Select value={form.inquiry_id} onValueChange={(v) => set("inquiry_id")(v ?? "")} options={pick(o.inquiries, m("ph_inquiry"))} placeholder={m("ph_inquiry")} />
          </Row>
          <Row label={m("df.sc")}>
            <Input value={form.sales_contract_no} onChange={(e) => set("sales_contract_no")(e.target.value)} placeholder={m("ph_sc")} />
          </Row>
          <Row label={m("df.style")}>
            <Input value={style.style_no} disabled readOnly />
          </Row>
          <Row label={m("df.po")}>
            <Input value={form.po_no} onChange={(e) => set("po_no")(e.target.value)} />
          </Row>
          <Row label={m("df.sub")}>
            <Input value={form.sub_style} onChange={(e) => set("sub_style")(e.target.value)} />
          </Row>
          <Row label={m("df.conf")} required>
            <div className="flex flex-wrap items-center gap-4">
              <Input type="date" value={form.conf_date} onChange={(e) => set("conf_date")(e.target.value)} className="w-44" />
              {["projection_only", "is_multi"].map((flag) => (
                <label key={flag} className="flex items-center gap-1.5 text-[13px] font-semibold text-foreground">
                  <Checkbox checked={form[flag]} onCheckedChange={(c) => set(flag)(c === true)} />
                  {m(flag === "projection_only" ? "df.projection" : "df.multi")}
                </label>
              ))}
            </div>
          </Row>
          <Row label={m("df.ship")} required>
            <Input type="date" value={form.ship_date} onChange={(e) => set("ship_date")(e.target.value)} />
          </Row>
          <Row label={m("df.lead")}>
            <Input value={lead === null ? "" : String(lead)} disabled readOnly />
          </Row>
          <Row label={m("df.qty")} required>
            <div className="grid grid-cols-[1fr_7rem_5rem] gap-2">
              <Input type="number" min="1" step="1" value={form.total_qty} onChange={(e) => set("total_qty")(e.target.value)} aria-label={m("df.qty")} />
              <Select value={form.unit} onValueChange={(v) => v && set("unit")(v)} options={o.units.map((v) => ({ value: v, label: mo(`unit.${v}`) }))} aria-label={m("df.unit")} />
              <Input type="number" min="1" step="1" value={form.unit === "PCS" ? "1" : form.pcs_per_unit} onChange={(e) => set("pcs_per_unit")(e.target.value)} disabled={form.unit === "PCS"} aria-label={m("df.pcs")} />
            </div>
          </Row>
          <Row label={m("df.price")} required>
            <div className="grid grid-cols-[1fr_7rem] gap-2">
              <Input type="number" min="0" step="0.0001" value={form.unit_price} onChange={(e) => set("unit_price")(e.target.value)} aria-label={m("df.price")} />
              <Select value={form.currency} onValueChange={(v) => v && set("currency")(v)} options={o.currencies.map((c) => ({ value: c, label: c }))} aria-label={m("df.ccy")} />
            </div>
          </Row>
          <Row label={m("df.value", { ccy: form.currency })}>
            <Input value={value} disabled readOnly />
          </Row>
          <Row label={m("df.rate", { ccy: form.currency })} required>
            <Input type="number" min="0" step="0.0001" value={form.exchange_rate} onChange={(e) => set("exchange_rate")(e.target.value)} />
          </Row>
          <Row label={m("df.company")}>
            <Input value={o.company ?? ""} disabled readOnly />
          </Row>
          <Row label={m("df.prod_unit")}>
            <Select value={form.factory_id} onValueChange={(v) => set("factory_id")(v ?? "")} options={pick(o.factories, t("master.select"))} placeholder={t("master.select")} />
          </Row>
          <Row label={m("df.status")}>
            <Select value={form.status} onValueChange={(v) => v && set("status")(v)} options={o.statuses.map((v) => ({ value: v, label: mo(`status.${v}`) }))} />
          </Row>
          <Row label={m("df.tna")}>
            <Select value={form.tna_template_id} onValueChange={(v) => set("tna_template_id")(v ?? "")} options={pick(o.tna_templates, m("ph_tna"))} placeholder={m("ph_tna")} />
          </Row>
          <Row label={m("df.delivery")} required>
            <Select value={form.ship_country_id} onValueChange={(v) => set("ship_country_id")(v ?? "")} options={pick(o.countries, t("master.select"))} placeholder={t("master.select")} />
          </Row>
          <div className="flex gap-2 pt-1">
            <Button onClick={submit} loading={pending}>
              <Save /> {form.id ? m("update") : m("save")}
            </Button>
          </div>
        </fieldset>
      ) : null}
    </div>
  );
}

function Row({ label, required = false, children }) {
  return (
    <div className="grid items-center gap-3 sm:grid-cols-[10rem_1fr]">
      <span className="text-[13px] font-semibold text-foreground sm:text-right">
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      <div className="min-w-0">{children}</div>
    </div>
  );
}

export { StyleOrders };
