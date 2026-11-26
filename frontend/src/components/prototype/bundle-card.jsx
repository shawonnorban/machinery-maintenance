"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Ban, Hand, PackageCheck, Play, Plus, Search, Send, Undo2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { DocFoot, DocHead, Field, ReadOnly, Section, blank, card, messagesOf, str, todayIso, useDocForm } from "@/components/prototype/proto-kit";

/*
 * Prototype Desk → Sample Bundle Card Issue (AnnotechERP 07/03): the styling
 * and cutting header with its bundling grid, saved together; the buttons
 * beside Save move the bundles on the floor. The cutting list shows the lays
 * cut on each table.
 */

const HEAD = ["sample_id", "buyer_id", "buyer_office_id", "style_id", "order_id", "cutting_no", "table_id", "fabric_type_id", "cutting_date", "status", "department_id", "assigned_team", "operator_id", "issue_date", "due_date", "priority", "remarks"];
const LINE = ["order_id", "sample_type_id", "color", "size", "req_qty", "cutting_qty", "remarks"];
const blankLine = () => Object.fromEntries(LINE.map((k) => [k, ""]));
/** Each button: where the bundles must stand for it, and its icon. */
const MOVES = [
  { key: "issue", from: ["CREATED"], icon: Send },
  { key: "receive", from: ["ISSUED"], icon: PackageCheck },
  { key: "start", from: ["RECEIVED", "HOLD"], icon: Play },
  { key: "hold", from: ["ISSUED", "RECEIVED", "IN_PRODUCTION"], icon: Hand },
  { key: "return", from: ["ISSUED", "RECEIVED", "IN_PRODUCTION", "HOLD"], icon: Undo2 },
  { key: "cancel", from: ["CREATED", "ISSUED", "RECEIVED", "IN_PRODUCTION", "HOLD"], icon: Ban, destructive: true },
];
const TO = { issue: "ISSUED", receive: "RECEIVED", start: "IN_PRODUCTION", hold: "HOLD", return: "RETURNED", cancel: "CANCELLED" };

function BundleCardForm({ options, initial, existing, canManage, actions }) {
  const t = useT("prototype");
  const router = useRouter();
  const toastManager = useToastManager();
  const [confirm, setConfirm] = useState(null);
  const formOf = (row) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(row?.[k])])),
    cutting_date: row?.cutting_date ?? todayIso(),
    status: row?.status ?? "DRAFT",
    is_finalized: Boolean(row?.is_finalized),
    bundles: row?.bundles?.length ? row.bundles.map((b) => ({ ...Object.fromEntries(LINE.map((k) => [k, str(b[k])])), bundle_no: b.bundle_no, now_at: b.now_at })) : [blankLine()],
  });
  const payloadOf = (form) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, blank(form[k])])),
    is_finalized: form.is_finalized,
    bundles: form.bundles.map((b) => Object.fromEntries(LINE.map((k) => [k, blank(b[k])]))),
  });
  const { row, form, set, setForm, errors, setErrors, pending, startTransition, load, save, take, address } = useDocForm({
    kind: "bundle-cards", tab: "card", param: "card", noKey: "card_no", initial, formOf, payloadOf, actions, savedText: (no) => t("ui.bc.saved", { no }),
  });
  const editable = canManage && !row?.locked;
  const bundling = editable && (!row || row.rebundle);
  const opt = (list) => [{ value: "", label: t("ui.select") }, ...list];
  const dash = (list) => [{ value: "", label: "—" }, ...list];
  const buyer = options.buyers.find((b) => b.value === form.buyer_id);
  const styles = options.styles.filter((s) => s.buyer_id === form.buyer_id);
  const orders = options.orders.filter((o) => o.buyer_id === form.buyer_id && (!form.style_id || o.style_id === form.style_id));
  const setLine = (i, key) => (v) => setForm((f) => ({ ...f, bundles: f.bundles.map((b, j) => (j === i ? { ...b, [key]: v ?? "" } : b)) }));
  // The sample's buyer, style, order and buying house fill the header.
  const pickSample = (v) => {
    const s = options.samples.find((x) => x.value === v);
    setForm((f) => ({ ...f, sample_id: v ?? "", ...(s ? { buyer_id: str(s.buyer_id), style_id: str(s.style_id), order_id: str(s.order_id), buyer_office_id: str(s.buyer_office_id) } : {}) }));
  };
  const pickBuyer = (v) => setForm((f) => ({ ...f, buyer_id: v ?? "", buyer_office_id: "", style_id: "", order_id: "" }));
  const moves = row && canManage ? MOVES.filter((m) => m.from.includes(row.bundle_status)) : [];
  const total = (k) => form.bundles.reduce((a, b) => a + (Number(b[k]) || 0), 0);

  function run(action) {
    setConfirm(null);
    setErrors(null);
    startTransition(async () => {
      const result = await actions.act("bundle-cards", row.card_no, action);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      address(result.data.card_no);
      toastManager.add({ title: t("ui.bc.done", { no: result.data.card_no, status: t(`bundle_status.${result.data.bundle_status}`) }), type: "success" });
      router.refresh();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-col gap-4 p-5")}>
        <DocHead
          title={t("ui.bc.form")}
          hint={t("ui.bc.form_hint")}
          badge={row ? `${row.card_no} · ${t(`bundle_status.${row.bundle_status}`)}` : t("ui.new")}
          existingLabel={t("ui.bc.existing")}
          newOption={t("ui.bc.new_option")}
          existing={existing}
          value={row?.card_no ?? ""}
          onPick={load}
          errors={errors}
        />
        <Section title={t("ui.bc.s.styling")}>
          <Field label={t("ui.bc.f.sample_no")} hint={t("ui.bc.h.sample_no")}>
            <Select value={form.sample_id} onValueChange={pickSample} options={opt(options.samples)} disabled={!editable} aria-label={t("ui.bc.f.sample_no")} />
          </Field>
          <Field label={t("ui.bc.f.buyer")} required>
            <Select value={form.buyer_id} onValueChange={pickBuyer} options={opt(options.buyers.map((b) => ({ value: b.value, label: b.label })))} disabled={!editable} aria-label={t("ui.bc.f.buyer")} />
          </Field>
          <Field label={t("ui.bc.f.buyer_ac")} hint={buyer && buyer.houses.length === 0 ? t("ui.no_choices") : undefined}>
            <Select value={form.buyer_office_id} onValueChange={set("buyer_office_id")} options={opt(buyer?.houses ?? [])} disabled={!editable || !buyer || buyer.houses.length === 0} aria-label={t("ui.bc.f.buyer_ac")} />
          </Field>
          <Field label={t("ui.bc.f.style")}>
            <Select value={form.style_id} onValueChange={(v) => setForm((f) => ({ ...f, style_id: v ?? "", order_id: "" }))} options={opt(styles)} disabled={!editable || !form.buyer_id} aria-label={t("ui.bc.f.style")} />
          </Field>
          <Field label={t("ui.bc.f.order")}>
            <Select value={form.order_id} onValueChange={set("order_id")} options={opt(orders)} disabled={!editable || !form.buyer_id} aria-label={t("ui.bc.f.order")} />
          </Field>
          <Field label={t("ui.bc.f.cutting_no")} required hint={t("ui.bc.h.cutting_no")}>
            <Input value={form.cutting_no} onChange={(e) => set("cutting_no")(e.target.value)} disabled={!editable} maxLength={32} />
          </Field>
          <Field label={t("ui.bc.f.table")} required hint={options.tables.length === 0 ? t("ui.no_choices") : t("ui.bc.h.table")}>
            <Select value={form.table_id} onValueChange={set("table_id")} options={opt(options.tables)} disabled={!editable} aria-label={t("ui.bc.f.table")} />
          </Field>
          <Field label={t("ui.bc.f.fabric")}>
            <Select value={form.fabric_type_id} onValueChange={set("fabric_type_id")} options={opt(options.fabric_types)} disabled={!editable} aria-label={t("ui.bc.f.fabric")} />
          </Field>
          <Field label={t("ui.bc.f.cutting_date")} required>
            <Input type="date" value={form.cutting_date} onChange={(e) => set("cutting_date")(e.target.value)} disabled={!editable} />
          </Field>
          <Field label={t("ui.bc.f.status")}>
            <Select value={form.status} onValueChange={set("status")} options={opt(options.statuses.map((s) => ({ value: s, label: t(`bc_status.${s}`) })))} disabled={!editable} aria-label={t("ui.bc.f.status")} />
          </Field>
          <div className="flex items-start gap-2">
            <Checkbox checked={form.is_finalized} onCheckedChange={(c) => set("is_finalized")(c === true)} disabled={!editable || Boolean(row?.is_finalized)} aria-label={t("ui.bc.f.is_finalized")} className="mt-0.5" />
            <div>
              <p className="text-[13px] font-medium text-foreground">{t("ui.bc.f.is_finalized")}</p>
              <p className="text-xs text-foreground-muted">{t("ui.bc.h.is_finalized")}</p>
            </div>
          </div>
          <Field label={t("ui.bc.f.bundle_status")} hint={t("ui.bc.h.bundle_status")}>
            <ReadOnly value={t(`bundle_status.${row?.bundle_status ?? "CREATED"}`)} />
          </Field>
        </Section>
        <Section title={t("ui.bc.s.issue")}>
          <Field label={t("ui.bc.f.department")}>
            <Select value={form.department_id} onValueChange={set("department_id")} options={opt(options.departments)} disabled={!editable} aria-label={t("ui.bc.f.department")} />
          </Field>
          <Field label={t("ui.bc.f.assigned_team")}>
            <Input value={form.assigned_team} onChange={(e) => set("assigned_team")(e.target.value)} disabled={!editable} maxLength={120} />
          </Field>
          <Field label={t("ui.bc.f.operator")}>
            <Select value={form.operator_id} onValueChange={set("operator_id")} options={opt(options.users)} disabled={!editable} aria-label={t("ui.bc.f.operator")} />
          </Field>
          <Field label={t("ui.bc.f.issue_date")} hint={t("ui.bc.h.issue_date")}>
            <Input type="date" value={form.issue_date} onChange={(e) => set("issue_date")(e.target.value)} disabled={!editable} />
          </Field>
          <Field label={t("ui.bc.f.due_date")}>
            <Input type="date" value={form.due_date} onChange={(e) => set("due_date")(e.target.value)} disabled={!editable} />
          </Field>
          <Field label={t("ui.bc.f.priority")}>
            <Select value={form.priority} onValueChange={set("priority")} options={opt(options.priorities.map((p) => ({ value: p, label: t(`priority.${p}`) })))} disabled={!editable} aria-label={t("ui.bc.f.priority")} />
          </Field>
          <Field label={t("ui.bc.f.remarks")} wide>
            <Textarea rows={2} value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} disabled={!editable} />
          </Field>
        </Section>
      </section>
      <section className={cn(card, "p-4")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-sm font-semibold text-foreground">{t("ui.bc.bundles")}</h3>
            <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.bc.bundles_hint")}</p>
          </div>
          {bundling ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setForm((f) => ({ ...f, bundles: [...f.bundles, blankLine()] }))}>
              <Plus /> {t("ui.bc.add")}
            </Button>
          ) : null}
        </div>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full min-w-[1280px] text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["order", "sample", "color", "size", "req_qty", "cutting_qty", "scan_code", "now_at", "remarks"].map((k) => (
                  <th key={k} className={cn("px-1.5 py-2 font-medium", ["req_qty", "cutting_qty"].includes(k) && "text-right")}>
                    {t(`ui.bc.lc.${k}`)}
                  </th>
                ))}
                <th />
              </tr>
            </thead>
            <tbody>
              {form.bundles.length === 0 ? (
                <tr>
                  <td colSpan={10} className="px-2 py-6 text-center text-foreground-muted">
                    <p className="font-medium text-foreground">{t("ui.no_lines")}</p>
                    <p className="text-xs">{t("ui.bc.bundles_empty")}</p>
                  </td>
                </tr>
              ) : (
                form.bundles.map((b, i) => (
                  <tr key={i} className="border-b border-border last:border-0">
                    <td className="w-60 px-1.5 py-1.5">
                      <Select value={b.order_id} onValueChange={setLine(i, "order_id")} options={dash(options.orders.filter((o) => !form.buyer_id || o.buyer_id === form.buyer_id))} placeholder="—" disabled={!bundling} aria-label={`${t("ui.bc.lc.order")} ${i + 1}`} />
                    </td>
                    <td className="w-48 px-1.5 py-1.5">
                      <Select value={b.sample_type_id} onValueChange={setLine(i, "sample_type_id")} options={dash(options.sample_types)} placeholder="—" disabled={!bundling} aria-label={`${t("ui.bc.lc.sample")} ${i + 1}`} />
                    </td>
                    {[["color", "w-28", 96], ["size", "w-20", 32]].map(([k, w, max]) => (
                      <td key={k} className={cn("px-1.5 py-1.5", w)}>
                        <Input value={b[k]} onChange={(e) => setLine(i, k)(e.target.value)} disabled={!bundling} maxLength={max} aria-label={`${t(`ui.bc.lc.${k}`)} ${i + 1}`} />
                      </td>
                    ))}
                    {["req_qty", "cutting_qty"].map((k) => (
                      <td key={k} className="w-24 px-1.5 py-1.5">
                        <Input type="number" min={0} value={b[k]} onChange={(e) => setLine(i, k)(e.target.value)} disabled={!bundling} aria-label={`${t(`ui.bc.lc.${k}`)} ${i + 1}`} />
                      </td>
                    ))}
                    <td className="w-44 px-1.5 py-1.5 font-mono text-foreground-muted">{b.bundle_no ?? "—"}</td>
                    <td className="w-28 px-1.5 py-1.5 text-foreground-muted">{b.now_at ?? "—"}</td>
                    <td className="w-48 px-1.5 py-1.5">
                      <Input value={b.remarks} onChange={(e) => setLine(i, "remarks")(e.target.value)} disabled={!bundling} maxLength={500} aria-label={`${t("ui.bc.lc.remarks")} ${i + 1}`} />
                    </td>
                    <td className="px-1.5 py-1.5">
                      {bundling ? (
                        <Button type="button" variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, bundles: f.bundles.filter((_, j) => j !== i) }))} aria-label={`${t("ui.bc.remove")} ${i + 1}`}>
                          <X />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="mt-3 flex items-center justify-between text-[13px] text-foreground-muted">
          <span>{t("ui.mr.total", { n: form.bundles.length })}</span>
          <span className="tabular-nums">
            {t("ui.bc.lc.req_qty")} <span className="font-medium text-foreground">{total("req_qty")}</span> · {t("ui.bc.lc.cutting_qty")} <span className="font-medium text-foreground">{total("cutting_qty")}</span>
          </span>
        </div>
      </section>
      <DocFoot note={t("ui.bc.note")} onClear={() => load("")} onSave={editable ? save : undefined} saveLabel={t("ui.bc.save")} pending={pending}>
        {moves.map((m) => (
          <Button key={m.key} type="button" variant="outline" onClick={() => setConfirm(m.key)} disabled={pending}>
            <m.icon /> {t(`ui.bc.act.${m.key}`)}
          </Button>
        ))}
      </DocFoot>
      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? t("ui.bc.confirm.title", { action: t(`ui.bc.act.${confirm}`) }) : ""}
        description={confirm ? t("ui.bc.confirm.hint", { no: row?.card_no ?? "", status: t(`bundle_status.${TO[confirm]}`) }) : ""}
        confirmLabel={confirm ? t(`ui.bc.act.${confirm}`) : ""}
        cancelLabel={t("ui.bc.confirm.keep")}
        destructive={MOVES.find((m) => m.key === confirm)?.destructive}
        loading={pending}
        onConfirm={() => run(confirm)}
      />
    </div>
  );
}

/** Cutting List: the cutting tables, and on picking one the cards cut on it with their bundles. */
function CuttingListBrowser({ tables, cards }) {
  const t = useT("prototype");
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(null);
  const shown = tables.filter((x) => x.label.toLowerCase().includes(query.trim().toLowerCase()));
  const cut = cards.filter((c) => c.table_id === picked);
  return (
    <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
      <section className={cn(card, "flex flex-col gap-3 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.bc.tables")}</h3>
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("ui.bc.tables_search")} className="pl-8" aria-label={t("ui.bc.tables")} />
        </div>
        {shown.length === 0 ? (
          <p className="py-6 text-center text-[13px] text-foreground-muted">{t("ui.bc.tables_none")}</p>
        ) : (
          <ul className="flex flex-col gap-1">
            {shown.map((x) => (
              <li key={x.value}>
                <button type="button" onClick={() => setPicked(x.value)} className={cn("flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-[13px]", picked === x.value ? "bg-brand-subtle text-brand" : "hover:bg-surface-muted")}>
                  <span>{x.label}</span>
                  <span className="rounded-full bg-surface-muted px-1.5 text-xs tabular-nums">{cards.filter((c) => c.table_id === x.value).length}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>
      <section className={cn(card, "min-w-0 p-4")}>
        <h3 className="text-sm font-semibold text-foreground">{t("ui.bc.tables_bundles")}</h3>
        <p className="mt-0.5 text-xs text-foreground-muted">{t("ui.bc.tables_hint")}</p>
        {!picked ? (
          <div className="py-10 text-center text-foreground-muted">
            <p className="font-medium text-foreground">{t("ui.bc.tables_pick")}</p>
            <p className="text-xs">{t("ui.bc.tables_pick_hint")}</p>
          </div>
        ) : cut.length === 0 ? (
          <p className="py-10 text-center text-[13px] text-foreground-muted">{t("ui.bc.tables_empty")}</p>
        ) : (
          <table className="mt-3 w-full text-[13px]">
            <thead>
              <tr className="border-b border-border text-left text-foreground-muted">
                {["card_no", "cut_date", "sample_no", "cutting_no", "bundles", "cutting_qty", "bundle_status"].map((k) => (
                  <th key={k} className="px-2 py-2 font-medium">
                    {t(`ui.bc.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {cut.map((c) => (
                <tr key={c.card_no} className="border-b border-border last:border-0">
                  <td className="px-2 py-2 font-mono">
                    <a className="text-brand hover:underline" href={`?tab=card&card=${encodeURIComponent(c.card_no)}`}>
                      {c.card_no}
                    </a>
                  </td>
                  <td className="px-2 py-2">
                    <FormattedDateTime value={c.cutting_date} mode="date" />
                  </td>
                  <td className="px-2 py-2 font-mono">{c.sample_no ?? "—"}</td>
                  <td className="px-2 py-2">{c.cutting_no}</td>
                  <td className="px-2 py-2 tabular-nums">{c.bundles_count}</td>
                  <td className="px-2 py-2 tabular-nums">{c.cutting_qty}</td>
                  <td className="px-2 py-2">{t(`bundle_status.${c.bundle_status}`)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}

export { BundleCardForm, CuttingListBrowser };
