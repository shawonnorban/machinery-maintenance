"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { Dialog } from "@base-ui/react/dialog";
import { ArrowLeft, ArrowRight, Check, CheckCircle2, Circle, FileText, History, ImagePlus, Loader2, Save, Send, ShieldCheck, Trash2, Upload, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { StyleForm } from "@/components/merchandise/style-form";
import { StyleItemDesk, ImageBox } from "@/components/merchandise/style-item-desk";
import { StyleFabrication } from "@/components/merchandise/style-fabrication";
import { StyleColourGrid } from "@/components/merchandise/style-colour-grid";
import { StyleServices } from "@/components/merchandise/style-services";
import { StyleBom } from "@/components/merchandise/style-bom";
import { StyleFabricMapping } from "@/components/merchandise/style-fabric-mapping";
import { StylePacking } from "@/components/merchandise/style-packing";

const STEPS = ["profile", "items", "fabric", "colour", "services", "bom", "mapping", "packing", "attachments", "review"];
const KINDS = ["STYLE_IMAGE", "FRONT_VIEW", "BACK_VIEW", "TECH_PACK", "MEASUREMENT_SHEET", "FABRIC_SWATCH", "ARTWORK", "OTHER"];
const PICTURES = ["STYLE_IMAGE", "FRONT_VIEW", "BACK_VIEW"];
const STATUS_TONE = { DRAFT: "warning", SENT: "info", APPROVED: "success", REJECTED: "danger", REVISION: "warning" };

/**
 * Style & Order → Development Style Catalogue → the development style wizard
 * (AnnotechERP 06/07), Brickland's ten steps: the style profile and its
 * image, garment items, fabric studio, colour studio, process & services,
 * material BOM, fabric mapping, packing (details and pack breakdown),
 * attachments, and the review with its approval. Each step is the style
 * sheet's own desk; moving between steps reads the step's data and never
 * leaves the page. A new style opens on its profile and the other steps wait
 * until it is saved; a style with its approver, or approved, is closed to
 * changes and opens on its review.
 *
 * @param {{ open: boolean, styleId: ?string, look: object, canManage: boolean, canApprove: boolean, actions: object, onClose: Function, onChanged: Function }} props
 */
function DevStyleWizard({ open, styleId, look, canManage, canApprove, actions, onClose, onChanged }) {
  const t = useT("merchandise");
  const w = (k, p) => t(`master.wiz.${k}`, p);
  const toastManager = useToastManager();
  const [id, setId] = useState(styleId);
  const [step, setStep] = useState(0);
  const [row, setRow] = useState(null);
  const [review, setReview] = useState(null);
  const [data, setData] = useState({});
  const [loading, startLoading] = useTransition();

  const status = review?.status ?? "DRAFT";
  const locked = Boolean(review?.locked);
  const editable = canManage && !locked;

  function fail(result) {
    toastManager.add({ title: result?.message, type: "danger" });
  }

  /** Reads what a step shows, fresh every time it opens. */
  function load(next, forId = id) {
    if (!forId) return;
    const key = STEPS[next];
    const loaders = {
      profile: async () => {
        const [style, files] = await Promise.all([actions.loadStyle(forId), actions.loadStyleAttachments(forId)]);
        if (style?.status === "success") setRow(style.data);
        return files;
      },
      items: () => actions.loadStyleItems(forId),
      fabric: () => actions.loadStyleFabrication(forId),
      colour: () => actions.loadStyleColours(forId),
      services: () => actions.loadStyleServices(forId),
      bom: () => actions.loadStyleBom(forId),
      mapping: () => actions.loadStyleFabricMapping(forId),
      packing: async () => {
        const [details, lines] = await Promise.all([actions.loadPackingDetails(forId), actions.loadStylePacking(forId)]);
        return details?.status === "success" && lines?.status === "success" ? { status: "success", data: { details: details.data, lines: lines.data } } : details?.status !== "success" ? details : lines;
      },
      attachments: () => actions.loadStyleAttachments(forId),
      review: () => actions.loadDevReview(forId),
    };
    setData((all) => ({ ...all, [key]: null }));
    startLoading(async () => {
      const result = await loaders[key]();
      if (result?.status === "success") setData((all) => ({ ...all, [key]: result.data }));
      else fail(result);
    });
  }

  /** The review is read on opening too: it says where the style stands and whether it may change. */
  async function refreshReview(forId = id) {
    const result = await actions.loadDevReview(forId);
    if (result?.status === "success") {
      setReview(result.data);
      setData((all) => ({ ...all, review: result.data }));
      return result.data;
    }
    return null;
  }

  // Opening the wizard: a new style starts on its profile; an existing one on
  // its profile, or on its review when it is with its approver or approved.
  const opened = useRef(null);
  useEffect(() => {
    if (!open || opened.current === (styleId ?? "new")) return;
    opened.current = styleId ?? "new";
    setId(styleId);
    setRow(null);
    setData({});
    setReview(null);
    setStep(0);
    if (!styleId) return;
    startLoading(async () => {
      const r = await refreshReview(styleId);
      const first = r?.locked ? STEPS.length - 1 : 0;
      setStep(first);
      load(first, styleId);
    });
    // `load` and `refreshReview` read the latest state through their arguments.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, styleId]);

  function go(next) {
    if (next < 0 || next >= STEPS.length || (!id && next > 0)) return;
    setStep(next);
    load(next);
  }

  function close() {
    opened.current = null;
    onClose();
  }

  async function profileSaved(style) {
    setId(style.id);
    setRow(style);
    await refreshReview(style.id);
    onChanged();
  }

  const name = (i) => w(`steps.${STEPS[i]}`);
  const current = STEPS[step];
  const stepData = data[current];
  const style = row ? { id: row.id, style_no: row.style_no, is_set: row.is_set } : null;

  return (
    <Dialog.Root open={open} onOpenChange={(o) => !o && close()}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-foreground/40" />
        <Dialog.Popup className="fixed inset-4 z-50 flex flex-col overflow-hidden rounded-lg border border-border bg-background shadow-xl outline-none lg:inset-x-10">
          <div className="flex flex-wrap items-center gap-3 border-b border-border bg-surface px-5 py-3">
            <span className="rounded-md bg-brand px-3 py-1.5 text-base font-semibold text-brand-foreground">{w("step", { n: step + 1, name: name(step) })}</span>
            <div className="min-w-0">
              <Dialog.Title className="text-sm font-semibold text-foreground">{id && review ? w("dev_style", { style: review.style.style_no }) : w("new_title")}</Dialog.Title>
              {id && review ? <Badge variant={STATUS_TONE[status]}>{t(`master.dsc.status.${status}`)}</Badge> : null}
            </div>
            <div className="ml-auto flex items-center gap-2">
              <Button variant="outline" onClick={() => go(step - 1)} disabled={step === 0}>
                <ArrowLeft /> {w("previous")}
              </Button>
              {step < STEPS.length - 1 ? (
                <Button onClick={() => go(step + 1)} disabled={!id}>
                  {w("continue")} <ArrowRight />
                </Button>
              ) : null}
              <Button variant="outline" size="sm" onClick={close} aria-label={w("close")}>
                <X />
              </Button>
            </div>
          </div>

          <ol className="flex gap-1 overflow-x-auto border-b border-border bg-surface px-5 py-3">
            {STEPS.map((key, i) => {
              const done = i < step;
              return (
                <li key={key} className="min-w-24 flex-1">
                  <button type="button" onClick={() => go(i)} disabled={!id && i > 0} aria-label={w("step_aria", { n: i + 1, name: name(i) })} aria-current={i === step ? "step" : undefined} className="group flex w-full flex-col items-center gap-1 disabled:cursor-not-allowed disabled:opacity-50">
                    <span className={cn("grid size-8 place-items-center rounded-full border text-xs font-semibold", i === step ? "border-brand bg-brand text-brand-foreground" : done ? "border-brand/50 text-brand" : "border-border text-foreground-muted")}>{done ? <Check className="size-4" /> : i + 1}</span>
                    <span className={cn("text-center text-[12px] whitespace-nowrap", i === step ? "font-semibold text-brand" : "text-foreground")}>{name(i)}</span>
                  </button>
                </li>
              );
            })}
          </ol>

          <div className="flex-1 overflow-y-auto p-5">
            {locked && current !== "review" ? <Alert variant={status === "APPROVED" ? "success" : "info"} className="mb-4">{status === "APPROVED" ? w("locked_approved") : w("locked_sent")}</Alert> : null}
            {!id && current === "profile" ? (
              <div className="grid gap-4 xl:grid-cols-[1fr_20rem]">
                <StyleForm key="new" mode="new" look={look} save={actions.saveStyle} recordsHref="#" onSaved={profileSaved} />
                <section className="h-fit rounded-lg border border-border bg-surface p-4">
                  <h3 className="text-sm font-semibold text-foreground">{w("style_image")}</h3>
                  <p className="mt-1 text-[13px] text-foreground-muted">{w("image_after_save")}</p>
                </section>
              </div>
            ) : !stepData || (current === "profile" && !row) ? (
              <div className="flex items-center justify-center gap-2 py-16 text-sm text-foreground-muted">{loading ? <Loader2 className="size-4 animate-spin" /> : null}</div>
            ) : current === "review" ? (
              <ReviewStep review={review ?? stepData} canManage={canManage} canApprove={canApprove} act={actions.devStyleAct} styleId={id} onDone={async (next) => { setReview(next); setData((all) => ({ ...all, review: next })); onChanged(); }} onDraft={close} />
            ) : (
              <fieldset disabled={locked} className="min-w-0">
                {current === "profile" ? (
                  <div className="grid gap-4 xl:grid-cols-[1fr_20rem]">
                    <StyleForm key={row.id} mode="sheet" row={row} look={look} save={actions.saveStyle} recordsHref="#" onSaved={profileSaved} />
                    <AttachmentBox styleId={id} kind="STYLE_IMAGE" file={stepData.STYLE_IMAGE} onChange={(files) => setData((all) => ({ ...all, profile: files }))} remove={actions.detachStyleFile} canManage={editable} large />
                  </div>
                ) : current === "items" ? (
                  <Card>
                    <StyleItemDesk key={id} mode="fresh" style={style ?? { id, style_no: review?.style.style_no, is_set: false }} data={stepData} save={actions.saveStyleItem} canManage={editable} />
                  </Card>
                ) : current === "fabric" ? (
                  <Card>
                    <StyleFabrication styleId={id} data={stepData} save={actions.saveStyleFabrication} canManage={editable} />
                  </Card>
                ) : current === "colour" ? (
                  <Card>
                    <StyleColourGrid styleId={id} data={stepData} save={actions.saveStyleColours} canManage={editable} />
                  </Card>
                ) : current === "services" ? (
                  <Card>
                    <StyleServices styleId={id} data={stepData} save={actions.saveStyleServices} canManage={editable} />
                  </Card>
                ) : current === "bom" ? (
                  <Card>
                    <StyleBom styleId={id} styleNo={review?.style.style_no ?? ""} data={stepData} save={actions.saveStyleBom} approve={actions.approveStyleBom} canManage={editable} />
                  </Card>
                ) : current === "mapping" ? (
                  <Card>
                    <StyleFabricMapping styleId={id} data={stepData} save={actions.saveStyleFabricMapping} canManage={editable} />
                  </Card>
                ) : current === "packing" ? (
                  <div className="flex flex-col gap-4">
                    <PackingDetails styleId={id} data={stepData.details} save={actions.savePackingDetails} canManage={editable} />
                    <h3 className="text-sm font-semibold text-foreground">{w("pack_breakdown")}</h3>
                    <Card>
                      <StylePacking styleId={id} data={stepData.lines} save={actions.saveStylePacking} canManage={editable} />
                    </Card>
                  </div>
                ) : (
                  <section className="rounded-lg border border-border bg-surface p-5">
                    <h3 className="text-base font-semibold text-foreground">{w("attachments")}</h3>
                    <p className="mt-0.5 text-[13px] text-foreground-muted">{w("attachments_hint")}</p>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                      {KINDS.map((kind) => (
                        <AttachmentBox key={kind} styleId={id} kind={kind} file={stepData[kind]} onChange={(files) => setData((all) => ({ ...all, attachments: files }))} remove={actions.detachStyleFile} canManage={editable} />
                      ))}
                    </div>
                  </section>
                )}
              </fieldset>
            )}
          </div>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

function Card({ children }) {
  return <div className="rounded-lg border border-border bg-surface">{children}</div>;
}

/** One attachment box: its file (a picture shown, any other file named), Upload and remove. */
function AttachmentBox({ styleId, kind, file, onChange, remove, canManage, large = false }) {
  const t = useT("merchandise");
  const w = (k, p) => t(`master.wiz.${k}`, p);
  const toastManager = useToastManager();
  const input = useRef(null);
  const [busy, setBusy] = useState(false);
  const picture = file && (PICTURES.includes(kind) || /\.(jpe?g|png|webp)$/i.test(file.name ?? ""));

  async function upload(chosen) {
    if (!chosen) return;
    setBusy(true);
    const body = new FormData();
    body.append("kind", kind);
    body.append("file", chosen);
    const response = await fetch(`/api/merchandise/style-attachments/${styleId}`, { method: "POST", body });
    const json = await response.json().catch(() => null);
    setBusy(false);
    if (response.ok && json?.data) onChange(json.data);
    else toastManager.add({ title: json?.message ?? json?.errors?.file?.[0] ?? "Upload failed", type: "danger" });
  }

  async function clear() {
    const result = await remove(styleId, kind);
    if (result?.status === "success") onChange(result.data);
    else toastManager.add({ title: result?.message, type: "danger" });
  }

  return (
    <section className="rounded-lg border border-border bg-surface p-3">
      <div className="flex items-center justify-between gap-2">
        <h4 className="text-sm font-semibold text-foreground">{w(`kind.${kind}`)}</h4>
        <div className="flex gap-1">
          {file && canManage ? (
            <Button variant="outline" size="sm" onClick={clear} aria-label={w("remove_file", { name: w(`kind.${kind}`) })}>
              <Trash2 />
            </Button>
          ) : null}
          {canManage ? (
            <Button variant="outline" size="sm" onClick={() => input.current?.click()} loading={busy}>
              <Upload /> {w("upload")}
            </Button>
          ) : null}
          <input ref={input} type="file" accept="image/jpeg,image/png,image/webp,application/pdf" className="sr-only" onChange={(e) => { upload(e.target.files?.[0]); e.target.value = ""; }} />
        </div>
      </div>
      <div className={cn("mt-2 grid place-items-center overflow-hidden rounded-md bg-surface-muted text-[13px] text-foreground-muted", large || PICTURES.includes(kind) ? "h-40" : "h-16")}>
        {picture ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={file.path} alt={w(`kind.${kind}`)} className="h-full w-full object-contain" />
        ) : file ? (
          <a href={file.path} target="_blank" rel="noreferrer" className="flex items-center gap-2 px-3 font-medium text-brand hover:underline">
            <FileText className="size-4 shrink-0" /> <span className="truncate">{file.name ?? w(`kind.${kind}`)}</span>
          </a>
        ) : (
          <span className="flex flex-col items-center gap-1">
            {PICTURES.includes(kind) ? <ImagePlus className="size-5" /> : null}
            {w("nothing")}
          </span>
        )}
      </div>
    </section>
  );
}

/** Packing Details: how every pack of the style is made up and cartoned, with its photo. */
function PackingDetails({ styleId, data, save, canManage }) {
  const t = useT("merchandise");
  const w = (k, p) => t(`master.wiz.${k}`, p);
  const toastManager = useToastManager();
  const d = data.details ?? {};
  const [form, setForm] = useState({
    packing_method: d.packing_method ?? "", carton_type: d.carton_type ?? "", pcs_per_poly: d.pcs_per_poly == null ? "" : String(d.pcs_per_poly), pcs_per_carton: d.pcs_per_carton == null ? "" : String(d.pcs_per_carton),
    carton_size: d.carton_size ?? "", carton_marking: d.carton_marking ?? "", barcode: Boolean(d.barcode), hangtag: Boolean(d.hangtag), remarks: d.remarks ?? "", photo_id: d.photo_id ?? null, photo: d.photo ?? null,
  });
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));

  function submit() {
    startTransition(async () => {
      const result = await save(styleId, {
        ...form,
        packing_method: form.packing_method || null,
        carton_type: form.carton_type || null,
        pcs_per_poly: form.pcs_per_poly === "" ? null : Number(form.pcs_per_poly),
        pcs_per_carton: form.pcs_per_carton === "" ? null : Number(form.pcs_per_carton),
        photo: undefined,
      });
      if (result?.status === "success") {
        setErrors(null);
        toastManager.add({ title: w("packing_saved"), type: "success" });
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  const label = "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground";
  return (
    <div className="grid gap-4 xl:grid-cols-[1fr_1fr]">
      <section className="rounded-lg border border-border bg-surface p-5">
        <h3 className="text-base font-semibold text-foreground">{w("packing_details")}</h3>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{w("packing_hint")}</p>
        {errors ? (
          <Alert variant="danger" className="mt-3">
            {errors.join(" · ")}
          </Alert>
        ) : null}
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className={label}>
            {w("pf.method")}
            <Select value={form.packing_method} onValueChange={(v) => set("packing_method")(v ?? "")} options={[{ value: "", label: w("pf.ph_method") }, ...data.methods.map((v) => ({ value: v, label: w(`method.${v}`) }))]} placeholder={w("pf.ph_method")} />
          </label>
          <label className={label}>
            {w("pf.carton_type")}
            <Select value={form.carton_type} onValueChange={(v) => set("carton_type")(v ?? "")} options={[{ value: "", label: w("pf.ph_carton") }, ...data.cartons.map((v) => ({ value: v, label: w(`carton.${v}`) }))]} placeholder={w("pf.ph_carton")} />
          </label>
          <label className={label}>
            {w("pf.per_poly")}
            <Input type="number" min="1" step="1" value={form.pcs_per_poly} onChange={(e) => set("pcs_per_poly")(e.target.value)} />
          </label>
          <label className={label}>
            {w("pf.per_carton")}
            <Input type="number" min="1" step="1" value={form.pcs_per_carton} onChange={(e) => set("pcs_per_carton")(e.target.value)} />
          </label>
          <label className={label}>
            {w("pf.carton_size")}
            <Input value={form.carton_size} onChange={(e) => set("carton_size")(e.target.value)} placeholder={w("pf.ph_size")} />
          </label>
          <label className={label}>
            {w("pf.marking")}
            <Input value={form.carton_marking} onChange={(e) => set("carton_marking")(e.target.value)} placeholder={w("pf.ph_marking")} />
          </label>
          <div className="flex gap-5 sm:col-span-2">
            {["barcode", "hangtag"].map((flag) => (
              <label key={flag} className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
                <Checkbox checked={form[flag]} onCheckedChange={(c) => set(flag)(c === true)} />
                {w(`pf.${flag}`)}
              </label>
            ))}
          </div>
          <label className={cn(label, "sm:col-span-2")}>
            {w("pf.remarks")}
            <Textarea value={form.remarks} onChange={(e) => set("remarks")(e.target.value)} rows={3} />
          </label>
        </div>
        {canManage ? (
          <Button className="mt-4" onClick={submit} loading={pending}>
            <Save /> {w("save_packing")}
          </Button>
        ) : null}
      </section>
      <section className="rounded-lg border border-border bg-surface p-5">
        <h3 className="mb-3 text-base font-semibold text-foreground">{w("packing_photo")}</h3>
        <ImageBox large label={w("packing_photo")} path={form.photo} onUploaded={(f) => setForm((x) => ({ ...x, photo_id: f.id, photo: f.path }))} onClear={() => setForm((x) => ({ ...x, photo_id: null, photo: null }))} m={(k, p) => t(`master.itm.${k}`, p)} disabled={!canManage} />
      </section>
    </div>
  );
}

/** Step 10: the summary, what each step holds and how complete the style is, its approval and history. */
function ReviewStep({ review, canManage, canApprove, act, styleId, onDone, onDraft }) {
  const t = useT("merchandise");
  const w = (k, p) => t(`master.wiz.${k}`, p);
  const toastManager = useToastManager();
  const [dialog, setDialog] = useState(null);
  const s = review.style;
  const status = review.status;
  const checklist = ["profile", "items", "fabric", "colour", "services", "bom", "mapping", "packing", "attachments"];
  const openDialog = (move) => setDialog(move);

  const row = (label, value) => (
    <div className="flex justify-between gap-3 border-b border-border py-2 text-[13px] last:border-0">
      <span className="text-foreground-muted">{label}</span>
      <span className="font-semibold text-foreground">{value ?? "—"}</span>
    </div>
  );

  return (
    <div className="grid gap-4 xl:grid-cols-[18rem_1fr_20rem]">
      <section className="rounded-lg border border-border bg-surface p-4">
        <h3 className="text-base font-semibold text-foreground">{w("summary")}</h3>
        <div className="mt-3 grid h-44 place-items-center overflow-hidden rounded-md bg-surface-muted text-[13px] text-foreground-muted">
          {s.image ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={s.image} alt={s.style_no} className="h-full w-full object-contain" />
          ) : (
            w("no_image")
          )}
        </div>
        <div className="mt-3">
          {row(w("sum.style"), s.style_no)}
          {row(w("sum.buyer"), s.buyer)}
          {row(w("sum.garment"), s.garment_type)}
          {row(w("sum.season"), s.season)}
          {row(w("sum.size_set"), s.size_set)}
          {row(w("sum.colours"), review.counts.colour)}
          {row(w("sum.items"), review.counts.items)}
          {row(w("sum.bom"), review.counts.bom)}
        </div>
      </section>

      <section className="rounded-lg border border-border bg-surface p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">{w("review_approval")}</h3>
            <p className="text-[13px] text-foreground-muted">{[s.buyer, s.description, s.season].filter(Boolean).join(" · ")}</p>
          </div>
          <Badge variant={STATUS_TONE[status]}>{t(`master.dsc.status.${status}`)}</Badge>
        </div>
        <div className="mt-4 flex items-center justify-between text-[13px] font-semibold text-foreground">
          <span>{w("completion")}</span>
          <span>{review.completion}%</span>
        </div>
        <div className="mt-1 h-2 overflow-hidden rounded-full bg-surface-muted">
          <div className="h-full rounded-full bg-success" style={{ width: `${review.completion}%` }} />
        </div>
        <ul className="mt-4 flex flex-col gap-2.5">
          {checklist.map((key) => {
            const n = review.counts[key];
            return (
              <li key={key} className="flex items-center justify-between gap-3 text-sm">
                <span className="flex items-center gap-2 text-foreground">
                  {n > 0 ? <CheckCircle2 className="size-4 text-success" /> : <Circle className="size-4 text-foreground-muted" strokeDasharray="3 3" />}
                  {w(`steps.${key}`)}
                </span>
                <span className={cn("text-xs", n > 0 ? "text-success" : "text-foreground-muted")}>{n > 0 ? w("completed", { what: w(`what.${key}`, { n }) }) : w("not_started")}</span>
              </li>
            );
          })}
        </ul>
      </section>

      <div className="flex flex-col gap-4">
        <section className="rounded-lg border border-border bg-surface p-4">
          <h3 className="text-base font-semibold text-foreground">{w("approval")}</h3>
          {review.last_note && status !== "DRAFT" ? (
            <div className="mt-2 rounded-md bg-surface-muted px-3 py-2 text-[13px]">
              <div className="text-foreground-muted">{w("last_note")}</div>
              <div className="font-medium text-foreground">{review.last_note}</div>
            </div>
          ) : null}
          <div className="mt-3 flex flex-col items-start gap-2">
            {["DRAFT", "REVISION", "REJECTED"].includes(status) ? (
              <>
                {canManage ? (
                  <Button variant="outline" onClick={() => { toastManager.add({ title: w("kept_draft"), type: "success" }); onDraft(); }}>
                    <Save /> {w("save_draft")}
                  </Button>
                ) : null}
                {canManage ? (
                  <Button className="bg-success text-white hover:bg-success/90" onClick={() => openDialog("send")}>
                    <Send /> {w("send")}
                  </Button>
                ) : null}
                {canApprove ? (
                  <Button onClick={() => openDialog("direct")}>
                    <ShieldCheck /> {w("direct")}
                  </Button>
                ) : null}
              </>
            ) : status === "SENT" && canApprove ? (
              <>
                <Button className="bg-success text-white hover:bg-success/90" onClick={() => openDialog("approve")}>
                  <CheckCircle2 /> {w("approve")}
                </Button>
                <Button variant="outline" onClick={() => openDialog("revision")}>
                  {w("revision")}
                </Button>
                <Button variant="outline" className="text-danger" onClick={() => openDialog("reject")}>
                  {w("reject")}
                </Button>
              </>
            ) : null}
          </div>
        </section>
        <section className="rounded-lg border border-border bg-surface p-4">
          <h3 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <History className="size-4" /> {w("history")}
          </h3>
          {review.history.length === 0 ? (
            <p className="mt-2 text-[13px] text-foreground-muted">{w("no_history")}</p>
          ) : (
            <ol className="mt-2 flex flex-col gap-3">
              {[...review.history].reverse().map((h, i) => (
                <li key={i} className="border-l-2 border-brand/40 pl-3 text-[13px]">
                  <div className="font-semibold text-foreground">{w(`action.${h.action}`)}</div>
                  <div className="text-foreground-muted">{h.at ? new Date(h.at).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : ""}</div>
                  <div className="text-foreground-muted">{h.by}</div>
                  {h.note ? <div className="mt-1 text-foreground">{h.note}</div> : null}
                </li>
              ))}
            </ol>
          )}
        </section>
      </div>

      <ApprovalDialog move={dialog} style={s} styleId={styleId} act={act} onClose={() => setDialog(null)} onDone={onDone} />
    </div>
  );
}

/**
 * An approval move's note dialog — Send for approval and Approve (note
 * optional), Return for revision and Reject (a note says why) — shared by the
 * wizard's review and the Inbox rows.
 *
 * @param {{ move: ?string, style: { style_no: string, buyer?: string, description?: string }, styleId: string, act: Function, onClose: Function, onDone: Function }} props
 */
function ApprovalDialog({ move, style, styleId, act, onClose, onDone }) {
  const t = useT("merchandise");
  const w = (k, p) => t(`master.wiz.${k}`, p);
  const toastManager = useToastManager();
  const [note, setNote] = useState("");
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const [shown, setShown] = useState(move);
  // A new move opens with an empty note (adjusting state during render).
  if (shown !== move) {
    setShown(move);
    setNote("");
    setError(null);
  }
  const DIALOGS = {
    send: { title: w("dlg.send_title"), hint: w("dlg.send_hint"), required: false, confirm: w("send"), tone: "bg-success text-white hover:bg-success/90" },
    approve: { title: w("dlg.approve_title"), hint: w("dlg.approve_hint"), required: false, confirm: w("approve"), tone: "" },
    direct: { title: w("dlg.direct_title"), hint: w("dlg.approve_hint"), required: false, confirm: w("direct"), tone: "" },
    revision: { title: w("dlg.revision_title"), hint: w("dlg.revision_hint"), required: true, confirm: w("revision"), tone: "bg-warning text-white hover:bg-warning/90" },
    reject: { title: w("dlg.reject_title"), hint: w("dlg.reject_hint"), required: true, confirm: w("reject"), tone: "bg-danger text-white hover:bg-danger/90" },
  };
  const d = move ? DIALOGS[move] : null;

  function confirm() {
    if (d.required && !note.trim()) {
      setError(d.hint);
      return;
    }
    startTransition(async () => {
      const result = await act(styleId, move, note.trim());
      if (result?.status === "success") {
        onClose();
        toastManager.add({ title: w("done", { style: style.style_no, status: t(`master.dsc.status.${result.data.status}`) }), type: "success" });
        onDone(result.data);
      } else {
        setError(result?.errors ? Object.values(result.errors).flat().join(" · ") : result?.message);
      }
    });
  }

  return (
    <Modal
      open={Boolean(move)}
      onOpenChange={(o) => !o && onClose()}
      title={d?.title ?? ""}
      description={[style.style_no, style.buyer, style.description].filter(Boolean).join(" · ")}
      footer={
        d ? (
          <>
            <Button variant="outline" onClick={onClose} disabled={pending}>
              {w("dlg.cancel")}
            </Button>
            <Button className={d.tone} onClick={confirm} loading={pending}>
              {d.confirm}
            </Button>
          </>
        ) : null
      }
    >
      {d ? (
        <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
          <span>
            {d.required ? (
              <>
                {w("dlg.note")}
                <span className="text-danger">*</span>
              </>
            ) : (
              w("dlg.note_optional")
            )}
          </span>
          <Textarea value={note} onChange={(e) => setNote(e.target.value)} rows={4} />
          <span className={cn("text-xs font-normal", error ? "text-danger" : "text-foreground-muted")}>{error ?? d.hint}</span>
        </label>
      ) : null}
    </Modal>
  );
}

export { DevStyleWizard, ApprovalDialog };
