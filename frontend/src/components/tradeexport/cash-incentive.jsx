"use client";

import { useEffect, useState, useTransition } from "react";
import Link from "next/link";
import { ArrowLeft, Ban, Check, Pencil, RefreshCw, Search, Settings } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { useToastManager } from "@/components/ui/toast";
import { blank, day, Filters, firstError, money, Register } from "@/components/sales-shipment/shared";
import { Box, dateF, ReadGrid, Row, textF, useW, withFinal } from "@/components/tradeexport/operations";

/*
 * Trade & Export → Cash Incentive (AnnotechERP 22/05): the claims list, and
 * "Manage Cash Incentive" in place — an L/C or contract searched by number,
 * what it carries (its invoices, realisation and PRC, the days left to
 * claim), the claim followed from application to the bank's collection, and
 * every claim on the L/C below. The L/C's number stays in the address.
 */

const P = "/tradeexport/ops";
const TYPES = { REGULAR: 4, SPECIAL: 1 };
const PARTS = ["1", "2", "3", "4"];
const BLANK = { cia_type: "REGULAR", claim_part_no: "", claim_sl_no: "", application_date: "", doc_sub_date: "", claim_amount: "", claim_currency: "USD", certificate_no: "", audit_currency: "BDT", audit_cert_date: "", audit_amount: "", collection_date: "", remarks: "", final: false };
const KEYS = Object.keys(BLANK);
const toForm = (c) => Object.fromEntries(KEYS.map((k) => [k, c[k] === null || c[k] === undefined ? BLANK[k] : k === "claim_part_no" ? String(c[k]) : c[k]]));

function Read({ label, value }) {
  return (
    <Row label={label}>
      <Input value={value ?? ""} disabled readOnly aria-label={label} />
    </Row>
  );
}

function Manage({ w, opt, canManage, act, start, claimId, onBack }) {
  const toastManager = useToastManager();
  const [text, setText] = useState(start ?? "");
  const [credit, setCredit] = useState(null);
  const [missing, setMissing] = useState("");
  const [form, setForm] = useState(BLANK);
  const [editing, setEditing] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const address = (no) => {
    const url = new URL(window.location.href);
    if (no) url.searchParams.set("lc", no);
    else url.searchParams.delete("lc");
    window.history.replaceState(null, "", url);
  };
  const find = (q, pick) =>
    startTransition(async () => {
      const res = await act.read(`${P}/cash-incentives/credit?lc=${encodeURIComponent(q)}`);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setCredit(res.data);
      setMissing(res.data ? "" : q);
      setEditing(null);
      setForm(BLANK);
      address(res.data?.lc_no ?? null);
      const hit = pick && res.data?.claims.find((c) => c.id === pick);
      if (hit) {
        setEditing(hit);
        setForm(toForm(hit));
      }
    });
  useEffect(() => {
    if (start) find(start, claimId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const save = () => {
    if (!credit) return toastManager.add({ title: w("ci_search_first"), type: "warning" });
    startTransition(async () => {
      const body = { ...Object.fromEntries(KEYS.map((k) => [k, blank(form[k])])), final: Boolean(form.final), ...(editing ? {} : { lc_id: credit.id }) };
      const res = await act.write(editing ? "PUT" : "POST", editing ? `${P}/cash-incentives/${editing.id}` : `${P}/cash-incentives`, body);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("saved"), type: "success" });
      setCredit(res.data);
      setEditing(null);
      setForm({ ...BLANK, claim_currency: form.claim_currency });
    });
  };
  const locked = !canManage || Boolean(editing?.locked);
  const dis = locked || !credit;
  const sel = (k, label, options, req) => (
    <Row label={label} req={req}>
      <Select value={form[k] ?? ""} disabled={dis} onValueChange={(v) => set(k, v ?? "")} options={options} placeholder={w("select")} aria-label={label} />
    </Row>
  );
  const inp = (k, label, type = "text", req) => (
    <Row label={label} req={req}>
      <Input type={type} step={type === "number" ? "any" : undefined} value={form[k] ?? ""} disabled={dis} onChange={(e) => set(k, e.target.value)} aria-label={label} />
    </Row>
  );
  const c = credit;
  return (
    <div className="flex flex-col gap-4">
      <Box legend={w("box.search_by")} grid={false}>
        <form
          className="max-w-3xl"
          onSubmit={(e) => {
            e.preventDefault();
            if (text.trim()) find(text.trim());
          }}
        >
          <Row label={w("f.lc_sc_no")} req>
            <div className="flex gap-2">
              <Input value={text} onChange={(e) => setText(e.target.value)} placeholder={w("search_dots")} maxLength={80} aria-label={w("f.lc_sc_no")} />
              <Button type="submit" variant="outline" loading={pending}>
                <Search /> {w("od.f.search")}
              </Button>
            </div>
          </Row>
        </form>
        {missing ? <p className="mt-2 text-sm text-danger">{w("ci_no_credit", { q: missing })}</p> : null}
      </Box>
      <Box legend={w("box.lc_sc_info")}>
        <Read label={w("f.lc_sc_no")} value={c?.lc_no} />
        <Read label={w("f.lc_issue_date")} value={c ? day(c.issue_date) : ""} />
        <Read label={w("f.beneficiary")} value={c?.beneficiary} />
        <Read label={w("f.applicant")} value={c?.applicant} />
        <Read label={w("f.destination")} value={c?.destination} />
        <Read label={w("f.lc_value")} value={c ? money(c.lc_value) : ""} />
        <Read label={w("f.bank")} value={c?.bank} />
      </Box>
      <Box legend={w("box.invoice_realization")}>
        <Read label={w("f.invoice_total", { cur: c?.currency ?? "" })} value={c ? money(c.invoice_total) : ""} />
        <Read label={w("f.realization_value")} value={c ? money(c.realized) : ""} />
        <Read label={w("f.min_rate")} value={c?.min_rate ?? ""} />
        <Read label={w("f.cia_notification")} value="" />
        <Read label={w("f.realization_pct")} value={c?.realized_pct ?? ""} />
      </Box>
      <Box legend={w("box.prc_info")}>
        <Read label={w("f.prc_id")} value={c?.prc_no} />
        <Read label={w("f.prc_issue_date")} value={c?.prc_date ? day(c.prc_date) : ""} />
        <Row label={w("f.prc_doc")}>
          <div className="flex flex-wrap items-center gap-2">
            {c?.prc_file_id ? (
              <a href={`/api/files/${c.prc_file_id}/download`} target="_blank" rel="noreferrer" className="rounded-md bg-success-subtle px-2.5 py-1 text-xs font-semibold text-success">
                {c.prc_file_name}
              </a>
            ) : (
              <span className="rounded-md bg-danger px-2.5 py-1 text-xs font-semibold text-white">{w("prc_not_found")}</span>
            )}
            <Link href={c ? `/trade-export/export-lc-sales-contract-entry?no=${encodeURIComponent(c.lc_no)}` : "/trade-export/export-lc-sales-contract-entry"} className="inline-flex items-center gap-1.5 rounded-md bg-warning px-2.5 py-1 text-xs font-semibold text-white">
              <Settings className="size-3.5" /> {w("manage_prc")}
            </Link>
          </div>
        </Row>
        <Read label={w("f.latest_realization")} value={c?.latest_realized ? day(c.latest_realized) : ""} />
        <Read label={w("f.prc_entry_date")} value={c?.prc_entry_date ? day(c.prc_entry_date) : ""} />
        <Read label={w("f.claim_countdown")} value={c?.countdown ?? ""} />
      </Box>
      <Box legend={w("box.ci_followup")} grid={false}>
        <div className="flex flex-col gap-4">
          {editing?.locked ? <p className="rounded-lg bg-warning-subtle px-4 py-2 text-sm text-warning">{w("locked_note")}</p> : null}
          <Box legend={w("box.application")}>
            {sel("cia_type", w("f.cia_type"), Object.entries(TYPES).map(([v, r]) => ({ value: v, label: `${w(`cia.${v}`)} (${r})` })), true)}
            {sel("claim_part_no", w("f.claim_part_no"), PARTS.map((v) => ({ value: v, label: v })), true)}
            {inp("claim_sl_no", w("f.application_sl"), "text", true)}
            {inp("application_date", w("f.application_date"), "date", true)}
          </Box>
          <Box legend={w("box.document_submission")}>
            {inp("doc_sub_date", w("f.doc_submit_date"), "date")}
            {sel("claim_currency", w("f.claim_currency"), opt.currencies)}
            {inp("claim_amount", w("f.claim_amount"), "number")}
          </Box>
          <Box legend={w("box.audit_info")}>
            {inp("certificate_no", w("f.certificate_no"))}
            {inp("audit_cert_date", w("f.audit_cert_date"), "date")}
            {sel("audit_currency", w("f.audit_currency"), opt.currencies)}
            {inp("audit_amount", w("f.audit_amount"), "number")}
          </Box>
          <Box legend={w("box.bank_collection")}>
            {inp("collection_date", w("f.collection_date"), "date")}
            <Read label={w("f.collection_amount")} value={form.audit_amount} />
            <Row label={w("f.remarks_dot")}>
              <textarea rows={2} maxLength={600} value={form.remarks ?? ""} disabled={dis} onChange={(e) => set("remarks", e.target.value)} aria-label={w("f.remarks_dot")} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm disabled:bg-surface-muted" />
            </Row>
            <Row label={w("f.finalized")}>
              <Checkbox checked={Boolean(form.final)} disabled={dis} onCheckedChange={(v) => set("final", v === true)} aria-label={w("f.finalized")} />
            </Row>
          </Box>
          <div className="flex justify-end gap-2">
            {!locked ? (
              <Button onClick={save} loading={pending} disabled={!credit}>
                <Check /> {editing ? w("update_claim") : w("save_claim")}
              </Button>
            ) : null}
            {editing ? (
              <Button
                variant="outline"
                disabled={pending}
                onClick={() => {
                  setEditing(null);
                  setForm(BLANK);
                }}
              >
                <Ban /> {w("cancel")}
              </Button>
            ) : null}
          </div>
        </div>
      </Box>
      <Box legend={w("box.ci_data")} grid={false}>
        <ReadGrid
          w={w}
          empty={w("no_data")}
          rows={c?.claims ?? []}
          cols={[
            { key: "sl", label: w("col.sl"), render: (r) => (c.claims.indexOf(r) + 1).toString() },
            { key: "cia_type", label: w("f.cia_type"), render: (r) => `${w(`cia.${r.cia_type}`)} (${r.cia_rate})` },
            { key: "claim_part_no", label: w("col.part_no") },
            { key: "application_date", label: w("f.application_date"), render: (r) => day(r.application_date) },
            { key: "claim_sl_no", label: w("col.application_sl") },
            { key: "doc_sub_date", label: w("col.doc_submit_date"), render: (r) => (r.doc_sub_date ? day(r.doc_sub_date) : "—") },
            { key: "certificate_no", label: w("f.certificate_no") },
            { key: "audit_cert_date", label: w("f.audit_cert_date"), render: (r) => (r.audit_cert_date ? day(r.audit_cert_date) : "—") },
            { key: "collection_date", label: w("f.collection_date"), render: (r) => (r.collection_date ? day(r.collection_date) : "—") },
            { key: "remarks", label: w("col.remarks") },
            { key: "final", label: w("f.finalized"), render: (r) => w(`yn.${r.final ? "YES" : "NO"}`) },
            { key: "claim_amount", label: w("f.claim_amount"), right: true, render: (r) => money(r.claim_amount) },
            { key: "audit_amount", label: w("f.audit_amount"), right: true, render: (r) => money(r.audit_amount) },
            {
              key: "act",
              label: w("col.action"),
              render: (r) => (
                <Button
                  size="sm"
                  variant="ghost"
                  aria-label={w("edit_claim", { no: r.claim_sl_no })}
                  onClick={() => {
                    setEditing(r);
                    setForm(toForm(r));
                  }}
                >
                  <Pencil />
                </Button>
              ),
            },
          ]}
        />
        {c ? <p className="mt-2 text-right text-sm font-semibold">{w("ci_total", { claim: money(c.claim_total), audit: money(c.audit_total) })}</p> : null}
      </Box>
      <div className="flex rounded-lg border border-border bg-surface p-4">
        <Button
          variant="outline"
          disabled={pending}
          onClick={() => {
            address(null);
            onBack();
          }}
        >
          <ArrowLeft /> {w("back")}
        </Button>
      </div>
    </div>
  );
}

function CashIncentiveDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const [rows, setRows] = useState(initial);
  const [filter, setFilter] = useState({});
  const [manage, setManage] = useState(query?.lc ? { lc: query.lc } : null);
  const reload = () => act.read(`${P}/cash-incentives`).then((r) => r?.status === "success" && setRows(r.data));
  if (manage)
    return (
      <Manage
        key={`${manage.lc ?? ""}-${manage.claim ?? ""}`}
        w={w}
        opt={opt}
        canManage={canManage}
        act={act}
        start={manage.lc}
        claimId={manage.claim}
        onBack={() => {
          setManage(null);
          reload();
        }}
      />
    );
  const filters = [textF("lc", w("f.lc_sc_no_dot"), (r) => r.lc_no), textF("sl", w("f.claim_sl_dot"), (r) => r.claim_sl_no), dateF("from", w("f.from"), (r) => r.application_date, "from"), dateF("to", w("f.to"), (r) => r.application_date, "to")];
  const shown = withFinal(w, rows)
    .map((r) => ({ ...r, cia_label: `${w(`cia.${r.cia_type}`)} (${r.cia_rate})` }))
    .filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  return (
    <>
      <Filters
        w={w}
        value={filter}
        onChange={setFilter}
        fields={filters}
        extra={
          <>
            {canManage ? (
              <Button type="button" variant="outline" onClick={() => setManage({})}>
                <Settings /> {w("manage_ci")}
              </Button>
            ) : null}
            <Button type="button" variant="outline" onClick={reload}>
              <RefreshCw /> {w("refresh")}
            </Button>
          </>
        }
      />
      <Register
        w={w}
        rows={shown}
        refKey="claim_sl_no"
        exportName="cash-incentive-claims"
        onDone={reload}
        emptyTitle={w("ci_empty")}
        emptyText=""
        actions={[{ key: "open", label: w("open"), open: (r) => setManage({ lc: r.lc_no, claim: r.id }) }]}
        columns={[
          { key: "final_label", label: w("col.finalized"), type: "badge" },
          { key: "lc_no", label: w("f.lc_sc_no_dot"), type: "mono" },
          { key: "cia_label", label: w("f.cia_type") },
          { key: "claim_sl_no", label: w("col.claim_sl") },
          { key: "application_date", label: w("col.claim_sub_date"), type: "date" },
          { key: "doc_sub_date", label: w("col.doc_sub_date"), type: "date" },
          { key: "audit_cert_date", label: w("col.audit_cert_date_short"), type: "date" },
          { key: "collection_date", label: w("col.close_date"), type: "date" },
          { key: "remarks", label: w("col.remarks") },
          { key: "audit_amount", label: w("col.audit_amount_short"), type: "number", align: "right" },
        ]}
      />
    </>
  );
}

export { CashIncentiveDesk };
