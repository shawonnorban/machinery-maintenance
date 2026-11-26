"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const today = () => new Date().toISOString().slice(0, 10);
const number = (v) => Number(v ?? 0).toLocaleString("en-US");
const shortDate = (iso) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "—");

/**
 * Projection & Call-Off Order → Call-Offs (AnnotechERP 06/07): one
 * projection's projected, called-off and remaining quantity, its call-off
 * orders, and the form that draws the next one — job, PO, dates, quantity
 * (never past the balance), unit price and delivery country, the rest carried
 * from the projection.
 *
 * @param {{ projectionId: string, data: { projection: object, call_offs: object[] }, countries: {value: string, label: string}[], draw: Function, canManage: boolean }} props
 */
function ProjectionCallOffs({ projectionId, data, countries, draw, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.prj.${k}`, p);
  const mo = (k, p) => t(`master.ord.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [view, setView] = useState(data);
  const blank = () => ({ job_no: "", po_no: "", conf_date: today(), ship_date: view.projection.ship_date ?? "", total_qty: "", unit_price: view.projection.unit_price == null ? "" : String(view.projection.unit_price), ship_country_id: view.projection.ship_country_id ?? "" });
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const p = view.projection;
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));

  function submit(event) {
    event.preventDefault();
    startTransition(async () => {
      const result = await draw(projectionId, {
        ...form,
        po_no: form.po_no || null,
        total_qty: form.total_qty === "" ? null : Number(form.total_qty),
        unit_price: form.unit_price === "" ? null : Number(form.unit_price),
        ship_country_id: form.ship_country_id || null,
      });
      if (result?.status === "success") {
        const drawn = result.data.call_offs.at(-1);
        setView(result.data);
        setForm(blank());
        setErrors(null);
        toastManager.add({ title: m("saved", { no: drawn?.order_no ?? "" }), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";

  return (
    <div className="flex flex-col gap-4 p-5">
      <div className="grid gap-3 sm:grid-cols-3">
        {[
          ["projected", p.total_qty, "text-foreground"],
          ["called_off", p.called_off, "text-info"],
          ["balance", p.balance, p.balance > 0 ? "text-success" : "text-foreground-muted"],
        ].map(([k, v, tone]) => (
          <div key={k} className="rounded-md border border-border px-4 py-3">
            <div className="text-[13px] text-foreground-muted">{m(k)}</div>
            <div className={`text-xl font-bold tabular-nums ${tone}`}>{m("pcs", { qty: number(v) })}</div>
          </div>
        ))}
      </div>

      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-brand-foreground">
              {["order_no", "job", "po", "conf", "ship", "qty", "price", "status"].map((k) => (
                <th key={k} className={th}>
                  {m(`col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {view.call_offs.length === 0 ? (
              <tr>
                <td colSpan={8} className="px-4 py-10 text-center text-foreground-muted">
                  {m("none")}
                </td>
              </tr>
            ) : (
              view.call_offs.map((o) => (
                <tr key={o.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2.5 font-semibold text-foreground">{o.order_no}</td>
                  <td className="px-3 py-2.5">{o.job_no}</td>
                  <td className="px-3 py-2.5">{o.po_no ?? "—"}</td>
                  <td className="px-3 py-2.5">{shortDate(o.conf_date)}</td>
                  <td className="px-3 py-2.5">{shortDate(o.ship_date)}</td>
                  <td className="px-3 py-2.5 tabular-nums">{number(o.total_qty)}</td>
                  <td className="px-3 py-2.5 tabular-nums">{o.unit_price == null ? "—" : `${o.unit_price} ${o.currency}`}</td>
                  <td className="px-3 py-2.5">
                    <Badge variant={o.status === "CANCELLED" ? "danger" : "success"}>{mo(`status.${o.status}`)}</Badge>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {canManage && p.balance > 0 ? (
        <form onSubmit={submit} className="rounded-md border border-border p-4">
          <h3 className="text-sm font-semibold text-foreground">{m("new")}</h3>
          {errors ? (
            <Alert variant="danger" className="mt-3">
              {errors.join(" · ")}
            </Alert>
          ) : null}
          <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              ["job_no", "job", "text", true],
              ["po_no", "po", "text", false],
              ["conf_date", "conf", "date", true],
              ["ship_date", "ship", "date", true],
              ["total_qty", "qty", "number", true],
              ["unit_price", "price", "number", false],
            ].map(([field, label, type, required]) => (
              <label key={field} className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
                <span>
                  {m(`f.${label}`)}
                  {required ? <span className="text-danger">*</span> : null}
                </span>
                <Input type={type} value={form[field]} onChange={(e) => set(field)(e.target.value)} min={type === "number" ? "0" : undefined} max={field === "total_qty" ? String(p.balance) : undefined} step={field === "unit_price" ? "0.0001" : type === "number" ? "1" : undefined} />
              </label>
            ))}
            <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
              <span>{m("f.country")}</span>
              <Select value={form.ship_country_id} onValueChange={(v) => set("ship_country_id")(v ?? "")} options={[{ value: "", label: t("master.select") }, ...countries]} placeholder={t("master.select")} />
            </label>
          </div>
          <div className="mt-4 flex justify-end">
            <Button type="submit" loading={pending}>
              <Plus /> {m("add")}
            </Button>
          </div>
        </form>
      ) : null}
    </div>
  );
}

export { ProjectionCallOffs };
