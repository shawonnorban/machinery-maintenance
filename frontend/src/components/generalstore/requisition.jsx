"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import {
    Ban,
    CircleCheck,
    ClipboardList,
    FilePlus2,
    Eye,
    PackageMinus,
    Pencil,
    Plus,
    Printer,
    Save,
    Send,
    Trash2,
    X,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import {
    card,
    day,
    stamp,
    num,
    dash,
    messagesOf,
    today,
    dropError,
    Field,
    printWindow,
} from "./common";

/*
 * General Store → Requisition (AnnotechERP 10/04–10/05), as Brickland lays
 * it out. New Requisition: department, store and reason, the item lines
 * (each showing its code, unit and what the store holds), Save Draft or
 * Submit, and "My Requisitions" — a draft is edited in place, submitted or
 * cancelled. Pending: the Store Manager approves line by line with a note,
 * rejects with a reason, or cancels. Approved: issue against it (Stock
 * Issue opens with it picked). Issued: the record. Every requisition opens
 * in its view and prints. Nothing reloads.
 */

const TONE = {
    DRAFT: "bg-surface-muted text-foreground-muted",
    PENDING: "bg-warning-subtle text-warning",
    APPROVED: "bg-success-subtle text-success",
    PARTIAL: "bg-info-subtle text-info",
    ISSUED: "bg-success-subtle text-success",
    REJECTED: "bg-danger-subtle text-danger",
    CANCELLED: "bg-danger-subtle text-danger",
};

function StatusPill({ status, r }) {
    return (
        <span
            className={cn(
                "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap",
                TONE[status],
            )}
        >
            {r(`status.${status}`)}
        </span>
    );
}

const blankLine = () => ({ gs_item_id: "", quantity: "", remarks: "" });

/** The requisition's view: its facts and lines. */
function ReqView({ req, r }) {
    const facts = [
        ["date", day(req.date)],
        ["department", req.department],
        ["store", req.store],
        ["requested_by", req.requested_by],
        ["reason", req.reason],
        ["submitted", req.submitted_at ? stamp(req.submitted_at) : null],
        [
            "decided_by",
            req.decided_by || req.decided_at
                ? `${req.decided_by ?? "—"} · ${stamp(req.decided_at)}`
                : null,
        ],
        ["approval_note", req.approval_note],
        ["closing_reason", req.closing_reason],
    ];
    return (
        <div className="flex flex-col gap-4">
            <div>
                <StatusPill status={req.status} r={r} />
            </div>
            <dl className="grid grid-cols-[10rem_1fr] gap-x-4 gap-y-1.5 text-sm">
                {facts.map(([k, v]) => (
                    <div key={k} className="contents">
                        <dt className="text-foreground-muted">
                            {r(`view.${k}`)}
                        </dt>
                        <dd className="font-semibold text-foreground">
                            {dash(v)}
                        </dd>
                    </div>
                ))}
            </dl>
            <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[640px] text-sm">
                    <thead className="bg-brand text-brand-foreground">
                        <tr>
                            {[
                                "sl",
                                "item",
                                "unit",
                                "requested",
                                "approved",
                                "issued",
                                "remaining",
                                "in_store",
                            ].map((k) => (
                                <th
                                    key={k}
                                    className={cn(
                                        "px-3 py-2 text-left text-xs font-semibold uppercase",
                                        !["sl", "item", "unit"].includes(k) &&
                                            "text-right",
                                    )}
                                >
                                    {r(`view.col.${k}`)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {req.lines.map((l) => (
                            <tr key={l.id} className="border-t border-border">
                                <td className="px-3 py-2">{l.sl}</td>
                                <td className="px-3 py-2 font-semibold">
                                    {l.item}
                                </td>
                                <td className="px-3 py-2">{dash(l.unit)}</td>
                                {[
                                    "requested",
                                    "approved",
                                    "issued",
                                    "remaining",
                                    "in_store",
                                ].map((k) => (
                                    <td
                                        key={k}
                                        className="px-3 py-2 text-right tabular-nums"
                                    >
                                        {num(l[k])}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/**
 * The requisitions on one screen, with the actions each status allows.
 *
 * @param {{ list: string, rows: object[], perms: object, act: Function, onEdit?: Function, voucher?: object }} props
 */
function ReqTable({ list, rows, setRows, perms, act, onEdit, voucher }) {
    const t = useT("store");
    const r = (k, p) => t(`req.${k}`, p);
    const toastManager = useToastManager();
    const [viewing, setViewing] = useState(null);
    const [approving, setApproving] = useState(null);
    const [rejecting, setRejecting] = useState(null);
    const [cancelling, setCancelling] = useState(null);
    const [errors, setErrors] = useState({});
    const [pending, startTransition] = useTransition();
    const screen = {
        mine: "gs-req-new",
        pending: "gs-req-pending",
        approved: "gs-req-approved",
        issued: "gs-req-issued",
    }[list];

    const fail = (result) => {
        const all = messagesOf(result);
        setErrors(all);
        const first = Object.values(all)[0]?.[0];
        if (first) toastManager.add({ title: first, type: "error" });
    };
    const reload = async () => {
        const res = await act("reqList", list);
        if (res?.status === "success") setRows(res.data.rows);
    };
    const open = (no, then) =>
        startTransition(async () => {
            const res = await act("reqShow", no);
            if (res?.status !== "success") return fail(res);
            then(res.data);
        });
    const print = (req) => {
        act("reqPrinted", req.req_no, { screen });
        printWindow({
            title: t("inv.col.requisition"),
            docNo: req.req_no,
            org: voucher?.name,
            footer: voucher?.footer,
            facts: [
                [r("view.date"), req.date],
                [r("view.department"), dash(req.department)],
                [r("view.store"), dash(req.store)],
                [r("view.requested_by"), dash(req.requested_by)],
                [r("view.reason"), dash(req.reason)],
                [r("col.status"), r(`status.${req.status}`)],
                [r("view.approval_note"), dash(req.approval_note)],
            ],
            columns: [
                "sl",
                "item",
                "unit",
                "requested",
                "approved",
                "issued",
                "remaining",
                "in_store",
            ].map((k) => ({
                label: r(`view.col.${k}`),
                right: !["sl", "item", "unit"].includes(k),
            })),
            rows: req.lines.map((l) => [
                l.sl,
                l.item,
                dash(l.unit),
                num(l.requested),
                num(l.approved),
                num(l.issued),
                num(l.remaining),
                num(l.in_store),
            ]),
        });
    };
    const decide = (op, no, payload, toastKey, done) =>
        startTransition(async () => {
            setErrors({});
            const res = await act(op, no, payload);
            if (res?.status !== "success") return fail(res);
            toastManager.add({
                title: r(`toast.${toastKey}`, { no }),
                type: "success",
            });
            done();
            await reload();
        });

    const actions = (row) => {
        const b = (label, Icon, onClick, extra = {}) => (
            <Button
                key={label}
                size="sm"
                variant={extra.primary ? "primary" : "outline"}
                onClick={onClick}
                aria-label={`${extra.aria ?? r(`btn.${label}`)} ${row.req_no}`}
            >
                <Icon className="size-4" aria-hidden />
                {extra.iconOnly ? null : r(`btn.${label}`)}
            </Button>
        );
        const out = [b("view", Eye, () => open(row.req_no, setViewing))];
        if (row.status !== "DRAFT")
            out.push(b("print", Printer, () => open(row.req_no, print)));
        if (row.status === "DRAFT" && row.mine && list === "mine") {
            out.push(b("edit", Pencil, () => open(row.req_no, onEdit)));
            out.push(
                b("submit", Send, () =>
                    decide(
                        "reqSubmit",
                        row.req_no,
                        undefined,
                        "submitted",
                        () => {},
                    ),
                ),
            );
        }
        if (row.status === "PENDING" && list === "pending" && perms.approve) {
            out.push(
                b(
                    "approve",
                    CircleCheck,
                    () =>
                        open(row.req_no, (req) =>
                            setApproving({
                                req,
                                qty: Object.fromEntries(
                                    req.lines.map((l) => [
                                        l.id,
                                        String(l.requested),
                                    ]),
                                ),
                                note: "",
                            }),
                        ),
                    { primary: true },
                ),
            );
            out.push(
                b(
                    "reject",
                    X,
                    () => (
                        setErrors({}),
                        setRejecting({ no: row.req_no, reason: "" })
                    ),
                ),
            );
        }
        if (["APPROVED", "PARTIAL"].includes(row.status) && perms.issue) {
            out.push(
                <Link
                    key="issue"
                    href={`/general-store/gs-issue?req=${encodeURIComponent(row.req_no)}`}
                    className={buttonVariants({ size: "sm" })}
                >
                    <PackageMinus className="size-4" aria-hidden />
                    {r("btn.issue")}
                </Link>,
            );
        }
        const mayCancel =
            (row.status === "DRAFT" && row.mine) ||
            (row.status === "PENDING" && (row.mine || perms.approve));
        if (mayCancel && (list === "mine" || list === "pending")) {
            out.push(
                b(
                    "cancel_req",
                    Ban,
                    () => (
                        setErrors({}),
                        setCancelling({ no: row.req_no, reason: "" })
                    ),
                    { iconOnly: true, aria: r("btn.cancel_req") },
                ),
            );
        }
        return out;
    };

    return (
        <section className={cn(card, "min-w-0 overflow-hidden")}>
            <div className="p-5">
                <h2 className="text-base font-semibold text-foreground">
                    {r(`list.${list}`, { n: rows.length })}
                </h2>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1080px] text-sm">
                    <thead className="bg-brand text-brand-foreground">
                        <tr>
                            {[
                                "req_no",
                                "date",
                                "department",
                                "store",
                                "items",
                                "requested",
                                "approved",
                                "issued",
                                "requested_by",
                                "status",
                                "",
                            ].map((k) => (
                                <th
                                    key={k}
                                    className={cn(
                                        "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                                        [
                                            "requested",
                                            "approved",
                                            "issued",
                                        ].includes(k) && "text-right",
                                    )}
                                >
                                    {k ? r(`col.${k}`) : ""}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={11}
                                    className="px-3 py-10 text-center text-foreground-muted"
                                >
                                    {r("list.empty")}
                                </td>
                            </tr>
                        ) : null}
                        {rows.map((row) => (
                            <tr
                                key={row.req_no}
                                className="border-t border-border align-top"
                            >
                                <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                                    {row.req_no}
                                </td>
                                <td className="px-3 py-2.5 whitespace-nowrap">
                                    {day(row.date)}
                                </td>
                                <td className="px-3 py-2.5">
                                    {dash(row.department)}
                                </td>
                                <td className="px-3 py-2.5">
                                    {dash(row.store)}
                                </td>
                                <td className="px-3 py-2.5">
                                    {dash(row.items)}
                                </td>
                                <td className="px-3 py-2.5 text-right tabular-nums">
                                    {num(row.requested)}
                                </td>
                                <td className="px-3 py-2.5 text-right tabular-nums">
                                    {num(row.approved)}
                                </td>
                                <td className="px-3 py-2.5 text-right tabular-nums">
                                    {num(row.issued)}
                                </td>
                                <td className="px-3 py-2.5">
                                    {dash(row.requested_by)}
                                </td>
                                <td className="px-3 py-2.5">
                                    <StatusPill status={row.status} r={r} />
                                </td>
                                <td className="px-3 py-2.5">
                                    <div className="flex flex-wrap justify-end gap-1">
                                        {actions(row)}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {viewing ? (
                <Modal
                    open
                    onOpenChange={(o) => !o && setViewing(null)}
                    title={r("view.title", { no: viewing.req_no })}
                    className="max-w-3xl"
                    footer={
                        <>
                            {viewing.status !== "DRAFT" ? (
                                <Button
                                    variant="outline"
                                    onClick={() => print(viewing)}
                                >
                                    <Printer className="size-4" aria-hidden />
                                    {r("btn.print")}
                                </Button>
                            ) : null}
                            <Button
                                variant="outline"
                                onClick={() => setViewing(null)}
                            >
                                {r("btn.close")}
                            </Button>
                        </>
                    }
                >
                    <ReqView req={viewing} r={r} />
                </Modal>
            ) : null}

            {approving ? (
                <Modal
                    open
                    onOpenChange={(o) => !o && setApproving(null)}
                    title={r("approve.title", { no: approving.req.req_no })}
                    description={r("approve.sub", {
                        department: approving.req.department,
                        store: approving.req.store,
                        who: approving.req.requested_by ?? "—",
                    })}
                    className="max-w-2xl"
                    footer={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setApproving(null)}
                                disabled={pending}
                            >
                                {r("btn.cancel")}
                            </Button>
                            <Button
                                loading={pending}
                                onClick={() =>
                                    decide(
                                        "reqApprove",
                                        approving.req.req_no,
                                        {
                                            note: approving.note || null,
                                            lines: approving.req.lines.map(
                                                (l) => ({
                                                    line_id: l.id,
                                                    approved_qty:
                                                        approving.qty[l.id] ===
                                                        ""
                                                            ? 0
                                                            : approving.qty[
                                                                  l.id
                                                              ],
                                                }),
                                            ),
                                        },
                                        "approved",
                                        () => setApproving(null),
                                    )
                                }
                            >
                                {r("btn.approve")}
                            </Button>
                        </>
                    }
                >
                    <div className="flex flex-col gap-3">
                        <p className="text-[13px] font-semibold text-foreground">
                            {r("approve.lines")}
                        </p>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs font-semibold uppercase text-foreground-muted">
                                    {[
                                        "item",
                                        "requested",
                                        "in_store",
                                        "approved",
                                    ].map((k) => (
                                        <th
                                            key={k}
                                            className={cn(
                                                "px-2 py-1.5",
                                                k !== "item" && "text-right",
                                            )}
                                        >
                                            {r(`approve.col.${k}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {approving.req.lines.map((l, i) => (
                                    <tr
                                        key={l.id}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-2 py-2 font-semibold">
                                            {l.item}
                                        </td>
                                        <td className="px-2 py-2 text-right tabular-nums">
                                            {num(l.requested)} {l.unit}
                                        </td>
                                        <td className="px-2 py-2 text-right tabular-nums">
                                            {num(l.in_store)} {l.unit}
                                        </td>
                                        <td className="px-2 py-1.5">
                                            <div className="relative ml-auto w-36">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    max={l.requested}
                                                    value={approving.qty[l.id]}
                                                    onChange={(ev) => {
                                                        const v =
                                                            ev.target.value;
                                                        setApproving((a) => ({
                                                            ...a,
                                                            qty: {
                                                                ...a.qty,
                                                                [l.id]: v,
                                                            },
                                                        }));
                                                        setErrors((all) =>
                                                            dropError(
                                                                dropError(
                                                                    all,
                                                                    `lines.${i}.approved_qty`,
                                                                ),
                                                                "lines",
                                                            ),
                                                        );
                                                    }}
                                                    aria-label={r(
                                                        "approve.qty_for",
                                                        { item: l.item },
                                                    )}
                                                    className={cn(
                                                        "pr-12 text-right",
                                                        errors[
                                                            `lines.${i}.approved_qty`
                                                        ] && "border-danger",
                                                    )}
                                                />
                                                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">
                                                    {l.unit}
                                                </span>
                                            </div>
                                            {errors[
                                                `lines.${i}.approved_qty`
                                            ] ? (
                                                <p className="mt-1 text-right text-xs text-danger">
                                                    {
                                                        errors[
                                                            `lines.${i}.approved_qty`
                                                        ][0]
                                                    }
                                                </p>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {errors.lines ? (
                            <Alert variant="danger">{errors.lines[0]}</Alert>
                        ) : null}
                        <Field label={r("approve.note")}>
                            <Textarea
                                rows={2}
                                maxLength={2000}
                                value={approving.note}
                                onChange={(ev) =>
                                    setApproving((a) => ({
                                        ...a,
                                        note: ev.target.value,
                                    }))
                                }
                                aria-label={r("approve.note")}
                            />
                        </Field>
                    </div>
                </Modal>
            ) : null}

            {rejecting ? (
                <Modal
                    open
                    onOpenChange={(o) => !o && setRejecting(null)}
                    title={r("reject.title")}
                    description={r("reject.body")}
                    footer={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setRejecting(null)}
                                disabled={pending}
                            >
                                {r("btn.cancel")}
                            </Button>
                            <Button
                                variant="danger"
                                loading={pending}
                                disabled={!rejecting.reason.trim()}
                                onClick={() =>
                                    decide(
                                        "reqReject",
                                        rejecting.no,
                                        { reason: rejecting.reason },
                                        "rejected",
                                        () => setRejecting(null),
                                    )
                                }
                            >
                                {r("btn.reject")}
                            </Button>
                        </>
                    }
                >
                    <Field
                        label={r("reject.reason")}
                        required
                        error={errors.reason?.[0] ?? errors.requisition?.[0]}
                    >
                        <Textarea
                            rows={3}
                            maxLength={2000}
                            value={rejecting.reason}
                            onChange={(ev) =>
                                setRejecting((x) => ({
                                    ...x,
                                    reason: ev.target.value,
                                }))
                            }
                            aria-label={r("reject.reason")}
                        />
                    </Field>
                </Modal>
            ) : null}

            {cancelling ? (
                <Modal
                    open
                    onOpenChange={(o) => !o && setCancelling(null)}
                    title={r("cancel.title")}
                    description={r("cancel.body")}
                    footer={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setCancelling(null)}
                                disabled={pending}
                            >
                                {r("btn.cancel")}
                            </Button>
                            <Button
                                variant="danger"
                                loading={pending}
                                onClick={() =>
                                    decide(
                                        "reqCancel",
                                        cancelling.no,
                                        {
                                            reason: cancelling.reason || null,
                                            screen,
                                        },
                                        "cancelled",
                                        () => setCancelling(null),
                                    )
                                }
                            >
                                {r("btn.cancel_req")}
                            </Button>
                        </>
                    }
                >
                    <Field
                        label={r("cancel.reason")}
                        error={errors.requisition?.[0]}
                    >
                        <Textarea
                            rows={3}
                            maxLength={2000}
                            value={cancelling.reason}
                            onChange={(ev) =>
                                setCancelling((x) => ({
                                    ...x,
                                    reason: ev.target.value,
                                }))
                            }
                            aria-label={r("cancel.reason")}
                        />
                    </Field>
                </Modal>
            ) : null}
        </section>
    );
}

/**
 * New Requisition: the form (new or a draft being edited) and My Requisitions,
 * each on its own tab. The tabs switch in place (no reload, `?tab=mine` kept
 * in the address); Edit on a draft opens it on the form tab, and a saved or
 * submitted requisition is shown on My Requisitions.
 *
 * @param {{ options: object, rows: object[], perms: object, act: Function }} props
 */
function NewRequisition({ options, rows: firstRows, perms, act }) {
    const t = useT("store");
    const r = (k, p) => t(`req.${k}`, p);
    const toastManager = useToastManager();
    const defaultStore =
        options.stores.find((s) => s.is_default)?.value ??
        options.stores[0]?.value ??
        "";
    const blank = () => ({
        req_date: today(),
        gs_department_id: "",
        gs_store_id: defaultStore,
        reason: "",
    });
    const [head, setHead] = useState(blank);
    const [lines, setLines] = useState([blankLine()]);
    const [editing, setEditing] = useState(null);
    const [rows, setRows] = useState(firstRows);
    const [errors, setErrors] = useState({});
    const [pending, startTransition] = useTransition();
    const params = useSearchParams();
    const [tab, setTabState] = useState(
        params.get("tab") === "mine" ? "mine" : "new",
    );
    const setTab = (next) => {
        setTabState(next);
        const url = new URL(window.location.href);
        if (next === "mine") url.searchParams.set("tab", "mine");
        else url.searchParams.delete("tab");
        window.history.replaceState(window.history.state, "", url);
    };
    const items = useMemo(
        () => Object.fromEntries(options.items.map((i) => [i.value, i])),
        [options.items],
    );
    const err = (k) => errors[k]?.[0];

    const setH = (k) => (v) => {
        setHead((all) => ({ ...all, [k]: v ?? "" }));
        setErrors((all) => dropError(all, k));
    };
    const setLine = (i, k) => (v) => {
        setLines((all) =>
            all.map((l, j) => (j === i ? { ...l, [k]: v ?? "" } : l)),
        );
        setErrors((all) =>
            dropError(
                dropError(
                    all,
                    `lines.${i}.${k === "gs_item_id" ? "gs_item_id" : k}`,
                ),
                "lines",
            ),
        );
    };
    const reset = () => {
        setHead(blank());
        setLines([blankLine()]);
        setEditing(null);
        setErrors({});
    };
    const edit = (req) => {
        setEditing(req.req_no);
        setHead({
            req_date: req.date,
            gs_department_id: req.department_id,
            gs_store_id: req.store_id,
            reason: req.reason ?? "",
        });
        setLines(
            req.lines.map((l) => ({
                gs_item_id: l.item_id,
                quantity: String(l.requested),
                remarks: l.remarks ?? "",
            })),
        );
        setErrors({});
        setTab("new");
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    function save(submit) {
        setErrors({});
        const sent = lines
            .map((l, i) => ({ l, i }))
            .filter(
                ({ l }) => l.gs_item_id || String(l.quantity).trim() !== "",
            );
        const payload = {
            ...head,
            reason: head.reason || null,
            submit,
            lines: sent.map(({ l }) => ({
                gs_item_id: l.gs_item_id || null,
                quantity: l.quantity,
                remarks: l.remarks || null,
            })),
        };
        startTransition(async () => {
            const res = editing
                ? await act("reqUpdate", editing, payload)
                : await act("reqSave", payload);
            if (res?.status !== "success") {
                const all = Object.fromEntries(
                    Object.entries(messagesOf(res)).map(([k, v]) => {
                        const m = k.match(/^lines\.(\d+)\.(.+)$/);
                        return [
                            m
                                ? `lines.${sent[Number(m[1])]?.i ?? m[1]}.${m[2]}`
                                : k,
                            v,
                        ];
                    }),
                );
                setErrors(all);
                const first = Object.values(all)[0]?.[0];
                if (first) toastManager.add({ title: first, type: "error" });
                return;
            }
            toastManager.add({
                title: r(submit ? "toast.submitted" : "toast.saved", {
                    no: res.data.req_no,
                }),
                type: "success",
            });
            reset();
            const list = await act("reqList", "mine");
            if (list?.status === "success") setRows(list.data.rows);
            setTab("mine");
        });
    }

    const tabs = [
        {
            key: "new",
            label: r(editing ? "edit" : "new"),
            Icon: editing ? Pencil : FilePlus2,
        },
        {
            key: "mine",
            label: r("tabs.mine"),
            Icon: ClipboardList,
            count: rows.length,
        },
    ];

    return (
        <div className="flex flex-col gap-4">
            <div
                role="tablist"
                className="flex gap-1 overflow-x-auto border-b border-border"
            >
                {tabs.map(({ key, label, Icon, count }) => {
                    const current = key === tab;
                    return (
                        <button
                            key={key}
                            type="button"
                            role="tab"
                            aria-selected={current}
                            onClick={() => setTab(key)}
                            className={cn(
                                "-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors [&_svg]:size-4",
                                current
                                    ? "border-brand text-brand"
                                    : "border-transparent text-foreground-muted hover:border-border-strong hover:text-foreground",
                            )}
                        >
                            <Icon aria-hidden />
                            {label}
                            {count !== undefined ? (
                                <span
                                    className={cn(
                                        "rounded-full px-1.5 py-px text-[11px] font-semibold tabular-nums",
                                        current
                                            ? "bg-brand-subtle text-brand"
                                            : "bg-surface-muted text-foreground-muted",
                                    )}
                                >
                                    {count}
                                </span>
                            ) : null}
                        </button>
                    );
                })}
            </div>
            {tab === "new" ? (
                <section className={cn(card, "flex flex-col gap-4 p-5")}>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-base font-semibold text-foreground">
                                {r(editing ? "edit" : "new")}
                            </h2>
                            <p className="mt-0.5 text-[13px] text-foreground-muted">
                                {r("steps")}
                            </p>
                            {!options.approval ? (
                                <p className="mt-1 text-xs text-info">
                                    {r("approval_off")}
                                </p>
                            ) : null}
                        </div>
                        {editing ? (
                            <Button size="sm" variant="outline" onClick={reset}>
                                <X className="size-4" aria-hidden />
                                {r("stop")}
                            </Button>
                        ) : null}
                    </div>
                    <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Field
                            label={r("f.date")}
                            required
                            error={err("req_date")}
                        >
                            <Input
                                type="date"
                                value={head.req_date}
                                onChange={(ev) =>
                                    setH("req_date")(ev.target.value)
                                }
                                aria-label={r("f.date")}
                            />
                        </Field>
                        <Field
                            label={r("f.department")}
                            required
                            error={err("gs_department_id")}
                        >
                            <Select
                                value={head.gs_department_id}
                                onValueChange={setH("gs_department_id")}
                                options={options.departments}
                                placeholder={r("f.select")}
                                aria-label={r("f.department")}
                            />
                        </Field>
                        <Field
                            label={r("f.store")}
                            required
                            error={err("gs_store_id")}
                        >
                            <Select
                                value={head.gs_store_id}
                                onValueChange={setH("gs_store_id")}
                                options={options.stores.map((s) => ({
                                    value: s.value,
                                    label: s.label,
                                }))}
                                placeholder={r("f.select")}
                                aria-label={r("f.store")}
                            />
                        </Field>
                        <Field label={r("f.reason")}>
                            <Textarea
                                rows={1}
                                value={head.reason}
                                onChange={(ev) =>
                                    setH("reason")(ev.target.value)
                                }
                                aria-label={r("f.reason")}
                            />
                        </Field>
                    </div>
                    <div className="flex flex-col gap-3">
                        {lines.map((l, i) => {
                            const item = items[l.gs_item_id];
                            const inStore = item
                                ? Number(item.stock?.[head.gs_store_id] ?? 0)
                                : 0;
                            return (
                                <div
                                    key={i}
                                    className="grid items-start gap-3 rounded-lg border border-border p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.5fr)_auto]"
                                >
                                    <Field
                                        label={r("f.item", { n: i + 1 })}
                                        required
                                        error={err(`lines.${i}.gs_item_id`)}
                                        hint={
                                            item
                                                ? r("hint", {
                                                      code: item.code,
                                                      unit: item.unit,
                                                      n: num(inStore),
                                                  })
                                                : null
                                        }
                                    >
                                        <Select
                                            value={l.gs_item_id}
                                            onValueChange={setLine(
                                                i,
                                                "gs_item_id",
                                            )}
                                            options={options.items.map((o) => ({
                                                value: o.value,
                                                label: o.label,
                                            }))}
                                            placeholder={r("f.select")}
                                            aria-label={r("f.item", {
                                                n: i + 1,
                                            })}
                                        />
                                    </Field>
                                    <Field
                                        label={r("f.qty")}
                                        required
                                        error={err(`lines.${i}.quantity`)}
                                    >
                                        <div className="relative">
                                            <Input
                                                type="number"
                                                min="0"
                                                step="any"
                                                value={l.quantity}
                                                onChange={(ev) =>
                                                    setLine(
                                                        i,
                                                        "quantity",
                                                    )(ev.target.value)
                                                }
                                                aria-label={`${r("f.qty")} ${i + 1}`}
                                                className="pr-14"
                                            />
                                            {item ? (
                                                <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">
                                                    {item.unit}
                                                </span>
                                            ) : null}
                                        </div>
                                    </Field>
                                    <Field label={r("f.remarks")}>
                                        <Input
                                            value={l.remarks}
                                            onChange={(ev) =>
                                                setLine(
                                                    i,
                                                    "remarks",
                                                )(ev.target.value)
                                            }
                                            aria-label={`${r("f.remarks")} ${i + 1}`}
                                        />
                                    </Field>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="mt-6 size-9"
                                        disabled={lines.length === 1}
                                        onClick={() =>
                                            setLines((all) =>
                                                all.filter((_, j) => j !== i),
                                            )
                                        }
                                        aria-label={`${r("btn.remove")} ${i + 1}`}
                                    >
                                        <Trash2
                                            className="size-4"
                                            aria-hidden
                                        />
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                    {errors.lines ? (
                        <Alert variant="danger">{errors.lines[0]}</Alert>
                    ) : null}
                    {errors.requisition ? (
                        <Alert variant="danger">{errors.requisition[0]}</Alert>
                    ) : null}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setLines((all) => [...all, blankLine()])
                            }
                        >
                            <Plus className="size-4" aria-hidden />
                            {r("btn.add")}
                        </Button>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => save(false)}
                                disabled={pending}
                            >
                                <Save className="size-4" aria-hidden />
                                {r("btn.save_draft")}
                            </Button>
                            <Button
                                onClick={() => save(true)}
                                loading={pending}
                            >
                                <Send className="size-4" aria-hidden />
                                {r("btn.submit")}
                            </Button>
                        </div>
                    </div>
                </section>
            ) : (
                <ReqTable
                    list="mine"
                    rows={rows}
                    setRows={setRows}
                    perms={perms}
                    act={act}
                    onEdit={edit}
                    voucher={options.voucher}
                />
            )}
        </div>
    );
}

/** Pending, Approved or Issued. */
function RequisitionList({ list, rows: firstRows, perms, act, voucher }) {
    const [rows, setRows] = useState(firstRows);
    return (
        <ReqTable
            list={list}
            rows={rows}
            setRows={setRows}
            perms={perms}
            act={act}
            voucher={voucher}
        />
    );
}

export { NewRequisition, RequisitionList };
