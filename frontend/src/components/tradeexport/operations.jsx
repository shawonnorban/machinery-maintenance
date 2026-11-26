"use client";

import { useEffect, useState, useTransition } from "react";
import { ArrowLeft, Ban, Plus, RefreshCw, Save, Scale, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { blank, day, EditGrid, Filters, firstError, has, money, qty, Register, within } from "@/components/sales-shipment/shared";
import { ampm } from "@/lib/clock";

/*
 * Trade & Export → Operations (AnnotechERP 22/02), as Brickland lays the
 * screens out: Searching Data over the list, Add New opening the entry (in
 * place, the paper's own number in the address), the entry in its titled
 * boxes with the grids under them, and Back To List / Save / Cancel with the
 * Take Action where the paper has one.
 */

const P = "/tradeexport/ops";
const yes = (v) => (v === "YES" ? true : v === "NO" ? false : null);
const n = (v) => Number(v || 0);
const clean = (form, keys) => Object.fromEntries(keys.map((k) => [k, blank(form[k])]));
const uid = () => Math.random().toString(36).slice(2);

function useW() {
  const t = useT("tradeop");
  return (k, p) => t(k, p);
}

/* ───────────────────────── Form pieces ───────────────────────── */

/** Brickland's titled box. */
function Box({ legend, children, grid = true }) {
  return (
    <fieldset className="min-w-0 rounded-lg border border-border bg-surface p-4">
      <legend className="px-1 text-[13px] font-semibold text-brand">{legend}</legend>
      {grid ? <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">{children}</div> : children}
    </fieldset>
  );
}

function Row({ label, req, wide, hint, children }) {
  return (
    <div className={cn("grid items-center gap-2 sm:grid-cols-[10.5rem_1fr]", wide && "lg:col-span-2")}>
      <span className="text-[13px] font-semibold sm:text-right">
        {req ? <span className="text-danger">* </span> : null}
        {label}
      </span>
      <div className="min-w-0">
        {children}
        {hint ? <p className="mt-1 text-xs text-foreground-muted">{hint}</p> : null}
      </div>
    </div>
  );
}

/** Field makers bound to one form. */
function fieldsOf(w, form, set, locked) {
  const dis = (o) => locked || o.readOnly;
  const input = (type) => (k, label, o = {}) => (
    <Row key={k} label={label} req={o.req} wide={o.wide} hint={o.hint}>
      <Input type={type} step={type === "number" ? "any" : undefined} value={form[k] ?? ""} disabled={dis(o)} placeholder={o.placeholder} onChange={(e) => set(k, e.target.value)} aria-label={label} />
    </Row>
  );
  return {
    text: input("text"),
    date: input("date"),
    num: input("number"),
    area: (k, label, o = {}) => (
      <Row key={k} label={label} req={o.req} wide={o.wide ?? true}>
        <textarea rows={2} value={form[k] ?? ""} disabled={locked} onChange={(e) => set(k, e.target.value)} aria-label={label} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm disabled:bg-surface-muted" />
      </Row>
    ),
    sel: (k, label, options, o = {}) => (
      <Row key={k} label={label} req={o.req} wide={o.wide} hint={o.hint}>
        <Select value={form[k] ?? ""} disabled={dis(o)} onValueChange={(v) => (o.onPick ? o.onPick(v ?? "") : set(k, v ?? ""))} options={options} placeholder={o.placeholder ?? w("select")} aria-label={label} />
      </Row>
    ),
    chk: (k, label, o = {}) => (
      <Row key={k} label={o.side ?? ""} wide={o.wide}>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox checked={Boolean(form[k])} disabled={locked} onCheckedChange={(v) => (o.onPick ? o.onPick(v === true) : set(k, v === true))} aria-label={label} /> {label}
        </label>
      </Row>
    ),
    read: (label, value, o = {}) => (
      <Row key={label} label={label} req={o.req} wide={o.wide}>
        <Input value={value ?? ""} disabled readOnly aria-label={label} />
      </Row>
    ),
  };
}

/** A grid that only shows, with a remove action on a row where allowed. */
function ReadGrid({ w, cols, rows, empty, onRemove }) {
  return (
    <div className="overflow-x-auto rounded-md border border-border">
      <table className="w-full min-w-max text-sm">
        <thead>
          <tr className="bg-brand text-left text-brand-foreground">
            {cols.map((c) => (
              <th key={c.key} className={cn("px-3 py-2 font-semibold whitespace-nowrap", c.right && "text-right")}>
                {c.label}
              </th>
            ))}
            {onRemove ? <th className="px-3 py-2">{w("col.action")}</th> : null}
          </tr>
        </thead>
        <tbody>
          {rows.length ? (
            rows.map((r, i) => (
              <tr key={r._k ?? r.id ?? i} className="border-b border-border last:border-0">
                {cols.map((c) => (
                  <td key={c.key} className={cn("px-3 py-1.5 whitespace-nowrap", c.right && "text-right tabular-nums")}>
                    {c.render ? c.render(r) : (ampm(r[c.key]) ?? "—")}
                  </td>
                ))}
                {onRemove ? (
                  <td className="px-2 py-1">
                    <Button size="sm" variant="ghost" onClick={() => onRemove(r, i)} aria-label={`${w("remove")} ${i + 1}`}>
                      <Trash2 className="text-danger" />
                    </Button>
                  </td>
                ) : null}
              </tr>
            ))
          ) : (
            <tr>
              <td colSpan={cols.length + (onRemove ? 1 : 0)} className="px-3 py-5 text-center text-foreground-muted">
                {empty}
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

/* ───────────────────────── List + entry desk ───────────────────────── */

function Desk({ w, path, itemPath, initial, query, refKey, filters, columns, adds, blankForm, toForm, save, Form, canManage, act, finalAction, takeAction, exportName, opt, decorate, extraActions = [], saveLabel }) {
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [filter, setFilter] = useState({});
  const [form, setForm] = useState(null);
  const [editing, setEditing] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const patch = (o) => setForm((f) => ({ ...f, ...o }));
  const item = itemPath ?? path;
  const reload = () => act.read(path).then((r) => r?.status === "success" && setRows(r.data));
  const address = (no) => {
    const url = new URL(window.location.href);
    if (no) url.searchParams.set("no", no);
    else url.searchParams.delete("no");
    window.history.replaceState(null, "", url);
  };
  const open = (row) =>
    startTransition(async () => {
      const res = await act.read(`${item}/${row.id}`);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setEditing(res.data);
      setForm(toForm(res.data));
      address(row[refKey]);
    });
  useEffect(() => {
    const hit = query?.no && initial.find((r) => r[refKey] === query.no);
    if (hit) open(hit);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const back = () => {
    setForm(null);
    setEditing(null);
    address(null);
    reload();
  };
  const submit = () =>
    startTransition(async () => {
      const res = await save(form, editing);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("saved"), type: "success" });
      setEditing(res.data);
      setForm(toForm(res.data));
      address(res.data[refKey]);
      reload();
    });
  const shown = rows.map(decorate).filter((r) => filters.every((f) => f.test(r, filter[f.name])));

  if (form) {
    const locked = !canManage || Boolean(editing?.locked);
    return (
      <div className="flex flex-col gap-4">
        {editing?.locked ? <p className="rounded-lg bg-warning-subtle px-4 py-2 text-sm text-warning">{w("locked_note")}</p> : null}
        <Form w={w} form={form} set={set} patch={patch} locked={locked} opt={opt} act={act} editing={editing} canManage={canManage} F={fieldsOf(w, form, set, locked)} />
        <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-4">
          <Button variant="outline" onClick={back} disabled={pending}>
            <ArrowLeft /> {w("back")}
          </Button>
          {!locked ? (
            <Button onClick={submit} loading={pending}>
              <Save /> {saveLabel ?? w("save")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={back} disabled={pending}>
            <Ban /> {w("cancel")}
          </Button>
          <span className="flex-1" />
          {takeAction ? (
            <label className="flex items-center gap-2 text-[13px] font-semibold">
              <span>
                <span className="text-danger">* </span>
                {w("take_action")}
              </span>
              <div className="w-48">
                <Select value={form.status} disabled={locked} onValueChange={(v) => set("status", v)} options={takeAction.map((s) => ({ value: s, label: w(`action.${s}`) }))} aria-label={w("take_action")} />
              </div>
            </label>
          ) : null}
        </div>
      </div>
    );
  }
  return (
    <>
      <Filters
        w={w}
        value={filter}
        onChange={setFilter}
        fields={filters}
        extra={
          <>
            {canManage
              ? adds.map((a) => (
                  <Button
                    key={a.key}
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setEditing(null);
                      setForm(blankForm(a.key));
                      address(null);
                    }}
                  >
                    <Plus /> {a.label}
                  </Button>
                ))
              : null}
            <Button type="button" variant="outline" onClick={reload}>
              <RefreshCw /> {w("refresh")}
            </Button>
          </>
        }
      />
      <Register
        w={w}
        rows={shown}
        refKey={refKey}
        exportName={exportName}
        onDone={reload}
        emptyTitle={w("empty")}
        emptyText=""
        actions={[
          { key: "open", label: w("open"), open },
          ...extraActions,
          ...(canManage
            ? [
                ...(finalAction ? [{ key: "final", label: w("final"), when: (r) => !r.final, confirm: w("confirm.final"), done: w("done.final"), run: (no, _e, r) => act.write("POST", `${item}/${r.id}/final`) }] : []),
                { key: "delete", label: w("delete"), when: (r) => !r.locked, confirm: w("confirm.delete"), done: w("done.delete"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${item}/${r.id}`) },
              ]
            : []),
        ]}
        columns={columns}
      />
    </>
  );
}

/* ───────────────────────── Shared filters / columns ───────────────────────── */

const yesNo = (w) => ["YES", "NO"].map((v) => ({ value: v, label: w(`yn.${v}`) }));
const textF = (name, label, field) => ({ name, label, test: (r, v) => has(field(r), v) });
const dateF = (name, label, field, side) => ({ name, label, type: "date", test: (r, v) => (side === "from" ? within(field(r), v, null) : within(field(r), null, v)) });
const selF = (name, label, options, test) => ({ name, label, type: "select", options, test: (r, v) => !v || test(r, v) });
const finalF = (w) => selF("final", w("f.with_final"), yesNo(w), (r, v) => r.final === yes(v));
const companyF = (w, opt) => selF("company", w("f.company"), [opt.company], () => true);
const buyerF = (w, opt, label) => selF("buyer", label ?? w("f.buyer"), opt.buyers, (r, v) => r.buyer_id === v);
const finalCol = (w) => ({ key: "final_label", label: w("col.final"), type: "badge" });
const withFinal = (w, rows) => rows.map((r) => ({ ...r, final_label: w(`yn.${r.final ? "YES" : "NO"}`) }));
const amt = (key, label) => ({ key, label, type: "number", align: "right" });
const branchOpts = (opt) => opt.branches;
const refs = (opt, list) => opt.refs[list] ?? [];
const strip = (o) => ({ value: o.value, label: o.label.replace(/\s\([^)]*\)$/, "") });
const refShort = (opt, list) => refs(opt, list).map(strip);
const today = (opt) => opt.today;
const nulled = (row) => Object.fromEntries(Object.entries(row).map(([k, v]) => [k, v === null ? "" : v]));

/* ───────────────────────── Export L/C Proforma Invoice ───────────────────────── */

const PI_FIELDS = ["pi_no", "buyer_id", "buyer_attention", "pi_date", "pi_amount", "currency", "commodity_type", "inco_term", "delivery_place", "payment_term", "expected_delivery_date", "bank_branch_id", "origin_country_id", "packing", "shipment_mode", "transport_mode", "last_shipment_date", "shipment_schedule", "validity_days", "place_of_signature", "tolerance_pct", "tolerance_description", "payment_way", "delivery_terms", "port_of_loading", "port_of_discharge", "insurance", "shipment_terms", "remarks", "advance_pct", "discount_type", "discount_value", "status"];

function PiForm({ w, form, set, locked, opt, F }) {
  const total = form.lines.reduce((a, l) => a + n(l.qty) * n(l.unit_price), 0);
  const discount = form.discount_type === "PERCENT" ? (total * n(form.discount_value)) / 100 : n(form.discount_value);
  const opts = (list) => list.map((v) => ({ value: v, label: v }));
  return (
    <>
      <Box legend={w("box.master_info")}>
        {F.sel("_c", w("f.beneficiary"), [opt.company], { req: true, readOnly: true, placeholder: opt.company.label })}
        {F.text("buyer_attention", w("f.buyer_attention"), { req: true })}
        {F.sel("buyer_id", w("f.buyer_importer"), opt.buyers, { req: true })}
        {F.date("pi_date", w("f.pi_date"), { req: true })}
        {F.text("pi_no", w("f.pi_no"), { req: true })}
        {F.num("pi_amount", w("f.pi_amount"), { req: true })}
        {F.sel("currency", w("f.currency_code"), opt.currencies, { req: true })}
      </Box>
      <Box legend={w("box.terms")}>
        {F.sel("commodity_type", w("f.commodity"), opts(opt.commodities))}
        {F.sel("inco_term", w("f.inco_term"), refShort(opt, "INCO_TERM"))}
        {F.sel("payment_term", w("f.payment_term"), refShort(opt, "PAYMENT_TERM"))}
        {F.sel("delivery_place", w("f.delivery_place"), refs(opt, "DELIVERY_PLACE"))}
        {F.sel("bank_branch_id", w("f.bank"), branchOpts(opt))}
        {F.date("expected_delivery_date", w("f.expected_delivery"))}
        {F.sel("origin_country_id", w("f.origin"), opt.countries)}
        {F.text("packing", w("f.packing"))}
        {F.sel("shipment_mode", w("f.mode_of_shipment"), refShort(opt, "SHIPMENT_MODE"))}
        {F.sel("transport_mode", w("f.transport_mode"), opts(opt.transport_modes))}
        {F.chk("partial_shipment", w("f.partial_allowed"))}
        {F.chk("transhipment", w("f.transhipment_allowed"))}
        {F.date("last_shipment_date", w("f.last_shipment"))}
        {F.num("validity_days", w("f.validity"))}
        {F.text("place_of_signature", w("f.place_of_signature"))}
        {F.num("tolerance_pct", w("f.tolerance"))}
        {F.text("tolerance_description", w("f.tolerance_desc"))}
        {F.sel("port_of_loading", w("f.port_of_loading"), refShort(opt, "DELIVERY_PLACE"))}
        {F.text("port_of_discharge", w("f.port_of_discharge"))}
        {F.area("shipment_schedule", w("f.shipment_schedule"))}
        {F.area("payment_way", w("f.payment_way"))}
        {F.area("delivery_terms", w("f.delivery_terms"))}
        {F.area("insurance", w("f.insurance"))}
        {F.area("shipment_terms", w("f.shipment_terms"))}
        {F.area("remarks", w("f.remarks_upper"))}
      </Box>
      <EditGrid
        w={w}
        title={w("box.pi_detail")}
        editable={!locked}
        addLabel={w("add")}
        onAdd={() => set("lines", [...form.lines, { _k: uid(), item_group: "", item_type: "", description: "", sku: "", color: "", size: "", qty: "", uom: "", unit_price: "" }])}
        rows={form.lines}
        setRows={(v) => set("lines", v)}
        emptyNote={w("no_data")}
        cols={[
          { key: "item_group", label: w("col.item_group"), type: "select", options: opts(opt.item_groups), width: 170 },
          { key: "item_type", label: w("col.item_type"), type: "select", options: opts(opt.item_types), width: 150 },
          { key: "description", label: w("col.description"), width: 200 },
          { key: "sku", label: w("col.sku"), width: 100 },
          { key: "color", label: w("col.color"), type: "select", options: opts(opt.colours), width: 140 },
          { key: "size", label: w("col.size"), type: "select", options: opts(opt.sizes), width: 110 },
          { key: "qty", label: w("col.quantity"), type: "number", align: "right" },
          { key: "uom", label: w("col.mou"), type: "select", options: opt.uoms.map((u) => ({ value: u.code, label: u.code })), width: 110 },
          { key: "unit_price", label: w("col.unit_price"), type: "number", align: "right" },
          { key: "total", label: w("col.total_price"), align: "right", read: (r) => money(n(r.qty) * n(r.unit_price)) },
        ]}
      />
      <Box legend={w("box.other_info")}>
        {F.read(w("f.total_pi"), money(total))}
        {F.num("advance_pct", w("f.advance_pct"))}
        {F.read(w("f.total_advance"), money((total * n(form.advance_pct)) / 100))}
        <Row label={w("f.discount")}>
          <div className="flex gap-5 text-sm" role="radiogroup" aria-label={w("f.discount")}>
            {["PERCENT", "AMOUNT"].map((v) => (
              <label key={v} className="flex items-center gap-1.5">
                <input type="radio" name="pi-discount" className="size-4 accent-[var(--color-brand)]" disabled={locked} checked={form.discount_type === v} onChange={() => set("discount_type", v)} /> {w(`discount.${v}`)}
              </label>
            ))}
          </div>
        </Row>
        {F.num("discount_value", w("f.discount_amount"))}
        {F.read(w("f.net_pi"), money(total - discount))}
      </Box>
    </>
  );
}

function PiDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const decorate = (r) => ({ ...withFinal(w, [r])[0], status_label: w(`action.${r.status}`) });
  return (
    <Desk
      w={w}
      path={`${P}/pis`}
      initial={initial}
      decorate={decorate}
      query={query}
      refKey="pi_no"
      exportName="export-pi"
      opt={opt}
      canManage={canManage}
      act={act}
      Form={PiForm}
      takeAction={["DRAFT", "SUBMITTED", "COMPLETE", "CANCELLED"]}
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ pi_date: today(opt), currency: "USD", discount_type: "AMOUNT", status: "DRAFT", partial_shipment: false, transhipment: false, lines: [] })}
      toForm={(d) => ({ ...nulled(d), lines: d.lines.map((l) => ({ ...nulled(l), _k: uid() })) })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/pis/${e.id}` : `${P}/pis`, { ...clean(f, PI_FIELDS), discount_type: f.discount_type || "AMOUNT", partial_shipment: Boolean(f.partial_shipment), transhipment: Boolean(f.transhipment), lines: f.lines.map(({ _k, total_price, ...l }) => l) })}
      filters={[textF("no", w("f.pi_no_short"), (r) => r.pi_no), dateF("from", w("f.from"), (r) => r.pi_date, "from"), dateF("to", w("f.to"), (r) => r.pi_date, "to"), companyF(w, opt), buyerF(w, opt)]}
      columns={[finalCol(w), { key: "status_label", label: w("col.status"), type: "badge" }, { key: "pi_no", label: w("col.pi_no"), type: "mono" }, { key: "company", label: w("col.company") }, { key: "buyer", label: w("col.buyer_name") }, { key: "country", label: w("col.export_country") }, amt("total_amount", w("col.total_pi")), amt("advance_amount", w("col.advance_amt")), amt("discount_amount", w("col.discount")), amt("net_amount", w("col.net_pi")), { key: "pi_date", label: w("col.pi_date"), type: "date" }]}
    />
  );
}

/* ───────────────────────── Export L/C & Contract Entry ───────────────────────── */

const LC_FIELDS = ["kind", "lc_no", "buyer_id", "issue_date", "expiry_date", "place_of_expiry_id", "currency", "sender_branch_id", "receiving_branch_id", "advising_branch_id", "inco_term", "delivery_place", "payment_term_description", "credit_tolerance_pct", "port_of_loading", "port_of_discharge", "last_shipment_date", "notify_party", "applicant_id", "lc_amount", "payment_term", "payment_days", "bb_limit_pct", "shipment_mode", "lc_value", "lc_charge", "status"];

/**
 * The L/C's PRC (proceeds realisation certificate), kept apart from the L/C
 * itself: it comes after realisation, when the L/C is long complete — the
 * place Cash Incentive's "Manage PRC" opens (22/05).
 */
function PrcBox({ w, form, patch, canManage, act }) {
  const toastManager = useToastManager();
  const [prc, setPrc] = useState({ prc_no: form.prc_no ?? "", prc_date: form.prc_date ?? "", file: null });
  const [pending, startTransition] = useTransition();
  const save = () =>
    startTransition(async () => {
      let res = await act.write("PUT", `${P}/lcs/${form.id}/prc`, { prc_no: blank(prc.prc_no), prc_date: blank(prc.prc_date) });
      if (res?.status === "success" && prc.file) {
        const body = new FormData();
        body.append("upload", prc.file);
        const r = await fetch(`/api/trade-export/bill-attachment?kind=prc&id=${encodeURIComponent(form.id)}`, { method: "POST", body });
        const json = await r.json().catch(() => ({}));
        res = r.ok ? { status: "success", data: json.data } : { status: "error", message: json?.message };
      }
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("saved"), type: "success" });
      patch(res.data);
      setPrc({ prc_no: res.data.prc_no ?? "", prc_date: res.data.prc_date ?? "", file: null });
    });
  return (
    <Box legend={w("box.prc_info")}>
      <Row label={w("f.prc_id")}>
        <Input value={prc.prc_no} disabled={!canManage} onChange={(e) => setPrc((p) => ({ ...p, prc_no: e.target.value }))} aria-label={w("f.prc_id")} />
      </Row>
      <Row label={w("f.prc_issue_date")}>
        <Input type="date" value={prc.prc_date} disabled={!canManage} onChange={(e) => setPrc((p) => ({ ...p, prc_date: e.target.value }))} aria-label={w("f.prc_issue_date")} />
      </Row>
      <Row label={w("f.prc_doc")} hint={form.prc_file_name ? w("attached", { name: form.prc_file_name }) : undefined}>
        <input type="file" disabled={!canManage} accept=".pdf,.png,.jpg,.jpeg,.webp" onChange={(e) => setPrc((p) => ({ ...p, file: e.target.files?.[0] ?? null }))} aria-label={w("f.prc_doc")} className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-subtle file:px-3 file:py-2 file:text-brand" />
      </Row>
      <Row label={w("f.prc_entry_date")}>
        <Input value={form.prc_entry_date ? day(form.prc_entry_date) : ""} disabled readOnly aria-label={w("f.prc_entry_date")} />
      </Row>
      {canManage ? (
        <div className="flex justify-end lg:col-span-2">
          <Button type="button" variant="outline" onClick={save} loading={pending}>
            <Save /> {w("save_prc")}
          </Button>
        </div>
      ) : null}
    </Box>
  );
}

function LcForm({ w, form, set, patch, locked, opt, act, canManage, F }) {
  const applicants = opt.applicants.filter((a) => !form.buyer_id || a.buyer_id === form.buyer_id);
  const orders = opt.orders.filter((o) => (!form.buyer_id || o.buyer_id === form.buyer_id) && !form.orders.some((x) => x.order_id === o.value));
  const pis = opt.open_pis.filter((p) => (!form.buyer_id || p.buyer_id === form.buyer_id) && !form.pis.includes(p.value));
  const po = form.orders.reduce((a, o) => a + n(o.qty) * n(o.unit_price), 0);
  const addPi = async (id) => {
    if (!id) return;
    const res = await act.read(`${P}/pis/${id}/lines`);
    if (res?.status === "success") patch({ pis: [...form.pis, id], pi_rows: [...form.pi_rows, ...res.data.map((r) => ({ ...r, _k: uid() }))] });
  };
  const [b2b, setB2b] = useState(null);
  const openB2b = async () => {
    const res = await act.read(`${P}/lcs/${form.id}/b2b`);
    if (res?.status === "success") setB2b(res.data);
  };
  const addOrder = (id) => {
    const o = opt.orders.find((x) => x.value === id);
    if (o) set("orders", [...form.orders, { _k: uid(), order_id: o.value, order_no: o.order_no, style_no: o.style_no, unit: o.unit, qty: o.qty, unit_price: o.unit_price }]);
  };
  return (
    <>
      <Box legend={w("box.key_info")}>
        <Row label={w("f.option")} req>
          <div className="flex gap-5 text-sm" role="radiogroup" aria-label={w("f.option")}>
            {["LC", "SC"].map((v) => (
              <label key={v} className="flex items-center gap-1.5">
                <input type="radio" name="lc-kind" className="size-4 accent-[var(--color-brand)]" disabled={locked} checked={form.kind === v} onChange={() => set("kind", v)} /> {w(`kind.${v}`)}
              </label>
            ))}
          </div>
        </Row>
        {F.chk("is_advance", w("f.is_advance"))}
        {F.sel("_c", w("f.beneficiary"), [opt.company], { req: true, readOnly: true, placeholder: opt.company.label })}
        {F.sel("applicant_id", w("f.applicant"), applicants)}
        {F.sel("buyer_id", w("f.buyer"), opt.buyers, { req: true })}
        {F.date("issue_date", w("f.date_of_issue"), { req: true })}
        {F.text("lc_no", w(form.kind === "LC" ? "f.lc_no" : "f.sc_no"), { req: true })}
        {F.sel("place_of_expiry_id", w("f.place_of_expiry"), opt.countries, { req: true })}
        {F.date("expiry_date", w("f.date_of_expiry"), { req: true })}
        {F.num("lc_amount", w("f.lc_amount"))}
        {F.sel("currency", w("f.currency"), opt.currencies, { req: true })}
        {F.chk("same_receiver_bank", w("f.same_receiver"), { onPick: (v) => patch(v ? { same_receiver_bank: true, receiving_branch_id: form.advising_branch_id } : { same_receiver_bank: false }) })}
        {F.sel("sender_branch_id", w("f.sender_bank"), branchOpts(opt), { req: true })}
        <Row label={w("f.payment_term")} req>
          <div className="grid grid-cols-[1fr_6rem] gap-2">
            <Select value={form.payment_term ?? ""} disabled={locked} onValueChange={(v) => set("payment_term", v ?? "")} options={refs(opt, "PAYMENT_TERM")} placeholder={w("select")} aria-label={w("f.payment_term")} />
            <Input type="number" value={form.payment_days ?? ""} disabled={locked} onChange={(e) => set("payment_days", e.target.value)} aria-label={w("f.payment_days")} />
          </div>
        </Row>
        {F.sel("receiving_branch_id", w("f.receiving_bank"), branchOpts(opt), { req: true })}
        <Row label={w("f.bb_limit")}>
          <div className="flex items-center gap-2">
            <Input type="number" step="any" value={form.bb_limit_pct ?? ""} disabled={locked} onChange={(e) => set("bb_limit_pct", e.target.value)} aria-label={w("f.bb_limit")} />
            <Button type="button" variant="outline" className="shrink-0" disabled={!form.id} onClick={openB2b}>
              {w("b2b_summary")}
            </Button>
          </div>
        </Row>
        {F.sel("advising_branch_id", w("f.advising_bank"), branchOpts(opt), { req: true, onPick: (v) => patch(form.same_receiver_bank ? { advising_branch_id: v, receiving_branch_id: v } : { advising_branch_id: v }) })}
        {F.text("port_of_discharge", w("f.port_of_discharge"), { req: true })}
        <Row label={w("f.inco_delivery")} req>
          <div className="grid grid-cols-2 gap-2">
            <Select value={form.inco_term ?? ""} disabled={locked} onValueChange={(v) => set("inco_term", v ?? "")} options={refs(opt, "INCO_TERM")} placeholder={w("select")} aria-label={w("f.inco_term")} />
            <Select value={form.delivery_place ?? ""} disabled={locked} onValueChange={(v) => set("delivery_place", v ?? "")} options={refs(opt, "DELIVERY_PLACE")} placeholder={w("select")} aria-label={w("f.delivery_place")} />
          </div>
        </Row>
        {F.sel("shipment_mode", w("f.shipping_mode"), refs(opt, "SHIPMENT_MODE"))}
        {F.area("payment_term_description", w("f.payment_desc"))}
        {F.num("credit_tolerance_pct", w("f.credit_tolerance"))}
        {F.sel("port_of_loading", w("f.port_of_loading"), refs(opt, "TRANSIT_PORT"), { req: true })}
        <Row label={w("f.shipment")}>
          <div className="flex gap-5">
            {["partial_shipment", "trans_shipment"].map((k) => (
              <label key={k} className="flex items-center gap-2 text-sm">
                <Checkbox checked={Boolean(form[k])} disabled={locked} onCheckedChange={(v) => set(k, v === true)} aria-label={w(`f.${k}`)} /> {w(`f.${k}`)}
              </label>
            ))}
          </div>
        </Row>
        {F.date("last_shipment_date", w("f.last_shipment"), { req: true })}
        {F.text("notify_party", w("f.notify_party"), { req: true })}
        {F.chk("other_info", w("f.other_info"))}
      </Box>
      <Box legend={w("box.link_pi")} grid={false}>
        {!locked ? (
          <div className="mb-3 max-w-2xl">
            <Row label={w("f.export_pi")} req>
              <Select value="" onValueChange={(v) => addPi(v)} options={pis} placeholder={w("select")} aria-label={w("f.export_pi")} />
            </Row>
          </div>
        ) : null}
        <ReadGrid
          w={w}
          empty={w("no_pi")}
          rows={form.pi_rows}
          onRemove={locked ? null : (r) => patch({ pis: form.pis.filter((p) => p !== r.pi_id), pi_rows: form.pi_rows.filter((x) => x.pi_id !== r.pi_id) })}
          cols={[{ key: "pi_no", label: w("col.export_pi_no") }, { key: "item_group", label: w("col.item_group") }, { key: "item_type", label: w("col.item_category") }, { key: "description", label: w("col.description") }, { key: "uom", label: w("col.mou") }, { key: "qty", label: w("col.quantity"), right: true, render: (r) => qty(r.qty) }, { key: "unit_price", label: w("col.unit_price_usd"), right: true, render: (r) => money(r.unit_price) }, { key: "total_price", label: w("col.total_price_usd"), right: true, render: (r) => money(r.total_price) }, { key: "advance_amount", label: w("f.total_advance"), right: true, render: (r) => money(r.advance_amount) }, { key: "discount_amount", label: w("f.discount_amount"), right: true, render: (r) => money(r.discount_amount) }, { key: "net_amount", label: w("f.net_pi"), right: true, render: (r) => money(r.net_amount) }]}
        />
      </Box>
      <Box legend={w("box.link_po")} grid={false}>
        {!locked ? (
          <div className="mb-3 max-w-2xl">
            <Row label={w("f.order_number")}>
              <Select value="" onValueChange={(v) => addOrder(v)} options={orders} placeholder={w("select")} aria-label={w("f.order_number")} />
            </Row>
          </div>
        ) : null}
        <EditGrid
          w={w}
          title=""
          editable={!locked}
          rows={form.orders}
          setRows={(v) => set("orders", v)}
          emptyNote={w("no_order")}
          cols={[{ key: "order_no", label: w("col.order_no"), read: (r) => r.order_no }, { key: "style_no", label: w("col.style_no"), read: (r) => r.style_no }, { key: "unit", label: w("col.unit"), read: (r) => r.unit }, { key: "qty", label: w("col.quantity"), type: "number", align: "right" }, { key: "unit_price", label: w("col.unit_price_usd"), type: "number", align: "right" }, { key: "t", label: w("col.total_price_usd"), align: "right", read: (r) => money(n(r.qty) * n(r.unit_price)) }]}
        />
        <div className="mt-3 ml-auto grid max-w-xl gap-3">
          {F.num("lc_value", w("f.lc_value"), { req: true })}
          {F.read(w("f.po_value"), money(po))}
          <Row label={w("f.difference")}>
            <div className="flex items-center gap-3">
              <Input value={money(n(form.lc_value) - po)} disabled readOnly aria-label={w("f.difference")} />
              <label className="flex shrink-0 items-center gap-2 text-sm">
                <Checkbox checked={Boolean(form.ignore_difference)} disabled={locked} onCheckedChange={(v) => set("ignore_difference", v === true)} aria-label={w("f.ignore_difference")} /> {w("f.ignore_difference")}
              </label>
            </div>
          </Row>
          {F.num("lc_charge", w("f.lc_charge"))}
        </div>
      </Box>
      {form.id ? <PrcBox key={form.id} w={w} form={form} patch={patch} canManage={canManage} act={act} /> : null}
      <Modal open={b2b !== null} onOpenChange={(o) => !o && setB2b(null)} title={w("b2b_title", { no: b2b?.lc_no ?? "" })} className="max-w-3xl">
        {b2b ? (
          <div className="flex flex-col gap-3">
            <ReadGrid w={w} empty={w("no_data")} rows={b2b.rows} cols={[{ key: "lc_no", label: w("col.b2b_lc") }, { key: "supplier", label: w("col.supplier") }, { key: "commodity", label: w("col.commodity") }, { key: "issue_date", label: w("col.issue_date"), render: (r) => day(r.issue_date) }, { key: "lc_value", label: w("col.lc_value"), right: true, render: (r) => money(r.lc_value) }]} />
            <p className="text-sm">{w("b2b_line", { value: money(b2b.lc_value), pct: b2b.bb_limit_pct ?? "—", limit: b2b.limit === null ? "—" : money(b2b.limit), used: money(b2b.used), left: b2b.left === null ? "—" : money(b2b.left) })}</p>
          </div>
        ) : null}
      </Modal>
    </>
  );
}

function LcDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const decorate = (r) => ({ ...withFinal(w, [r])[0], kind_label: w(`kind.${r.kind}`), merge_label: w(`merge.${r.merge_state}`), status_label: w(`action.${r.status}`) });
  return (
    <Desk
      w={w}
      path={`${P}/lcs`}
      initial={initial}
      decorate={decorate}
      query={query}
      refKey="lc_no"
      exportName="export-lc"
      opt={opt}
      canManage={canManage}
      act={act}
      Form={LcForm}
      takeAction={["DRAFT", "SUBMITTED", "COMPLETE"]}
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ kind: "SC", status: "DRAFT", credit_tolerance_pct: 3, issue_date: today(opt), lc_amount: 0, lc_value: 0, payment_days: 0, pis: [], pi_rows: [], orders: [] })}
      toForm={(d) => ({ ...nulled(d), pi_rows: d.pi_rows.map((r) => ({ ...r, _k: uid() })), orders: d.orders.map((o) => ({ ...o, _k: uid() })) })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/lcs/${e.id}` : `${P}/lcs`, { ...clean(f, LC_FIELDS), same_receiver_bank: Boolean(f.same_receiver_bank), partial_shipment: Boolean(f.partial_shipment), trans_shipment: Boolean(f.trans_shipment), other_info: Boolean(f.other_info), is_advance: Boolean(f.is_advance), ignore_difference: Boolean(f.ignore_difference), pis: f.pis, orders: f.orders.map((o) => ({ order_id: o.order_id, qty: o.qty, unit_price: o.unit_price })) })}
      filters={[textF("no", w("f.lc_document_no"), (r) => r.lc_no), dateF("from", w("f.issue_from"), (r) => r.issue_date, "from"), dateF("to", w("f.issue_to"), (r) => r.issue_date, "to"), companyF(w, opt), buyerF(w, opt), finalF(w), selF("merge", w("f.merge"), ["NA", "MARGED", "REPLACED"].map((v) => ({ value: v, label: w(`merge.${v}`) })), (r, v) => r.merge_state === v), selF("term", w("f.payment_term"), refShort(opt, "PAYMENT_TERM"), (r, v) => r.payment_term === v), selF("bank", w("f.receiving_bank"), branchOpts(opt), (r, v) => r.receiving_branch_id === v), textF("ud", w("f.ud_no"), (r) => r.ud_no)]}
      columns={[finalCol(w), { key: "status_label", label: w("col.status"), type: "badge" }, { key: "lc_no", label: w("col.lc_no"), type: "mono" }, { key: "issue_date", label: w("col.issue_date"), type: "date" }, amt("lc_value", w("col.lc_value")), { key: "kind_label", label: w("col.lc_sc") }, { key: "merge_label", label: w("col.merge") }, { key: "company", label: w("col.company") }, { key: "buyer", label: w("col.buyer_name") }, { key: "country", label: w("col.export_country") }, { key: "payment_term_label", label: w("col.payment_term") }, { key: "amendment_no", label: w("col.amdt"), type: "number", align: "right" }, { key: "ud_no", label: w("col.ud_no") }]}
    />
  );
}

/* ───────────────────────── Export Commercial Invoice ───────────────────────── */

const INV_FIELDS = ["lc_id", "invoice_prefix", "invoice_date", "currency", "loc_exchange_rate", "exp_no", "exp_part_no", "exp_date", "forwarder", "ex_factory_date", "on_board_date", "bl_no", "bl_date", "handover_date", "payment_term", "payment_days", "port_of_loading", "port_of_discharge", "delivery_country_id", "target_collection_date", "shipment_mode", "applicant_id", "penalty_amount", "agency_commission", "sales_discount", "freight_type", "freight_charges", "export_percentage", "vessel_no", "exp_reg_no", "exp_reg_date", "marks_and_nos", "pre_carriage_by", "container_size", "container_no", "exp_inv_rex", "remarks"];
const newLine = (o) => ({ _k: uid(), order_id: o.order_id, order_no: o.order_no, style_no: o.style_no, description: o.description ?? "", unit: o.unit, po_qty: o.po_qty, done_qty: o.done_qty, unit_price: o.unit_price, inv_qty: "", ctn_qty: "", net_weight: "", gross_weight: "", cbm: "", ctn_measurement: "", pattern: "", s_all: "", hs_code: "", hs_category: "", sizes: o.sizes ?? [], size_breakdown: [] });

function InvoiceForm({ w, form, set, patch, locked, opt, act, editing, F }) {
  const [source, setSource] = useState(null);
  const [populate, setPopulate] = useState(false);
  const [bd, setBd] = useState(null);
  const except = editing ? `?except=${editing.id}` : "";
  const loadLc = async (id, fresh) => {
    const res = await act.read(`${P}/invoices/source/${id}${except}`);
    if (res?.status !== "success") return;
    setSource(res.data);
    if (fresh) {
      const l = res.data.lc;
      patch({ lc_id: id, currency: l.currency, applicant_id: l.applicant_id ?? "", payment_term: l.payment_term, payment_days: l.payment_days, port_of_loading: l.port_of_loading, shipment_mode: l.shipment_mode ?? "", loc_exchange_rate: opt.rates[l.currency] ?? "" });
    }
  };
  useEffect(() => {
    if (form.lc_id) loadLc(form.lc_id, false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing?.id, editing?.net_amount]);
  const lc = source?.lc;
  const buyerId = lc?.buyer_id;
  const applicants = opt.applicants.filter((a) => !buyerId || a.buyer_id === buyerId);
  const orders = opt.orders.filter((o) => (!buyerId || o.buyer_id === buyerId) && !form.lines.some((l) => l.order_id === o.value));
  const addOrder = async (id) => {
    if (!id) return;
    const res = await act.read(`${P}/invoices/order/${id}${except}`);
    if (res?.status === "success") set("lines", [...form.lines, newLine(res.data)]);
  };
  const togglePopulate = (v) => {
    setPopulate(v);
    if (v && source) set("lines", [...form.lines, ...source.orders.filter((o) => !form.lines.some((l) => l.order_id === o.order_id)).map(newLine)]);
  };
  const invoiced = form.lines.reduce((a, l) => a + n(l.inv_qty) * n(l.unit_price), 0);
  const net = invoiced - n(form.penalty_amount) - n(form.agency_commission) - n(form.sales_discount);
  const totalPcs = form.lines.reduce((a, l) => a + n(l.inv_qty), 0);
  const line = bd === null ? null : form.lines[bd.i];
  return (
    <>
      <Box legend={w("box.master_info")}>
        {F.sel("lc_id", w("f.export_lc_no"), opt.lcs, { req: true, onPick: (v) => v && loadLc(v, true) })}
        {F.read(w("f.buyer"), lc?.buyer, { req: true })}
        {F.read(w("f.export_lc_amount"), lc ? money(lc.lc_value) : "", { req: true })}
        {F.read(w("f.receiving_bank"), lc?.receiving_bank, { req: true })}
        {F.read(w("f.beneficiary"), lc?.beneficiary ?? opt.company.label, { req: true })}
        {F.sel("applicant_id", w("f.applicant_importer"), applicants, { req: true })}
        <Row label={w("f.invoice_prefix")} req>
          <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
            <Input value={form.invoice_prefix ?? ""} disabled={locked} onChange={(e) => set("invoice_prefix", e.target.value)} aria-label={w("f.invoice_prefix")} />
            <span className="text-[13px] font-semibold">{w("f.auto_no")}</span>
            <Input value={form.invoice_no ?? ""} disabled readOnly placeholder={w("auto_gen")} aria-label={w("f.auto_no")} />
          </div>
        </Row>
        {F.date("invoice_date", w("f.invoice_date"), { req: true })}
        {F.sel("currency", w("f.currencies"), opt.currencies, { req: true })}
        {F.num("loc_exchange_rate", w("f.loc_exc_rate"))}
        <Row label={w("f.exp_no")} req>
          <div className="grid grid-cols-[1fr_6rem] gap-2">
            <Input value={form.exp_no ?? ""} disabled={locked} onChange={(e) => set("exp_no", e.target.value)} aria-label={w("f.exp_no")} />
            <Select value={String(form.exp_part_no ?? 3)} disabled={locked} onValueChange={(v) => set("exp_part_no", v)} options={[1, 2, 3, 4].map((v) => ({ value: String(v), label: String(v) }))} aria-label={w("f.exp_part")} />
          </div>
        </Row>
        {F.date("exp_date", w("f.exp_date"), { req: true })}
        {F.sel("forwarder", w("f.forwarder"), refs(opt, "FORWARDER"))}
        <Row label={w("f.bl_no")}>
          <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
            <Input value={form.bl_no ?? ""} disabled={locked} onChange={(e) => set("bl_no", e.target.value)} aria-label={w("f.bl_no")} />
            <span className="text-[13px] font-semibold">{w("f.bl_date")}</span>
            <Input type="date" value={form.bl_date ?? ""} disabled={locked} onChange={(e) => set("bl_date", e.target.value)} aria-label={w("f.bl_date")} />
          </div>
        </Row>
        {F.date("ex_factory_date", w("f.ex_factory"), { req: true })}
        {F.date("handover_date", w("f.handover"), { req: true })}
        {F.date("on_board_date", w("f.on_board"))}
        {F.sel("delivery_country_id", w("f.delivery_country"), opt.countries, { req: true })}
        <Row label={w("f.payment_term")} req>
          <div className="grid grid-cols-[1fr_6rem] gap-2">
            <Select value={form.payment_term ?? ""} disabled={locked} onValueChange={(v) => set("payment_term", v ?? "")} options={refs(opt, "PAYMENT_TERM")} placeholder={w("select")} aria-label={w("f.payment_term")} />
            <Input type="number" value={form.payment_days ?? ""} disabled={locked} onChange={(e) => set("payment_days", e.target.value)} aria-label={w("f.payment_days")} />
          </div>
        </Row>
        {F.date("target_collection_date", w("f.tgt_collection"))}
        {F.sel("port_of_loading", w("f.port_of_loading"), refs(opt, "TRANSIT_PORT"), { req: true })}
        {F.sel("port_of_discharge", w("f.port_of_discharge"), refs(opt, "TRANSIT_PORT"), { req: true })}
        {F.sel("shipment_mode", w("f.shipping_mode"), refs(opt, "SHIPMENT_MODE"), { req: true })}
        {F.chk("is_negotiated", w("f.is_negotiated"))}
        {F.read(w("f.invoiced_amount"), money(invoiced), { req: true })}
        {F.num("agency_commission", w("f.agency_commission"))}
        {F.num("penalty_amount", w("f.penalty"))}
        {F.num("sales_discount", w("f.sales_discount"))}
        {F.sel("freight_type", w("f.freight_type"), ["Collect", "Prepaid"].map((v) => ({ value: v, label: w(`freight.${v}`) })))}
        {F.num("freight_charges", w("f.freight_charges"))}
        {F.chk("adjust_against_advance", w("f.adjust_advance"))}
        {F.num("export_percentage", w("f.export_pct"))}
        {F.read(w("f.net_amount"), money(net), { req: true })}
        {F.sel("container_size", w("f.container_size"), opt.container_sizes.map((v) => ({ value: v, label: v })))}
        {F.text("vessel_no", w("f.vessel_no"))}
        {F.date("exp_reg_date", w("f.exp_reg_date"))}
        {F.text("exp_reg_no", w("f.exp_reg_no"))}
        {F.text("exp_inv_rex", w("f.exp_inv_rex"))}
        {F.text("marks_and_nos", w("f.marks"))}
        {F.text("pre_carriage_by", w("f.pre_carriage"))}
        {F.text("container_no", w("f.container_no"), { wide: true })}
        {F.area("remarks", w("f.remarks"))}
      </Box>
      <Box legend={w("box.link_po")} grid={false}>
        {!locked ? (
          <div className="mb-3 flex max-w-3xl flex-wrap items-center gap-4">
            <div className="min-w-96 flex-1">
              <Row label={w("f.order_number")}>
                <Select value="" onValueChange={(v) => addOrder(v)} options={orders} placeholder={w("select")} aria-label={w("f.order_number")} />
              </Row>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <Checkbox checked={populate} disabled={!source} onCheckedChange={(v) => togglePopulate(v === true)} aria-label={w("f.populate")} /> {w("f.populate")}
            </label>
          </div>
        ) : null}
        <EditGrid
          w={w}
          title=""
          editable={!locked}
          rows={form.lines}
          setRows={(v) => set("lines", v)}
          emptyNote={w("no_order")}
          summaries={[[w("totals"), qty(totalPcs)]]}
          cols={[
            { key: "order_no", label: w("col.order_no"), read: (r) => r.order_no },
            { key: "style_no", label: w("col.style_no"), read: (r) => r.style_no },
            { key: "pattern", label: w("col.pattern"), width: 90 },
            { key: "s_all", label: w("col.s_all"), width: 80 },
            { key: "hs_code", label: w("col.hs_code"), width: 100 },
            { key: "hs_category", label: w("col.hs_category"), width: 110 },
            { key: "description", label: w("col.description"), width: 180 },
            { key: "unit", label: w("col.unit"), read: (r) => r.unit },
            { key: "po_qty", label: w("col.po_qty"), align: "right", read: (r) => qty(r.po_qty) },
            { key: "done_qty", label: w("col.done_qty"), align: "right", read: (r) => qty(r.done_qty) },
            { key: "bd", label: w("col.breakdown"), read: (r) => (r.sizes?.length ? <Button size="sm" variant="outline" onClick={() => { const i = form.lines.findIndex((x) => x._k === r._k); setBd({ i, rows: r.sizes.map((s) => ({ ...s, inv: r.size_breakdown?.find((b) => b.colour === s.colour && b.size === s.size)?.qty ?? "" })) }); }}>{w("breakdown")}</Button> : "—") },
            { key: "inv_qty", label: w("col.inv_qty"), type: "number", align: "right" },
            { key: "unit_price", label: w("col.unit_price_usd"), type: "number", align: "right" },
            { key: "amt", label: w("col.invoiced_usd"), align: "right", read: (r) => money(n(r.inv_qty) * n(r.unit_price)) },
            { key: "ctn_qty", label: w("col.ctn_qty"), type: "number", align: "right", width: 80 },
            { key: "net_weight", label: w("col.net_weight"), type: "number", align: "right", width: 90 },
            { key: "gross_weight", label: w("col.gross_weight"), type: "number", align: "right", width: 90 },
            { key: "cbm", label: w("col.cbm"), type: "number", align: "right", width: 80 },
            { key: "ctn_measurement", label: w("col.ctn_measure"), width: 110 },
          ]}
        />
      </Box>
      <Box legend={w("box.invoice_list")} grid={false}>
        <ReadGrid
          w={w}
          empty={w("pick_lc")}
          rows={source?.invoices ?? []}
          cols={[{ key: "invoice_no", label: w("col.inv_no") }, { key: "invoice_date", label: w("col.inv_date"), render: (r) => day(r.invoice_date) }, { key: "amount", label: w("col.inv_amt"), right: true, render: (r) => money(r.amount) }, { key: "buyer", label: w("col.buyer") }, { key: "lc_no", label: w("col.lc_no") }, { key: "lc_value", label: w("col.lc_val"), right: true, render: (r) => money(r.lc_value) }, { key: "currency", label: "$" }, { key: "order_no", label: w("col.order_no") }, { key: "unit", label: w("col.unit") }, { key: "ctn", label: w("col.total_ctn"), right: true }, { key: "pcs", label: w("col.total_pcs"), right: true, render: (r) => qty(r.pcs) }, { key: "shipment_mode", label: w("col.ship_mode") }, { key: "freight", label: w("col.freight"), right: true, render: (r) => money(r.freight) }]}
        />
        <div className="mt-3 ml-auto grid max-w-md gap-3">
          {F.read(w("f.total_bill"), money(source?.total_bill ?? 0))}
          {F.read(w("f.total_lc_due"), money(source?.lc_due ?? 0))}
        </div>
      </Box>
      <Modal open={bd !== null} onOpenChange={(o) => !o && setBd(null)} title={w("breakdown_title", { no: line?.order_no ?? "" })} className="max-w-xl">
        {bd ? (
          <div className="flex flex-col gap-3">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-surface-muted text-left">
                  <th className="px-3 py-2">{w("col.color")}</th>
                  <th className="px-3 py-2">{w("col.size")}</th>
                  <th className="px-3 py-2 text-right">{w("col.po_qty")}</th>
                  <th className="px-3 py-2 text-right">{w("col.inv_qty")}</th>
                </tr>
              </thead>
              <tbody>
                {bd.rows.map((s, i) => (
                  <tr key={`${s.colour}-${s.size}`} className="border-b border-border">
                    <td className="px-3 py-1.5">{s.colour}</td>
                    <td className="px-3 py-1.5">{s.size}</td>
                    <td className="px-3 py-1.5 text-right">{qty(s.qty)}</td>
                    <td className="px-3 py-1.5">
                      <Input type="number" className="ml-auto w-28 text-right" value={s.inv} disabled={locked} aria-label={`${s.colour} ${s.size}`} onChange={(e) => setBd({ ...bd, rows: bd.rows.map((x, j) => (j === i ? { ...x, inv: e.target.value } : x)) })} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {!locked ? (
              <div className="flex justify-end">
                <Button
                  onClick={() => {
                    const chosen = bd.rows.filter((s) => n(s.inv) > 0).map((s) => ({ colour: s.colour, size: s.size, qty: n(s.inv) }));
                    const sum = chosen.reduce((a, s) => a + s.qty, 0);
                    set("lines", form.lines.map((l, j) => (j === bd.i ? { ...l, size_breakdown: chosen, inv_qty: chosen.length ? sum : l.inv_qty } : l)));
                    setBd(null);
                  }}
                >
                  {w("apply")}
                </Button>
              </div>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </>
  );
}

function InvoiceDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const decorate = (r) => ({ ...withFinal(w, [r])[0], collected_label: w(`yn.${r.bill_collected ? "YES" : "NO"}`), finance_label: w(`yn.${r.bank_finance ? "YES" : "NO"}`) });
  return (
    <Desk
      w={w}
      path={`${P}/invoices`}
      initial={initial}
      decorate={decorate}
      query={query}
      refKey="invoice_no"
      exportName="commercial-invoice"
      opt={opt}
      canManage={canManage}
      act={act}
      Form={InvoiceForm}
      finalAction
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ invoice_date: today(opt), exp_part_no: 3, payment_days: 0, penalty_amount: 0, agency_commission: 0, sales_discount: 0, freight_charges: 0, lines: [] })}
      toForm={(d) => ({ ...nulled(d), lines: d.lines.map((l) => ({ ...nulled(l), size_breakdown: l.size_breakdown ?? [], sizes: l.sizes ?? [], _k: uid() })) })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/invoices/${e.id}` : `${P}/invoices`, { ...clean(f, INV_FIELDS), adjust_against_advance: Boolean(f.adjust_against_advance), is_negotiated: Boolean(f.is_negotiated), lines: f.lines.map(({ _k, order_no, style_no, po_qty, done_qty, sizes, ...l }) => l) })}
      filters={[textF("no", w("f.invoice_no"), (r) => r.invoice_no), textF("lc", w("f.lc_no_short"), (r) => r.lc_no), dateF("from", w("f.inv_from"), (r) => r.invoice_date, "from"), dateF("to", w("f.inv_to"), (r) => r.invoice_date, "to"), dateF("ob_from", w("f.ob_from"), (r) => r.on_board_date, "from"), dateF("ob_to", w("f.ob_to"), (r) => r.on_board_date, "to"), companyF(w, opt), textF("order", w("f.order_no"), (r) => r.order_no), buyerF(w, opt), selF("ob", w("f.with_on_board"), ["WITH", "WITHOUT"].map((v) => ({ value: v, label: w(`onboard.${v}`) })), (r, v) => (v === "WITH") === Boolean(r.on_board_date)), finalF(w)]}
      columns={[finalCol(w), { key: "collected_label", label: w("col.bill_collected") }, { key: "invoice_no", label: w("col.inv_no"), type: "mono" }, { key: "bl_no", label: w("col.bl_no") }, { key: "on_board_date", label: w("col.on_board"), type: "date" }, { key: "buyer", label: w("col.buyer") }, { key: "lc_no", label: w("col.lc_no"), type: "mono" }, { key: "order_no", label: w("col.order_no") }, { key: "exp_no", label: w("col.exp_no") }, { key: "boe_no", label: w("col.boe_no") }, { key: "finance_label", label: w("col.bank_finance") }, { key: "bank", label: w("col.bank") }, { key: "ctn", label: w("col.ctn"), type: "number", align: "right" }, { key: "pcs", label: w("col.pcs"), type: "number", align: "right" }, amt("amount", w("col.inv_amt")), amt("freight", w("col.freight")), { key: "ex_factory_date", label: w("col.ex_fact"), type: "date" }, { key: "cfs_date", label: w("col.cfs"), type: "date" }]}
    />
  );
}

/* ───────────────────────── Export Bill Lodgement ───────────────────────── */

function LodgementForm({ w, form, set, patch, locked, act, editing, F }) {
  const [pool, setPool] = useState([]);
  useEffect(() => {
    act.read(`${P}/lodgements/invoices${editing ? `?except=${editing.id}` : ""}`).then((r) => r?.status === "success" && setPool(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const left = pool.filter((p) => !form.invoice_rows.some((r) => r.id === p.id)).map((p) => ({ value: p.id, label: `${p.invoice_no} · ${p.buyer}` }));
  return (
    <>
      <Box legend={w("box.master_info")}>
        {F.text("bill_no", w("f.bill_no"), { req: true })}
        {F.date("bill_date", w("f.bill_date"), { req: true })}
        {F.num("charge", w("f.export_bill_charge"))}
        {F.text("pass_book_page", w("f.pass_book"))}
        <Row label={w("f.export_invoice")} req>
          <Select value="" disabled={locked} onValueChange={(v) => { const hit = pool.find((p) => p.id === v); if (hit) patch({ invoice_rows: [...form.invoice_rows, hit] }); }} options={left} placeholder={w("search_invoices")} aria-label={w("f.export_invoice")} />
        </Row>
        <Row label={w("f.attached_doc")} hint={form.file_name ? w("attached", { name: form.file_name }) : undefined}>
          <input type="file" disabled={locked} accept=".pdf,.png,.jpg,.jpeg,.webp" onChange={(e) => set("_file", e.target.files?.[0] ?? null)} aria-label={w("f.attached_doc")} className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-subtle file:px-3 file:py-2 file:text-brand" />
        </Row>
        {F.area("remarks", w("f.remarks"))}
      </Box>
      <Box legend={w("box.detail_info")} grid={false}>
        <ReadGrid
          w={w}
          empty={w("no_invoice_bill")}
          rows={form.invoice_rows}
          onRemove={locked ? null : (r) => patch({ invoice_rows: form.invoice_rows.filter((x) => x.id !== r.id) })}
          cols={[{ key: "beneficiary", label: w("col.exporter") }, { key: "buyer", label: w("col.importer") }, { key: "lc_no", label: w("col.export_lc") }, { key: "lc_value", label: w("col.export_lc_value"), right: true, render: (r) => money(r.lc_value) }, { key: "invoice_no", label: w("col.invoice_no") }, { key: "exp_no", label: w("col.exp_no_full") }, { key: "pcs", label: w("col.invoice_qty"), right: true, render: (r) => qty(r.pcs) }, { key: "net", label: w("col.invoice_value"), right: true, render: (r) => money(r.net) }, { key: "net_weight", label: w("col.net_weight"), right: true, render: (r) => qty(r.net_weight) }, { key: "ud", label: w("col.ud") }]}
        />
      </Box>
    </>
  );
}

function LodgementDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const decorate = (r) => withFinal(w, [r])[0];
  const upload = async (id, file) => {
    const body = new FormData();
    body.append("upload", file);
    const r = await fetch(`/api/trade-export/bill-attachment?id=${encodeURIComponent(id)}`, { method: "POST", body });
    return r.ok ? { status: "success" } : { status: "error", message: (await r.json().catch(() => ({})))?.message };
  };
  return (
    <Desk
      w={w}
      path={`${P}/lodgements`}
      initial={initial}
      decorate={decorate}
      query={query}
      refKey="bill_no"
      exportName="export-bill-lodgement"
      opt={opt}
      canManage={canManage}
      act={act}
      Form={LodgementForm}
      finalAction
      adds={[{ key: "new", label: w("add_new") }]}
      blankForm={() => ({ bill_date: today(opt), charge: "", invoice_rows: [] })}
      toForm={(d) => ({ ...nulled(d), invoice_rows: d.invoice_rows })}
      save={async (f, e) => {
        const res = await act.write(e ? "PUT" : "POST", e ? `${P}/lodgements/${e.id}` : `${P}/lodgements`, { ...clean(f, ["bill_no", "bill_date", "charge", "pass_book_page", "remarks"]), invoices: f.invoice_rows.map((r) => r.id) });
        if (res?.status !== "success" || !f._file) return res;
        const up = await upload(res.data.id, f._file);
        if (up.status !== "success") return up;
        const again = await act.read(`${P}/lodgements/${res.data.id}`);
        return again;
      }}
      extraActions={[{ key: "doc", label: w("doc"), when: (r) => Boolean(r.file_id), open: (r) => window.open(`/api/files/${r.file_id}/download`, "_blank") }]}
      filters={[textF("no", w("f.bill_no"), (r) => r.bill_no), textF("inv", w("f.exp_inv_ud"), (r) => r.invoices), dateF("from", w("f.from"), (r) => r.bill_date, "from"), dateF("to", w("f.to"), (r) => r.bill_date, "to")]}
      columns={[{ key: "file_name", label: w("col.doc") }, finalCol(w), { key: "bill_no", label: w("col.bill_of_exp_no"), type: "mono" }, { key: "bill_date", label: w("col.bill_of_exp_date"), type: "date" }, { key: "invoices", label: w("col.invoices_ud") }, { key: "remarks", label: w("col.remarks") }, amt("charge", w("col.charge"))]}
    />
  );
}

/* ───────────────────────── Distribution grid ───────────────────────── */

function Distribution({ w, form, set, locked, opt, legend, match }) {
  const toast = useToastManager();
  const rows = form.distribution;
  const side = (s) => rows.reduce((a, r) => a + (r.dr_cr === s ? n(r.amount) : 0), 0);
  const balance = () => {
    if (rows.length < 2) return toast.add({ title: w("match_need"), type: "warning" });
    const others = rows.slice(0, -1);
    const diff = others.reduce((a, r) => a + (r.dr_cr === "DR" ? n(r.amount) : -n(r.amount)), 0);
    set("distribution", [...others, { ...rows[rows.length - 1], dr_cr: diff >= 0 ? "CR" : "DR", amount: Math.abs(Number(diff.toFixed(4))) }]);
  };
  return (
    <div className="flex flex-col gap-2">
      <EditGrid
        w={w}
        title={legend}
        editable={!locked}
        addLabel={w("add_row")}
        onAdd={() => set("distribution", [...rows, { _k: uid(), account_id: "", dr_cr: "DR", currency: form.currency || form.local_currency || "BDT", amount: "", note: "" }])}
        rows={rows}
        setRows={(v) => set("distribution", v)}
        emptyNote={w("no_rows")}
        summaries={[[w("total_dr"), money(side("DR"))], [w("total_cr"), money(side("CR"))]]}
        cols={[
          { key: "account_id", label: w("col.account_head"), type: "select", options: opt.accounts, width: 190 },
          { key: "dr_cr", label: w("col.dr_cr"), type: "select", options: ["DR", "CR"].map((v) => ({ value: v, label: v })), width: 80 },
          { key: "currency", label: w("col.currency"), type: "select", options: opt.currencies, width: 90 },
          { key: "amount", label: w("col.amount"), type: "number", align: "right", width: 100 },
          { key: "note", label: w("col.note"), width: 110 },
        ]}
      />
      {match && !locked ? (
        <div>
          <Button variant="outline" onClick={balance}>
            <Scale /> {w("match")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}

/* ───────────────────────── Bill of Exchange Draft ───────────────────────── */

const MARGIN_TYPES = ["MARGIN", "COMMISSION", "INTEREST", "BANK_CHARGE"];
const BOE_FIELDS = ["margin_type", "bill_no", "lc_id", "buyer_id", "applicant_id", "bill_date", "bill_amount", "currency", "local_rate", "local_currency", "issuing_branch_id", "advising_branch_id", "doc_credit_branch_id", "receiving_branch_id", "payment_term", "remarks", "bank_bill_type", "bank_bill_ref_no", "remittance_ref_no", "due_date", "ref_date", "link_type", "pi_id", "order_id"];

function BoeForm({ w, form, set, patch, locked, opt, act, editing, F }) {
  const [source, setSource] = useState(null);
  const [ticked, setTicked] = useState([]);
  const except = editing ? `?except=${editing.id}` : "";
  const loadLc = async (id, fresh) => {
    const res = await act.read(`${P}/boes/source/${id}${except}`);
    if (res?.status !== "success") return;
    setSource(res.data);
    if (fresh) {
      const l = res.data.lc;
      patch({ lc_id: id, buyer_id: l.buyer_id, applicant_id: l.applicant_id ?? "", currency: l.currency, payment_term: l.payment_term, receiving_branch_id: l.receiving_branch_id ?? "", advising_branch_id: l.advising_branch_id ?? "", issuing_branch_id: l.sender_branch_id ?? "", local_rate: form.local_rate || (opt.rates[l.currency] ?? ""), invoice_rows: [] });
    }
  };
  useEffect(() => {
    if (form.lc_id) loadLc(form.lc_id, false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const adv = Boolean(form.is_advance);
  const avail = (source?.invoices ?? []).filter((i) => !form.invoice_rows.some((r) => r.id === i.id));
  const billed = form.invoice_rows.reduce((a, r) => a + n(r.net), 0);
  const applicants = opt.applicants.filter((a) => !form.buyer_id || a.buyer_id === form.buyer_id);
  const invCols = [{ key: "invoice_no", label: w("col.inv_no") }, { key: "invoice_date", label: w("col.inv_date"), render: (r) => day(r.invoice_date) }];
  const tail = [{ key: "beneficiary", label: w("col.beneficiary") }, { key: "buyer", label: w("col.buyer") }, { key: "lc_no", label: w("col.lc_no") }, { key: "order_no", label: w("col.order_no") }, { key: "ship_date", label: w("col.ship_date"), render: (r) => day(r.ship_date) }, { key: "order_qty", label: w("col.order_qty"), right: true, render: (r) => qty(r.order_qty) }, { key: "unit", label: w("col.unit") }, { key: "ctn", label: w("col.total_ctn"), right: true }, { key: "pcs", label: w("col.total_pcs"), right: true, render: (r) => qty(r.pcs) }];
  return (
    <>
      <Box legend={w("box.master_info")}>
        <div className="lg:col-span-2">{F.chk("is_advance", w("f.is_advance_export"), { wide: true, onPick: (v) => patch({ is_advance: v, bank_bill_type: v ? "IP" : "FDBP", invoice_rows: [], link_type: v ? "PI" : "" }) })}</div>
        {F.sel("lc_id", w("f.export_lc_no"), opt.lcs, { req: !adv, onPick: (v) => v && loadLc(v, true) })}
        {F.sel("payment_term", w("f.payment_term"), refShort(opt, "PAYMENT_TERM"))}
        {F.read(w("f.export_lc_amount"), source ? money(source.lc.lc_value) : "")}
        {F.sel("buyer_id", w("f.buyer"), opt.buyers, { req: true })}
        {F.sel("_c", w("f.benificary_company"), [opt.company], { readOnly: true, placeholder: opt.company.label })}
        {F.sel("applicant_id", w("f.applicant_importer"), applicants, { req: true })}
        {F.text("bill_no", w("f.bill_no"), { req: true })}
        {F.date("bill_date", w("f.bill_date"), { req: true })}
        <Row label={w("f.bill_amount")} req>
          <div className="grid grid-cols-[auto_1fr_6rem] gap-2">
            <Button variant="outline" size="sm" disabled={locked || adv} onClick={() => set("bill_amount", Number(billed.toFixed(4)))} aria-label={w("recalc")}>
              <RefreshCw />
            </Button>
            <Input type="number" step="any" value={form.bill_amount ?? ""} disabled={locked} onChange={(e) => set("bill_amount", e.target.value)} aria-label={w("f.bill_amount")} />
            <Select value={form.currency ?? ""} disabled={locked} onValueChange={(v) => set("currency", v ?? "")} options={opt.currencies} aria-label={w("f.currency")} />
          </div>
        </Row>
        {F.num("local_rate", w("f.local_rate"), { req: true })}
        {F.read(w("f.lc_balance"), source ? money(source.balance) : "")}
        {F.sel("doc_credit_branch_id", w("f.doc_credit_bank"), branchOpts(opt))}
        {F.sel("issuing_branch_id", w("f.lc_issuing_bank"), branchOpts(opt), { req: true })}
        {F.sel("receiving_branch_id", w("f.receiving_bank"), branchOpts(opt), { req: true })}
        {F.sel("advising_branch_id", w("f.advising_bank"), branchOpts(opt), { req: true, onPick: (v) => patch(form.same_receiver_bank ? { advising_branch_id: v, receiving_branch_id: v } : { advising_branch_id: v }) })}
        {F.chk("same_receiver_bank", w("f.same_receiver"), { onPick: (v) => patch(v ? { same_receiver_bank: true, receiving_branch_id: form.advising_branch_id } : { same_receiver_bank: false }) })}
        {F.area("remarks", w("f.remarks"))}
      </Box>
      <Box legend={w("box.bank_bill")}>
        {F.sel("bank_bill_type", w("f.bank_bill_type"), (adv ? [{ value: "IP", label: w("bill_type.IP") }] : ["FDBP", "FDBC", "DA", "DP"].map((v) => ({ value: v, label: v }))), { req: true })}
        {F.date("due_date", w("f.bill_due"), { req: true })}
        {F.text("bank_bill_ref_no", w("f.bank_bill_ref"))}
        {F.date("ref_date", w("f.bill_ref_date"))}
        {F.text("remittance_ref_no", w("f.remittance_ref"))}
      </Box>
      {adv && form._margin ? (
        <>
          <Box legend={w("box.margin")}>{F.sel("margin_type", w("f.accounting_type"), MARGIN_TYPES.map((v) => ({ value: v, label: w(`margin_type.${v}`) })), { req: true })}</Box>
          <Distribution w={w} form={form} set={set} locked={locked} opt={opt} legend={w("box.distribution")} />
        </>
      ) : adv ? (
        <>
          <Box legend={w("box.pi_po_ref")}>
            <Row label={w("f.link_type")} req>
              <div className="flex gap-5 text-sm" role="radiogroup" aria-label={w("f.link_type")}>
                {["PI", "PO"].map((v) => (
                  <label key={v} className="flex items-center gap-1.5">
                    <input type="radio" name="boe-link" className="size-4 accent-[var(--color-brand)]" disabled={locked} checked={form.link_type === v} onChange={() => patch({ link_type: v, pi_id: "", order_id: "" })} /> {w(`link.${v}`)}
                  </label>
                ))}
              </div>
            </Row>
            {form.link_type === "PO" ? F.sel("order_id", w("f.order_number"), opt.orders.filter((o) => !form.buyer_id || o.buyer_id === form.buyer_id), { req: true }) : F.sel("pi_id", w("f.export_pi"), opt.open_pis.filter((o) => !form.buyer_id || o.buyer_id === form.buyer_id), { req: true })}
          </Box>
          <Distribution w={w} form={form} set={set} locked={locked} opt={opt} legend={w("box.distribution")} />
        </>
      ) : (
        <>
          <Box legend={w("box.invoice_list")} grid={false}>
            <ReadGrid
              w={w}
              empty={w("pick_lc")}
              rows={avail}
              cols={[{ key: "tick", label: "+/-", render: (r) => <Checkbox checked={ticked.includes(r.id)} disabled={locked} onCheckedChange={(v) => setTicked(v === true ? [...ticked, r.id] : ticked.filter((x) => x !== r.id))} aria-label={`${w("tick")} ${r.invoice_no}`} /> }, ...invCols, { key: "gross", label: w("col.inv_gross"), right: true, render: (r) => money(r.gross) }, { key: "net", label: w("col.inv_net"), right: true, render: (r) => money(r.net) }, ...tail]}
            />
            {!locked ? (
              <div className="mt-3 flex justify-end">
                <Button
                  variant="outline"
                  onClick={() => {
                    const added = [...form.invoice_rows, ...avail.filter((a) => ticked.includes(a.id))];
                    patch({ invoice_rows: added, bill_amount: Number(added.reduce((a, r) => a + n(r.net), 0).toFixed(4)) });
                    setTicked([]);
                  }}
                >
                  <Plus /> {w("add_to_grid")}
                </Button>
              </div>
            ) : null}
          </Box>
          <Box legend={w("box.boe_invoices")} grid={false}>
            <ReadGrid
              w={w}
              empty={w("no_data")}
              rows={form.invoice_rows}
              onRemove={locked ? null : (r) => { const rest = form.invoice_rows.filter((x) => x.id !== r.id); patch({ invoice_rows: rest, bill_amount: Number(rest.reduce((a, x) => a + n(x.net), 0).toFixed(4)) }); }}
              cols={[...invCols, { key: "net", label: w("col.inv_amt"), right: true, render: (r) => money(r.net) }, ...tail]}
            />
          </Box>
        </>
      )}
    </>
  );
}

/** Bill Adjustment's "Manage Bill Submit and Payment Date": the drafts drawn on an L/C, their dates at the bank. */
function BillDatesForm({ w, form, set, locked, opt, act }) {
  const [busy, setBusy] = useState(false);
  const load = async () => {
    if (!form.lc_id) return;
    setBusy(true);
    const res = await act.read(`${P}/boes/dates/${form.lc_id}`);
    setBusy(false);
    if (res?.status === "success") set("rows", res.data);
  };
  const edit = (i, k, v) => set("rows", form.rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)));
  const dateBox = (r, i, k, label) => <Input type="date" value={r[k] ?? ""} disabled={locked} onChange={(e) => edit(i, k, e.target.value)} aria-label={`${label} ${r.bill_no}`} className="w-40" />;
  return (
    <>
      <Box legend={w("box.searching")}>
        <Row label={w("f.export_lc_no")}>
          <div className="flex items-center gap-2">
            <div className="min-w-0 flex-1">
              <Select value={form.lc_id ?? ""} onValueChange={(v) => set("lc_id", v ?? "")} options={opt.lcs} placeholder={w("select")} aria-label={w("f.export_lc_no")} />
            </div>
            <Button type="button" variant="outline" onClick={load} loading={busy}>
              <RefreshCw /> {w("od.f.search")}
            </Button>
          </div>
        </Row>
      </Box>
      <Box legend={w("box.drafts")} grid={false}>
        <ReadGrid
          w={w}
          empty={w("pick_lc_drafts")}
          rows={form.rows}
          cols={[
            { key: "bill_no", label: w("col.boe_no_full") },
            { key: "bill_date", label: w("col.bill_date"), render: (r) => day(r.bill_date) },
            { key: "bill_amount", label: w("col.bill_amount"), right: true, render: (r) => money(r.bill_amount) },
            { key: "s", label: w("col.submit_at_bank"), render: (r) => dateBox(r, form.rows.indexOf(r), "bank_submit_date", w("col.submit_at_bank")) },
            { key: "p", label: w("col.received_at_bank"), render: (r) => dateBox(r, form.rows.indexOf(r), "payment_recv_date", w("col.received_at_bank")) },
            { key: "bank_bill_ref_no", label: w("col.bill_ref") },
            { key: "f", label: w("col.final"), render: (r) => w(`yn.${r.final ? "YES" : "NO"}`) },
          ]}
        />
      </Box>
    </>
  );
}

function BoeDesk({ initial, opt, canManage, act, query, variant = "boe" }) {
  const w = useW();
  const margin = variant === "margin";
  const adjust = variant === "adjustment";
  const decorate = (r) => ({ ...withFinal(w, [r])[0], margin_label: r.margin_type ? w(`margin_type.${r.margin_type}`) : "—" });
  const types = ["FDBP", "FDBC", "DA", "DP", "IP"].map((v) => ({ value: v, label: v === "IP" ? w("bill_type.IP") : v }));
  return (
    <Desk
      w={w}
      path={margin ? `${P}/margin-closings` : `${P}/boes`}
      itemPath={`${P}/boes`}
      initial={initial}
      decorate={decorate}
      query={query}
      refKey="bill_no"
      exportName={margin ? "margin-closing" : adjust ? "bill-adjustment" : "bill-of-exchange"}
      opt={opt}
      canManage={canManage}
      act={act}
      Form={(p) => (p.form.__dates ? <BillDatesForm {...p} /> : <BoeForm {...p} />)}
      finalAction
      adds={margin ? [{ key: "margin", label: w("add_new") }] : [{ key: "new", label: w("add_new") }, { key: "advance", label: w("add_advance") }, ...(adjust ? [{ key: "dates", label: w("manage_dates") }] : [])]}
      blankForm={(k) => (k === "dates" ? { __dates: true, lc_id: "", rows: [] } : { ...(k === "margin" ? { _margin: true, margin_type: "MARGIN" } : {}), is_advance: k === "advance" || k === "margin", bank_bill_type: k === "advance" || k === "margin" ? "IP" : "FDBP", link_type: k === "advance" ? "PI" : "", bill_date: today(opt), local_currency: "BDT", currency: "USD", local_rate: opt.rates.USD ?? "", invoice_rows: [], distribution: [] })}
      toForm={(d) => (d.__dates ? d : { ...nulled(d), _margin: Boolean(d.margin_type), invoice_rows: d.invoice_rows, distribution: d.distribution.map((r) => ({ ...nulled(r), _k: uid() })) })}
      save={async (f, e) => {
        if (f.__dates) {
          const res = await act.write("PUT", `${P}/boes/dates`, { rows: f.rows.map((r) => ({ id: r.id, bank_submit_date: r.bank_submit_date || null, payment_recv_date: r.payment_recv_date || null })) });
          return res?.status === "success" ? { status: "success", data: f } : res;
        }
        return act.write(e ? "PUT" : "POST", e ? `${P}/boes/${e.id}` : `${P}/boes`, { ...clean(f, BOE_FIELDS), is_advance: Boolean(f.is_advance), same_receiver_bank: Boolean(f.same_receiver_bank), invoices: f.invoice_rows.map((r) => r.id), distribution: f.distribution.map(({ _k, ...r }) => r) });
      }}
      filters={margin ? [selF("bank", w("f.bank"), opt.banks, (r, v) => r.bank_id === v), selF("mt", w("f.accounting_type"), MARGIN_TYPES.map((v) => ({ value: v, label: w(`margin_type.${v}`) })), (r, v) => r.margin_type === v), dateF("from", w("f.from"), (r) => r.created_on, "from"), dateF("to", w("f.to"), (r) => r.created_on, "to")] : [textF("lc", w("f.lc_no_short"), (r) => r.lc_no), textF("no", w("f.boe_no"), (r) => r.bill_no), textF("ref", w("f.bank_bill_ref"), (r) => r.bank_bill_ref_no), textF("inv", w("f.exp_invoice_no"), (r) => r.invoices), companyF(w, opt), selF("bank", w("f.bank"), opt.banks, (r, v) => r.bank_id === v), buyerF(w, opt, w("f.buyer_importer")), selF("adv", w("f.advance_agnst"), yesNo(w), (r, v) => r.is_advance === yes(v)), finalF(w), selF("type", w("f.bank_bill_type"), types, (r, v) => r.bank_bill_type === v), dateF("from", w("f.boe_from"), (r) => r.bill_date, "from"), dateF("to", w("f.boe_to"), (r) => r.bill_date, "to")]}
      columns={margin ? [{ key: "bank", label: w("col.bank_name") }, { key: "margin_label", label: w("col.account_type") }, amt("amount", w("col.amount")), { key: "created_on", label: w("col.created_on"), type: "date" }] : [finalCol(w), { key: "bill_no", label: w("col.boe_no_full"), type: "mono" }, { key: "bank_bill_type", label: w("col.bank_bill_type") }, { key: "bill_date", label: w("col.bill_date"), type: "date" }, { key: "submit_date", label: w("col.submit_at_bank"), type: "date" }, { key: "received_date", label: w("col.received_at_bank"), type: "date" }, { key: "bank_bill_ref_no", label: w("col.bill_ref") }, { key: "lc_no", label: w("col.lc_no"), type: "mono" }, amt("lc_value", w("col.lc_value")), amt("bill_amount", w("col.bill_amount")), amt("negotiated", w("col.negotiated")), amt("remaining", w("col.remaining"))]}
    />
  );
}

/* ───────────────────────── Bank Bill Realisation ───────────────────────── */

const REAL_FIELDS = ["type", "realized_no", "boe_id", "realized_date", "bank_bill_type", "bank_name", "export_currency", "local_currency", "rate_ods", "rate_ttc", "invoice_amount", "collection_amount", "fbc_amount", "remarks"];

function RealisationForm({ w, form, set, patch, locked, opt, act, editing, F }) {
  const [bills, setBills] = useState(editing?.boe ? [editing.boe] : []);
  useEffect(() => {
    act.read(`${P}/realisations/bills${editing ? `?except=${editing.id}` : ""}`).then((r) => r?.status === "success" && setBills((old) => [...old.filter((o) => !r.data.some((x) => x.value === o.value)), ...r.data]));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const bill = bills.find((b) => b.value === form.boe_id);
  const final = form.type === "FINAL";
  const types = ["FDBP", "FDBC", "DA", "DP", "IP"].map((v) => ({ value: v, label: v }));
  return (
    <>
      <Box legend={w("box.master_info")}>
        {F.sel("boe_id", w("f.company_bank_bill"), bills, { req: true, onPick: (v) => { const b = bills.find((x) => x.value === v); if (b) patch({ boe_id: v, bank_name: b.bank_name, bank_bill_type: b.bank_bill_type, export_currency: b.currency, local_currency: b.local_currency, rate_ods: b.rate, invoice_amount: b.amount, collection_amount: final ? b.amount : "", distribution: form.distribution }); } })}
        {F.read(w("f.company_benificary"), bill?.beneficiary, { req: true })}
        {F.text("bank_name", w("f.bank_name"), { req: true })}
        {F.sel("bank_bill_type", w("f.bill_type"), types, { req: true })}
        {F.text("realized_no", w("f.realized_no"), { req: true })}
        {F.date("realized_date", w("f.realized_date"), { req: true })}
        {F.num("rate_ods", w("f.rate_ods"), { req: true })}
        {F.sel("export_currency", w("f.export_currency"), opt.currencies, { req: true })}
        {final ? F.num("rate_ttc", w("f.rate_ttc"), { req: true }) : null}
        {F.sel("local_currency", w("f.local_currency"), opt.currencies, { req: true })}
        {F.num("invoice_amount", w("f.invoice_amt_usd"), { req: true })}
        {F.read(w("f.invoice_amt_bdt"), money(n(form.invoice_amount) * n(form.rate_ods)), { req: true })}
        {final ? F.num("collection_amount", w("f.collection_usd"), { req: true }) : null}
        {final ? F.num("fbc_amount", w("f.fbc")) : null}
        {F.area("remarks", w("f.remarks"))}
      </Box>
      <div className="grid gap-4 lg:grid-cols-2">
        <Distribution w={w} form={form} set={set} locked={locked} opt={opt} legend={w("box.distribution")} match />
        <Box legend={w("box.boe_invoices")} grid={false}>
          <ReadGrid w={w} empty={w("pick_bill")} rows={bill?.invoices ?? []} cols={[{ key: "invoice_no", label: w("col.inv_no") }, { key: "invoice_date", label: w("col.inv_date"), render: (r) => day(r.invoice_date) }, { key: "net", label: w("col.inv_amt"), right: true, render: (r) => money(r.net) }, { key: "buyer", label: w("col.buyer") }, { key: "lc_no", label: w("col.lc_no") }]} />
        </Box>
      </div>
    </>
  );
}

function RealisationDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const decorate = (r) => ({ ...withFinal(w, [r])[0], type_label: w(`rtype.${r.type}`) });
  const types = ["FDBP", "FDBC", "DA", "DP", "IP"].map((v) => ({ value: v, label: v }));
  return (
    <Desk
      w={w}
      path={`${P}/realisations`}
      initial={initial}
      decorate={decorate}
      query={query}
      refKey="realized_no"
      exportName="bank-bill-realisation"
      opt={opt}
      canManage={canManage}
      act={act}
      Form={RealisationForm}
      finalAction
      adds={[{ key: "EARLY", label: w("add_early") }, { key: "FINAL", label: w("add_final") }]}
      blankForm={(k) => ({ type: k, realized_date: today(opt), export_currency: "USD", local_currency: "BDT", invoice_amount: 0, collection_amount: 0, fbc_amount: 0, distribution: [] })}
      toForm={(d) => ({ ...nulled(d), distribution: d.distribution.map((r) => ({ ...nulled(r), _k: uid() })) })}
      save={(f, e) => act.write(e ? "PUT" : "POST", e ? `${P}/realisations/${e.id}` : `${P}/realisations`, { ...clean(f, REAL_FIELDS), distribution: f.distribution.map(({ _k, ...r }) => r) })}
      filters={[textF("no", w("f.realized_no_short"), (r) => r.realized_no), companyF(w, opt), textF("bb", w("f.bank_bill_no"), (r) => r.bank_bill_no), textF("boe", w("f.boe_short"), (r) => r.boe_no), selF("type", w("f.bank_bill_type"), types, (r, v) => r.bank_bill_type === v), textF("inv", w("f.invoice_no"), (r) => r.invoices), selF("bank", w("f.bank"), opt.banks, (r, v) => r.bank_id === v), finalF(w), selF("ctype", w("f.collection_type"), ["EARLY", "FINAL"].map((v) => ({ value: v, label: v })), (r, v) => r.type === v), dateF("from", w("f.realized_from"), (r) => r.realized_date, "from"), dateF("to", w("f.realized_to"), (r) => r.realized_date, "to")]}
      columns={[{ key: "company", label: w("col.company") }, { key: "realized_no", label: w("col.realized_no"), type: "mono" }, { key: "type_label", label: w("col.type"), type: "badge" }, finalCol(w), { key: "realized_date", label: w("col.ref_date"), type: "date" }, { key: "boe_no", label: w("col.boe_full"), type: "mono" }, { key: "bank_bill_no", label: w("col.bank_bill_no") }, amt("collect_amount", w("col.collect_amt")), { key: "bank_name", label: w("col.bank_name") }, { key: "bank_bill_type", label: w("col.bank_bill_type") }, amt("fbc_amount", w("col.fbc")), amt("penalty_amount", w("col.penalty")), { key: "invoices", label: w("col.invoices") }, amt("invoice_amount", w("col.invoice_amt"))]}
    />
  );
}

const BillAdjustmentDesk = (props) => <BoeDesk {...props} variant="adjustment" />;
const MarginClosingDesk = (props) => <BoeDesk {...props} variant="margin" />;

export { PiDesk, LcDesk, InvoiceDesk, LodgementDesk, BoeDesk, BillAdjustmentDesk, MarginClosingDesk, RealisationDesk, Desk, Box, Row, ReadGrid, useW, textF, dateF, selF, finalF, companyF, finalCol, withFinal, amt, refs, refShort, today, nulled, clean, uid, n };
