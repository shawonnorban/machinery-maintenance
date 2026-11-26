"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { AlertTriangle, Filter, Hourglass, ListChecks, Plus, Search, Siren } from "lucide-react";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { RequestDetail } from "@/components/approval/request-detail";
import { useT } from "@/lib/i18n";

const STATUSES = ["DRAFT", "PENDING", "APPROVED", "REJECTED", "RETURNED", "CANCELLED", "EXPIRED"];

/** The New Request form's fields, from the options the server offers. */
function ticketFields(t, options) {
  const opt = (values, labels) => values.map((v) => ({ value: String(v), label: labels?.[v] ?? v }));
  return [
    {
      name: "request_type_id",
      label: t("center.f.request_type"),
      type: "select",
      required: true,
      options: options.types.map((x) => ({ value: x.id, label: `${x.name} · ${x.module}` })),
    },
    {
      name: "priority",
      label: t("center.f.priority"),
      type: "select",
      required: true,
      options: opt(options.priorities, Object.fromEntries(options.priorities.map((p) => [p, t(`center.priorities.${p}`)]))),
    },
    { name: "title", label: t("center.f.title"), placeholder: t("center.f.title_hint"), span: 3 },
    { name: "reference_type", label: t("center.f.reference_type"), placeholder: t("center.f.reference_type_hint") },
    { name: "reference_no", label: t("center.f.reference_no"), required: true, placeholder: "FB-2026-0418" },
    { name: "buyer_name", label: t("center.f.buyer") },
    { name: "order_no", label: t("center.f.order_no") },
    { name: "style_no", label: t("center.f.style_no") },
    { name: "po_no", label: t("center.f.po_no") },
    { name: "quantity", label: t("center.f.quantity"), type: "number" },
    { name: "unit", label: t("center.f.unit"), type: "select", options: opt(options.units) },
    { name: "amount", label: t("center.f.amount"), type: "number", help: t("center.f.amount_hint") },
    { name: "currency", label: t("center.f.currency"), type: "select", options: opt(options.currencies), default: "USD" },
    {
      name: "request_to_user_id",
      label: t("center.f.request_to"),
      type: "select",
      required: true,
      options: options.users.map((u) => ({ value: u.id, label: u.name })),
      help: t("center.f.request_to_hint"),
    },
    { name: "due_date", label: t("center.f.due_date"), type: "date", help: t("center.f.due_date_hint") },
    { name: "remarks", label: t("center.f.remarks"), type: "textarea" },
  ];
}

/** The ticket as the form holds it. */
function ticketValues(ticket) {
  const out = {};
  for (const [key, value] of Object.entries(ticket)) out[key] = value === null || value === undefined ? "" : String(value);
  return out;
}

/**
 * Brickland's request list (Approval Center, AnnotechERP 04/01–04/02): four
 * counters, the Requests card with New Request, search and filters, the grid,
 * and a request's own panel with its levels, history and decision buttons.
 */
function RequestList({ scope, basePath, rows, stats, options, values, columns, canCreate, saveTicket, loadRequest, loadTicket, decide, cancelTicket, deleteTicket }) {
  const t = useT("approval");
  const router = useRouter();
  const [query, setQuery] = useState(values.q ?? "");
  const [showFilters, setShowFilters] = useState(Boolean(values.type || values.status || values.priority || values.from || values.to));
  const [filters, setFilters] = useState({ type: values.type ?? "", status: values.status ?? "", priority: values.priority ?? "", from: values.from ?? "", to: values.to ?? "" });
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState(null);
  const [openId, setOpenId] = useState(null);

  function go(next) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(next)) if (value) params.set(key, value);
    router.push(`${basePath}${params.size ? `?${params}` : ""}`);
  }

  async function open(row) {
    if (row.status === "DRAFT") {
      const result = await loadTicket(row.ticket_id);
      if (result?.status === "success") setEditing(result.ticket);
      return;
    }
    setOpenId(row.id);
  }

  const statusLabels = Object.fromEntries(STATUSES.map((s) => [s, t(`center.statuses.${s}`)]));
  const typeOptions = [...options.types.map((x) => ({ value: x.id, label: x.name })), ...options.entity_types.map((e) => ({ value: e, label: t(`center.entities.${e}`) }))];
  const counters = [
    { key: "in_list", icon: ListChecks, tone: "bg-success-subtle text-success" },
    { key: "pending", icon: Hourglass, tone: "bg-warning-subtle text-warning" },
    { key: "overdue", icon: AlertTriangle, tone: "bg-danger-subtle text-danger" },
    { key: "urgent_open", icon: Siren, tone: "bg-brand-subtle text-brand" },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {counters.map(({ key, icon: Icon, tone }) => (
          <div key={key} className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4">
            <span className={`flex size-10 items-center justify-center rounded-lg ${tone}`}>
              <Icon className="size-5" />
            </span>
            <span>
              <span className="block text-[13px] text-foreground-muted">{t(`center.stats.${key}`)}</span>
              <span className="block text-xl font-semibold text-foreground tabular-nums">{stats[key] ?? 0}</span>
            </span>
          </div>
        ))}
      </div>

      <Card>
        <CardHeader className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle>{t("center.requests")}</CardTitle>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{t("center.count", { count: rows.length })}</p>
          </div>
          {canCreate ? (
            <Button onClick={() => setCreating(true)}>
              <Plus /> {t("center.new_request")}
            </Button>
          ) : null}
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          <form
            className="flex flex-wrap gap-2"
            onSubmit={(e) => {
              e.preventDefault();
              go({ q: query, ...filters });
            }}
          >
            <div className="relative min-w-[240px] flex-1">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
              <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("center.search_hint")} className="pl-9" />
            </div>
            <Button type="submit">{t("center.search")}</Button>
            <Button type="button" variant="outline" onClick={() => setShowFilters((v) => !v)}>
              <Filter /> {t("center.filters")}
            </Button>
          </form>
          {showFilters ? (
            <div className="grid gap-3 rounded-md border border-border bg-surface-muted/40 p-3 sm:grid-cols-3 lg:grid-cols-6">
              <Select value={filters.type} onValueChange={(v) => setFilters((f) => ({ ...f, type: v }))} options={typeOptions} placeholder={t("center.all_types")} />
              {scope === "all" || scope === "mine" ? (
                <Select
                  value={filters.status}
                  onValueChange={(v) => setFilters((f) => ({ ...f, status: v }))}
                  options={STATUSES.map((s) => ({ value: s, label: statusLabels[s] }))}
                  placeholder={t("center.all_statuses")}
                />
              ) : null}
              <Select
                value={filters.priority}
                onValueChange={(v) => setFilters((f) => ({ ...f, priority: v }))}
                options={options.priorities.map((p) => ({ value: p, label: t(`center.priorities.${p}`) }))}
                placeholder={t("center.all_priorities")}
              />
              <Input type="date" value={filters.from} onChange={(e) => setFilters((f) => ({ ...f, from: e.target.value }))} aria-label={t("center.from")} />
              <Input type="date" value={filters.to} onChange={(e) => setFilters((f) => ({ ...f, to: e.target.value }))} aria-label={t("center.to")} />
              <div className="flex gap-2">
                <Button type="button" onClick={() => go({ q: query, ...filters })}>
                  {t("center.apply")}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    setFilters({ type: "", status: "", priority: "", from: "", to: "" });
                    setQuery("");
                    go({});
                  }}
                >
                  {t("center.clear")}
                </Button>
              </div>
            </div>
          ) : null}
          <RecordsGrid
            exportName={`approval-${scope}`}
            rows={rows}
            columns={columns.map((c) => (c.key === "status" ? { ...c, labels: statusLabels } : c))}
            rowOpen={{ label: t("center.open"), onOpen: open }}
            searchable={false}
          />
        </CardBody>
      </Card>

      {creating || editing ? (
        <Modal
          open
          onOpenChange={(o) => {
            if (!o) {
              setCreating(false);
              setEditing(null);
            }
          }}
          title={editing?.request_no ? t("center.edit_request", { number: editing.request_no }) : t("center.new_request_title")}
          description={t("center.new_request_hint")}
          className="max-w-3xl"
        >
          <EntryForm
            dialog
            fields={ticketFields(t, options)}
            initial={editing ? ticketValues(editing) : null}
            action={saveTicket.bind(null, editing?.id ?? null)}
            submitLabel={t("center.submit_request")}
            secondaryLabel={t("center.save_draft")}
            note={editing?.status === "RETURNED" ? t("center.resubmit_note") : t("center.form_note")}
            onDone={() => {
              setCreating(false);
              setEditing(null);
            }}
          />
          {editing?.status === "DRAFT" ? (
            <div className="mt-3 flex justify-end">
              <Button
                variant="outline"
                onClick={async () => {
                  const result = await deleteTicket(editing.id);
                  if (result?.status === "success") {
                    setEditing(null);
                    router.refresh();
                  }
                }}
              >
                {t("center.delete_draft")}
              </Button>
            </div>
          ) : null}
        </Modal>
      ) : null}

      {openId ? (
        <RequestDetail
          id={openId}
          onClose={() => setOpenId(null)}
          onEdit={async (ticketId) => {
            const result = await loadTicket(ticketId);
            if (result?.status === "success") {
              setOpenId(null);
              setEditing(result.ticket);
            }
          }}
          loadRequest={loadRequest}
          decide={decide}
          cancelTicket={cancelTicket}
          statusLabels={statusLabels}
        />
      ) : null}
    </div>
  );
}

export { RequestList };
