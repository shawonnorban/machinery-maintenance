"use client";

import { useEffect, useState, useTransition } from "react";
import { ArrowLeft, Ban, BadgeCheck, Landmark, ListChecks, Plus, Printer, RotateCcw, Save, Search, Table2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { printDocument } from "@/lib/print-document";
import { blank, day, EditGrid, Filters, firstError, has, money, qty, Register } from "@/components/sales-shipment/shared";
import { Box } from "@/components/tradeexport/operations";
import { address, dateF, Field, opts, selF, Tabs, textF, uid, useW } from "@/components/finance/vouchers";

/*
 * Finance & Ledger → Combined MRR Bill (AnnotechERP 23/02), as Brickland lays
 * it out: the bill list; Select Bill (bills already raised, searched, one
 * opened on the entry); and the entry — Master Info, then the MRR lines of
 * the chosen kind and supplier, each filled from its receipt, other cost /
 * VDS / TDS added and the net derived. Print, Mark as Fixed Asset and
 * Finalize act on a saved bill; a final bill no longer changes.
 */

const P = "/accounting/mrr-bills";
const KINDS = ["GENERAL", "YARN", "DYES", "TRIMS", "OIL", "NEEDLE"];
const n = (v) => Number(v || 0);
const net = (l) => Math.round((n(l.total_amount) + n(l.other_cost) - n(l.vds) - n(l.tds)) * 1000) / 1000;
const blankBill = (opt) => ({ supplier_id: "", bill_date: opt.today, mrr_type: "", prepared_by: "", maker_id: "", credit_account_id: "", remarks: "", lines: [] });
const toForm = (d) => ({
  id: d.id,
  bill_no: d.bill_no,
  final: d.final,
  fixed_asset: d.fixed_asset,
  supplier_id: d.supplier_id,
  bill_date: d.bill_date,
  mrr_type: d.mrr_type,
  prepared_by: d.prepared_by ?? "",
  maker_id: d.maker_id ?? "",
  credit_account_id: d.credit_account_id,
  remarks: d.remarks ?? "",
  lines: d.lines.map((l) => ({ ...l, _k: uid(), other_cost: String(l.other_cost || ""), vds: String(l.vds || ""), tds: String(l.tds || "") })),
});

function printBill(w, b, opt) {
  const label = (list, v) => opt[list].find((o) => o.value === v)?.label ?? "—";
  printDocument({
    title: w("mb.print_title"),
    docNo: b.bill_no,
    orientation: "landscape",
    meta: [
      [w("mb.f.supplier"), label("suppliers", b.supplier_id)],
      [w("mb.f.bill_date"), day(b.bill_date)],
      [w("mb.f.mrr_type"), w(`mrr_kind.${b.mrr_type}`)],
      [w("mb.f.credit_to"), label("accounts", b.credit_account_id)],
      [w("mb.f.prepared_by"), b.prepared_by ? label("people", b.prepared_by) : "—"],
      [w("mb.f.finalized"), w(`yn.${b.final ? "YES" : "NO"}`)],
    ],
    sections: [
      {
        title: w("mb.box.detail"),
        columns: [{ label: "#", align: "right" }, { label: w("mb.col.debit_to") }, { label: w("mb.col.mrr_no") }, { label: w("mb.col.mrr_date") }, { label: w("mb.col.po_wo") }, { label: w("mb.col.rate"), align: "right" }, { label: w("mb.col.mou") }, { label: w("mb.col.qty"), align: "right" }, { label: w("mb.col.total"), align: "right" }, { label: w("mb.col.other"), align: "right" }, { label: w("mb.col.vds"), align: "right" }, { label: w("mb.col.tds"), align: "right" }, { label: w("mb.col.net"), align: "right" }],
        rows: b.lines.map((l, i) => [i + 1, label("accounts", l.debit_account_id), l.mrr_no, day(l.mrr_date), l.po_wo ?? "—", money(l.rate), l.uom ?? "—", qty(l.total_qty), money(l.total_amount), money(l.other_cost), money(l.vds), money(l.tds), money(net(l))]),
        foot: [[w("mb.total"), "", "", "", "", "", "", "", money(b.lines.reduce((a, l) => a + n(l.total_amount), 0)), money(b.lines.reduce((a, l) => a + n(l.other_cost), 0)), money(b.lines.reduce((a, l) => a + n(l.vds), 0)), money(b.lines.reduce((a, l) => a + n(l.tds), 0)), money(b.lines.reduce((a, l) => a + net(l), 0))]],
      },
    ],
    notes: b.remarks ? [b.remarks] : undefined,
    signatures: true,
  });
}

function MrrBillForm({ w, form, set, patch, opt, act, locked }) {
  const [mrrs, setMrrs] = useState([]);
  const [pick, setPick] = useState({ mrr_id: "", debit_account_id: "" });
  useEffect(() => {
    if (!form.mrr_type || !form.supplier_id) return setMrrs([]);
    act.read(`${P}/mrrs?type=${form.mrr_type}&supplier=${form.supplier_id}${form.id ? `&except=${form.id}` : ""}`).then((r) => r?.status === "success" && setMrrs(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.mrr_type, form.supplier_id, form.id]);
  const free = mrrs.filter((m) => !form.lines.some((l) => l.mrr_id === m.value));
  const add = () => {
    const m = mrrs.find((x) => x.value === pick.mrr_id);
    if (!m || !pick.debit_account_id) return;
    set("lines", [...form.lines, { _k: uid(), mrr_id: m.value, mrr_no: m.mrr_no, debit_account_id: pick.debit_account_id, mrr_date: m.mrr_date, po_wo: m.po_wo, rate: m.rate, uom: m.uom, total_qty: m.total_qty, total_amount: m.total_amount, other_cost: "", vds: "", tds: "" }]);
    setPick({ mrr_id: "", debit_account_id: pick.debit_account_id });
  };
  const sum = (k) => form.lines.reduce((a, l) => a + (k === "net" ? net(l) : n(l[k])), 0);
  const sel = (k, label, options, o = {}) => (
    <Field label={label} req={o.req}>
      <Select value={form[k] ?? ""} disabled={locked || o.readOnly} onValueChange={(v) => (o.onPick ? o.onPick(v ?? "") : set(k, v ?? ""))} options={options} placeholder={o.placeholder ?? w("select")} aria-label={label} />
    </Field>
  );
  return (
    <>
      <Box legend={w("mb.box.master")} grid={false}>
        <div className="grid gap-x-6 gap-y-3 lg:grid-cols-3">
          {sel("supplier_id", w("mb.f.supplier"), opt.suppliers, { req: true, placeholder: w("mb.select_supplier"), readOnly: form.lines.length > 0 })}
          <Field label={w("f.company")} req>
            <Select value={opt.companies[0]?.value ?? ""} disabled options={opt.companies} aria-label={w("f.company")} />
          </Field>
          <Field label={w("mb.f.bill_no")}>
            <Input value={form.bill_no ?? ""} placeholder={w("generated_on_save")} disabled readOnly aria-label={w("mb.f.bill_no")} />
          </Field>
          <Field label={w("mb.f.bill_date")} req>
            <Input type="date" value={form.bill_date ?? ""} disabled={locked} onChange={(e) => set("bill_date", e.target.value)} aria-label={w("mb.f.bill_date")} />
          </Field>
          {sel("mrr_type", w("mb.f.mrr_type"), opts(w, "mrr_kind", KINDS), { req: true, placeholder: w("mb.select_mrr_for"), readOnly: form.lines.length > 0 })}
          {sel("prepared_by", w("mb.f.prepared_by"), [{ value: "", label: w("select") }, ...opt.people])}
          {sel("maker_id", w("mb.f.created_by"), [{ value: "", label: w("select") }, ...opt.users])}
          {sel("credit_account_id", w("mb.f.credit_to"), opt.accounts, { req: true })}
          <Field label={w("mb.f.remarks")}>
            <textarea rows={2} value={form.remarks ?? ""} disabled={locked} onChange={(e) => set("remarks", e.target.value)} aria-label={w("mb.f.remarks")} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm disabled:bg-surface-muted" />
          </Field>
        </div>
        <p className="mt-2 text-xs text-foreground-muted">{w("mb.type_hint")}</p>
      </Box>
      {!locked ? (
        <Box legend={w("mb.box.add")} grid={false}>
          <div className="grid items-end gap-3 lg:grid-cols-[1fr_1fr_auto]">
            <Field label={w("mb.col.mrr_no")} req>
              <Select value={pick.mrr_id} disabled={!form.mrr_type || !form.supplier_id} onValueChange={(v) => setPick({ ...pick, mrr_id: v ?? "" })} options={free.map((m) => ({ value: m.value, label: `${m.mrr_no} · ${day(m.mrr_date)} · ${money(m.total_amount)}` }))} placeholder={w("mb.select_mrr")} aria-label={w("mb.col.mrr_no")} />
            </Field>
            <Field label={w("mb.col.debit_to")} req>
              <Select value={pick.debit_account_id} onValueChange={(v) => setPick({ ...pick, debit_account_id: v ?? "" })} options={opt.accounts} placeholder={w("select")} aria-label={w("mb.col.debit_to")} />
            </Field>
            <Button type="button" variant="outline" onClick={add} disabled={!pick.mrr_id || !pick.debit_account_id}>
              <Plus /> {w("mb.add_line")}
            </Button>
          </div>
          <p className="mt-2 text-xs text-foreground-muted">{w("mb.mrr_hint")}</p>
        </Box>
      ) : null}
      <EditGrid
        w={w}
        title={w("mb.box.detail")}
        hint={w("mb.box.detail_hint")}
        editable={!locked}
        rows={form.lines}
        setRows={(v) => set("lines", v)}
        emptyNote={w("mb.no_lines")}
        cols={[
          { key: "debit_account_id", label: w("mb.col.debit_to"), type: "select", options: opt.accounts, width: 220 },
          { key: "mrr_no", label: w("mb.col.mrr_no"), read: (r) => r.mrr_no },
          { key: "mrr_date", label: w("mb.col.mrr_date"), read: (r) => day(r.mrr_date) },
          { key: "po_wo", label: w("mb.col.po_wo"), read: (r) => r.po_wo ?? "—" },
          { key: "rate", label: w("mb.col.rate"), align: "right", read: (r) => money(r.rate) },
          { key: "uom", label: w("mb.col.mou"), read: (r) => r.uom ?? "—" },
          { key: "total_qty", label: w("mb.col.qty"), align: "right", read: (r) => qty(r.total_qty) },
          { key: "total_amount", label: w("mb.col.total"), align: "right", read: (r) => money(r.total_amount) },
          { key: "other_cost", label: w("mb.col.other"), type: "number", align: "right" },
          { key: "vds", label: w("mb.col.vds"), type: "number", align: "right" },
          { key: "tds", label: w("mb.col.tds"), type: "number", align: "right" },
          { key: "net", label: w("mb.col.net"), align: "right", read: (r) => money(net(r)) },
        ]}
      />
      <div className="grid gap-2 rounded-lg border border-border bg-surface p-4 text-sm sm:grid-cols-4">
        {[
          ["mb.sum.other", sum("other_cost")],
          ["mb.sum.vds", sum("vds")],
          ["mb.sum.tds", sum("tds")],
          ["mb.sum.net", sum("net")],
        ].map(([k, v]) => (
          <p key={k} className="flex justify-between gap-2">
            <span className="text-foreground-muted">{w(k)}</span>
            <span className="font-semibold tabular-nums">{money(v)}</span>
          </p>
        ))}
      </div>
    </>
  );
}

function MrrBillDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [tab, setTab] = useState(query?.no ? "entry" : "records");
  const [filter, setFilter] = useState({});
  const [pickFilter, setPickFilter] = useState({});
  const [pickShown, setPickShown] = useState({});
  const [form, setForm] = useState(blankBill(opt));
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const patch = (o) => setForm((f) => ({ ...f, ...o }));
  const reload = () => act.read(P).then((r) => r?.status === "success" && setRows(r.data));
  const open = (id) =>
    startTransition(async () => {
      const res = await act.read(`${P}/${id}`);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setForm(toForm(res.data));
      setTab("entry");
      address("no", res.data.bill_no);
    });
  useEffect(() => {
    const hit = query?.no && initial.find((r) => r.bill_no === query.no);
    if (hit) open(hit.id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const clear = () => {
    setForm(blankBill(opt));
    address("no", null);
  };
  const save = () =>
    startTransition(async () => {
      const body = { ...Object.fromEntries(["supplier_id", "bill_date", "mrr_type", "prepared_by", "maker_id", "credit_account_id", "remarks"].map((k) => [k, blank(form[k])])), lines: form.lines.map((l) => ({ mrr_id: l.mrr_id, debit_account_id: l.debit_account_id || null, other_cost: l.other_cost || 0, vds: l.vds || 0, tds: l.tds || 0 })) };
      const res = await act.write(form.id ? "PUT" : "POST", form.id ? `${P}/${form.id}` : P, body);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("saved"), type: "success" });
      setForm(toForm(res.data));
      address("no", res.data.bill_no);
      reload();
    });
  const record = (kind) =>
    startTransition(async () => {
      const res = await act.write("POST", `${P}/${form.id}/${kind}`);
      setAsking(null);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w(`mb.done.${kind}`, { no: res.data.bill_no }), type: "success" });
      setForm(toForm(res.data));
      reload();
    });
  const filters = [
    selF("company", w("f.company"), opt.companies, () => opt.companies[0]?.value),
    selF("supplier", w("mb.f.supplier_name"), opt.suppliers, (r) => r.supplier_id),
    textF("mrr", w("mb.f.mrr_no_f"), (r) => r.mrr_nos),
    textF("bill", w("mb.f.bill_no_f"), (r) => r.bill_no),
    dateF("from", w("f.from"), (r) => r.bill_date, "from"),
    dateF("to", w("mb.f.to_issue"), (r) => r.bill_date, "to"),
  ];
  const decorated = rows.map((r) => ({ ...r, kind_label: w(`mrr_kind.${r.mrr_type}`), final_label: w(`yn.${r.final ? "YES" : "NO"}`) }));
  const shown = decorated.filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const pickFilters = [
    { name: "supplier", test: (r, v) => !v || r.supplier_id === v },
    { name: "bill", test: (r, v) => has(r.bill_no, v) },
    { name: "ref", test: (r, v) => has(r.mrr_nos, v) },
  ];
  const picked = decorated.filter((r) => pickFilters.every((f) => f.test(r, pickShown[f.name])));
  const locked = !canManage || Boolean(form.final);
  return (
    <div className="flex flex-col gap-4">
      <Tabs
        active={tab}
        onPick={setTab}
        tabs={[
          { key: "records", label: w("mb.tab.list"), icon: <Table2 className="size-4" />, count: rows.length },
          { key: "select", label: w("mb.tab.select"), icon: <ListChecks className="size-4" /> },
          ...(canManage || form.id ? [{ key: "entry", label: w("mb.tab.entry"), icon: <Plus className="size-4" /> }] : []),
        ]}
      />
      {tab === "records" ? (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            fields={filters}
            note={w("mb.filter_note")}
            extra={
              canManage ? (
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    clear();
                    setTab("entry");
                  }}
                >
                  <Plus /> {w("add_new")}
                </Button>
              ) : null
            }
          />
          <Register
            w={w}
            rows={shown}
            refKey="bill_no"
            exportName="mrr-combine-bills"
            onDone={reload}
            emptyTitle={w("empty")}
            emptyText=""
            actions={[
              {
                key: "print",
                label: w("print"),
                open: async (r) => {
                  const res = await act.read(`${P}/${r.id}`);
                  if (res?.status === "success") printBill(w, res.data, opt);
                },
              },
              { key: "details", label: w("mb.details"), open: (r) => open(r.id) },
              ...(canManage ? [{ key: "delete", label: w("delete"), when: (r) => !r.final, confirm: w("confirm.delete"), done: w("done.delete"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${P}/${r.id}`) }] : []),
            ]}
            columns={[
              { key: "company", label: w("mb.col.company") },
              { key: "supplier", label: w("mb.col.supplier") },
              { key: "kind_label", label: w("mb.col.mrr_type") },
              { key: "mrr_nos", label: w("mb.col.mrr_no") },
              { key: "bill_no", label: w("mb.col.bill_no"), type: "mono" },
              { key: "total", label: w("mb.col.total_bdt"), type: "number", align: "right" },
              { key: "net", label: w("mb.col.net_bdt"), type: "number", align: "right" },
              { key: "bill_date", label: w("mb.col.bill_date"), type: "date" },
              { key: "final_label", label: w("mb.col.finalized"), type: "badge" },
              { key: "prepared_by", label: w("mb.col.prepared_by") },
              { key: "created_by", label: w("mb.col.created_by") },
            ]}
          />
        </>
      ) : tab === "select" ? (
        <>
          <Box legend={w("mb.search")} grid={false}>
            <div className="grid items-end gap-3 lg:grid-cols-5">
              <Field label={w("mb.f.supplier")}>
                <Select value={pickFilter.supplier ?? ""} onValueChange={(v) => setPickFilter({ ...pickFilter, supplier: v ?? "" })} options={[{ value: "", label: w("mb.any_supplier") }, ...opt.suppliers]} aria-label={w("mb.f.supplier")} />
              </Field>
              <Field label={w("f.company")}>
                <Select value={opt.companies[0]?.value ?? ""} options={opt.companies} aria-label={w("f.company")} />
              </Field>
              <Field label={w("mb.f.bill_no_cap")}>
                <Input type="search" value={pickFilter.bill ?? ""} onChange={(e) => setPickFilter({ ...pickFilter, bill: e.target.value })} aria-label={w("mb.f.bill_no_cap")} />
              </Field>
              <Field label={w("mb.f.ref_no")}>
                <Input type="search" value={pickFilter.ref ?? ""} onChange={(e) => setPickFilter({ ...pickFilter, ref: e.target.value })} aria-label={w("mb.f.ref_no")} />
              </Field>
              <Button type="button" onClick={() => setPickShown(pickFilter)}>
                <Search /> {w("mb.show_same")}
              </Button>
            </div>
          </Box>
          <Box legend={w("mb.bill_list")} grid={false}>
            <div className="overflow-x-auto rounded-md border border-border">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    {["bill_ref", "ref_no", "bill_no_cap", "date", "note", "bill_amt", "pay_amt", "final_amt", "cur", "action"].map((k) => (
                      <th key={k} className={cn("px-3 py-2 font-semibold whitespace-nowrap", k.endsWith("amt") && "text-right")}>
                        {w(`mb.pick.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {picked.length ? (
                    picked.map((r) => (
                      <tr key={r.id} className="border-b border-border last:border-0">
                        <td className="px-3 py-1.5 font-mono text-xs">{r.bill_no}</td>
                        <td className="px-3 py-1.5">{r.mrr_nos}</td>
                        <td className="px-3 py-1.5">{r.challans ?? "—"}</td>
                        <td className="px-3 py-1.5">{day(r.bill_date)}</td>
                        <td className="px-3 py-1.5">{r.remarks ?? "—"}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{money(r.total)}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{money(0)}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{money(r.net)}</td>
                        <td className="px-3 py-1.5">BDT</td>
                        <td className="px-3 py-1.5">
                          <Button size="sm" variant="outline" onClick={() => open(r.id)} aria-label={`${w("mb.select")} ${r.bill_no}`}>
                            {w("mb.select")}
                          </Button>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={10} className="px-3 py-6 text-center text-foreground-muted">
                        {w("mb.no_match")}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            <div className="mt-3 flex items-center justify-between gap-3">
              <p className="text-xs text-foreground-muted">{w("mb.select_note")}</p>
              <Button variant="outline" onClick={() => setTab("records")}>
                <ArrowLeft /> {w("mb.bill_list")}
              </Button>
            </div>
          </Box>
        </>
      ) : (
        <>
          {form.final ? <p className="rounded-lg bg-warning-subtle px-4 py-2 text-sm text-warning">{w("mb.final_warning")}</p> : null}
          {form.fixed_asset ? <p className="rounded-lg bg-info-subtle px-4 py-2 text-sm text-info">{w("mb.fixed_note")}</p> : null}
          <MrrBillForm w={w} form={form} set={set} patch={patch} opt={opt} act={act} locked={locked} />
          <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-4">
            <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{w("mb.note")}</p>
            {form.id ? (
              <>
                <Button variant="outline" onClick={() => printBill(w, form, opt)}>
                  <Printer /> {w("print")}
                </Button>
                {canManage && !form.fixed_asset ? (
                  <Button variant="outline" onClick={() => setAsking("fixed-asset")} disabled={pending}>
                    <Landmark /> {w("mb.fixed_asset")}
                  </Button>
                ) : null}
                {canManage && !form.final ? (
                  <Button variant="outline" onClick={() => setAsking("final")} disabled={pending}>
                    <BadgeCheck /> {w("mb.finalize")}
                  </Button>
                ) : null}
              </>
            ) : null}
            <Button variant="outline" onClick={() => setTab("records")} disabled={pending}>
              <Ban /> {w("cancel")}
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                setTab("records");
                reload();
              }}
              disabled={pending}
            >
              <ArrowLeft /> {w("back")}
            </Button>
            {!locked ? (
              <>
                <Button variant="outline" onClick={clear} disabled={pending}>
                  <RotateCcw /> {w("clear")}
                </Button>
                <Button onClick={save} loading={pending}>
                  <Save /> {w("mb.save")}
                </Button>
              </>
            ) : null}
          </div>
        </>
      )}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? w(asking === "final" ? "mb.finalize" : "mb.fixed_asset") : ""}
        description={asking ? w(asking === "final" ? "mb.confirm_final" : "mb.confirm_fixed") : ""}
        confirmLabel={asking ? w(asking === "final" ? "mb.finalize" : "mb.fixed_asset") : ""}
        cancelLabel={w("od.reg.keep")}
        loading={pending}
        onConfirm={() => record(asking)}
      />
    </div>
  );
}

export { MrrBillDesk };
