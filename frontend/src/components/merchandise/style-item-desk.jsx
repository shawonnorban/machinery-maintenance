"use client";

import { useMemo, useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ChevronLeft, ChevronRight, ImageIcon, ListChecks, Plus, Save, Upload, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { MultiPick } from "@/components/erp/multi-pick";
import { useT } from "@/lib/i18n";

const FLAGS_TYPE = ["is_solid", "is_yd"];
const FLAGS_SERVICE = ["is_aop", "is_gmt_wash", "is_heat_seal", "is_print", "is_embroidery"];
const blankCerts = () => [0, 1, 2, 3].map(() => ({ checked: false, file_id: null, path: null }));

/** A form's values from an item, or a new item for this style. */
function start(item, style) {
  return {
    id: item?.id ?? null,
    set_style_id: item?.set_style_id ?? "",
    for_style_ids: item?.for_style_ids ?? [style.id],
    model_no: item?.model_no ?? "",
    item_group: item?.item_group ?? "",
    item_type: item?.item_type ?? "",
    division: item?.division ?? "",
    neck_type: item?.neck_type ?? "",
    sleeve_type: item?.sleeve_type ?? "",
    short_name: item?.short_name ?? "",
    full_name: item?.full_name ?? "",
    description: item?.description ?? "",
    gender: item?.gender ?? "",
    age_group: item?.age_group ?? "",
    status: item?.status ?? "ACTIVE",
    ...Object.fromEntries([...FLAGS_TYPE, ...FLAGS_SERVICE].map((f) => [f, item?.[f] ?? f === "is_solid"])),
    // Several at a time; the item carries them as one comma-joined text.
    nature_of_work: String(item?.nature_of_work ?? "").split(",").map((w) => w.trim()).filter(Boolean),
    no_of_mc: item?.no_of_mc ?? "",
    exp_prod_hr: item?.exp_prod_hr ?? "",
    smv_marketing: item?.smv_marketing === null || item?.smv_marketing === undefined ? "" : String(item.smv_marketing),
    tech_spec: item?.tech_spec ?? "",
    key_image_id: item?.key_image_id ?? null,
    key_image: item?.key_image ?? null,
    second_image_id: item?.second_image_id ?? null,
    second_image: item?.second_image ?? null,
    item_image_id: item?.item_image_id ?? null,
    item_image: item?.item_image ?? null,
    certificates: item?.certificates?.length ? [...item.certificates, ...blankCerts()].slice(0, 4) : blankCerts(),
    care_labels: item?.care_labels?.length ? item.care_labels.map((l) => ({ garment_part: l.garment_part ?? "", service: l.service ?? "" })) : [{ garment_part: "", service: "" }],
  };
}

/**
 * Style Development Sheet → Item/Combo/Model (Fresh / Copy) (AnnotechERP
 * 06/05), Brickland's desk under the sheet: the items serving the style, one
 * at a time — Add New, Item List, Previous / Next — on Item/Combo/Model
 * Registration: set item, the styles it is for, group and type, division,
 * neck and sleeve, names, gender, age, status, service type and services,
 * nature of work and the IE figures; on the right the key and 2nd images, the
 * item image, the technical specification, four certificate instructions and
 * the care label lines. A Copy desk's "Same as" offers every style's items; a
 * Fresh desk's this style's own.
 *
 * The desk's header and tabs are `StyleDesk`'s; this is the Registration tab.
 *
 * @param {{ mode: "fresh"|"copy", style: {id: string, style_no: string, is_set: boolean}, data: object, save: Function, canManage: boolean }} props
 */
function StyleItemDesk({ mode, style, data, save, canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.itm.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [items, setItems] = useState(data.items);
  const [at, setAt] = useState(data.items.length ? 0 : -1);
  const [values, setValues] = useState(() => start(data.items[0] ?? null, style));
  const [listOpen, setListOpen] = useState(false);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (field) => (value) => setValues((v) => ({ ...v, [field]: value }));
  const o = (group, values) => values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
  const ph = (key, list) => [{ value: "", label: m(`ph.${key}`) }, ...list];
  // The masters' lists, keeping what this item already carries though since switched off.
  const offered = values.item_group ? (data.options.types[values.item_group] ?? []) : [];
  const types = values.item_type && !offered.includes(values.item_type) ? [...offered, values.item_type] : offered;
  const natures = [...new Set([...data.options.nature_of_work, ...values.nature_of_work])];
  const sameAs = (mode === "copy" ? data.copy_from : data.copy_from.filter((c) => c.style_id === style.id)).filter((c) => c.value !== values.id);

  function show(index) {
    setAt(index);
    setValues(start(items[index] ?? null, style));
    setErrors(null);
    setListOpen(false);
  }

  function addNew() {
    setAt(-1);
    setValues(start(null, style));
    setErrors(null);
  }

  /** "Same as": the picked item's particulars, kept as a new item of this style. */
  function copyFrom(id) {
    const source = data.copy_from.find((c) => c.value === id)?.item;
    if (!source) return;
    const next = start(source, style);
    setValues({ ...next, id: values.id, for_style_ids: values.for_style_ids, set_style_id: values.set_style_id });
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        ...values,
        set_style_id: values.set_style_id || null,
        item_type: values.item_type || null,
        neck_type: values.neck_type || null,
        sleeve_type: values.sleeve_type || null,
        gender: values.gender || null,
        age_group: values.age_group || null,
        smv_marketing: values.smv_marketing === "" ? null : Number(values.smv_marketing),
        certificates: values.certificates.map((c) => ({ checked: c.checked, file_id: c.file_id })),
        care_labels: values.care_labels.map((l) => ({ garment_part: l.garment_part || null, service: l.service || null })),
      };
      const result = await save(style.id, values.id, payload);
      if (result?.status === "success") {
        const saved = result.item;
        const next = values.id ? items.map((i) => (i.id === saved.id ? saved : i)) : [...items, saved];
        setItems(next);
        setAt(next.findIndex((i) => i.id === saved.id));
        setValues(start(saved, style));
        setErrors(null);
        toastManager.add({ title: m("saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ? [...new Set(Object.values(result.errors).flat())] : [result?.message]);
      }
    });
  }

  return (
    <>
      <div className="flex flex-col gap-4 p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="relative flex gap-2">
            {canManage ? (
              <Button variant="outline" size="sm" onClick={addNew}>
                <Plus /> {m("add_new")}
              </Button>
            ) : null}
            <Button variant="outline" size="sm" onClick={() => setListOpen((x) => !x)}>
              <ListChecks /> {m("item_list")}
            </Button>
            {listOpen ? (
              <div className="absolute top-10 left-0 z-20 w-96 rounded-md border border-border bg-surface p-3 shadow-lg">
                <p className="mb-2 text-sm font-semibold">{m("list_title")}</p>
                <p className="mb-2 text-xs text-foreground-muted">{items.length === 1 ? m("list_count", { count: 1 }) : m("list_count_many", { count: items.length })}</p>
                <ul className="max-h-64 overflow-y-auto">
                  {items.map((i, index) => (
                    <li key={i.id}>
                      <button type="button" onClick={() => show(index)} className={cn("w-full rounded px-2 py-1.5 text-left text-sm hover:bg-surface-muted", index === at && "bg-brand-subtle text-brand")}>
                        <span className="font-semibold">{i.short_name}</span> — {i.full_name}
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" disabled={at <= 0} onClick={() => show(at - 1)}>
              <ChevronLeft /> {m("previous")}
            </Button>
            <span className="text-sm tabular-nums">{m("of", { at: at < 0 ? items.length + 1 : at + 1, count: at < 0 ? items.length + 1 : items.length })}</span>
            <Button variant="outline" size="sm" disabled={at < 0 || at >= items.length - 1} onClick={() => show(at + 1)}>
              {m("next")} <ChevronRight />
            </Button>
          </div>
        </div>

        {items.length === 0 && at < 0 ? <p className="text-[13px] text-foreground-muted">{m("no_items")}</p> : null}
        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}

        {style.is_set ? (
          <div className="grid items-center gap-3 rounded-md border border-border bg-surface-muted/40 px-4 py-3 sm:grid-cols-[8rem_1fr]">
            <span className="text-[13px] font-semibold sm:text-right">{m("f.set_item")}</span>
            <Select value={values.set_style_id} onValueChange={(v) => set("set_style_id")(v ?? "")} options={ph("set", data.sets)} placeholder={m("ph.set")} />
          </div>
        ) : null}
        <div className="grid items-center gap-3 rounded-md border border-border bg-surface-muted/40 px-4 py-3 sm:grid-cols-[8rem_1fr]">
          <span className="text-[13px] font-semibold sm:text-right">{m("f.for_styles")}</span>
          <StylePicker styles={data.styles} value={values.for_style_ids} onChange={set("for_style_ids")} placeholder={m("ph.styles")} />
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <div className="flex flex-col gap-3">
            <Row label={m("f.model_no")}>
              <div className="grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                <Input value={values.model_no} onChange={(e) => set("model_no")(e.target.value)} aria-label={m("f.model_no")} />
                <span className="text-[13px] font-semibold">{m("f.same_as")}</span>
                <Select value="" onValueChange={(v) => v && copyFrom(v)} options={ph("same", sameAs.map(({ value, label }) => ({ value, label })))} placeholder={m("ph.same")} />
              </div>
            </Row>
            <Row label={m("f.group_type")} required>
              <div className="grid grid-cols-2 gap-3">
                <Select value={values.item_group} onValueChange={(v) => v && setValues((x) => ({ ...x, item_group: v, item_type: (data.options.types[v] ?? []).includes(x.item_type) ? x.item_type : "" }))} options={ph("group", o("group", data.options.groups))} placeholder={m("ph.group")} />
                <Select value={values.item_type} onValueChange={(v) => set("item_type")(v ?? "")} options={ph("type", types.map((x) => ({ value: x, label: x })))} placeholder={m("ph.type")} disabled={types.length === 0} />
              </div>
            </Row>
            <Row label={m("f.division")} required>
              <Select value={values.division} onValueChange={(v) => set("division")(v ?? "")} options={ph("division", o("division", data.options.divisions))} placeholder={m("ph.division")} />
            </Row>
            <Row label={m("f.neck_type")}>
              <Select value={values.neck_type} onValueChange={(v) => set("neck_type")(v ?? "")} options={ph("neck", o("neck", data.options.neck_types))} placeholder={m("ph.neck")} />
            </Row>
            <Row label={m("f.sleeve_type")}>
              <Select value={values.sleeve_type} onValueChange={(v) => set("sleeve_type")(v ?? "")} options={ph("sleeve", o("sleeve", data.options.sleeve_types))} placeholder={m("ph.sleeve")} />
            </Row>
            <Row label={m("f.short_name")} required>
              <Input value={values.short_name} onChange={(e) => set("short_name")(e.target.value)} aria-label={m("f.short_name")} />
            </Row>
            <Row label={m("f.full_name")} required>
              <Textarea value={values.full_name} onChange={(e) => set("full_name")(e.target.value)} rows={2} aria-label={m("f.full_name")} />
            </Row>
            <Row label={m("f.description")}>
              <Textarea value={values.description} onChange={(e) => set("description")(e.target.value)} rows={2} aria-label={m("f.description")} />
            </Row>
            <Row label={m("f.gender")}>
              <Select value={values.gender} onValueChange={(v) => set("gender")(v ?? "")} options={ph("gender", o("gender", data.options.genders))} placeholder={m("ph.gender")} />
            </Row>
            <Row label={m("f.age_group")}>
              <Select value={values.age_group} onValueChange={(v) => set("age_group")(v ?? "")} options={ph("age", o("age", data.options.age_groups))} placeholder={m("ph.age")} />
            </Row>
            <Row label={m("f.status")}>
              <Select value={values.status} onValueChange={(v) => v && set("status")(v)} options={o("status", ["ACTIVE", "INACTIVE"])} />
            </Row>
            <Row label={m("f.service_type")}>
              <Flags flags={FLAGS_TYPE} values={values} set={set} m={m} />
            </Row>
            <Row label={m("f.services")}>
              <Flags flags={FLAGS_SERVICE} values={values} set={set} m={m} />
            </Row>
            <Row label={m("f.nature_of_work")} required>
              <MultiPick value={values.nature_of_work} onChange={(v) => set("nature_of_work")(v)} options={natures.map((w) => ({ value: w, label: w }))} placeholder={m("ph.work")} ariaLabel={m("f.nature_of_work")} />
            </Row>
            <Row label={m("f.no_of_mc")} hint={m("data_ie")}>
              <Input value={values.no_of_mc} onChange={(e) => set("no_of_mc")(e.target.value)} aria-label={m("f.no_of_mc")} />
            </Row>
            <Row label={m("f.exp_prod_hr")} hint={m("data_ie")}>
              <Input value={values.exp_prod_hr} onChange={(e) => set("exp_prod_hr")(e.target.value)} aria-label={m("f.exp_prod_hr")} />
            </Row>
            <Row label={m("f.smv_marketing")} hint={m("data_ie_mkt")}>
              <div className="flex items-center gap-2">
                <Input type="number" min="0" step="0.01" value={values.smv_marketing} onChange={(e) => set("smv_marketing")(e.target.value)} aria-label={m("f.smv_marketing")} />
                <span className="text-[13px] font-semibold">{m("min")}</span>
              </div>
            </Row>
          </div>

          <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <ImageBox label={m("key_image")} path={values.key_image} onUploaded={(f) => setValues((v) => ({ ...v, key_image_id: f.id, key_image: f.path }))} onClear={() => setValues((v) => ({ ...v, key_image_id: null, key_image: null }))} m={m} large disabled={!canManage} />
              <ImageBox label={m("second_image")} path={values.second_image} onUploaded={(f) => setValues((v) => ({ ...v, second_image_id: f.id, second_image: f.path }))} onClear={() => setValues((v) => ({ ...v, second_image_id: null, second_image: null }))} m={m} large disabled={!canManage} />
            </div>
            <div className="rounded-md border border-border p-3">
              <ImageBox label={m("item_image")} path={values.item_image} onUploaded={(f) => setValues((v) => ({ ...v, item_image_id: f.id, item_image: f.path }))} onClear={() => setValues((v) => ({ ...v, item_image_id: null, item_image: null }))} m={m} disabled={!canManage} />
            </div>
            <Textarea value={values.tech_spec} onChange={(e) => set("tech_spec")(e.target.value)} rows={3} placeholder={m("tech_spec")} aria-label={m("tech_spec")} />

            <div className="rounded-md border border-border">
              <p className="border-b border-border px-3 py-2 text-[13px] font-semibold text-brand">{m("certificates")}</p>
              <div className="flex flex-wrap gap-4 p-3">
                {values.certificates.map((c, i) => (
                  <div key={i} className="flex items-center gap-2">
                    <Checkbox checked={c.checked} onCheckedChange={(x) => setValues((v) => ({ ...v, certificates: v.certificates.map((y, j) => (j === i ? { ...y, checked: x === true } : y)) }))} aria-label={m("certificate", { n: i + 1 })} />
                    <ImageBox compact label={m("certificate", { n: i + 1 })} path={c.path} onUploaded={(f) => setValues((v) => ({ ...v, certificates: v.certificates.map((y, j) => (j === i ? { ...y, file_id: f.id, path: f.path } : y)) }))} onClear={() => setValues((v) => ({ ...v, certificates: v.certificates.map((y, j) => (j === i ? { ...y, file_id: null, path: null } : y)) }))} m={m} disabled={!canManage} />
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-md border border-border">
              <p className="border-b border-border px-3 py-2 text-[13px] font-semibold text-brand">{m("care_labels")}</p>
              <div className="grid grid-cols-[1fr_auto_1fr_2.25rem] items-center gap-2 bg-surface-muted/40 px-3 py-2 text-[13px] font-semibold">
                <span>{m("gmt_parts")}</span>
                <Button variant="outline" size="sm" onClick={() => setValues((v) => ({ ...v, care_labels: [...v.care_labels, { garment_part: "", service: "" }] }))} aria-label={m("add_care")}>
                  <Plus />
                </Button>
                <span>{m("service")}</span>
                <span />
              </div>
              <div className="flex flex-col gap-2 p-3">
                {values.care_labels.map((l, i) => (
                  <div key={i} className="grid grid-cols-[1fr_1fr_2.25rem] items-center gap-2">
                    <Input value={l.garment_part} onChange={(e) => setValues((v) => ({ ...v, care_labels: v.care_labels.map((y, j) => (j === i ? { ...y, garment_part: e.target.value } : y)) }))} aria-label={m("gmt_parts")} />
                    <Select value={l.service} onValueChange={(x) => setValues((v) => ({ ...v, care_labels: v.care_labels.map((y, j) => (j === i ? { ...y, service: x ?? "" } : y)) }))} options={ph("service", o("care", data.options.care_services))} placeholder={m("ph.service")} />
                    <Button variant="outline" size="sm" onClick={() => setValues((v) => ({ ...v, care_labels: v.care_labels.length > 1 ? v.care_labels.filter((_, j) => j !== i) : [{ garment_part: "", service: "" }] }))} aria-label={m("remove_care")}>
                      <X />
                    </Button>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {canManage ? (
          <div className="flex gap-2">
            <Button onClick={submit} loading={pending}>
              <Save /> {m("save")}
            </Button>
            <Button variant="outline" onClick={() => (at >= 0 ? show(at) : addNew())} disabled={pending}>
              {m("cancel")}
            </Button>
          </div>
        ) : null}
      </div>
    </>
  );
}

function Row({ label, required = false, hint = null, children }) {
  return (
    <div className="grid items-start gap-3 sm:grid-cols-[8rem_1fr]">
      <span className="pt-2.5 text-[13px] font-semibold text-foreground sm:text-right">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      <div className="flex flex-col gap-1">
        {children}
        {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
      </div>
    </div>
  );
}

function Flags({ flags, values, set, m }) {
  return (
    <div className="flex flex-wrap gap-x-4 gap-y-2 pt-2">
      {flags.map((f) => (
        <label key={f} className="flex items-center gap-1.5 text-[13px] font-semibold">
          <Checkbox checked={values[f]} onCheckedChange={(c) => set(f)(c === true)} /> {m(`f.${f}`)}
        </label>
      ))}
    </div>
  );
}

/** A picture box: upload through the route handler (pictures pass the server-action size limit), preview, clear. */
function ImageBox({ label, path, onUploaded, onClear, m, large = false, compact = false, disabled = false }) {
  const input = useRef(null);
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);

  async function upload(file) {
    if (!file) return;
    setBusy(true);
    setFailed(false);
    const body = new FormData();
    body.append("file", file);
    const response = await fetch("/api/merchandise/style-item-images", { method: "POST", body });
    const json = await response.json().catch(() => null);
    setBusy(false);
    if (response.ok && json?.data) onUploaded(json.data);
    else setFailed(true);
    if (input.current) input.current.value = "";
  }

  const picker = <input ref={input} type="file" accept="image/*" className="hidden" onChange={(e) => upload(e.target.files?.[0])} disabled={disabled} />;

  if (compact) {
    return (
      <>
        {picker}
        <button type="button" onClick={() => input.current?.click()} disabled={disabled || busy} title={label} aria-label={label} className="flex size-11 items-center justify-center overflow-hidden rounded-md border border-border bg-surface-muted text-foreground-muted">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          {path ? <img src={path} alt={label} className="size-full object-cover" /> : <ImageIcon className="size-4" />}
        </button>
        {path && !disabled ? (
          <button type="button" onClick={onClear} aria-label={m("clear_image", { name: label })} className="text-foreground-muted hover:text-danger">
            <X className="size-3.5" />
          </button>
        ) : null}
      </>
    );
  }

  if (large) {
    return (
      <div className="flex flex-col gap-1.5">
        {picker}
        <div className="flex h-44 items-center justify-center overflow-hidden rounded-md border border-dashed border-border-strong bg-surface-muted/40">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          {path ? <img src={path} alt={label} className="max-h-full max-w-full object-contain" /> : <ImageIcon className="size-6 text-foreground-muted" />}
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" className="flex-1 justify-start" onClick={() => input.current?.click()} disabled={disabled || busy}>
            {busy ? m("uploading") : m("choose")}
          </Button>
          <Button variant="outline" size="sm" onClick={onClear} disabled={disabled || !path} aria-label={m("clear_image", { name: label })}>
            <X />
          </Button>
        </div>
        <span className="text-xs text-brand">{label}</span>
        {failed ? <span className="text-xs text-danger">{m("upload_failed")}</span> : null}
      </div>
    );
  }

  return (
    <div className="flex items-center gap-3">
      {picker}
      <div className="flex size-16 items-center justify-center overflow-hidden rounded-md bg-surface-muted text-[11px] text-foreground-muted">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        {path ? <img src={path} alt={label} className="size-full object-cover" /> : m("no_image")}
      </div>
      <div className="flex flex-col gap-1">
        <span className="text-[13px] font-semibold">{label}</span>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={() => input.current?.click()} disabled={disabled || busy}>
            <Upload /> {busy ? m("uploading") : m("upload")}
          </Button>
          {path && !disabled ? (
            <Button variant="outline" size="sm" onClick={onClear} aria-label={m("clear_image", { name: label })}>
              <X />
            </Button>
          ) : null}
        </div>
        {failed ? <span className="text-xs text-danger">{m("upload_failed")}</span> : null}
      </div>
    </div>
  );
}

/** "For Style(s)#": the styles an item serves, as chips picked from a searchable list. */
function StylePicker({ styles, value, onChange, placeholder }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const box = useRef(null);
  const byId = useMemo(() => Object.fromEntries(styles.map((s) => [s.value, s.label])), [styles]);
  const needle = query.trim().toLowerCase();
  const free = styles.filter((s) => !value.includes(s.value) && (!needle || s.label.toLowerCase().includes(needle)));

  return (
    <div ref={box} className="relative" onBlur={(e) => !box.current?.contains(e.relatedTarget) && setOpen(false)}>
      <div className="flex min-h-10 flex-wrap items-center gap-1.5 rounded-md border border-border-strong bg-surface px-2 py-1.5">
        {value.map((id) => (
          <span key={id} className="inline-flex items-center gap-1 rounded bg-brand-subtle px-1.5 py-0.5 text-xs font-semibold text-brand">
            {byId[id] ?? "?"}
            <button type="button" onClick={() => onChange(value.filter((x) => x !== id))} aria-label={`× ${byId[id] ?? ""}`}>
              <X className="size-3" />
            </button>
          </span>
        ))}
        <input value={query} onChange={(e) => { setQuery(e.target.value); setOpen(true); }} onFocus={() => setOpen(true)} placeholder={value.length ? "" : placeholder} className="min-w-24 flex-1 bg-transparent text-sm outline-none" />
      </div>
      {open && free.length ? (
        <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-border bg-surface shadow-lg">
          {free.map((s) => (
            <li key={s.value}>
              <button type="button" className="w-full px-3 py-2 text-left text-sm hover:bg-surface-muted" onClick={() => { onChange([...value, s.value]); setQuery(""); }}>
                {s.label}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

export { StyleItemDesk, ImageBox, StylePicker as ChipPicker };
