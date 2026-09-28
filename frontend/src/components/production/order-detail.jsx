"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { ItemPicker } from "@/components/procurement/item-picker";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * One buyer order: its styles, what each style consumes, and the cutting
 * programmes cut from it (Master Data spec §6).
 *
 * Everything is on one page because it is one job — a merchandiser types
 * the order, its styles and their bills of materials in a single sitting,
 * and the cutting programmes come off the same numbers.
 */
function OrderDetail({ order, departments, searchItems, actions }) {
  const t = useT("production");
  const tc = useT("common");

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        {order.styles.length === 0 ? <Alert variant="info">{t("no_styles")}</Alert> : null}

        {order.styles.map((style) => (
          <StyleCard
            key={style.id}
            style={style}
            canManage={order.can_manage}
            departments={departments}
            searchItems={searchItems}
            actions={actions}
          />
        ))}

        {order.can_manage ? <AddStyleCard actions={actions} /> : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <Card>
          <CardHeader>
            <CardTitle>{t("order")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <StatusBadge status={order.status} label={t(`statuses.${order.status}`)} />
            </Field>
            <Field label={t("buyer")}>{order.buyer}</Field>
            <Field label={t("order_qty")}>{formatNumber(order.order_qty, { maximumFractionDigits: 2 })}</Field>
            <Field label={t("factory")}>{order.factory?.name ?? "—"}</Field>
            <Field label={t("order_date")}><FormattedDateTime value={order.order_date} mode="date" /></Field>
            <Field label={t("ship_date")}><FormattedDateTime value={order.ship_date} mode="date" /></Field>
            <Field label={t("created")}><FormattedDateTime value={order.created_at} mode="date" /></Field>
          </CardBody>
        </Card>

        {order.notes ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("notes")}</CardTitle>
            </CardHeader>
            <CardBody className="text-sm whitespace-pre-line">{order.notes}</CardBody>
          </Card>
        ) : null}

        {order.can_manage ? <CloseCard order={order} actions={actions} label={tc("close") ?? t("statuses.CLOSED")} /> : null}
      </div>
    </div>
  );
}

function StyleCard({ style, canManage, departments, searchItems, actions }) {
  const t = useT("production");
  const remaining = Number(style.quantity) - Number(style.programmed_qty ?? 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex flex-wrap items-baseline gap-2">
          {style.style_no}
          <span className="text-sm font-normal text-foreground-muted">
            {style.description ?? ""} {style.color ? `· ${style.color}` : ""}
          </span>
        </CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
          <Field label={t("quantity")}>{formatNumber(style.quantity, { maximumFractionDigits: 2 })}</Field>
          <Field label={t("remaining_to_programme")}>{formatNumber(String(remaining), { maximumFractionDigits: 2 })}</Field>
        </div>

        <BomEditor
          style={style}
          canManage={canManage}
          departments={departments}
          searchItems={searchItems}
          saveBom={actions.saveBom}
        />

        {canManage ? <AddProgramRow style={style} remaining={remaining} actions={actions} /> : null}
      </CardBody>
    </Card>
  );
}

/**
 * The bill of materials, saved whole. Editing line by line would leave the
 * requirement calculation reading a half-changed bill mid-edit.
 */
function BomEditor({ style, canManage, departments, searchItems, saveBom }) {
  const t = useT("production");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [editing, setEditing] = useState(false);
  const [rows, setRows] = useState(() => toRows(style.bom));
  const [errors, setErrors] = useState(null);

  function set(index, patch) {
    setRows((prev) => prev.map((row, i) => (i === index ? { ...row, ...patch } : row)));
  }

  function save() {
    startTransition(async () => {
      const result = await saveBom(
        style.id,
        rows
          .filter((row) => row.item && row.consumption_per_unit !== "")
          .map((row) => ({
            item_id: row.item.id,
            uom_id: row.item.uom?.id ?? undefined,
            consumption_per_unit: row.consumption_per_unit,
            wastage_percent: row.wastage_percent || "0",
          })),
      );

      if (result?.status === "success") {
        toastManager.add({ title: t("bom_saved"), type: "success" });
        setEditing(false);
        setErrors(null);
        router.refresh();
      } else if (result?.status === "error") {
        setErrors(result.errors ?? null);
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  if (!editing) {
    return (
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium text-foreground">{t("bom")}</span>
          {canManage ? (
            <Button type="button" size="sm" variant="outline" onClick={() => { setRows(toRows(style.bom)); setEditing(true); }}>
              {style.bom.length === 0 ? t("add_bom_line") : tc("edit")}
            </Button>
          ) : null}
        </div>

        {style.bom.length === 0 ? (
          <p className="text-xs text-foreground-muted">{t("no_bom")}</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs text-foreground-muted">
                <th className="py-1 font-medium">{t("item")}</th>
                <th className="py-1 font-medium">{t("unit")}</th>
                <th className="py-1 text-right font-medium">{t("consumption_per_unit")}</th>
                <th className="py-1 text-right font-medium">{t("wastage_percent")}</th>
              </tr>
            </thead>
            <tbody>
              {style.bom.map((line) => (
                <tr key={line.id} className="border-b border-border/60">
                  <td className="py-1">
                    <div className="text-foreground">{line.item_name}</div>
                    <div className="text-xs text-foreground-muted">{line.item_code}</div>
                  </td>
                  <td className="py-1">{line.uom?.code ?? "—"}</td>
                  <td className="py-1 text-right tabular-nums">{formatNumber(line.consumption_per_unit, { maximumFractionDigits: 4 })}</td>
                  <td className="py-1 text-right tabular-nums">{formatNumber(line.wastage_percent, { maximumFractionDigits: 2 })}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      <span className="text-sm font-medium text-foreground">{t("bom")}</span>
      <p className="text-xs text-foreground-muted">{t("bom_hint")}</p>

      {rows.map((row, index) => (
        <div key={row.rowId} className="grid grid-cols-1 gap-3 border-b border-border/60 pb-3 sm:grid-cols-12">
          <div className="sm:col-span-6">
            <ItemPicker
              value={row.item}
              onChange={(item) => set(index, { item })}
              departments={departments}
              searchItems={searchItems}
              error={errors?.[`lines.${index}.item_id`]?.[0]}
            />
          </div>
          <div className="sm:col-span-3">
            <FormField label={t("consumption_per_unit")} error={errors?.[`lines.${index}.consumption_per_unit`]?.[0]}>
              {(fp) => (
                <Input
                  {...fp}
                  type="number"
                  step="0.0001"
                  min="0"
                  value={row.consumption_per_unit}
                  onChange={(e) => set(index, { consumption_per_unit: e.target.value })}
                />
              )}
            </FormField>
          </div>
          <div className="sm:col-span-2">
            <FormField label={t("wastage_percent")} error={errors?.[`lines.${index}.wastage_percent`]?.[0]}>
              {(fp) => (
                <Input
                  {...fp}
                  type="number"
                  step="0.01"
                  min="0"
                  max="100"
                  value={row.wastage_percent}
                  onChange={(e) => set(index, { wastage_percent: e.target.value })}
                />
              )}
            </FormField>
          </div>
          <div className="flex items-end sm:col-span-1">
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => setRows((prev) => (prev.length > 1 ? prev.filter((_, i) => i !== index) : prev))}
            >
              {t("remove_line")}
            </Button>
          </div>
        </div>
      ))}

      <div className="flex flex-wrap justify-end gap-2">
        <Button type="button" size="sm" variant="outline" onClick={() => setRows((prev) => [...prev, emptyRow()])}>
          {t("add_bom_line")}
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={() => setEditing(false)}>
          {tc("cancel")}
        </Button>
        <Button type="button" size="sm" loading={pending} onClick={save}>
          {t("save_bom")}
        </Button>
      </div>
    </div>
  );
}

function AddProgramRow({ style, remaining, actions }) {
  const t = useT("production");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [quantity, setQuantity] = useState("");
  const [date, setDate] = useState("");

  function add() {
    startTransition(async () => {
      const result = await actions.addProgram(style.id, { program_qty: quantity, cutting_date: date || undefined });

      if (result?.status === "success") {
        toastManager.add({ title: t("program_saved", { program: result.data.program_no }), type: "success" });
        setQuantity("");
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex flex-wrap items-end gap-3 border-t border-border pt-3">
      <div className="w-36">
        <FormField label={t("program_qty")}>
          {(fp) => <Input {...fp} type="number" step="1" min="0" value={quantity} onChange={(e) => setQuantity(e.target.value)} />}
        </FormField>
      </div>
      <div className="w-44">
        <FormField label={t("cutting_date")}>
          {(fp) => <Input {...fp} type="date" value={date} onChange={(e) => setDate(e.target.value)} />}
        </FormField>
      </div>
      <Button type="button" size="sm" loading={pending} disabled={!quantity || remaining <= 0} onClick={add}>
        {t("add_program")}
      </Button>
    </div>
  );
}

function AddStyleCard({ actions }) {
  const t = useT("production");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [styleNo, setStyleNo] = useState("");
  const [description, setDescription] = useState("");
  const [color, setColor] = useState("");
  const [quantity, setQuantity] = useState("");
  const [errors, setErrors] = useState(null);

  function add() {
    startTransition(async () => {
      const result = await actions.addStyle({ style_no: styleNo, description, color, quantity });

      if (result?.status === "success") {
        toastManager.add({ title: t("style_saved", { style: styleNo }), type: "success" });
        setStyleNo("");
        setDescription("");
        setColor("");
        setQuantity("");
        setErrors(null);
        router.refresh();
      } else if (result?.status === "error") {
        setErrors(result.errors ?? null);
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("add_style")}</CardTitle>
      </CardHeader>
      <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <FormField label={t("style_no")} required error={errors?.style_no?.[0]}>
          {(fp) => <Input {...fp} value={styleNo} onChange={(e) => setStyleNo(e.target.value)} maxLength={64} />}
        </FormField>
        <FormField label={t("description")}>
          {(fp) => <Input {...fp} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={500} />}
        </FormField>
        <FormField label={t("color")}>
          {(fp) => <Input {...fp} value={color} onChange={(e) => setColor(e.target.value)} maxLength={64} />}
        </FormField>
        <FormField label={t("quantity")} required error={errors?.quantity?.[0]}>
          {(fp) => <Input {...fp} type="number" step="1" min="0" value={quantity} onChange={(e) => setQuantity(e.target.value)} />}
        </FormField>
        <div className="sm:col-span-4 flex justify-end">
          <Button type="button" size="sm" loading={pending} disabled={!styleNo || !quantity} onClick={add}>
            {t("add_style")}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

function CloseCard({ order, actions }) {
  const t = useT("production");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  function close() {
    startTransition(async () => {
      const result = await actions.setStatus("CLOSED");

      if (result?.status === "success") {
        toastManager.add({ title: t("order_saved", { order: order.order_no }), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardBody className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-xs text-foreground-muted">{t("minimal_master_hint")}</p>
        <Button type="button" size="sm" variant="outline" loading={pending} onClick={close}>
          {t("statuses.CLOSED")}
        </Button>
      </CardBody>
    </Card>
  );
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs text-foreground-muted">{label}</span>
      <span>{children}</span>
    </div>
  );
}

let nextRowId = 1;

function emptyRow(overrides = {}) {
  return { rowId: nextRowId++, item: null, consumption_per_unit: "", wastage_percent: "0", ...overrides };
}

function toRows(bom) {
  if (!bom || bom.length === 0) return [emptyRow()];

  return bom.map((line) =>
    emptyRow({
      item: { id: line.item_id, item_code: line.item_code, name: line.item_name, uom: line.uom },
      consumption_per_unit: trim(line.consumption_per_unit),
      wastage_percent: trim(line.wastage_percent),
    }),
  );
}

/** API decimals ("1.8500") open clean in a number input. */
function trim(value) {
  if (value === null || value === undefined || value === "") return "";
  return String(Number(value));
}

export { OrderDetail };
