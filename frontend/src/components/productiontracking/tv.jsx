"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Eye, KeyRound, Link2Off, Pencil, Plus, Trash2, Tv } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { usePt, stamp } from "./common";
import { Field, NativeSelect } from "./plans";
import { TvDisplay } from "./tv-display";

/*
 * Production Tracking → TV Dashboard Show (AnnotechERP 30/04): each TV's saved
 * lines, layout and refresh, and its display link — issued, replaced or
 * revoked. The link is shown once; a TV opens it with no ERP sign-in.
 */

const LAYOUTS = ["AUTO", "1", "2", "3", "4", "6"];

function TvScreens({ screens, lookups, canManage, act }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [link, setLink] = useState(null);
  const [preview, setPreview] = useState(null);
  const [pending, start] = useTransition();
  const run = (fn, done) =>
    start(async () => {
      const res = await fn();
      if (res?.status === "success") {
        done?.(res.data);
        router.refresh();
      } else toast.add({ title: res?.message ?? p("error"), type: "danger" });
    });
  const url = (token) => `${window.location.origin}/tv/${token}`;

  return (
    <>
      <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
        <p className="max-w-3xl text-sm text-foreground">{p("tv.intro")}</p>
        {canManage ? <Button onClick={() => setEditing({})}><Plus /> {p("btn.new_tv")}</Button> : null}
      </div>
      {screens.length === 0 ? <p className="rounded-lg border border-border bg-surface px-5 py-10 text-center text-sm text-foreground-muted">{p("empty.tv")}</p> : null}
      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {screens.map((s) => (
          <section key={s.id} className="rounded-lg border border-border bg-surface p-5">
            <div className="flex items-start justify-between gap-3">
              <div className="flex items-start gap-3">
                <span className="flex size-9 items-center justify-center rounded-md bg-surface-muted"><Tv className="size-4" aria-hidden /></span>
                <div>
                  <p className="font-semibold text-foreground">{s.name}</p>
                  <p className="text-[13px] text-foreground-muted">{p("tv.summary", { section: p(`section.${s.section}`), n: s.lines.length, layout: p(`layout.${s.layout}`), sec: s.refresh_seconds })}</p>
                </div>
              </div>
              <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold", s.active ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted")}>{p(s.active ? "active" : "inactive")}</span>
            </div>
            <div className="mt-3 flex flex-wrap gap-1.5">
              {s.lines.map((l) => <span key={l} className="rounded bg-surface-muted px-2 py-0.5 text-xs font-semibold">{l}</span>)}
            </div>
            <dl className="mt-3 grid grid-cols-2 gap-y-1 text-[13px]">
              <dt className="text-foreground-muted">{p("tv.display_link")}</dt>
              <dd>{s.link ? p("tv.issued", { hint: s.link.hint, at: stamp(s.link.issued_at) }) : p("tv.no_link")}</dd>
              <dt className="text-foreground-muted">{p("tv.last_seen")}</dt>
              <dd>{stamp(s.last_seen_at) || "—"}</dd>
              <dt className="text-foreground-muted">{p("tv.defects")}</dt>
              <dd>{p(s.show_defects ? "tv.shown" : "tv.hidden")}</dd>
              <dt className="text-foreground-muted">{p("tv.updated_by")}</dt>
              <dd>{s.updated_by ?? "—"}{s.updated_at ? ` · ${stamp(s.updated_at)}` : ""}</dd>
            </dl>
            {canManage ? (
              <div className="mt-4 flex flex-wrap gap-2">
                <Button size="sm" variant="outline" loading={pending} onClick={() => run(() => act("previewTv", s.id), (d) => setPreview(d))}><Eye /> {p("btn.preview")}</Button>
                <Button size="sm" variant="outline" onClick={() => setEditing(s)}><Pencil /> {p("btn.edit")}</Button>
                <Button size="sm" loading={pending} onClick={() => run(() => act("issueTv", s.id), (d) => setLink({ name: s.name, url: url(d.token) }))}><KeyRound /> {p(s.link ? "btn.replace_link" : "btn.issue_link")}</Button>
                {s.link ? <Button size="sm" variant="outline" loading={pending} onClick={() => run(() => act("revokeTv", s.id), () => toast.add({ title: p("toast.link_revoked"), type: "success" }))}><Link2Off /> {p("btn.revoke_link")}</Button> : null}
                <Button size="sm" variant="danger" onClick={() => setDeleting(s)}><Trash2 /> {p("btn.delete")}</Button>
              </div>
            ) : null}
          </section>
        ))}
      </div>
      {editing ? <TvForm row={editing.id ? editing : null} lookups={lookups} act={act} onClose={() => setEditing(null)} /> : null}
      <Modal open={Boolean(link)} onOpenChange={() => setLink(null)} title={p("tv.link_title", { name: link?.name ?? "" })} description={p("tv.link_once")}>
        <div className="rounded-sm border border-border bg-surface-muted px-3 py-2 font-mono text-xs break-all">{link?.url}</div>
        <div className="mt-3 flex gap-2">
          <Button size="sm" variant="outline" onClick={() => navigator.clipboard?.writeText(link?.url ?? "")}>{p("btn.copy")}</Button>
          <a href={link?.url} target="_blank" rel="noreferrer" className="text-sm font-semibold text-brand hover:underline">{p("btn.open_tv")}</a>
        </div>
      </Modal>
      <Modal open={Boolean(preview)} onOpenChange={() => setPreview(null)} title={p("tv.preview_title", { name: preview?.name ?? "" })} description={p("tv.preview_hint")} className="max-w-[min(96vw,110rem)]">
        {preview ? <div className="max-h-[75vh] overflow-auto rounded-lg border border-border"><TvDisplay data={preview} embedded /></div> : null}
      </Modal>
      <ConfirmDialog open={Boolean(deleting)} onOpenChange={() => setDeleting(null)} title={p("tv.delete_title", { name: deleting?.name ?? "" })} description={p("tv.delete_hint")} confirmLabel={p("btn.delete")} loading={pending} onConfirm={() => run(() => act("deleteTv", deleting.id), () => setDeleting(null))} />
    </>
  );
}

function TvForm({ row, lookups, act, onClose }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const [v, setV] = useState(() => ({ name: row?.name ?? "", section: row?.section ?? "SEWING", line_ids: row?.line_ids ?? [], layout: row?.layout ?? "AUTO", refresh_seconds: row?.refresh_seconds ?? 30, show_defects: row?.show_defects ?? true, active: row?.active ?? true }));
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const lines = v.section === "FINISHING" ? lookups.finishing_lines : lookups.sewing_lines;
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const toggle = (id) => set("line_ids")(v.line_ids.includes(id) ? v.line_ids.filter((x) => x !== id) : [...v.line_ids, id]);
  const save = () =>
    start(async () => {
      setErrors({});
      const res = await act("saveTv", { ...v, refresh_seconds: Number(v.refresh_seconds) }, row?.id);
      if (res?.status === "success") {
        toast.add({ title: p("toast.tv_saved"), type: "success" });
        onClose();
        router.refresh();
      } else {
        setErrors(res?.errors ?? {});
        toast.add({ title: res?.message ?? p("error"), type: "danger" });
      }
    });
  const err = (k) => errors[k]?.[0];
  return (
    <Modal open onOpenChange={onClose} title={p(row ? "tv.edit_title" : "tv.new_title")} className="max-w-2xl" footer={<><Button variant="outline" onClick={onClose}>{p("btn.close")}</Button><Button loading={pending} onClick={save}>{p("btn.save_screen")}</Button></>}>
      <div className="space-y-3">
        <Field label={p("f.tv_name")} required error={err("name")}>
          <Input value={v.name} onChange={(e) => set("name")(e.target.value)} placeholder={p("f.tv_name_hint")} />
        </Field>
        <fieldset>
          <legend className="mb-1.5 text-[13px] font-semibold text-foreground">{p("f.section")}</legend>
          <div className="flex gap-4 text-sm">
            {["SEWING", "FINISHING"].map((s) => (
              <label key={s} className="inline-flex items-center gap-2">
                <input type="radio" name="tv_section" checked={v.section === s} onChange={() => setV((all) => ({ ...all, section: s, line_ids: [] }))} /> {p(`section.${s}`)}
              </label>
            ))}
          </div>
        </fieldset>
        <fieldset>
          <legend className="mb-1.5 flex w-full items-center justify-between text-[13px] font-semibold text-foreground">
            <span>{p("tv.lines_selected", { n: v.line_ids.length, of: lines.length })} <span className="text-danger">*</span></span>
            <span className="flex gap-2 text-xs">
              <button type="button" className="text-brand" onClick={() => set("line_ids")(lines.map((l) => l.value))}>{p("btn.select_all")}</button>
              <button type="button" className="text-foreground-muted" onClick={() => set("line_ids")([])}>{p("btn.clear")}</button>
            </span>
          </legend>
          <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
            {lines.map((l) => (
              <label key={l.value} className="inline-flex items-center gap-2 rounded border border-border px-2 py-1 text-sm">
                <input type="checkbox" checked={v.line_ids.includes(l.value)} onChange={() => toggle(l.value)} /> {l.label}
              </label>
            ))}
          </div>
          {err("line_ids") ? <p className="mt-1 text-xs text-danger">{err("line_ids")}</p> : null}
        </fieldset>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field label={p("f.layout")}>
            <NativeSelect value={v.layout} onChange={set("layout")} options={LAYOUTS.map((l) => ({ value: l, label: p(`layout.${l}`) }))} />
          </Field>
          <Field label={p("f.tv_refresh")} error={err("refresh_seconds")}>
            <Input type="number" min="15" max="600" value={v.refresh_seconds} onChange={(e) => set("refresh_seconds")(e.target.value)} />
          </Field>
        </div>
        <label className="inline-flex items-center gap-2 text-sm"><input type="checkbox" checked={v.show_defects} onChange={(e) => set("show_defects")(e.target.checked)} /> {p("f.show_defects")}</label>
        <label className="ml-6 inline-flex items-center gap-2 text-sm"><input type="checkbox" checked={v.active} onChange={(e) => set("active")(e.target.checked)} /> {p("active")}</label>
      </div>
    </Modal>
  );
}

export { TvScreens };
