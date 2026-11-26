"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { ArrowLeft, ArrowRight, ChevronDown, Download, Pencil, Plus, RotateCcw, Save, Search, Send, Trash2, Upload, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Dropdown } from "@/components/ui/dropdown";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ampm } from "@/lib/clock";

/*
 * Settings → Device Integration (AnnotechERP 03/01), as Brickland lays the
 * four screens out:
 *
 * - Attendance Devices: the register's numbers, the terminals (status, last
 *   contact, last punch, users / fingers / logs, posting to HR, last result)
 *   with their actions, and Register Device in two tabs — Device and
 *   Attendance.
 * - Device Punch Log: Punch Search (dates, device, User ID / employee code,
 *   employee match), the punches, Post Pending to HR.
 * - Device Enrolled Users: User Search (device, match) and the users each
 *   terminal reported.
 * - Device Command Queue: the queue, and Send Command.
 *
 * Tabs switch in place; nothing reloads.
 */

const card = "rounded-xl border border-border bg-surface";
const PAGES = [10, 25, 50, 100];
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
/** A moment in Dhaka time, the same on the server and in the browser; a time without an offset is already Dhaka's. */
const at = (iso) => new Date(/(Z|[+-]\d\d:?\d\d)$/.test(iso) ? iso : `${iso.replace(" ", "T")}+06:00`);
const fmt = (iso) => (iso ? at(iso).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—");

function useWords() {
  const t = useT("settings");
  return (k, p) => t(`dev.${k}`, p);
}

/** The time once the page is in the browser — relative times are drawn only then, so the server's render and the browser's agree. */
function useNow() {
  const [now, setNow] = useState(null);
  useEffect(() => {
    setNow(Date.now());
    const t = setInterval(() => setNow(Date.now()), 30000);
    return () => clearInterval(t);
  }, []);
  return now;
}

/** "7 s ago" — how long since a moment; the date itself until the browser has the time. */
function ago(w, iso, now) {
  if (!iso) return null;
  if (!now) return fmt(iso);
  const s = Math.max(0, Math.round((now - at(iso).getTime()) / 1000));
  if (s < 60) return w("ago.s", { n: s });
  if (s < 3600) return w("ago.m", { n: Math.round(s / 60) });
  if (s < 86400) return w("ago.h", { n: Math.round(s / 3600) });
  return w("ago.d", { n: Math.round(s / 86400) });
}

const fail = (toast, res) => toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "", type: "danger" });

function Tabs({ tabs, active, onPick }) {
  return (
    <div className="flex flex-wrap gap-1 border-b border-border" role="tablist">
      {tabs.map(([k, label, count, Icon]) => (
        <button key={k} type="button" role="tab" aria-selected={active === k} onClick={() => onPick(k)} className={cn("-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold", active === k ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
          {Icon ? <Icon className="size-4" /> : null}
          {label}
          {count !== undefined ? <span className="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] tabular-nums">{count}</span> : null}
        </button>
      ))}
    </div>
  );
}

function Tiles({ items }) {
  const tone = { good: "text-success", warning: "text-warning", info: "text-brand" };
  return (
    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
      {items.map(([label, value, t]) => (
        <div key={label} className={cn(card, "px-4 py-3")}>
          <p className="text-xs font-medium text-foreground-muted">{label}</p>
          <p className={cn("mt-1 text-xl font-semibold tabular-nums", tone[t] ?? "text-foreground")}>{value}</p>
        </div>
      ))}
    </div>
  );
}

function Field({ label, required, hint, error, wide, children }) {
  return (
    <div className={cn("flex min-w-0 flex-col gap-1.5", wide && "md:col-span-2 xl:col-span-3")}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {error ? <span className="text-xs text-danger">{error}</span> : hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </div>
  );
}

/**
 * The register grid the four screens share: a search box, Export, a filter
 * under every column where asked, an action column, pages of 10 / 25 / 50 / 100.
 * A column is `{ key, label, read(row), render?(row), mono?, right? }`.
 */
function Grid({ w, rows, columns, rowKey, exportName, colFilters = false, actions, empty, head }) {
  const [needle, setNeedle] = useState("");
  const [cols, setCols] = useState({});
  const [perPage, setPerPage] = useState(10);
  const [page, setPage] = useState(1);
  const text = (c, r) => String(c.read ? (c.read(r) ?? "") : (ampm(r[c.key]) ?? ""));
  const shown = useMemo(
    () => rows.filter((r) => (!needle || columns.some((c) => text(c, r).toLowerCase().includes(needle.toLowerCase()))) && columns.every((c) => !cols[c.key] || text(c, r).toLowerCase().includes(cols[c.key].toLowerCase()))),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [rows, needle, cols],
  );
  const pages = Math.max(1, Math.ceil(shown.length / perPage));
  const cur = Math.min(page, pages);
  const slice = shown.slice((cur - 1) * perPage, cur * perPage);
  const exportCsv = () => {
    const body = [columns.map((c) => c.label), ...shown.map((r) => columns.map((c) => text(c, r)))].map((l) => l.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([`﻿${body}`], { type: "text/csv;charset=utf-8" }));
    link.download = `${exportName}.csv`;
    link.click();
  };
  return (
    <section className={card}>
      <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
        <div className="relative w-full max-w-sm">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input className="pl-9" type="search" value={needle} onChange={(e) => (setNeedle(e.target.value), setPage(1))} placeholder={w("grid.search", { n: rows.length })} aria-label={w("grid.search", { n: rows.length })} />
        </div>
        <div className="flex gap-2">
          {head}
          <Button variant="outline" onClick={exportCsv} disabled={!shown.length}>
            <Download /> {w("grid.export")}
          </Button>
        </div>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[1100px] text-sm">
          <thead>
            <tr className="bg-brand text-left text-[13px] text-brand-foreground">
              {columns.map((c) => (
                <th key={c.key} className={cn("px-3 py-2.5 font-semibold whitespace-nowrap", c.right && "text-right")}>
                  {c.label}
                </th>
              ))}
              {actions ? <th className="px-3 py-2.5 text-right font-semibold">{w("grid.action")}</th> : null}
            </tr>
            {colFilters ? (
              <tr className="border-b border-border bg-surface-muted">
                {columns.map((c) => (
                  <th key={c.key} className="px-2 py-1.5">
                    <Input className="h-8 text-xs" value={cols[c.key] ?? ""} onChange={(e) => (setCols({ ...cols, [c.key]: e.target.value }), setPage(1))} aria-label={w("grid.filter_by", { col: c.label })} />
                  </th>
                ))}
                {actions ? <th /> : null}
              </tr>
            ) : null}
          </thead>
          <tbody>
            {slice.length ? (
              slice.map((r) => (
                <tr key={r[rowKey]} className="border-b border-border last:border-0 hover:bg-surface-muted">
                  {columns.map((c) => (
                    <td key={c.key} className={cn("px-3 py-2.5 align-top", c.mono && "font-mono text-xs", c.right && "text-right tabular-nums")}>
                      {c.render ? c.render(r) : dash(text(c, r))}
                    </td>
                  ))}
                  {actions ? <td className="px-3 py-2 text-right">{actions(r)}</td> : null}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={columns.length + (actions ? 1 : 0)} className="px-5 py-10 text-center text-sm text-foreground-muted">
                  {empty}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
        <span>{w("grid.count", { n: shown.length })}</span>
        <div className="flex items-center gap-2">
          <span className="whitespace-nowrap">{w("grid.per_page")}</span>
          <Select value={String(perPage)} onValueChange={(v) => (setPerPage(Number(v)), setPage(1))} options={PAGES.map((n) => ({ value: String(n), label: String(n) }))} aria-label={w("grid.per_page")} />
          <Button size="sm" variant="outline" disabled={cur <= 1} onClick={() => setPage(cur - 1)} aria-label={w("grid.previous")}>
            <ArrowLeft />
          </Button>
          <span className="whitespace-nowrap tabular-nums">
            {cur} / {pages}
          </span>
          <Button size="sm" variant="outline" disabled={cur >= pages} onClick={() => setPage(cur + 1)} aria-label={w("grid.next")}>
            <ArrowRight />
          </Button>
        </div>
      </div>
    </section>
  );
}

function Status({ tone, children }) {
  const tones = { good: "bg-success-subtle text-success", warning: "bg-warning-subtle text-warning", danger: "bg-danger-subtle text-danger", neutral: "bg-surface-muted text-foreground-muted" };
  return <span className={cn("rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap", tones[tone] ?? tones.neutral)}>{children}</span>;
}

const deviceStatus = (w, d) => (d.state === "PENDING" ? ["warning", w("state.PENDING")] : d.state === "DISABLED" ? ["neutral", w("state.DISABLED")] : d.online ? ["good", w("online")] : ["danger", w("offline")]);

/* ── Attendance Devices ───────────────────────────────────────────── */

const DEVICE_TAB = ["code", "name", "vendor", "model", "protocol", "serial_no", "ip_address", "port", "comm_key", "poll_minutes", "allowed_ips", "time_zone", "state", "location"];
const ATTENDANCE_TAB = ["user_match", "post_to_hr", "post_daily", "remarks"];
const HINTS = ["code", "serial_no", "ip_address", "comm_key", "allowed_ips", "user_match"];
const PLACEHOLDERS = ["code", "name", "model", "serial_no", "ip_address", "allowed_ips", "location"];
const blankDevice = { code: "", name: "", vendor: "ZKTECO", model: "", protocol: "ADMS_PUSH", serial_no: "", ip_address: "", port: "4370", comm_key: "0", poll_minutes: "5", allowed_ips: "", time_zone: "6", state: "ACTIVE", location: "", user_match: "AUTO", post_to_hr: true, post_daily: true, remarks: "" };

function DeviceForm({ w, initial, act, onSaved, onClose }) {
  const toast = useToastManager();
  const [f, setF] = useState(() => (initial ? Object.fromEntries(Object.keys(blankDevice).map((k) => [k, initial[k] === null || initial[k] === undefined ? "" : typeof initial[k] === "number" ? String(initial[k]) : initial[k]])) : blankDevice));
  const [tab, setTab] = useState("device");
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const set = (k, v) => (setF((x) => ({ ...x, [k]: v })), errors[k] && setErrors((e) => ({ ...e, [k]: undefined })));
  const push = f.protocol === "ADMS_PUSH";
  const choices = {
    vendor: ["ZKTECO", "OTHER"].map((v) => ({ value: v, label: w(`vendor.${v}`) })),
    state: ["ACTIVE", "DISABLED", "PENDING"].map((v) => ({ value: v, label: w(`state.${v}`) })),
    user_match: ["AUTO", "PROXY_NO", "EMPLOYEE_CODE"].map((v) => ({ value: v, label: w(`match_rule.${v}`) })),
  };
  const hidden = (k) => (k === "poll_minutes" && push) || (k === "allowed_ips" && !push);
  const control = (k) => {
    if (hidden(k)) return null;
    const label = w(`f.${k}`);
    const required = ["code", "name", "protocol"].includes(k) || (k === "serial_no" && push) || (k === "ip_address" && !push);
    if (k === "protocol")
      return (
        <Field key={k} label={label} required wide>
          <div className="flex flex-wrap gap-x-6 gap-y-2 rounded-lg border border-border px-4 py-3 text-sm" role="radiogroup" aria-label={label}>
            {["ADMS_PUSH", "TCP_PULL"].map((v) => (
              <label key={v} className="flex items-center gap-2">
                <input type="radio" name="dev-protocol" className="size-4 accent-[var(--color-brand)]" checked={f.protocol === v} onChange={() => set("protocol", v)} />
                {w(`protocol_long.${v}`)}
              </label>
            ))}
          </div>
        </Field>
      );
    if (k === "post_to_hr" || k === "post_daily")
      return (
        <label key={k} className="flex items-center gap-2 text-sm font-semibold">
          <Checkbox checked={Boolean(f[k])} onCheckedChange={(v) => set(k, v === true)} aria-label={label} /> {label}
        </label>
      );
    return (
      <Field key={k} label={label} required={required} error={errors[k]?.[0]} wide={k === "user_match" || k === "remarks"} hint={HINTS.includes(k) ? w(`hint.${k}`) : null}>
        {k in choices ? (
          <Select value={f[k]} onValueChange={(v) => set(k, v ?? "")} options={choices[k]} aria-label={label} />
        ) : k === "remarks" ? (
          <Textarea rows={2} value={f[k]} onChange={(e) => set(k, e.target.value)} aria-label={label} />
        ) : (
          <Input type={["port", "comm_key", "poll_minutes", "time_zone"].includes(k) ? "number" : "text"} step={k === "time_zone" ? "0.5" : undefined} value={f[k]} placeholder={PLACEHOLDERS.includes(k) ? w(`ph.${k}`) : undefined} onChange={(e) => set(k, e.target.value)} aria-label={label} />
        )}
      </Field>
    );
  };
  const save = (then) =>
    start(async () => {
      const body = { ...f, port: Number(f.port || 4370), comm_key: Number(f.comm_key || 0), poll_minutes: Number(f.poll_minutes || 5), time_zone: Number(f.time_zone || 6) };
      for (const k of ["model", "serial_no", "ip_address", "allowed_ips", "location", "remarks"]) if (body[k] === "") body[k] = null;
      const res = initial ? await act("update", initial.code, body) : await act("store", body);
      if (res?.status !== "success") {
        const e = res?.errors ?? {};
        setErrors(e);
        if (Object.keys(e).some((k) => DEVICE_TAB.includes(k))) setTab("device");
        else if (Object.keys(e).length) setTab("attendance");
        return fail(toast, res);
      }
      toast.add({ title: w(initial ? "updated" : "saved", { code: res.data.code }), type: "success" });
      onSaved(then);
    });
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h3 className="text-[15px] font-semibold">{initial ? w("edit_title", { code: initial.code }) : w("new_title")}</h3>
        <p className="mb-4 text-[13px] text-foreground-muted">{w("form_note")}</p>
        <Tabs tabs={[["device", w("tab.device")], ["attendance", w("tab.attendance")]]} active={tab} onPick={setTab} />
        <p className="mt-4 mb-3 text-[13px] text-foreground-muted">{w(`tab_note.${tab}`)}</p>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{(tab === "device" ? DEVICE_TAB : ATTENDANCE_TAB).map(control)}</div>
      </section>
      <div className={cn(card, "flex flex-wrap items-center justify-between gap-2 px-4 py-3")}>
        <div>
          {tab === "device" ? (
            <Button variant="outline" onClick={() => setTab("attendance")}>
              {w("continue")} <ArrowRight />
            </Button>
          ) : (
            <Button variant="outline" onClick={() => setTab("device")}>
              <ArrowLeft /> {w("back")}
            </Button>
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button onClick={() => save("list")} loading={pending}>
            <Save /> {w(initial ? "update" : "save")}
          </Button>
          {!initial ? (
            <Button variant="outline" onClick={() => save("new")} disabled={pending}>
              <Plus /> {w("add_new")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={onClose}>
            <ArrowLeft /> {w("back_to_list")}
          </Button>
        </div>
      </div>
    </div>
  );
}

function DeviceRegister({ initial, canManage, act }) {
  const w = useWords();
  const now = useNow();
  const toast = useToastManager();
  const [data, setData] = useState(initial);
  const [view, setView] = useState("records");
  const [editing, setEditing] = useState(null);
  const [formKey, setFormKey] = useState(0);
  const [asking, setAsking] = useState(null);
  const [pending, start] = useTransition();
  const reload = async () => {
    const res = await act("index");
    if (res?.status === "success") setData(res.data);
  };
  const run = (row, action) =>
    start(async () => {
      const res = await act("action", row.code, action);
      setAsking(null);
      if (res?.status !== "success") return fail(toast, res);
      toast.add({ title: res.data.message, type: "success" });
      reload();
    });
  const remove = (row) =>
    start(async () => {
      const res = await act("destroy", row.code);
      setAsking(null);
      if (res?.status !== "success") return fail(toast, res);
      toast.add({ title: w("deleted", { code: row.code }), type: "success" });
      reload();
    });
  // Brickland's row actions and when each shows.
  const ACTIONS = [
    ["approve", (d) => d.state === "PENDING"],
    ["test", () => true],
    ["reupload", (d) => d.protocol === "ADMS_PUSH", true],
    ["info", (d) => d.protocol === "ADMS_PUSH"],
    ["repost", () => true],
    ["disable", (d) => d.state === "ACTIVE", true, true],
    ["enable", (d) => d.state === "DISABLED"],
    ["reboot", (d) => d.protocol === "ADMS_PUSH", true, true],
  ];
  const s = data.summary;
  const columns = [
    { key: "code", label: w("col.code"), mono: true },
    { key: "name", label: w("col.name") },
    { key: "protocol", label: w("col.protocol"), read: (r) => w(`protocol.${r.protocol}`) },
    { key: "address", label: w("col.address"), read: (r) => (r.protocol === "ADMS_PUSH" ? `SN ${r.serial_no ?? "—"}${r.ip_address ? ` · ${r.ip_address}:${r.port}` : ""}` : `${r.ip_address}:${r.port}`), mono: true },
    { key: "location", label: w("col.location") },
    { key: "contact", label: w("col.contact"), read: (r) => (r.last_contact_at ? `${ago(w, r.last_contact_at, now)} · ${r.last_contact_ip ?? ""}` : w("never")) },
    { key: "last_punch", label: w("col.last_punch"), read: (r) => (r.last_punch ? fmt(r.last_punch) : "—") },
    { key: "counts", label: w("col.counts"), read: (r) => `${r.user_count ?? r.users_reported ?? "—"} / ${r.fp_count ?? "—"} / ${r.log_count ?? "—"}` },
    { key: "hr", label: w("col.hr"), read: (r) => (r.post_to_hr ? w(r.post_daily ? "hr.raw_daily" : "hr.raw") : w("hr.off")) },
    { key: "last_result", label: w("col.message") },
    { key: "status", label: w("col.status"), read: (r) => deviceStatus(w, r)[1], render: (r) => <Status tone={deviceStatus(w, r)[0]}>{deviceStatus(w, r)[1]}</Status> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <Tabs
        tabs={[
          ["records", w("register"), data.rows.length],
          ...(canManage ? [["form", editing ? w("edit_title", { code: editing.code }) : w("register_device"), undefined, editing ? Pencil : Plus]] : []),
        ]}
        active={view}
        onPick={(k) => (k === "form" && view !== "form" ? (setEditing(null), setFormKey((n) => n + 1)) : null, setView(k))}
      />
      {view === "records" ? (
        <>
          <Tiles
            items={[
              [w("tile.devices"), s.devices],
              [w("tile.online"), s.online, "good"],
              [w("tile.awaiting"), s.awaiting],
              [w("tile.punches_today"), s.punches_today.toLocaleString("en-US"), "info"],
              [w("tile.not_in_hr"), s.not_in_hr.toLocaleString("en-US"), s.not_in_hr ? "warning" : undefined],
            ]}
          />
          <Grid
            w={w}
            rows={data.rows}
            columns={columns}
            rowKey="code"
            exportName="attendance-devices"
            empty={w("empty.devices")}
            head={
              canManage ? (
                <Button variant="outline" onClick={() => (setEditing(null), setFormKey((n) => n + 1), setView("form"))}>
                  <Plus /> {w("register_device")}
                </Button>
              ) : null
            }
            actions={
              canManage
                ? (r) => (
                    <Dropdown
                      trigger={
                        <span className="inline-flex items-center gap-1 rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold hover:bg-surface-muted" aria-label={w("actions_for", { code: r.code })}>
                          {w("actions")} <ChevronDown className="size-3.5" />
                        </span>
                      }
                      items={[
                        { label: w("edit"), icon: <Pencil />, onSelect: () => (setEditing(r), setFormKey((n) => n + 1), setView("form")) },
                        ...ACTIONS.filter(([, when]) => when(r)).map(([a, , confirm, danger]) => ({ label: w(`action.${a}`), destructive: danger, onSelect: () => (confirm ? setAsking({ row: r, action: a }) : run(r, a)) })),
                        "separator",
                        { label: w("delete"), icon: <Trash2 />, destructive: true, onSelect: () => setAsking({ row: r, action: "delete" }) },
                      ]}
                    />
                  )
                : null
            }
          />
          <p className="text-xs text-foreground-muted">{w("adms_note", { host: data.adms_url })}</p>
        </>
      ) : (
        <DeviceForm
          key={formKey}
          w={w}
          initial={editing}
          act={act}
          onClose={() => setView("records")}
          onSaved={(then) => {
            reload();
            if (then === "new") {
              setEditing(null);
              setFormKey((n) => n + 1);
            } else setView("records");
          }}
        />
      )}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? w(asking.action === "delete" ? "delete_title" : `action.${asking.action}`, { code: asking.row.code }) : ""}
        description={asking ? w(`confirm.${asking.action}`) : ""}
        confirmLabel={asking ? w(asking.action === "delete" ? "delete" : `action.${asking.action}`) : ""}
        cancelLabel={w("keep")}
        destructive={["delete", "disable", "reboot"].includes(asking?.action)}
        loading={pending}
        onConfirm={() => (asking.action === "delete" ? remove(asking.row) : run(asking.row, asking.action))}
      />
    </div>
  );
}

/* ── Device Punch Log ─────────────────────────────────────────────── */

const STATES = { 0: "in", 1: "out", 2: "break_out", 3: "break_in", 4: "ot_in", 5: "ot_out" };
const VERIFY = { 0: "password", 1: "finger", 2: "card", 3: "password", 4: "card", 15: "face" };

function PunchLog({ initial, options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const blank = { from: "", to: "", device: "", pin: "", match: "" };
  const [q, setQ] = useState(blank);
  const [data, setData] = useState(initial);
  const [asking, setAsking] = useState(false);
  const [pending, start] = useTransition();
  const load = (x = q) =>
    start(async () => {
      const res = await act("punches", Object.fromEntries(Object.entries(x).filter(([, v]) => v)));
      if (res?.status === "success") setData(res.data);
      else fail(toast, res);
    });
  const s = data.summary;
  const columns = [
    { key: "time", label: w("col.punch_time"), read: (r) => fmt(r.time) },
    { key: "device", label: w("col.device"), mono: true },
    { key: "pin", label: w("col.pin"), mono: true },
    { key: "code", label: w("col.emp_code"), mono: true },
    { key: "employee", label: w("col.employee") },
    { key: "state", label: w("col.state"), read: (r) => w(`punch_state.${STATES[r.state] ?? "other"}`) },
    { key: "verify", label: w("col.verify"), read: (r) => w(`verify.${VERIFY[r.verify] ?? "other"}`) },
    { key: "source", label: w("col.via"), read: (r) => w(`via.${r.source}`) },
    { key: "in_hr", label: w("col.in_hr"), read: (r) => w(r.in_hr ? "yes" : "no") },
    { key: "received", label: w("col.received"), read: (r) => fmt(r.received) },
    { key: "match", label: w("col.status"), read: (r) => w(r.matched ? "matched" : "no_employee"), render: (r) => <Status tone={r.matched ? "good" : "warning"}>{w(r.matched ? "matched" : "no_employee")}</Status> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h3 className="flex items-center gap-2 text-[15px] font-semibold">
          <Search className="size-4" /> {w("punch_search")}
        </h3>
        <p className="mb-4 text-[13px] text-foreground-muted">{w("punch_note")}</p>
        <form
          className="grid gap-4 md:grid-cols-3 xl:grid-cols-5"
          onSubmit={(e) => {
            e.preventDefault();
            load();
          }}
        >
          <Field label={w("f.from")}>
            <Input type="date" value={q.from} onChange={(e) => setQ({ ...q, from: e.target.value })} aria-label={w("f.from")} />
          </Field>
          <Field label={w("f.to")}>
            <Input type="date" value={q.to} onChange={(e) => setQ({ ...q, to: e.target.value })} aria-label={w("f.to")} />
          </Field>
          <Field label={w("f.device")}>
            <Select value={q.device} onValueChange={(v) => setQ({ ...q, device: v ?? "" })} options={[{ value: "", label: w("all") }, ...options.devices]} placeholder={w("all")} aria-label={w("f.device")} />
          </Field>
          <Field label={w("f.pin")}>
            <Input value={q.pin} onChange={(e) => setQ({ ...q, pin: e.target.value })} placeholder={w("ph.pin")} aria-label={w("f.pin")} />
          </Field>
          <Field label={w("f.match")}>
            <Select value={q.match} onValueChange={(v) => setQ({ ...q, match: v ?? "" })} options={[{ value: "", label: w("all") }, { value: "matched", label: w("match.matched") }, { value: "unmatched", label: w("match.unmatched") }]} placeholder={w("all")} aria-label={w("f.match")} />
          </Field>
          <div className="flex flex-wrap items-end justify-between gap-2 md:col-span-3 xl:col-span-5">
            <div className="flex gap-2">
              <Button type="submit" loading={pending}>
                <Search /> {w("show")}
              </Button>
              {canManage ? (
                <Button type="button" variant="outline" onClick={() => setAsking(true)}>
                  <Upload /> {w("action.repost")}
                </Button>
              ) : null}
            </div>
            <Button type="button" variant="outline" onClick={() => (setQ(blank), load(blank))}>
              <RotateCcw /> {w("reset")}
            </Button>
          </div>
        </form>
      </section>
      <Tiles
        items={[
          [w("tile.punches"), s.punches.toLocaleString("en-US"), "info"],
          [w("tile.matched"), s.matched.toLocaleString("en-US"), "good"],
          [w("tile.no_employee"), s.unmatched.toLocaleString("en-US"), s.unmatched ? "warning" : undefined],
          [w("tile.posted"), s.posted.toLocaleString("en-US")],
        ]}
      />
      <Grid w={w} rows={data.rows} columns={columns} rowKey="id" exportName="device-punch-log" colFilters empty={w("empty.punches")} />
      <ConfirmDialog
        open={asking}
        onOpenChange={setAsking}
        title={w("action.repost")}
        description={w("confirm.repost_all")}
        confirmLabel={w("action.repost")}
        cancelLabel={w("keep")}
        loading={pending}
        onConfirm={() =>
          start(async () => {
            const res = await act("postPending");
            setAsking(false);
            if (res?.status !== "success") return fail(toast, res);
            toast.add({ title: res.data.message, type: "success" });
            const fresh = await act("punches", Object.fromEntries(Object.entries(q).filter(([, v]) => v)));
            if (fresh?.status === "success") setData(fresh.data);
          })
        }
      />
    </div>
  );
}

/* ── Device Enrolled Users ────────────────────────────────────────── */

const ROLES = { 0: "user", 2: "enroller", 6: "manager", 14: "admin" };

function EnrolledUsers({ initial, options, act }) {
  const w = useWords();
  const toast = useToastManager();
  const blank = { device: "", match: "" };
  const [q, setQ] = useState(blank);
  const [data, setData] = useState(initial);
  const [pending, start] = useTransition();
  const load = (x = q) =>
    start(async () => {
      const res = await act("users", Object.fromEntries(Object.entries(x).filter(([, v]) => v)));
      if (res?.status === "success") setData(res.data);
      else fail(toast, res);
    });
  const columns = [
    { key: "device", label: w("col.device"), mono: true },
    { key: "pin", label: w("col.pin"), mono: true },
    { key: "name", label: w("col.name_on_device") },
    { key: "card", label: w("col.card") },
    { key: "role", label: w("col.role"), read: (r) => w(`role.${ROLES[r.role] ?? "user"}`) },
    { key: "employee", label: w("col.matched_employee") },
    { key: "reported", label: w("col.reported"), read: (r) => fmt(r.reported) },
    { key: "status", label: w("col.status"), read: (r) => w(r.matched ? "matched" : "no_employee"), render: (r) => <Status tone={r.matched ? "good" : "warning"}>{w(r.matched ? "matched" : "no_employee")}</Status> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h3 className="flex items-center gap-2 text-[15px] font-semibold">
          <Search className="size-4" /> {w("user_search")}
        </h3>
        <p className="mb-4 text-[13px] text-foreground-muted">{w("user_note")}</p>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={w("f.device")}>
            <Select value={q.device} onValueChange={(v) => setQ({ ...q, device: v ?? "" })} options={[{ value: "", label: w("all") }, ...options.devices]} placeholder={w("all")} aria-label={w("f.device")} />
          </Field>
          <Field label={w("f.match")}>
            <Select value={q.match} onValueChange={(v) => setQ({ ...q, match: v ?? "" })} options={[{ value: "", label: w("all") }, { value: "matched", label: w("match.matched") }, { value: "unmatched", label: w("match.unmatched") }]} placeholder={w("all")} aria-label={w("f.match")} />
          </Field>
          <div className="flex items-end justify-between gap-2">
            <Button onClick={() => load()} loading={pending}>
              <Search /> {w("show")}
            </Button>
            <Button variant="outline" onClick={() => (setQ(blank), load(blank))}>
              <RotateCcw /> {w("reset")}
            </Button>
          </div>
        </div>
      </section>
      <Tiles
        items={[
          [w("tile.enrolled"), data.summary.enrolled.toLocaleString("en-US"), "info"],
          [w("tile.unmatched"), data.summary.unmatched.toLocaleString("en-US"), data.summary.unmatched ? "warning" : undefined],
        ]}
      />
      <Grid w={w} rows={data.rows} columns={columns} rowKey="id" exportName="device-enrolled-users" colFilters empty={w("empty.users")} />
    </div>
  );
}

/* ── Device Command Queue ─────────────────────────────────────────── */

function CommandQueue({ initial, options, canManage, act }) {
  const w = useWords();
  const toast = useToastManager();
  const [rows, setRows] = useState(initial);
  const [view, setView] = useState("records");
  const [f, setF] = useState({ device: "", command: "" });
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const push = options.devices.filter((d) => d.protocol === "ADMS_PUSH");
  const reload = async () => {
    const res = await act("commands");
    if (res?.status === "success") setRows(res.data);
  };
  const tone = { QUEUED: "neutral", SENT: "warning", DONE: "good", FAILED: "danger", CANCELLED: "neutral" };
  const columns = [
    { key: "id", label: "#", right: true },
    { key: "device", label: w("col.device"), mono: true },
    { key: "label", label: w("col.command") },
    { key: "by", label: w("col.requested_by") },
    { key: "queued", label: w("col.queued"), read: (r) => fmt(r.queued) },
    { key: "delivered", label: w("col.delivered"), read: (r) => (r.delivered ? fmt(r.delivered) : "—") },
    { key: "answered", label: w("col.answered"), read: (r) => (r.answered ? fmt(r.answered) : "—") },
    { key: "return_code", label: w("col.return"), right: true },
    { key: "status", label: w("col.status"), read: (r) => w(`cmd_status.${r.status}`), render: (r) => <Status tone={tone[r.status]}>{w(`cmd_status.${r.status}`)}</Status> },
  ];
  return (
    <div className="flex flex-col gap-4">
      <Tabs tabs={[["records", w("command_queue"), rows.length], ...(canManage ? [["new", w("send_command"), undefined, Send]] : [])]} active={view} onPick={setView} />
      {view === "records" ? (
        <Grid
          w={w}
          rows={rows}
          columns={columns}
          rowKey="id"
          exportName="device-command-queue"
          empty={w("empty.commands")}
          actions={
            canManage
              ? (r) =>
                  r.status === "QUEUED" ? (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() =>
                        start(async () => {
                          const res = await act("cancelCommand", r.id);
                          if (res?.status !== "success") return fail(toast, res);
                          toast.add({ title: w("cancelled", { n: r.id }), type: "success" });
                          reload();
                        })
                      }
                      aria-label={`${w("cancel")} #${r.id}`}
                    >
                      <X /> {w("cancel")}
                    </Button>
                  ) : null
              : null
          }
        />
      ) : (
        <section className={cn(card, "p-5")}>
          <h3 className="text-[15px] font-semibold">{w("new_command")}</h3>
          <p className="mb-4 text-[13px] text-foreground-muted">{w("command_note")}</p>
          <div className="grid gap-4 md:grid-cols-2">
            <Field label={w("f.device")} required error={errors.device?.[0]}>
              <Select value={f.device} onValueChange={(v) => setF({ ...f, device: v ?? "" })} options={push} placeholder={w("select")} aria-label={w("f.device")} />
            </Field>
            <Field label={w("f.command")} required error={errors.command?.[0]}>
              <Select value={f.command} onValueChange={(v) => setF({ ...f, command: v ?? "" })} options={options.commands} placeholder={w("select")} aria-label={w("f.command")} />
            </Field>
          </div>
          <div className="mt-5 flex justify-end gap-2 border-t border-border pt-4">
            <Button variant="outline" onClick={() => (setF({ device: "", command: "" }), setErrors({}))}>
              <RotateCcw /> {w("reset")}
            </Button>
            <Button
              loading={pending}
              onClick={() =>
                start(async () => {
                  const res = await act("sendCommand", f);
                  if (res?.status !== "success") return setErrors(res?.errors ?? {}), fail(toast, res);
                  toast.add({ title: w("queued", { n: res.data.id }), type: "success" });
                  setErrors({});
                  setF({ device: f.device, command: "" });
                  await reload();
                  setView("records");
                })
              }
            >
              <Send /> {w("queue_command")}
            </Button>
          </div>
        </section>
      )}
    </div>
  );
}

export { DeviceRegister, PunchLog, EnrolledUsers, CommandQueue, Grid, Tiles, Tabs, Status, ago, useNow, useWords as useDeviceWords };
