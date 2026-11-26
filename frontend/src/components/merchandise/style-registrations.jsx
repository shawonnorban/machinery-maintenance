"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { printDocument, escapeHtml as escHtml } from "@/lib/print-document";
import { Dialog } from "@base-ui/react/dialog";
import { ArrowLeft, ArrowRight, CheckCircle2, Circle, History, Lock, Plus, Printer, RefreshCw, Save, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "PENDING", "RETURNED", "APPROVED", "REJECTED"];
const TONE = { DRAFT: "warning", PENDING: "info", RETURNED: "warning", APPROVED: "success", REJECTED: "danger" };
const STEPS = ["profile", "items", "fabric", "colour", "services", "bom", "documents", "review"];
const CARRIED_FIELDS = [
  ["buyer", "development"],
  ["buyer_code", "buyer_master"],
  ["dev_no", "development"],
  ["dev_style_no", "development"],
  ["style_code", "system"],
  ["style_name", "development"],
];
const OWN_FIELDS = ["brand", "product", "product_category", "product_type", "gender", "season_year", "collection", "department", "division"];
const REQUIRED = ["brand", "product", "product_category", "product_type", "gender", "season_year", "department"];
const when = (iso) => (iso ? new Date(iso).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—");
const day = (iso) => (iso ? new Date(iso.length === 10 ? `${iso}T00:00:00` : iso).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "—");

/**
 * Style Registration Entry → Registration (AnnotechERP 06/07), Brickland's
 * tab: the request counts; Requests and Registered Styles; New Style
 * Registration Request (pick an approved development style); each request
 * opened in the registration wizard — the profile the style lacks, the
 * approved development data step by step, and the review where it is
 * submitted and decided. Nothing here moves the page.
 *
 * @param {{ data: object, canManage: boolean, canApprove: boolean, actions: object, openNo?: ?string }} props
 */
function StyleRegistrations({ data, canManage, canApprove, actions, openNo = null }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.srq.${k}`, p);
  const toastManager = useToastManager();
  const [lists, setLists] = useState(data);
  const [sub, setSub] = useState("requests");
  const [status, setStatus] = useState("");
  const [picking, setPicking] = useState(false);
  const [candidates, setCandidates] = useState(null);
  const [openId, setOpenId] = useState(() => (openNo ? (data.requests.find((r) => r.request_no === openNo)?.id ?? null) : null));
  const [pending, startTransition] = useTransition();

  function reload() {
    startTransition(async () => {
      const result = await actions.loadRegistrations();
      if (result?.status === "success") setLists(result.data);
      else toastManager.add({ title: result?.message, type: "danger" });
    });
  }

  function openPicker() {
    setPicking(true);
    setCandidates(null);
    startTransition(async () => {
      const result = await actions.loadRegistrationCandidates();
      if (result?.status === "success") setCandidates(result.data);
      else toastManager.add({ title: result?.message, type: "danger" });
    });
  }

  function raise(styleId) {
    startTransition(async () => {
      const result = await actions.raiseRegistration(styleId);
      if (result?.status === "success") {
        setPicking(false);
        toastManager.add({ title: m("raised", { no: result.data.request.request_no }), type: "success" });
        setOpenId(result.data.request.id);
        reload();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat().join(" · ") : result?.message, type: "danger" });
      }
    });
  }

  function change(id) {
    startTransition(async () => {
      const result = await actions.moveRegistration(id, "change");
      if (result?.status === "success") {
        toastManager.add({ title: m("raised", { no: result.data.request.request_no }), type: "success" });
        setOpenId(result.data.request.id);
        reload();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat().join(" · ") : result?.message, type: "danger" });
      }
    });
  }

  const c = lists.counts;
  const cards = [
    ["total", c.total, "requests"],
    ["DRAFT", c.DRAFT, "requests"],
    ["PENDING", c.PENDING, "requests"],
    ["RETURNED", c.RETURNED, "requests"],
    ["APPROVED", c.APPROVED, "requests"],
    ["registered", c.registered, "styles"],
    ["active", c.active, "styles"],
    ["REJECTED", c.REJECTED, "requests"],
  ];
  const requests = status ? lists.requests.filter((r) => r.status === status) : lists.requests;
  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-4 xl:grid-cols-8">
        {cards.map(([key, value, unit]) => (
          <div key={key} className="rounded-lg border border-border bg-surface p-3">
            <div className="text-[13px] font-semibold text-foreground">{m(`card.${key}`)}</div>
            <div className="mt-1 text-2xl font-bold text-foreground tabular-nums">{value}</div>
            <div className="text-xs text-foreground-muted">{m(`card.${unit}`)}</div>
          </div>
        ))}
      </div>

      <section className="rounded-lg border border-border bg-surface">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 pt-3">
          <div role="tablist" className="flex gap-1">
            {["requests", "registered_styles"].map((k) => (
              <button key={k} type="button" role="tab" aria-selected={sub === k} onClick={() => setSub(k)} className={cn("-mb-px border-b-2 px-3 py-2.5 text-sm font-semibold", sub === k ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
                {m(k)}
              </button>
            ))}
          </div>
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
          <p className="max-w-3xl text-[13px] text-foreground-muted">{m("intro")}</p>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={reload} loading={pending}>
              <RefreshCw /> {m("refresh")}
            </Button>
            {canManage ? (
              <Button size="sm" onClick={openPicker}>
                <Plus /> {m("new")}
              </Button>
            ) : null}
          </div>
        </div>
        {sub === "requests" ? (
          <>
            <div className="flex items-center justify-between gap-3 px-5 pb-3">
              <select className="h-9 rounded-md border border-border-strong bg-surface px-2 text-sm text-foreground" value={status} onChange={(e) => setStatus(e.target.value)} aria-label={m("all_statuses")}>
                <option value="">{m("all_statuses")}</option>
                {STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {m(`status.${s}`)}
                  </option>
                ))}
              </select>
              <span className="text-[13px] text-foreground-muted">{m("shown", { shown: requests.length, total: lists.requests.length })}</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-brand text-brand-foreground">
                    {["request_no", "type", "style_code", "style", "buyer", "season", "dev_style", "completion", "status", "updated"].map((k) => (
                      <th key={k} className={th}>
                        {m(`col.${k}`)}
                      </th>
                    ))}
                    <th className={cn(th, "text-right")}>{m("col.action")}</th>
                  </tr>
                </thead>
                <tbody>
                  {requests.length === 0 ? (
                    <tr>
                      <td colSpan={11} className="px-4 py-12 text-center text-foreground-muted">
                        {m("no_requests")}
                      </td>
                    </tr>
                  ) : (
                    requests.map((r) => (
                      <tr key={r.id} className="border-b border-border align-middle last:border-0">
                        <td className="px-3 py-2.5 font-semibold text-foreground">{r.request_no}</td>
                        <td className="px-3 py-2.5">{m(`type.${r.type}`, { v: r.version })}</td>
                        <td className="px-3 py-2.5">{r.style_code}</td>
                        <td className="px-3 py-2.5">
                          <div className="font-medium text-foreground">{r.style_no}</div>
                          <div className="text-xs text-foreground-muted">{r.style_name}</div>
                        </td>
                        <td className="px-3 py-2.5">{r.buyer}</td>
                        <td className="px-3 py-2.5">{r.season ?? "—"}</td>
                        <td className="px-3 py-2.5">{r.style_no}</td>
                        <td className="px-3 py-2.5">
                          <div className="flex items-center gap-2">
                            <div className="h-1.5 w-20 overflow-hidden rounded-full bg-surface-muted">
                              <div className="h-full rounded-full bg-brand" style={{ width: `${r.completion}%` }} />
                            </div>
                            <span className="text-xs tabular-nums">{r.completion}%</span>
                          </div>
                        </td>
                        <td className="px-3 py-2.5">
                          <Badge variant={TONE[r.status]}>{m(`status.${r.status}`)}</Badge>
                        </td>
                        <td className="px-3 py-2.5">{when(r.updated_at)}</td>
                        <td className="px-3 py-2.5">
                          <div className="flex justify-end gap-1.5">
                            <Button variant="outline" size="sm" onClick={() => setOpenId(r.id)}>
                              {m("open")}
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-brand-foreground">
                  {["style_code", "registration_no", "version", "style_ref", "style_desc", "buyer", "season", "orders"].map((k) => (
                    <th key={k} className={th}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                  <th className={cn(th, "text-right")}>{m("col.action")}</th>
                </tr>
              </thead>
              <tbody>
                {lists.registered.length === 0 ? (
                  <tr>
                    <td colSpan={9} className="px-4 py-12 text-center text-foreground-muted">
                      {m("no_registered")}
                    </td>
                  </tr>
                ) : (
                  lists.registered.map((r) => (
                    <tr key={r.id} className="border-b border-border last:border-0">
                      <td className="px-3 py-2.5 font-semibold text-foreground">{r.style_code}</td>
                      <td className="px-3 py-2.5">{r.registration_no}</td>
                      <td className="px-3 py-2.5">V{r.version}</td>
                      <td className="px-3 py-2.5">{r.style_ref}</td>
                      <td className="px-3 py-2.5">{r.style_desc}</td>
                      <td className="px-3 py-2.5">{r.buyer}</td>
                      <td className="px-3 py-2.5">{r.season ?? "—"}</td>
                      <td className="px-3 py-2.5 tabular-nums">{r.orders}</td>
                      <td className="px-3 py-2.5">
                        <div className="flex justify-end gap-1.5">
                          <Button variant="outline" size="sm" onClick={() => setOpenId(r.id)}>
                            {m("open")}
                          </Button>
                          {canManage ? (
                            <Button size="sm" onClick={() => change(r.id)} disabled={pending}>
                              {m("change")}
                            </Button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <CandidatePicker open={picking} onClose={() => setPicking(false)} rows={candidates} onSelect={raise} busy={pending} />
      {openId ? <RegistrationWizard key={openId} id={openId} actions={actions} canManage={canManage} canApprove={canApprove} onClose={() => { setOpenId(null); reload(); }} onChanged={reload} /> : null}
    </div>
  );
}

/** New Style Registration Request: the approved development styles, each selectable unless already requested. */
function CandidatePicker({ open, onClose, rows, onSelect, busy }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.srq.${k}`, p);
  const [buyer, setBuyer] = useState("");
  const [season, setSeason] = useState("");
  const [reg, setReg] = useState("");
  const list = useMemo(() => rows ?? [], [rows]);
  const buyers = useMemo(() => [...new Set(list.map((r) => r.buyer).filter(Boolean))].sort(), [list]);
  const seasons = useMemo(() => [...new Set(list.map((r) => r.season).filter(Boolean))].sort(), [list]);
  const shown = list.filter((r) => (!buyer || r.buyer === buyer) && (!season || r.season === season) && (!reg || (reg === "none" ? !r.registration : Boolean(r.registration))));
  const select = "h-9 rounded-md border border-border-strong bg-surface px-2 text-sm text-foreground";
  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";

  return (
    <Modal open={open} onOpenChange={(o) => !o && onClose()} title={m("pick_title")} description={m("pick_hint")} className="max-w-5xl">
      <div className="mb-3 flex flex-wrap gap-2">
        <select className={select} value={buyer} onChange={(e) => setBuyer(e.target.value)} aria-label={m("all_buyers")}>
          <option value="">{m("all_buyers")}</option>
          {buyers.map((b) => (
            <option key={b}>{b}</option>
          ))}
        </select>
        <select className={select} value={season} onChange={(e) => setSeason(e.target.value)} aria-label={m("all_seasons")}>
          <option value="">{m("all_seasons")}</option>
          {seasons.map((s) => (
            <option key={s}>{s}</option>
          ))}
        </select>
        <select className={select} value={reg} onChange={(e) => setReg(e.target.value)} aria-label={m("any_reg")}>
          <option value="">{m("any_reg")}</option>
          <option value="none">{m("not_requested")}</option>
          <option value="some">{m("requested")}</option>
        </select>
      </div>
      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-brand-foreground">
              {["dev_no", "dev_style_no", "buyer", "brand_product", "season", "approved", "reg_status"].map((k) => (
                <th key={k} className={th}>
                  {m(`col.${k}`)}
                </th>
              ))}
              <th className={cn(th, "text-right")}>{m("col.action")}</th>
            </tr>
          </thead>
          <tbody>
            {rows === null ? (
              <tr>
                <td colSpan={8} className="px-4 py-10 text-center text-foreground-muted">
                  …
                </td>
              </tr>
            ) : shown.length === 0 ? (
              <tr>
                <td colSpan={8} className="px-4 py-10 text-center text-foreground-muted">
                  {m("no_candidates")}
                </td>
              </tr>
            ) : (
              shown.map((r) => (
                <tr key={r.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2.5 font-semibold text-foreground">{r.dev_no}</td>
                  <td className="px-3 py-2.5">
                    <div className="font-medium text-foreground">{r.style_no}</div>
                    <div className="text-xs text-foreground-muted">{r.description}</div>
                  </td>
                  <td className="px-3 py-2.5">{r.buyer}</td>
                  <td className="px-3 py-2.5">{[r.brand ?? "—", r.product ?? "—"].join(" / ")}</td>
                  <td className="px-3 py-2.5">{r.season ?? "—"}</td>
                  <td className="px-3 py-2.5">{day(r.approved_on)}</td>
                  <td className="px-3 py-2.5">{r.registration ? `${m(`status.${r.registration.status}`)} · ${r.registration.request_no}` : m("not_requested_label")}</td>
                  <td className="px-3 py-2.5 text-right">
                    <Button size="sm" onClick={() => onSelect(r.id)} disabled={!r.selectable || busy}>
                      {m("select")}
                    </Button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </Modal>
  );
}

/** One request in Brickland's registration wizard: eight steps, the profile first; the rest open once it is complete. */
function RegistrationWizard({ id, actions, canManage, canApprove, onClose, onChanged }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.srq.${k}`, p);
  const toastManager = useToastManager();
  const [view, setView] = useState(null);
  const [form, setForm] = useState(null);
  const [step, setStep] = useState(0);
  const [dialog, setDialog] = useState(null);
  const [note, setNote] = useState("");
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  // The request is read when the wizard opens; the wizard is keyed by it, so this runs once per request.
  useEffect(() => {
    startTransition(async () => {
      const result = await actions.loadRegistration(id);
      if (result?.status === "success") show(result.data);
      else toastManager.add({ title: result?.message, type: "danger" });
    });
    // `show` and the toast manager need not re-run this.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  function show(data) {
    setView(data);
    setForm(Object.fromEntries(OWN_FIELDS.map((f) => [f, data.request[f] == null ? "" : String(data.request[f])])));
  }

  const editable = Boolean(view?.editable) && canManage;
  const profileDone = view ? view.missing.length === 0 : false;
  const locked = (i) => i > 0 && !profileDone;

  function saveProfile(next) {
    startTransition(async () => {
      const result = await actions.saveRegistration(id, { ...Object.fromEntries(OWN_FIELDS.map((f) => [f, form[f] === "" ? null : form[f]])), season_year: form.season_year === "" ? null : Number(form.season_year) });
      if (result?.status === "success") {
        show(result.data);
        onChanged();
        if (next && result.data.missing.length === 0) setStep(1);
        else toastManager.add({ title: m("saved"), type: "success" });
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat().join(" · ") : result?.message, type: "danger" });
      }
    });
  }

  function discard() {
    startTransition(async () => {
      const result = await actions.discardRegistration(id);
      if (result?.status === "success") {
        toastManager.add({ title: m("discarded", { no: view.request.request_no }), type: "success" });
        onClose();
      } else {
        toastManager.add({ title: result?.message, type: "danger" });
      }
    });
  }

  function confirm() {
    const needs = dialog === "return" || dialog === "reject";
    if (needs && !note.trim()) {
      setError(m(`${dialog}_hint`));
      return;
    }
    startTransition(async () => {
      const result = await actions.moveRegistration(id, dialog, note.trim());
      if (result?.status === "success") {
        setDialog(null);
        show(result.data);
        onChanged();
        toastManager.add({ title: dialog === "submit" ? m("submitted", { no: result.data.request.request_no }) : m("decided", { no: result.data.request.request_no, status: m(`status.${result.data.request.status}`) }), type: "success" });
      } else {
        setError(result?.errors ? Object.values(result.errors).flat().join(" · ") : result?.message);
      }
    });
  }

  // The registration request on paper: the style profile it carries, its own fields, and what each step brought along.
  function printPdf() {
    if (!view) return;
    const r = view.request;
    const p = view.profile;
    printDocument({
      title: m("print_title"),
      docNo: r.registration_no ?? r.request_no,
      subtitle: `${m(`type.${r.type}`, { v: r.version })} · ${r.style_code}`,
      meta: [
        ...CARRIED_FIELDS.map(([f]) => [m(`f.${f}`), p[f]]),
        [m("f.season"), p.season],
        ...OWN_FIELDS.map((f) => [m(`f.${f}`), f === "gender" && r.gender ? m(`gender.${r.gender}`) : r[f]]),
        [m("registration_no"), r.registration_no ?? m("issued_on_approval")],
        [m("col.status"), { v: m(`status.${r.status}`), tone: "info" }],
      ],
      blocks: ["items", "fabric", "colour", "services", "bom", "documents"].map((k) => ({
        title: m(`steps.${k}`),
        html: view.carried[k].lines.length ? `<ul style="margin:0;padding-left:18px">${view.carried[k].lines.map((x) => `<li>${escHtml(x)}</li>`).join("")}</ul>` : "<p class=\"muted\" style=\"margin:0\">—</p>",
      })),
      signatures: true,
    });
  }

  const current = STEPS[step];
  const r = view?.request;

  return (
    <Dialog.Root open onOpenChange={(o) => !o && onClose()}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-foreground/40" />
        <Dialog.Popup className="fixed inset-4 z-50 flex flex-col overflow-hidden rounded-lg border border-border bg-background shadow-xl outline-none lg:inset-x-10">
          {!view ? (
            <div className="grid flex-1 place-items-center text-sm text-foreground-muted">…</div>
          ) : (
            <>
              <div className="flex flex-wrap items-center gap-3 border-b border-border bg-surface px-5 py-3">
                <span className="rounded-md bg-brand px-3 py-1.5 text-base font-semibold text-brand-foreground">
                  {step + 1}. {m(`steps.${current}`)}
                </span>
                <div>
                  <Dialog.Title className="text-sm font-semibold text-foreground">{m("wiz_title", { no: r.request_no })}</Dialog.Title>
                  <div className="mt-0.5 flex items-center gap-2">
                    <Badge variant={TONE[r.status]}>{m(`status.${r.status}`)}</Badge>
                    <button type="button" onClick={printPdf} className="flex items-center gap-1 text-xs font-semibold text-foreground-muted hover:text-foreground">
                      <Printer className="size-3.5" /> {m("pdf")}
                    </button>
                  </div>
                </div>
                <div className="ml-auto flex flex-wrap items-center gap-2">
                  <Button variant="outline" onClick={() => setStep(step - 1)} disabled={step === 0}>
                    <ArrowLeft /> {m("previous")}
                  </Button>
                  {current === "profile" && editable ? (
                    <>
                      <Button variant="outline" onClick={() => saveProfile(false)} loading={pending}>
                        <Save /> {m("save_draft")}
                      </Button>
                      <Button onClick={() => saveProfile(true)} loading={pending}>
                        {m("save_continue")} <ArrowRight />
                      </Button>
                    </>
                  ) : step < STEPS.length - 1 ? (
                    <Button onClick={() => setStep(step + 1)} disabled={locked(step + 1)}>
                      {m("continue")} <ArrowRight />
                    </Button>
                  ) : null}
                  <Button variant="outline" size="sm" onClick={onClose} aria-label={m("close")}>
                    <X />
                  </Button>
                </div>
              </div>

              <div className="border-b border-border bg-surface px-5 py-3">
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px] text-foreground-muted">
                  <span>
                    {m("style_code")} <b className="text-foreground">{r.style_code}</b>
                  </span>
                  <span>
                    {m("registration_no")} <b className="text-foreground">{r.registration_no ?? m("issued_on_approval")}</b>
                  </span>
                  <span>
                    {m("version")} <b className="text-foreground">V{r.version}</b>
                  </span>
                  <span>
                    {m("from")} <b className="text-foreground">{view.profile.dev_style_no}</b>
                  </span>
                  {r.status === "DRAFT" && canManage ? (
                    <Button variant="outline" size="sm" onClick={discard} disabled={pending}>
                      <Trash2 /> {m("discard")}
                    </Button>
                  ) : null}
                </div>
                <div className="mt-3 flex items-center gap-3">
                  <span className="text-[13px] font-semibold text-foreground">{m("progress")}</span>
                  <div className="h-2 flex-1 overflow-hidden rounded-full bg-surface-muted">
                    <div className="h-full rounded-full bg-brand" style={{ width: `${view.progress}%` }} />
                  </div>
                  <span className="text-[13px] font-semibold tabular-nums">{view.progress}%</span>
                </div>
                <ol className="mt-3 grid gap-2 sm:grid-cols-4 xl:grid-cols-8">
                  {STEPS.map((key, i) => (
                    <li key={key}>
                      <button type="button" onClick={() => !locked(i) && setStep(i)} disabled={locked(i)} className={cn("flex w-full flex-col rounded-md border px-3 py-2 text-left text-[12px]", i === step ? "border-brand bg-brand-subtle text-brand" : "border-border text-foreground", locked(i) && "cursor-not-allowed opacity-60")}>
                        <span className="flex items-center gap-1.5 font-semibold">
                          {locked(i) ? <Lock className="size-3" /> : null}
                          {i + 1}. {m(`steps.${key}`)}
                        </span>
                        <span className="text-foreground-muted">{locked(i) ? m("locked") : `${view.steps[key]}%`}</span>
                      </button>
                    </li>
                  ))}
                </ol>
              </div>

              <div className="flex-1 overflow-y-auto p-5">
                {current === "profile" && view.missing.length ? (
                  <Alert variant="warning" className="mb-4">
                    <b>{m("still_required")}</b>
                    <ul className="mt-1 list-disc pl-5">
                      {view.missing.map((x) => (
                        <li key={x}>{x}</li>
                      ))}
                    </ul>
                  </Alert>
                ) : null}
                {current === "profile" ? (
                  <fieldset disabled={!editable} className="rounded-lg border border-border bg-surface px-4 pt-2 pb-4">
                    <legend className="px-1 text-[13px] font-semibold text-brand italic">
                      1. {m("steps.profile")}
                    </legend>
                    <div className="grid gap-x-6 gap-y-3 md:grid-cols-2 xl:grid-cols-3">
                      {CARRIED_FIELDS.map(([f, tag]) => (
                        <Field key={f} label={m(`f.${f}`)} required tag={m(`tag.${tag}`)}>
                          <Input value={view.profile[f] ?? ""} readOnly disabled />
                        </Field>
                      ))}
                      {OWN_FIELDS.map((f) =>
                        f === "gender" ? (
                          <Field key={f} label={m("f.gender")} required>
                            <select className="h-10 w-full rounded-md border border-border-strong bg-surface px-2 text-sm" value={form.gender} onChange={(e) => setForm((x) => ({ ...x, gender: e.target.value }))}>
                              <option value="">{m("ph_select")}</option>
                              {view.genders.map((g) => (
                                <option key={g} value={g}>
                                  {m(`gender.${g}`)}
                                </option>
                              ))}
                            </select>
                          </Field>
                        ) : (
                          <Field key={f} label={m(`f.${f}`)} required={REQUIRED.includes(f)}>
                            <Input type={f === "season_year" ? "number" : "text"} value={form[f]} onChange={(e) => setForm((x) => ({ ...x, [f]: e.target.value }))} />
                          </Field>
                        ),
                      )}
                      <Field label={m("f.season")} required tag={m("tag.development")}>
                        <Input value={view.profile.season ?? ""} readOnly disabled />
                      </Field>
                    </div>
                    <p className="mt-3 text-[13px] text-foreground-muted">{m("profile_note", { n: OWN_FIELDS.length })}</p>
                  </fieldset>
                ) : current === "review" ? (
                  <div className="grid gap-4 xl:grid-cols-[1fr_22rem]">
                    <section className="rounded-lg border border-border bg-surface p-4">
                      <p className="text-[13px] text-foreground-muted">{m("review_hint")}</p>
                      <ul className="mt-3 flex flex-col gap-2.5">
                        {STEPS.slice(0, -1).map((key) => (
                          <li key={key} className="flex items-center justify-between gap-3 text-sm">
                            <span className="flex items-center gap-2">
                              {view.steps[key] === 100 ? <CheckCircle2 className="size-4 text-success" /> : <Circle className="size-4 text-foreground-muted" />}
                              {m(`steps.${key}`)}
                            </span>
                            <span className="text-xs tabular-nums">{view.steps[key]}%</span>
                          </li>
                        ))}
                      </ul>
                      <div className="mt-4 flex flex-wrap gap-2">
                        {editable ? (
                          <Button className="bg-success text-white hover:bg-success/90" onClick={() => { setNote(""); setError(null); setDialog("submit"); }} disabled={!profileDone}>
                            {m("submit")}
                          </Button>
                        ) : null}
                        {r.status === "PENDING" && canApprove ? (
                          <>
                            <Button className="bg-success text-white hover:bg-success/90" onClick={() => { setNote(""); setError(null); setDialog("approve"); }}>
                              {m("approve")}
                            </Button>
                            <Button variant="outline" onClick={() => { setNote(""); setError(null); setDialog("return"); }}>
                              {m("return")}
                            </Button>
                            <Button variant="outline" className="text-danger" onClick={() => { setNote(""); setError(null); setDialog("reject"); }}>
                              {m("reject")}
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </section>
                    <section className="rounded-lg border border-border bg-surface p-4">
                      <h3 className="flex items-center gap-2 text-base font-semibold text-foreground">
                        <History className="size-4" /> {m("history")}
                      </h3>
                      {view.history.length === 0 ? (
                        <p className="mt-2 text-[13px] text-foreground-muted">{m("no_history")}</p>
                      ) : (
                        <ol className="mt-2 flex flex-col gap-3">
                          {[...view.history].reverse().map((h, i) => (
                            <li key={i} className="border-l-2 border-brand/40 pl-3 text-[13px]">
                              <div className="font-semibold">{m(`event.${h.action}`)}</div>
                              <div className="text-foreground-muted">
                                {when(h.at)} · {h.by}
                              </div>
                              {h.note ? <div className="mt-1">{h.note}</div> : null}
                            </li>
                          ))}
                        </ol>
                      )}
                    </section>
                  </div>
                ) : (
                  <section className="rounded-lg border border-border bg-surface p-4">
                    <h3 className="text-base font-semibold text-foreground">
                      {step + 1}. {m(`steps.${current}`)}
                    </h3>
                    <p className="mt-0.5 text-[13px] text-foreground-muted">{m("carried_hint")}</p>
                    {view.carried[current].lines.length === 0 ? (
                      <p className="mt-4 text-sm text-foreground-muted">{m("carried_none")}</p>
                    ) : (
                      <ol className="mt-3 list-decimal space-y-1 pl-6 text-sm text-foreground">
                        {view.carried[current].lines.map((line, i) => (
                          <li key={i}>{line}</li>
                        ))}
                      </ol>
                    )}
                  </section>
                )}
              </div>
            </>
          )}

          <Modal
            open={Boolean(dialog)}
            onOpenChange={(o) => !o && setDialog(null)}
            title={dialog ? m(dialog) : ""}
            description={r ? `${r.request_no} · ${r.style_code}` : undefined}
            footer={
              <>
                <Button variant="outline" onClick={() => setDialog(null)} disabled={pending}>
                  {m("cancel")}
                </Button>
                <Button onClick={confirm} loading={pending}>
                  {dialog ? m(dialog) : ""}
                </Button>
              </>
            }
          >
            <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
              <span>
                {dialog === "return" || dialog === "reject" ? (
                  <>
                    {m("note")}
                    <span className="text-danger">*</span>
                  </>
                ) : (
                  m("note_optional")
                )}
              </span>
              <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={4} />
              {error ? <span className="text-xs font-normal text-danger">{error}</span> : dialog === "return" || dialog === "reject" ? <span className="text-xs font-normal text-foreground-muted">{m(`${dialog}_hint`)}</span> : null}
            </label>
          </Modal>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

function Field({ label, required = false, tag = null, children }) {
  return (
    <div className="grid items-center gap-2 sm:grid-cols-[9rem_1fr]">
      <span className="text-[13px] font-semibold text-foreground sm:text-right">
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      <div className="flex items-center gap-2">
        <div className="min-w-0 flex-1">{children}</div>
        {tag ? (
          <span className="flex shrink-0 items-center gap-1 text-[11px] text-foreground-muted">
            <Lock className="size-3" /> {tag}
          </span>
        ) : null}
      </div>
    </div>
  );
}

export { StyleRegistrations };
