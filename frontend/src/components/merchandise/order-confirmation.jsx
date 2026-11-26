"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CheckCircle2, Circle, CircleCheck, Eye, ListChecks, Search, Shirt } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const INHERIT = ["items", "fabrics", "colours", "services", "bom", "fabric_maps", "packing", "attachments"];
const today = () => new Date().toISOString().slice(0, 10);
const shortDate = (iso) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "—");
const number = (v) => (v === null || v === undefined ? "—" : Number(v).toLocaleString("en-US"));

/**
 * Style & Order → Order Confirmation (AnnotechERP 06/07), Brickland's screen:
 * New Order / Quick Confirmation — the buyer, the PO, the order and shipment
 * dates and the currency, then the buyer's approved styles, each picked with
 * its quantity and unit price; Auto Fill Information counts what the picked
 * styles carry into their orders (View Details shows it per style); Confirm
 * Order makes one order per style. Confirmed Orders lists the order book, an
 * order's details a click away.
 *
 * @param {{ buyers: {value: string, label: string}[], currencies: string[], orders: object[], loadStyles: Function, confirm: Function, canManage: boolean }} props
 */
function OrderConfirmation({ buyers, currencies, orders, loadStyles, confirm, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.oc.${k}`, p);
  const mo = (k, p) => t(`master.ord.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [form, setForm] = useState({ buyer_id: "", po_no: "", order_date: today(), ship_date: "", currency: "USD" });
  const [styles, setStyles] = useState([]);
  const [picked, setPicked] = useState({});
  const [query, setQuery] = useState("");
  const [orderQuery, setOrderQuery] = useState("");
  const [details, setDetails] = useState(false);
  const [viewing, setViewing] = useState(null);
  const [errors, setErrors] = useState(null);
  const [loading, startLoading] = useTransition();
  const [pending, startTransition] = useTransition();
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));
  const buyerLabel = buyers.find((b) => b.value === form.buyer_id)?.label ?? "";
  const chosen = styles.filter((s) => picked[s.id]);
  const needle = query.trim().toLowerCase();
  const shownStyles = styles.filter((s) => !needle || `${s.style_no} ${s.description ?? ""} ${s.garment_type ?? ""}`.toLowerCase().includes(needle));
  const oNeedle = orderQuery.trim().toLowerCase();
  const shownOrders = useMemo(() => orders.filter((o) => !oNeedle || `${o.order_no} ${o.buyer ?? ""} ${o.style_no}`.toLowerCase().includes(oNeedle)), [orders, oNeedle]);
  const unitOf = (s) => mo(`unit.${["PCS", "SET", "DZN"].includes(s.qty_unit) ? s.qty_unit : "PCS"}`);

  function pickBuyer(id) {
    set("buyer_id")(id);
    setPicked({});
    setStyles([]);
    if (!id) return;
    startLoading(async () => {
      const result = await loadStyles(id);
      if (result?.status === "success") setStyles(result.data);
      else toastManager.add({ title: result?.message, type: "danger" });
    });
  }

  function toggle(id, on) {
    setPicked((all) => {
      const next = { ...all };
      if (on) next[id] = { qty: "", unit_price: "" };
      else delete next[id];
      return next;
    });
  }

  function submit() {
    startTransition(async () => {
      const result = await confirm({
        ...form,
        po_no: form.po_no.trim(),
        lines: chosen.map((s) => ({ style_id: s.id, qty: Number(picked[s.id].qty) || null, unit_price: picked[s.id].unit_price === "" ? null : Number(picked[s.id].unit_price) })),
      });
      if (result?.status === "success") {
        setErrors(null);
        setPicked({});
        toastManager.add({ title: m("done", { n: result.data.length, list: result.data.map((o) => o.order_no).join(", ") }), type: "success" });
        router.refresh();
        pickBuyer(form.buyer_id);
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 xl:grid-cols-[1fr_20rem]">
        <section className="rounded-lg border border-border bg-surface p-5">
          <h2 className="text-base font-semibold text-foreground">{m("new_title")}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{m("new_hint")}</p>
          {errors ? (
            <Alert variant="danger" className="mt-3">
              {errors.join(" · ")}
            </Alert>
          ) : null}
          <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr]">
            <Field label={m("f.buyer")} required>
              <Select value={form.buyer_id} onValueChange={(v) => pickBuyer(v ?? "")} options={[{ value: "", label: m("ph_buyer") }, ...buyers]} placeholder={m("ph_buyer")} disabled={!canManage} />
            </Field>
            <Field label={m("f.po")} required>
              <Input value={form.po_no} onChange={(e) => set("po_no")(e.target.value)} placeholder="PO-2026-145" disabled={!canManage} />
            </Field>
            <Field label={m("f.order_date")} required>
              <Input type="date" value={form.order_date} onChange={(e) => set("order_date")(e.target.value)} disabled={!canManage} />
            </Field>
            <Field label={m("f.ship_date")} required>
              <Input type="date" value={form.ship_date} onChange={(e) => set("ship_date")(e.target.value)} disabled={!canManage} />
            </Field>
            <Field label={m("f.currency")}>
              <Select value={form.currency} onValueChange={(v) => v && set("currency")(v)} options={currencies.map((c) => ({ value: c, label: c }))} disabled={!canManage} />
            </Field>
          </div>

          <h3 className="mt-5 text-sm font-semibold text-foreground">{form.buyer_id ? m("styles_of", { buyer: buyerLabel }) : m("select_styles")}</h3>
          {!form.buyer_id || (styles.length === 0 && !loading) ? (
            <div className="mt-2 rounded-md bg-surface-muted px-4 py-8 text-center text-sm text-foreground-muted">{form.buyer_id ? m("no_styles") : m("pick_buyer")}</div>
          ) : (
            <div className="mt-2 flex flex-col gap-2">
              <div className="relative max-w-sm">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
                <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search_style")} className="pl-9" />
              </div>
              <div className="overflow-x-auto rounded-md border border-border">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-brand text-brand-foreground">
                      <th className={th}>{m("col.select")}</th>
                      <th className={th}>{m("col.style")}</th>
                      <th className={th}>{m("col.garment")}</th>
                      <th className={th}>{m("col.image")}</th>
                      <th className={th}>{m("col.status")}</th>
                      <th className={th}>{m("col.qty", { unit: chosen[0] ? unitOf(chosen[0]) : mo("unit.PCS") })}</th>
                      <th className={th}>{m("col.price", { ccy: form.currency })}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {shownStyles.map((s) => (
                      <tr key={s.id} className={cn("border-b border-border align-middle last:border-0", picked[s.id] && "bg-brand-subtle/40")}>
                        <td className="px-3 py-2">
                          <Checkbox checked={Boolean(picked[s.id])} onCheckedChange={(c) => toggle(s.id, c === true)} disabled={!canManage} aria-label={s.style_no} />
                        </td>
                        <td className="px-3 py-2">
                          <div className="font-semibold text-foreground">{s.style_no}</div>
                          <div className="text-xs text-foreground-muted">{s.description}</div>
                          {s.orders ? <div className="text-xs text-warning">{m("repeat")} · {s.orders}</div> : null}
                        </td>
                        <td className="px-3 py-2">{s.garment_type ?? "—"}</td>
                        <td className="px-3 py-2">
                          {s.image ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img src={s.image} alt={s.style_no} className="size-10 rounded object-cover" />
                          ) : (
                            <span className="grid size-10 place-items-center rounded bg-surface-muted text-foreground-muted">
                              <Shirt className="size-4" />
                            </span>
                          )}
                        </td>
                        <td className="px-3 py-2">
                          <Badge variant="success">{t(`master.sty.status.${s.status}`)}</Badge>
                        </td>
                        <td className="px-3 py-2">
                          <Input type="number" min="1" step="1" value={picked[s.id]?.qty ?? ""} onChange={(e) => setPicked((all) => ({ ...all, [s.id]: { ...all[s.id], qty: e.target.value } }))} disabled={!picked[s.id]} className="w-28" aria-label={`${s.style_no} ${unitOf(s)}`} />
                        </td>
                        <td className="px-3 py-2">
                          <Input type="number" min="0" step="0.0001" value={picked[s.id]?.unit_price ?? ""} onChange={(e) => setPicked((all) => ({ ...all, [s.id]: { ...all[s.id], unit_price: e.target.value } }))} disabled={!picked[s.id]} className="w-28" aria-label={`${s.style_no} ${form.currency}`} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </section>

        <aside className="h-fit rounded-lg border border-border bg-surface p-5">
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <ListChecks className="size-4" /> {m("auto")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{m("auto_hint")}</p>
          {chosen.length === 0 ? (
            <p className="mt-3 text-[13px] text-foreground-muted">{m("auto_empty")}</p>
          ) : (
            <ul className="mt-3 flex flex-col gap-2">
              {INHERIT.map((k) => {
                const have = chosen.filter((s) => s.inherit[k] > 0).length;
                const full = have === chosen.length;
                return (
                  <li key={k} className="flex items-center justify-between text-sm">
                    <span className="flex items-center gap-2 text-foreground">
                      {full ? <CircleCheck className="size-4 text-success" /> : <Circle className="size-4 text-warning" strokeDasharray="3 3" />}
                      {m(`inherit.${k}`)}
                    </span>
                    <span className={cn("text-xs tabular-nums", full ? "text-success" : "text-warning")}>
                      {have}/{chosen.length}
                    </span>
                  </li>
                );
              })}
            </ul>
          )}
          <div className="mt-4 flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => setDetails(true)} disabled={chosen.length === 0}>
              {m("view")}
            </Button>
            {canManage ? (
              <Button onClick={submit} loading={pending} disabled={!form.buyer_id} className="bg-success text-white hover:bg-success/90">
                <CheckCircle2 /> {m("confirm")}
              </Button>
            ) : null}
          </div>
        </aside>
      </div>

      <section className="rounded-lg border border-border bg-surface">
        <div className="p-5 pb-3">
          <h2 className="text-base font-semibold text-foreground">{m("confirmed")}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{m("confirmed_hint")}</p>
          <div className="relative mt-3 max-w-sm">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
            <Input value={orderQuery} onChange={(e) => setOrderQuery(e.target.value)} placeholder={m("search_orders")} className="pl-9" />
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                <th className={th}>{m("col.order_no")}</th>
                <th className={th}>{m("col.buyer")}</th>
                <th className={th}>{m("col.style")}</th>
                <th className={th}>{m("col.garment")}</th>
                <th className={cn(th, "text-right")}>{m("col.qty_plain")}</th>
                <th className={th}>{m("col.shipment")}</th>
                <th className={th}>{m("col.status")}</th>
                <th className={cn(th, "text-right")}>{m("col.action")}</th>
              </tr>
            </thead>
            <tbody>
              {shownOrders.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-4 py-12 text-center text-foreground-muted">
                    {m("no_orders")}
                  </td>
                </tr>
              ) : (
                shownOrders.map((o) => (
                  <tr key={o.id} className="border-b border-border align-middle last:border-0">
                    <td className="px-3 py-2.5 font-semibold text-foreground">{o.order_no}</td>
                    <td className="px-3 py-2.5">{o.buyer}</td>
                    <td className="px-3 py-2.5">{o.style_no}</td>
                    <td className="px-3 py-2.5">{o.gmt_type || o.item || "—"}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">{number(o.total_qty)}</td>
                    <td className="px-3 py-2.5">{shortDate(o.ship_date)}</td>
                    <td className="px-3 py-2.5">
                      <Badge variant={o.status === "CANCELLED" ? "danger" : o.status === "DRAFT" ? "neutral" : "success"}>{mo(`status.${o.status}`)}</Badge>
                    </td>
                    <td className="px-3 py-2.5 text-right">
                      <Button variant="outline" size="sm" onClick={() => setViewing(o)} aria-label={m("view_order", { no: o.order_no })}>
                        <Eye />
                      </Button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      <Modal open={details} onOpenChange={setDetails} title={m("details_title")} description={m("details_sub", { n: chosen.length })} className="max-w-4xl">
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-brand-foreground">
                <th className={th}>{m("col.style")}</th>
                {INHERIT.map((k) => (
                  <th key={k} className={cn(th, "text-center")}>
                    {m(`inherit.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {chosen.map((s) => (
                <tr key={s.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2 font-semibold">{s.style_no}</td>
                  {INHERIT.map((k) => (
                    <td key={k} className="px-3 py-2 text-center tabular-nums">
                      {s.inherit[k] || "—"}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Modal>

      <Modal open={Boolean(viewing)} onOpenChange={(open) => !open && setViewing(null)} title={viewing?.order_no} description={viewing ? `${viewing.buyer ?? ""} · ${viewing.style_no}` : undefined} className="max-w-lg">
        {viewing ? (
          <dl className="divide-y divide-border text-sm">
            {[
              ["order_no", viewing.order_no],
              ["buyer", viewing.buyer],
              ["style", viewing.style_no],
              ["garment", viewing.gmt_type || viewing.item || "—"],
              ["po", viewing.po_no || viewing.job_no],
              ["quantity", `${number(viewing.total_qty)} ${viewing.unit ? mo(`unit.${viewing.unit}`) : ""}`],
              ["order_date", shortDate(viewing.order_date)],
              ["ship_date", shortDate(viewing.ship_date)],
              ["value", `${Number(viewing.total_price ?? 0).toFixed(2)} ${viewing.currency}`],
              ["status", mo(`status.${viewing.status}`)],
            ].map(([k, v]) => (
              <div key={k} className="flex justify-between gap-4 py-2.5">
                <dt className="text-foreground-muted">{m(`col.${k}`)}</dt>
                <dd className="font-semibold text-foreground">{v}</dd>
              </div>
            ))}
          </dl>
        ) : null}
      </Modal>
    </div>
  );
}

function Field({ label, required = false, children }) {
  return (
    <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
      <span>
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      {children}
    </label>
  );
}

export { OrderConfirmation };
