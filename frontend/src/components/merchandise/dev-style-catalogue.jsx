"use client";

import { useMemo, useState, useTransition } from "react";
import { usePathname, useRouter } from "next/navigation";
import { ArrowLeft, ArrowRight, Eye, Inbox, Plus, Search, Send } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { DevStyleWizard, ApprovalDialog } from "@/components/merchandise/dev-style-wizard";

const STATUSES = ["DRAFT", "SENT", "APPROVED", "REJECTED", "REVISION"];
const TONE = { DRAFT: "warning", SENT: "info", APPROVED: "success", REJECTED: "danger", REVISION: "warning" };
const DOT = { DRAFT: "bg-warning", SENT: "bg-info", APPROVED: "bg-success", REJECTED: "bg-danger", REVISION: "bg-brand" };
const shortDate = (iso) => (iso ? new Date(iso.length === 10 ? `${iso}T00:00:00` : iso).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "—");

/**
 * Style & Order → Development Style Catalogue (AnnotechERP 06/07), Brickland's
 * screen: New Development Style; the Inbox (styles waiting for an approver),
 * Sent (what the user sent and where each stands) and Quick Stats; the
 * development styles with buyer, season and status filters and View. Inbox
 * and Sent open in place of the list, with Dashboard back; View and New open
 * the style wizard. Nothing here moves the page.
 *
 * `startNew` (`?new=1`, the dashboard's New Style) opens the wizard on a new
 * style; `startStyle` (`?style=<style no>`, a dashboard row's Open) on that one.
 *
 * @param {{ data: object, look: object, canManage: boolean, canApprove: boolean, actions: object, startNew: boolean, startStyle?: ?string }} props
 */
function DevStyleCatalogue({ data, look, canManage, canApprove, actions, startNew, startStyle = null }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.dsc.${k}`, p);
  const router = useRouter();
  const pathname = usePathname();
  const toastManager = useToastManager();
  const [view, setView] = useState("main");
  const [lists, setLists] = useState(data);
  const [query, setQuery] = useState("");
  const [buyer, setBuyer] = useState("");
  const [season, setSeason] = useState("");
  const [status, setStatus] = useState("");
  const [wizard, setWizard] = useState(startStyle ? { styleId: startStyle } : startNew && canManage ? { styleId: null } : null);
  const [move, setMove] = useState(null);
  const [, startLoading] = useTransition();

  function reload() {
    startLoading(async () => {
      const result = await actions.loadDevStyles();
      if (result?.status === "success") setLists(result.data);
      else toastManager.add({ title: result?.message, type: "danger" });
    });
  }

  function closeWizard() {
    setWizard(null);
    // `?new=1` (the dashboard's New Style) has done its work once the wizard closes.
    if (startNew || startStyle) router.replace(pathname, { scroll: false });
    reload();
  }

  const buyers = useMemo(() => [...new Set(lists.rows.map((r) => r.buyer).filter(Boolean))].sort(), [lists.rows]);
  const seasons = useMemo(() => [...new Set(lists.rows.map((r) => r.season).filter(Boolean))].sort(), [lists.rows]);
  const needle = query.trim().toLowerCase();
  const rows = lists.rows.filter(
    (r) =>
      (!needle || `${r.style_no} ${r.buyer ?? ""} ${r.description ?? ""}`.toLowerCase().includes(needle)) &&
      (!buyer || r.buyer === buyer) &&
      (!season || r.season === season) &&
      (!status || r.dev_status === status),
  );
  const th = "px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const select = "h-9 rounded-md border border-border-strong bg-surface px-2 text-sm text-foreground";

  const header = (title, sub, Icon) => (
    <div className="flex flex-wrap items-start justify-between gap-3 p-5 pb-3">
      <div>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Icon className="size-4" /> {title}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{sub}</p>
      </div>
      <Button variant="outline" size="sm" onClick={() => setView("main")}>
        <ArrowLeft /> {m("dashboard")}
      </Button>
    </div>
  );

  return (
    <div className="flex flex-col gap-4">
      {view === "inbox" ? (
        <section className="rounded-lg border border-border bg-surface">
          {header(m("inbox"), m("inbox_sub"), Inbox)}
          <ListSearch value={query} onChange={setQuery} placeholder={m("inbox_search")} />
          <div className="overflow-x-auto">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-brand-foreground">
                  {["style", "buyer", "description", "sent_by", "sent_on"].map((k) => (
                    <th key={k} className={th}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                  <th className={cn(th, "text-right")}>{m("col.action")}</th>
                </tr>
              </thead>
              <tbody>
                {lists.inbox_rows.filter((r) => !needle || `${r.style_no} ${r.buyer ?? ""}`.toLowerCase().includes(needle)).length === 0 ? (
                  <Empty span={6} text={m("inbox_empty")} />
                ) : (
                  lists.inbox_rows
                    .filter((r) => !needle || `${r.style_no} ${r.buyer ?? ""}`.toLowerCase().includes(needle))
                    .map((r) => (
                      <tr key={r.id} className="border-b border-border last:border-0">
                        <td className="px-4 py-2.5 font-semibold text-foreground">{r.style_no}</td>
                        <td className="px-4 py-2.5">{r.buyer}</td>
                        <td className="px-4 py-2.5">{r.description}</td>
                        <td className="px-4 py-2.5">{r.sent_by ?? "—"}</td>
                        <td className="px-4 py-2.5">{shortDate(r.sent_at)}</td>
                        <td className="px-4 py-2.5">
                          <div className="flex justify-end gap-1.5">
                            <Button variant="outline" size="sm" onClick={() => setWizard({ styleId: r.id })}>
                              {m("review_btn")}
                            </Button>
                            {canApprove ? (
                              <>
                                <Button size="sm" className="bg-success text-white hover:bg-success/90" onClick={() => setMove({ move: "approve", row: r })}>
                                  {m("approve_btn")}
                                </Button>
                                <Button variant="outline" size="sm" onClick={() => setMove({ move: "revision", row: r })}>
                                  {m("revision_btn")}
                                </Button>
                                <Button variant="outline" size="sm" className="text-danger" onClick={() => setMove({ move: "reject", row: r })}>
                                  {m("reject_btn")}
                                </Button>
                              </>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      ) : view === "sent" ? (
        <section className="rounded-lg border border-border bg-surface">
          {header(m("sent"), m("sent_sub"), Send)}
          <ListSearch value={query} onChange={setQuery} placeholder={m("inbox_search")} />
          <div className="overflow-x-auto">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-brand-foreground">
                  {["style", "buyer", "description", "sent_on", "status", "note"].map((k) => (
                    <th key={k} className={th}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                  <th className={cn(th, "text-right")}>{m("col.action")}</th>
                </tr>
              </thead>
              <tbody>
                {lists.sent_rows.length === 0 ? (
                  <Empty span={7} text={m("sent_empty")} />
                ) : (
                  lists.sent_rows
                    .filter((r) => !needle || `${r.style_no} ${r.buyer ?? ""}`.toLowerCase().includes(needle))
                    .map((r) => (
                      <tr key={r.id} className="border-b border-border last:border-0">
                        <td className="px-4 py-2.5 font-semibold text-foreground">{r.style_no}</td>
                        <td className="px-4 py-2.5">{r.buyer}</td>
                        <td className="px-4 py-2.5">{r.description}</td>
                        <td className="px-4 py-2.5">{shortDate(r.sent_on)}</td>
                        <td className="px-4 py-2.5">
                          <Badge variant={TONE[r.dev_status]}>{m(`status.${r.dev_status}`)}</Badge>
                        </td>
                        <td className="px-4 py-2.5">{r.approver_note ?? "—"}</td>
                        <td className="px-4 py-2.5 text-right">
                          <Button variant="outline" size="sm" onClick={() => setWizard({ styleId: r.id })}>
                            <Eye /> {m("view")}
                          </Button>
                        </td>
                      </tr>
                    ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-[13px] text-foreground-muted">{m("manage")}</p>
            {canManage ? (
              <Button className="bg-success text-white hover:bg-success/90" onClick={() => setWizard({ styleId: null })}>
                <Plus /> {m("new")}
              </Button>
            ) : null}
          </div>
          <div className="grid gap-4 lg:grid-cols-3">
            <TrayCard icon={Inbox} tone="bg-brand" title={m("inbox")} count={lists.inbox} lines={[m("inbox_l1"), m("inbox_l2"), m("pending", { n: lists.inbox })]} button={m("open_inbox")} onOpen={() => { setQuery(""); setView("inbox"); }} />
            <TrayCard icon={Send} tone="bg-success" title={m("sent")} count={lists.sent} lines={[m("sent_l1"), m("sent_l2"), m("pending", { n: lists.sent_pending })]} button={m("open_sent")} onOpen={() => { setQuery(""); setView("sent"); }} />
            <section className="rounded-lg border border-border bg-surface p-5">
              <h3 className="text-sm font-semibold text-foreground">{m("quick")}</h3>
              <ul className="mt-2 divide-y divide-border text-sm">
                <li className="flex items-center justify-between py-2">
                  <span className="flex items-center gap-2">
                    <span className="size-2 rounded-full bg-brand" /> {m("total")}
                  </span>
                  <b className="tabular-nums">{lists.stats.total}</b>
                </li>
                {STATUSES.map((s) => (
                  <li key={s} className="flex items-center justify-between py-2">
                    <span className="flex items-center gap-2">
                      <span className={cn("size-2 rounded-full", DOT[s])} /> {m(`status.${s}`)}
                    </span>
                    <b className="tabular-nums">{lists.stats[s]}</b>
                  </li>
                ))}
              </ul>
            </section>
          </div>

          <section className="rounded-lg border border-border bg-surface">
            <div className="p-5 pb-3">
              <h2 className="text-base font-semibold text-foreground">{m("list")}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{m("list_sub")}</p>
              <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div className="relative w-full max-w-sm">
                  <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
                  <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-9" />
                </div>
                <div className="flex flex-wrap gap-2">
                  <select className={select} value={buyer} onChange={(e) => setBuyer(e.target.value)} aria-label={m("all_buyers")}>
                    <option value="">{m("all_buyers")}</option>
                    {buyers.map((b) => (
                      <option key={b} value={b}>
                        {b}
                      </option>
                    ))}
                  </select>
                  <select className={select} value={season} onChange={(e) => setSeason(e.target.value)} aria-label={m("all_seasons")}>
                    <option value="">{m("all_seasons")}</option>
                    {seasons.map((s) => (
                      <option key={s} value={s}>
                        {s}
                      </option>
                    ))}
                  </select>
                  <select className={select} value={status} onChange={(e) => setStatus(e.target.value)} aria-label={m("all_statuses")}>
                    <option value="">{m("all_statuses")}</option>
                    {STATUSES.map((s) => (
                      <option key={s} value={s}>
                        {m(`status.${s}`)}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-brand text-brand-foreground">
                    {["style", "buyer", "description", "garment", "season", "status", "updated"].map((k) => (
                      <th key={k} className={th}>
                        {m(`col.${k}`)}
                      </th>
                    ))}
                    <th className={cn(th, "text-right")}>{m("col.action")}</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <Empty span={8} text={m("empty")} />
                  ) : (
                    rows.map((r) => (
                      <tr key={r.id} className="border-b border-border last:border-0">
                        <td className="px-4 py-2.5 font-semibold text-foreground">{r.style_no}</td>
                        <td className="px-4 py-2.5">{r.buyer}</td>
                        <td className="px-4 py-2.5">{r.description}</td>
                        <td className="px-4 py-2.5">{r.garment_type ?? "—"}</td>
                        <td className="px-4 py-2.5">{r.season ?? "—"}</td>
                        <td className="px-4 py-2.5">
                          <Badge variant={TONE[r.dev_status]}>{m(`status.${r.dev_status}`)}</Badge>
                        </td>
                        <td className="px-4 py-2.5">{shortDate(r.updated_at)}</td>
                        <td className="px-4 py-2.5 text-right">
                          <Button size="sm" onClick={() => setWizard({ styleId: r.id })}>
                            <Eye /> {m("view")}
                          </Button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}

      <DevStyleWizard open={Boolean(wizard)} styleId={wizard?.styleId ?? null} look={look} canManage={canManage} canApprove={canApprove} actions={actions} onClose={closeWizard} onChanged={reload} />
      <ApprovalDialog move={move?.move ?? null} style={move?.row ?? { style_no: "" }} styleId={move?.row?.id} act={actions.devStyleAct} onClose={() => setMove(null)} onDone={reload} />
    </div>
  );
}

function TrayCard({ icon: Icon, tone, title, count, lines, button, onOpen }) {
  return (
    <section className="flex flex-col rounded-lg border border-border bg-surface p-5">
      <div className="flex items-start justify-between">
        <span className="flex items-center gap-3">
          <span className={cn("grid size-11 place-items-center rounded-md text-white", tone)}>
            <Icon className="size-5" />
          </span>
          <span className="text-lg font-semibold text-foreground">{title}</span>
        </span>
        <span className="text-3xl font-bold text-brand tabular-nums">{String(count).padStart(2, "0")}</span>
      </div>
      <ul className="mt-4 flex flex-col gap-1 text-sm text-foreground">
        {lines.map((l, i) => (
          <li key={i} className={i === lines.length - 1 ? "font-semibold" : undefined}>
            {l}
          </li>
        ))}
      </ul>
      <Button className="mt-4 w-full" onClick={onOpen}>
        {button} <ArrowRight />
      </Button>
    </section>
  );
}

function ListSearch({ value, onChange, placeholder }) {
  return (
    <div className="relative mx-5 mb-3 max-w-sm">
      <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
      <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-9" />
    </div>
  );
}

function Empty({ span, text }) {
  return (
    <tr>
      <td colSpan={span} className="px-4 py-12 text-center text-foreground-muted">
        {text}
      </td>
    </tr>
  );
}

export { DevStyleCatalogue };
