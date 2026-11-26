"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { FilePlus2, Layers, Paperclip, Plus, RotateCcw, Save, Search, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { UomSelect } from "@/components/erp/uom-select";

const GROUPS = ["KNITTING", "YARN_DYEING", "DYEING", "DYEING_FINISHING", "AOP", "YARN_TWIST"];
const STATUSES = ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED", "CANCELLED"];
const blank = (group = null) => ({ id: null, rate_group: group, quotation_no: "", auto: true, supplier_id: "", quotation_date: "", currency: "", status: "", remarks: "", documents: [], lines: [] });
const noEntry = { rate_line_id: "", head: "", rate: "" };

/** A quotation as the server returns it, shaped for the form. */
const toForm = (q) => ({ ...q, auto: false, remarks: q.remarks ?? "", documents: q.documents ?? [], lines: q.lines.map((l) => ({ ...l, rate_line_id: l.rate_line_id ?? "", uom: l.uom ?? "", rate: String(l.rate) })) });

/**
 * Dye House → Dye Sub-Contract Party Rate → S/C Party Wise Rate Entry
 * (AnnotechERP 17/01): pick a rate group on the left, fill the header — the
 * party, date, currency and status — add one rate per head filed under the
 * group, and Save writes the quotation and its lines together. An existing
 * quotation of the group loads back into the form. As in Brickland, a line is
 * entered in the Rate Details / Rate row and Added to the list under it, and
 * the papers the quotation came with are Attached by name (Selected Docs).
 *
 * `side` is OUT for the rates a subcontract party charges us (17/01) and IN
 * for the In-House Sub-Contract Party Rate we charge a party (17/10); a new
 * quotation is filed under it.
 *
 * @param {{ quotations: object[], parties: object[], currencies: object[], loadHeads: Function, load: Function, save: Function, canManage: boolean, initialId?: string, side?: "OUT"|"IN" }} props
 */
function ScQuotationEditor({ quotations, parties, currencies, loadHeads, load, save, canManage, initialId = null, side = "OUT" }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [form, setForm] = useState(blank());
  const [heads, setHeads] = useState([]);
  const [query, setQuery] = useState("");
  const [entry, setEntry] = useState(noEntry);
  const [doc, setDoc] = useState("");
  const [pending, startTransition] = useTransition();
  const label = (k, p) => t(`master.sc.${k}`, p);
  const groupLabel = (g) => t(`master.opt.rate_group.${g}`);

  useEffect(() => {
    if (initialId) open(initialId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialId]);

  useEffect(() => {
    if (!form.rate_group) return;
    let live = true;
    loadHeads(form.rate_group).then((r) => live && r?.status === "success" && setHeads(r.heads));
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.rate_group]);

  function open(id) {
    startTransition(async () => {
      const r = await load(id);
      if (r?.status === "success") setForm(toForm(r.quotation));
    });
  }

  const set = (field, value) => setForm((f) => ({ ...f, [field]: value }));
  const setLine = (i, field, value) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, [field]: value } : l)) }));
  const warn = (title) => toastManager.add({ title, type: "danger" });

  const reset = (group) => {
    setForm(blank(group));
    setEntry(noEntry);
    setDoc("");
  };

  /** Brickland's Add: the Rate Details / Rate row becomes a line, and the row clears. */
  function addLine() {
    const picked = heads.find((h) => h.value === entry.rate_line_id);
    const head = picked ? picked.label : entry.head.trim();
    if (!head || entry.rate === "") return warn(label("line_incomplete"));
    if (form.lines.some((l) => l.head === head)) return warn(label("head_taken"));
    setForm((f) => ({ ...f, lines: [...f.lines, { rate_line_id: picked?.value ?? "", head, uom: picked?.uom ?? "", rate: entry.rate }] }));
    setEntry(noEntry);
  }

  /** Brickland's Attach: the paper is recorded by name; nothing is uploaded. */
  function attach() {
    const name = doc.trim();
    if (!name || form.documents.includes(name)) return;
    setForm((f) => ({ ...f, documents: [...f.documents, name] }));
    setDoc("");
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        ...(form.id ? {} : { rate_group: form.rate_group, side }),
        quotation_no: form.quotation_no || null,
        auto: form.auto,
        supplier_id: form.supplier_id || null,
        quotation_date: form.quotation_date || null,
        currency: form.currency || null,
        status: form.status || null,
        remarks: form.remarks || null,
        documents: form.documents,
        lines: form.lines.map((l) => ({ rate_line_id: l.rate_line_id || null, head: l.head || null, uom: l.uom || null, rate: l.rate === "" ? null : Number(l.rate) })),
      };
      const result = await save(form.id, payload);
      if (result?.status === "success") {
        const q = result.quotation;
        setForm(toForm(q));
        toastManager.add({ title: label("saved", { no: q.quotation_no }), type: "success" });
        router.refresh();
      } else {
        warn(result?.errors ? Object.values(result.errors).flat()[0] : result?.message);
      }
    });
  }

  const ofGroup = quotations.filter((q) => q.rate_group === form.rate_group);
  // A quotation just saved is in the form before the list catches up.
  if (form.id && !ofGroup.some((q) => q.id === form.id)) ofGroup.unshift({ id: form.id, quotation_no: form.quotation_no, party: parties.find((p) => p.value === form.supplier_id)?.label, quotation_date: form.quotation_date });
  const shownGroups = GROUPS.filter((g) => groupLabel(g).toLowerCase().includes(query.trim().toLowerCase()));
  const field = (name, content, required = false, hint = null) => (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">
        {t(`master.f.${name}`)}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {content}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
  /** A section's title, hint and its two buttons (Add / Attach and Cancel). */
  const sectionHead = (title, hint, primary) => (
    <div className="flex items-start justify-between gap-3">
      <div className="min-w-0 max-w-2xl flex-1">
        <h3 className="text-base font-semibold">{title}</h3>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
      </div>
      {canManage ? (
        <div className="flex shrink-0 gap-2">
          <Button variant="outline" size="sm" onClick={primary.onClick} disabled={!form.rate_group}>
            <Plus /> {primary.label}
          </Button>
          <Button variant="outline" size="sm" onClick={primary.onCancel}>
            {label("cancel")}
          </Button>
        </div>
      ) : null}
    </div>
  );

  return (
    <div className="grid items-start gap-4 xl:grid-cols-[22rem_1fr]">
      <div className="rounded-lg border border-border bg-surface p-4">
        <h2 className="mb-3 text-base font-semibold">{label("groups")}</h2>
        <div className="relative mb-3">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={label("search")} className="pl-9" />
        </div>
        <ul className="overflow-hidden rounded-md border border-border">
          <li className="bg-brand px-3 py-2 text-[13px] font-semibold text-brand-foreground">{t("master.f.rate_group")}</li>
          {shownGroups.map((g) => (
            <li key={g}>
              <button
                type="button"
                onClick={() => reset(g)}
                className={cn("w-full border-t border-border px-3 py-2 text-left text-sm", form.rate_group === g ? "bg-brand-subtle font-semibold text-brand" : "hover:bg-surface-muted")}
              >
                {groupLabel(g)}
              </button>
            </li>
          ))}
        </ul>
      </div>

      <div className="flex flex-col gap-4">
        <div className="rounded-lg border border-border bg-surface p-5">
          <div className="mb-4 flex items-start justify-between gap-3">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold">
                <Layers className="size-4" /> {label("title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{label("hint")}</p>
            </div>
            <span className="rounded-full bg-surface-muted px-2.5 py-0.5 text-xs font-semibold">{form.id ? form.quotation_no : label("new")}</span>
          </div>
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="sm:col-span-3">
              {field(
                "existing",
                <Select
                  value={form.id ?? ""}
                  onValueChange={(v) => (v ? open(v) : reset(form.rate_group))}
                  options={[{ value: "", label: label("new_quotation") }, ...ofGroup.map((q) => ({ value: q.id, label: `${q.quotation_no} — ${q.party ?? ""} (${q.quotation_date})` }))]}
                  placeholder={label("new_quotation")}
                  disabled={!form.rate_group}
                />,
                false,
                label("existing_hint"),
              )}
            </div>
            {field("rate_group", <Input value={form.rate_group ? groupLabel(form.rate_group) : ""} placeholder={label("pick_group")} readOnly />)}
            {field(
              "quotation_no",
              <div className="flex items-center gap-2">
                <Input value={form.quotation_no} onChange={(e) => set("quotation_no", e.target.value)} placeholder={label("auto_placeholder")} disabled={!canManage || form.auto} />
                <label className="flex items-center gap-1.5 text-sm">
                  <Checkbox checked={form.auto} onCheckedChange={(c) => set("auto", c === true)} disabled={!canManage || Boolean(form.id)} /> {label("auto")}
                </label>
              </div>,
              false,
              label("auto_hint"),
            )}
            <div />
            {field("party", <Select value={form.supplier_id} onValueChange={(v) => v && set("supplier_id", v)} options={parties} placeholder={label("select")} disabled={!canManage} />, true)}
            {field("quotation_date", <Input type="date" value={form.quotation_date} onChange={(e) => set("quotation_date", e.target.value)} disabled={!canManage} />, true)}
            {field("currency", <Select value={form.currency} onValueChange={(v) => v && set("currency", v)} options={currencies} placeholder={label("select_currency")} disabled={!canManage} />, true)}
            {field(
              "status",
              <Select value={form.status} onValueChange={(v) => v && set("status", v)} options={STATUSES.map((s) => ({ value: s, label: t(`master.opt.quotation_status.${s}`) }))} placeholder={label("select_status")} disabled={!canManage} />,
              true,
            )}
            <div className="sm:col-span-3">{field("remarks", <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!canManage} />)}</div>
          </div>
        </div>

        <div className="rounded-lg border border-border bg-surface p-5">
          {sectionHead(label("detail"), label("detail_hint"), { label: label("add_line"), onClick: addLine, onCancel: () => setEntry(noEntry) })}
          {canManage ? (
            <div className="mt-3 grid gap-4 sm:grid-cols-3">
              {field(
                "rate_head",
                heads.length ? (
                  <Select value={entry.rate_line_id} onValueChange={(v) => v && setEntry((e) => ({ ...e, rate_line_id: v }))} options={heads} placeholder={label("select_head")} disabled={!form.rate_group} />
                ) : (
                  <Input value={entry.head} onChange={(e) => setEntry((x) => ({ ...x, head: e.target.value }))} placeholder={label("type_head")} disabled={!form.rate_group} />
                ),
              )}
              {field("rate", <Input type="number" min="0" step="0.0001" value={entry.rate} onChange={(e) => setEntry((x) => ({ ...x, rate: e.target.value }))} disabled={!form.rate_group} />)}
            </div>
          ) : null}
          {form.lines.length === 0 ? (
            <Empty title={label("no_lines_title")} text={label("no_lines_text")} />
          ) : (
            <div className="mt-4 overflow-x-auto rounded-md border border-border">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    <th className="px-3 py-2.5 font-semibold">{t("master.f.rate_head")}</th>
                    <th className="px-3 py-2.5 font-semibold">{t("master.f.uom")}</th>
                    <th className="px-3 py-2.5 text-right font-semibold">{t("master.f.rate")}</th>
                    <th className="w-10 px-3 py-2.5" />
                  </tr>
                </thead>
                <tbody>
                  {form.lines.map((l, i) => (
                    <tr key={`${l.head}-${i}`} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5">{l.head}</td>
                      <td className="px-3 py-1.5">
                        <UomSelect value={l.uom} onChange={(v) => setLine(i, "uom", v)} disabled={!canManage} compact className="w-32" aria-label={t("master.f.uom")} />
                      </td>
                      <td className="px-3 py-1.5">
                        <Input type="number" min="0" step="0.0001" value={l.rate} onChange={(e) => setLine(i, "rate", e.target.value)} disabled={!canManage} className="ml-auto w-32 text-right" aria-label={t("master.f.rate")} />
                      </td>
                      <td className="px-3 py-1.5">
                        {canManage ? (
                          <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }))} aria-label={`${t("master.remove")} ${l.head}`}>
                            <Trash2 />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="rounded-lg border border-border bg-surface p-5">
          {sectionHead(label("docs"), label("docs_hint"), { label: label("attach"), onClick: attach, onCancel: () => setDoc("") })}
          {canManage ? (
            <div className="mt-3">
              {field(
                "document",
                <Input
                  value={doc}
                  onChange={(e) => setDoc(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      attach();
                    }
                  }}
                  placeholder={label("document_placeholder")}
                  maxLength={255}
                  disabled={!form.rate_group}
                />,
              )}
            </div>
          ) : null}
          {form.documents.length === 0 ? (
            <Empty title={label("no_docs_title")} text={label("no_docs_text")} />
          ) : (
            <ul className="mt-4 divide-y divide-border rounded-md border border-border">
              {form.documents.map((name) => (
                <li key={name} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                  <span className="flex items-center gap-2">
                    <Paperclip className="size-4 text-foreground-muted" /> {name}
                  </span>
                  {canManage ? (
                    <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, documents: f.documents.filter((d) => d !== name) }))} aria-label={`${t("master.remove")} ${name}`}>
                      <X />
                    </Button>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </div>

        {canManage ? (
          <div className="rounded-lg border border-border bg-surface p-5">
            <p className="text-[13px] text-foreground-muted">{label("footer")}</p>
            <div className="mt-3 flex gap-2">
              <Button variant="outline" onClick={() => reset(form.rate_group)} disabled={pending}>
                <RotateCcw /> {label("clear")}
              </Button>
              <Button onClick={submit} loading={pending} disabled={!form.rate_group}>
                <Save /> {label("save")}
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

/** Brickland's empty list: an icon, a title and why it matters. */
function Empty({ title, text }) {
  return (
    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <FilePlus2 className="size-5" />
      </span>
      <p className="text-sm font-semibold text-foreground">{title}</p>
      <p className="max-w-sm text-[13px] text-foreground-muted">{text}</p>
    </div>
  );
}

export { ScQuotationEditor };
