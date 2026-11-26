"use client";

import { useEffect, useState, useTransition } from "react";
import Link from "next/link";
import { ArrowLeft, ArrowRight, Cpu, Download, Fingerprint, Link2, Search, Send, Trash2, Wand2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * HR & Payroll → Employee → Biometric / Fingerprint (AnnotechERP 24/03), as
 * Brickland lays it out: employees, registered, pending, not registered,
 * devices online and the last sync; Device Setup — the terminals — with a way
 * to the device admin; and two tabs:
 *
 * - Fingerprint Registration: search, biometric status, department; tick
 *   employees, then Queue enrolment on a terminal (mapped, PIN = card no.,
 *   Pending until the terminal reports their fingers), Send to all machines,
 *   Send everyone to all machines, Collect from machines, Delete from all
 *   machines; each row's device mapping, unmapped with ×, or mapped by hand.
 * - Employee Mapping: users the terminals hold that no employee is linked to,
 *   with the employee whose card matches; Auto-map by card.
 */

const card = "rounded-xl border border-border bg-surface";
const STATUS = ["REGISTERED", "PENDING", "NOT_REGISTERED"];
/** A moment in Dhaka time, the same on the server and in the browser; a time without an offset is already Dhaka's. */
const at = (iso) => new Date(/(Z|[+-]\d\d:?\d\d)$/.test(iso) ? iso : `${iso.replace(" ", "T")}+06:00`);
const fmt = (iso) => (iso ? at(iso).toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—");

/** The time once the page is in the browser, so the server's render and the browser's agree. */
function useNow() {
  const [now, setNow] = useState(null);
  useEffect(() => {
    setNow(Date.now());
    const t = setInterval(() => setNow(Date.now()), 30000);
    return () => clearInterval(t);
  }, []);
  return now;
}

function ago(w, iso, now) {
  if (!iso) return "—";
  if (!now) return fmt(iso);
  const s = Math.max(0, Math.round((now - at(iso).getTime()) / 1000));
  if (s < 60) return w("ago.s", { n: s });
  if (s < 3600) return w("ago.m", { n: Math.round(s / 60) });
  if (s < 86400) return w("ago.h", { n: Math.round(s / 3600) });
  return w("ago.d", { n: Math.round(s / 86400) });
}

function Pill({ tone, children }) {
  const tones = { good: "bg-success-subtle text-success", warning: "bg-warning-subtle text-warning", danger: "bg-danger-subtle text-danger", neutral: "bg-surface-muted text-foreground-muted" };
  return <span className={cn("inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap", tones[tone] ?? tones.neutral)}>{children}</span>;
}

const statusTone = { REGISTERED: "good", PENDING: "warning", NOT_REGISTERED: "neutral" };

/** Map one employee to a terminal under a PIN — the card number unless told otherwise. */
function MapDialog({ w, row, devices, act, onClose, onDone }) {
  const toast = useToastManager();
  const [device, setDevice] = useState(devices[0]?.value ?? "");
  const [pin, setPin] = useState(row.card_no ?? "");
  const [error, setError] = useState(null);
  const [pending, start] = useTransition();
  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={w("map_title", { code: row.code })} description={w("map_note", { name: row.name })}>
      <div className="flex flex-col gap-4">
        <label className="flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold">{w("f.device")}</span>
          <Select value={device} onValueChange={(v) => setDevice(v ?? "")} options={devices} aria-label={`${w("f.device")} ${row.code}`} />
        </label>
        <label className="flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold">{w("f.pin")}</span>
          <Input value={pin} onChange={(e) => (setPin(e.target.value), setError(null))} aria-label={`${w("f.pin")} ${row.code}`} />
          {error ? <span className="text-xs text-danger">{error}</span> : null}
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={onClose}>
            {w("cancel")}
          </Button>
          <Button
            loading={pending}
            onClick={() =>
              start(async () => {
                const res = await act("bioMap", { employee: row.code, device, pin });
                if (res?.status !== "success") return setError(Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "");
                toast.add({ title: res.data.message, type: "success" });
                onDone();
              })
            }
          >
            <Link2 /> {w("save_mapping")}
          </Button>
        </div>
      </div>
    </Modal>
  );
}

/**
 * Biometric / Fingerprint.
 *
 * @param {{ overview: object, initial: object, unmapped: object, canManage: boolean, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function Biometric({ overview: firstOverview, initial, unmapped: firstUnmapped, canManage, act }) {
  const t = useT("hrpayroll");
  const w = (k, p) => t(`emp.bio.${k}`, p);
  const toast = useToastManager();
  const now = useNow();
  const [overview, setOverview] = useState(firstOverview);
  const [list, setList] = useState(initial);
  const [unmapped, setUnmapped] = useState(firstUnmapped);
  const [tab, setTab] = useState("registration");
  const [q, setQ] = useState({ q: "", status: "", department: "" });
  const [device, setDevice] = useState(firstOverview.enrol_devices[0]?.value ?? "");
  const [ticked, setTicked] = useState(new Set());
  const [mapping, setMapping] = useState(null);
  const [asking, setAsking] = useState(false);
  const [codes, setCodes] = useState({});
  const [pending, start] = useTransition();
  const c = overview.counts;
  const fail = (res) => toast.add({ title: Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? "", type: "danger" });
  const query = (x, page = 1) => Object.fromEntries(Object.entries({ ...x, page }).filter(([, v]) => v));

  const refresh = async (page = list.page) => {
    const [o, l, u] = await Promise.all([act("bioOverview"), act("bioEmployees", query(q, page)), act("bioUnmapped")]);
    if (o?.status === "success") setOverview(o.data);
    if (l?.status === "success") setList(l.data);
    if (u?.status === "success") setUnmapped(u.data);
  };
  const load = (page = 1, x = q) =>
    start(async () => {
      const res = await act("bioEmployees", query(x, page));
      if (res?.status === "success") setList(res.data);
      else fail(res);
    });
  const run = (op, body, after) =>
    start(async () => {
      const res = await act(op, body);
      if (res?.status !== "success") return fail(res);
      toast.add({ title: res.data.message, type: "success" });
      after?.();
      await refresh();
    });

  const pages = Math.max(1, Math.ceil(list.total / list.per_page));
  const allTicked = list.rows.length > 0 && list.rows.every((r) => ticked.has(r.code));
  const tick = (code, on) => setTicked((s) => (on ? new Set([...s, code]) : new Set([...s].filter((x) => x !== code))));
  const picked = [...ticked];

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        {[
          [w("tile.employees"), c.employees],
          [w("tile.registered"), c.registered, "text-success"],
          [w("tile.pending"), c.pending, "text-warning"],
          [w("tile.not_registered"), c.not_registered],
          [w("tile.online"), `${overview.devices_online} / ${overview.devices.length}`, "text-brand"],
          [w("tile.last_sync"), ago(w, overview.last_sync, now), null, overview.last_sync ? fmt(overview.last_sync) : null],
        ].map(([label, value, tone, sub]) => (
          <div key={label} className={cn(card, "px-4 py-3")}>
            <p className="text-xs font-medium text-foreground-muted">{label}</p>
            <p className={cn("mt-1 text-xl font-semibold tabular-nums", tone)}>{value}</p>
            {sub ? <p className="text-[11px] text-foreground-muted">{sub}</p> : null}
          </div>
        ))}
      </div>

      <section className={card}>
        <div className="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
          <div>
            <h3 className="flex items-center gap-2 text-[15px] font-semibold">
              <Cpu className="size-4" /> {w("device_setup")}
            </h3>
            <p className="text-[13px] text-foreground-muted">{w("device_setup_note")}</p>
          </div>
          <Link href="/system-settings/attendance-devices" className={buttonVariants({ variant: "outline", size: "sm" })}>
            {w("open_admin")}
          </Link>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1000px] text-sm">
            <thead>
              <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                {["device", "model", "protocol", "location", "status", "last_seen", "users", "fingers", "mapped"].map((k) => (
                  <th key={k} className={cn("px-3 py-2.5 font-semibold whitespace-nowrap", ["users", "fingers"].includes(k) && "text-right")}>
                    {w(`dcol.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {overview.devices.length ? (
                overview.devices.map((d) => (
                  <tr key={d.code} className="border-b border-border last:border-0">
                    <td className="px-3 py-2.5">
                      <div className="font-semibold">{d.name}</div>
                      <div className="font-mono text-xs text-foreground-muted">{d.code}</div>
                    </td>
                    <td className="px-3 py-2.5">
                      <div>{d.model ?? "—"}</div>
                      <div className="font-mono text-xs text-foreground-muted">{d.serial_no ?? "—"}</div>
                    </td>
                    <td className="px-3 py-2.5">{w(`protocol.${d.protocol}`)}</td>
                    <td className="px-3 py-2.5">{d.location ?? "—"}</td>
                    <td className="px-3 py-2.5">
                      <Pill tone={d.online ? "good" : d.state === "ACTIVE" ? "danger" : "neutral"}>{d.online ? w("online") : d.state === "ACTIVE" ? w("offline") : w(`state.${d.state}`)}</Pill>
                    </td>
                    <td className="px-3 py-2.5 whitespace-nowrap">{ago(w, d.last_seen, now)}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">{d.users}</td>
                    <td className="px-3 py-2.5 text-right tabular-nums">{d.fingers}</td>
                    <td className="px-3 py-2.5 whitespace-nowrap">
                      {d.mapped}
                      {d.unmapped ? <span className="text-warning"> · {w("unmapped_n", { n: d.unmapped })}</span> : null}
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={9} className="px-5 py-8 text-center text-sm text-foreground-muted">
                    {w("no_devices")}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section className={cn(card, "p-5")}>
        <div className="mb-4 flex flex-wrap gap-1 border-b border-border" role="tablist">
          {[
            ["registration", w("tab.registration"), Fingerprint],
            ["mapping", w("tab.mapping"), Link2, unmapped.rows.length],
          ].map(([k, label, Icon, n]) => (
            <button key={k} type="button" role="tab" aria-selected={tab === k} onClick={() => setTab(k)} className={cn("-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold", tab === k ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}>
              <Icon className="size-4" /> {label}
              {n ? <span className="rounded-full bg-warning-subtle px-2 py-0.5 text-[11px] text-warning tabular-nums">{n}</span> : null}
            </button>
          ))}
        </div>

        {tab === "registration" ? (
          <div className="flex flex-col gap-4">
            <form
              className="grid gap-4 md:grid-cols-[2fr_1fr_1fr_auto]"
              onSubmit={(e) => {
                e.preventDefault();
                load(1);
              }}
            >
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold">{w("f.search")}</span>
                <Input value={q.q} onChange={(e) => setQ({ ...q, q: e.target.value })} placeholder={w("ph.search")} aria-label={w("f.search")} />
              </label>
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold">{w("f.status")}</span>
                <Select
                  value={q.status}
                  onValueChange={(v) => {
                    const next = { ...q, status: v ?? "" };
                    setQ(next);
                    load(1, next);
                  }}
                  options={[{ value: "", label: w("all") }, ...STATUS.map((s) => ({ value: s, label: w(`status_n.${s}`, { n: list.counts[{ REGISTERED: "registered", PENDING: "pending", NOT_REGISTERED: "not_registered" }[s]] }) }))]}
                  placeholder={w("all")}
                  aria-label={w("f.status")}
                />
              </label>
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold">{w("f.department")}</span>
                <Select value={q.department} onValueChange={(v) => setQ({ ...q, department: v ?? "" })} options={[{ value: "", label: w("all") }, ...overview.departments]} placeholder={w("all")} aria-label={w("f.department")} />
              </label>
              <div className="flex items-end">
                <Button type="submit" loading={pending}>
                  <Search /> {w("search")}
                </Button>
              </div>
            </form>

            {canManage ? (
              <div className="flex flex-col gap-2 rounded-lg border border-border bg-surface-muted/50 p-4">
                <div className="flex flex-wrap items-end gap-2">
                  <label className="flex min-w-60 flex-col gap-1.5">
                    <span className="text-[13px] font-semibold">{w("f.enrol_on")}</span>
                    <Select value={device} onValueChange={(v) => setDevice(v ?? "")} options={overview.enrol_devices} placeholder={w("select")} aria-label={w("f.enrol_on")} />
                  </label>
                  <Button disabled={!picked.length || !device || pending} onClick={() => run("bioEnrol", { employees: picked, device }, () => setTicked(new Set()))}>
                    <Send /> {w("queue_enrolment")}
                    {picked.length ? ` (${picked.length})` : ""}
                  </Button>
                  <Button variant="outline" disabled={!picked.length || pending} onClick={() => run("bioSend", { employees: picked }, () => setTicked(new Set()))}>
                    <Send /> {w("send_all")}
                  </Button>
                  <Button variant="outline" disabled={pending} onClick={() => run("bioSend", { everyone: true })}>
                    <Send /> {w("send_everyone")}
                  </Button>
                  <Button variant="outline" disabled={pending} onClick={() => run("bioCollect")}>
                    <Download /> {w("collect")}
                  </Button>
                  <Button variant="outline" className="text-danger" disabled={!picked.length || pending} onClick={() => setAsking(true)}>
                    <Trash2 /> {w("delete_all")}
                  </Button>
                </div>
                <p className="max-w-3xl text-[11px] text-foreground-muted">{w("enrol_note")}</p>
              </div>
            ) : null}

            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full min-w-[1100px] text-sm">
                <thead>
                  <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                    {canManage ? (
                      <th className="w-10 px-3 py-2.5">
                        <Checkbox checked={allTicked} onCheckedChange={(v) => list.rows.forEach((r) => tick(r.code, v === true))} aria-label={w("tick_page")} />
                      </th>
                    ) : null}
                    {["employee", "card", "department", "line", "mapping", "status", "registered"].map((k) => (
                      <th key={k} className="px-3 py-2.5 font-semibold whitespace-nowrap">
                        {w(`col.${k}`)}
                      </th>
                    ))}
                    {canManage ? <th className="px-3 py-2.5 text-right font-semibold">{w("col.action")}</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {list.rows.length ? (
                    list.rows.map((r) => (
                      <tr key={r.code} className="border-b border-border last:border-0 hover:bg-surface-muted">
                        {canManage ? (
                          <td className="px-3 py-2.5">
                            <Checkbox checked={ticked.has(r.code)} onCheckedChange={(v) => tick(r.code, v === true)} aria-label={w("tick", { code: r.code })} />
                          </td>
                        ) : null}
                        <td className="px-3 py-2.5">
                          <div className="font-semibold">{r.name}</div>
                          <div className="font-mono text-xs text-foreground-muted">{r.code}</div>
                        </td>
                        <td className="px-3 py-2.5 font-mono text-xs">{r.card_no}</td>
                        <td className="px-3 py-2.5">{r.department ?? "—"}</td>
                        <td className="px-3 py-2.5">{r.line ?? "—"}</td>
                        <td className="px-3 py-2.5">
                          <div className="flex flex-wrap gap-1">
                            {r.maps.length
                              ? r.maps.map((m) => (
                                  <Pill key={m.device} tone={m.status === "REGISTERED" ? "good" : "warning"}>
                                    {m.device} · {m.pin}
                                    {canManage ? (
                                      <button type="button" className="ml-0.5 rounded hover:bg-black/10" onClick={() => run("bioUnmap", { employee: r.code, device: m.device })} aria-label={w("unmap", { code: r.code, device: m.device })}>
                                        <X className="size-3" />
                                      </button>
                                    ) : null}
                                  </Pill>
                                ))
                              : "—"}
                          </div>
                        </td>
                        <td className="px-3 py-2.5">
                          <Pill tone={statusTone[r.status]}>{w(`status.${r.status}`)}</Pill>
                        </td>
                        <td className="px-3 py-2.5 whitespace-nowrap">
                          {r.fingers ? (
                            <>
                              <div>{w("fingers", { n: r.fingers })}</div>
                              <div className="text-xs text-foreground-muted">{fmt(r.registered)}</div>
                            </>
                          ) : (
                            "—"
                          )}
                        </td>
                        {canManage ? (
                          <td className="px-3 py-2 text-right">
                            <Button size="sm" variant="outline" onClick={() => setMapping(r)} disabled={!overview.devices.length} aria-label={w("map_title", { code: r.code })}>
                              <Link2 /> {w("map")}
                            </Button>
                          </td>
                        ) : null}
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan={9} className="px-5 py-10 text-center text-sm text-foreground-muted">
                        {w("empty")}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            <div className="flex items-center justify-between text-[13px] text-foreground-muted">
              <span>{w("page_of", { n: list.total, page: list.page, pages })}</span>
              <div className="flex gap-2">
                <Button size="sm" variant="outline" disabled={list.page <= 1 || pending} onClick={() => load(list.page - 1)}>
                  <ArrowLeft /> {w("previous")}
                </Button>
                <Button size="sm" variant="outline" disabled={list.page >= pages || pending} onClick={() => load(list.page + 1)}>
                  {w("next")} <ArrowRight />
                </Button>
              </div>
            </div>
          </div>
        ) : (
          <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-[13px] text-foreground-muted">{w("mapping_note")}</p>
              {canManage ? (
                <Button variant="outline" size="sm" disabled={!unmapped.suggested || pending} onClick={() => run("bioAutomap")}>
                  <Wand2 /> {w("automap", { n: unmapped.suggested })}
                </Button>
              ) : null}
            </div>
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full min-w-[900px] text-sm">
                <thead>
                  <tr className="bg-brand text-left text-[13px] text-brand-foreground">
                    {["device", "pin", "name_on_device", "card", "matching"].map((k) => (
                      <th key={k} className="px-3 py-2.5 font-semibold whitespace-nowrap">
                        {w(`mcol.${k}`)}
                      </th>
                    ))}
                    {canManage ? <th className="px-3 py-2.5 text-right font-semibold">{w("col.action")}</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {unmapped.rows.length ? (
                    unmapped.rows.map((u) => {
                      const key = `${u.device}|${u.pin}`;
                      const code = u.suggested_code ?? codes[key] ?? "";
                      return (
                        <tr key={key} className="border-b border-border last:border-0">
                          <td className="px-3 py-2.5 font-mono text-xs">{u.device}</td>
                          <td className="px-3 py-2.5 font-mono text-xs">{u.pin}</td>
                          <td className="px-3 py-2.5">{u.name ?? "—"}</td>
                          <td className="px-3 py-2.5 font-mono text-xs">{u.card_no ?? "—"}</td>
                          <td className="px-3 py-2.5">
                            {u.suggested ?? (canManage ? <Input className="h-8 max-w-48 text-xs" value={codes[key] ?? ""} placeholder={w("ph.employee_code")} onChange={(e) => setCodes({ ...codes, [key]: e.target.value })} aria-label={w("employee_for", { pin: u.pin })} /> : "—")}
                          </td>
                          {canManage ? (
                            <td className="px-3 py-2 text-right">
                              <Button size="sm" variant="outline" disabled={!code || pending} onClick={() => run("bioMap", { employee: code.trim(), device: u.device, pin: u.pin })} aria-label={w("map_pin", { pin: u.pin })}>
                                <Link2 /> {w("map")}
                              </Button>
                            </td>
                          ) : null}
                        </tr>
                      );
                    })
                  ) : (
                    <tr>
                      <td colSpan={6} className="px-5 py-10 text-center text-sm text-foreground-muted">
                        {w("all_mapped")}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </section>

      {mapping ? <MapDialog w={w} row={mapping} devices={overview.devices.map((d) => ({ value: d.code, label: `${d.name} (${d.code})` }))} act={act} onClose={() => setMapping(null)} onDone={() => (setMapping(null), refresh())} /> : null}
      <ConfirmDialog
        open={asking}
        onOpenChange={setAsking}
        title={w("delete_title", { n: picked.length })}
        description={w("delete_confirm")}
        confirmLabel={w("delete_all")}
        cancelLabel={w("keep")}
        destructive
        loading={pending}
        onConfirm={() => run("bioDelete", { employees: picked }, () => (setTicked(new Set()), setAsking(false)))}
      />
    </div>
  );
}

export { Biometric };
