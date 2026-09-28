"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { useToastManager } from "@/components/ui/toast";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * One cutting programme: what it still needs bought, what has been issued
 * to it, and how far that is from the bill of materials (Procurement spec
 * §4, Inventory spec §9).
 *
 * The three tables read left to right as the job does — plan, buy, issue —
 * and the variance sits beside the issue that caused it rather than in a
 * report nobody opens.
 */
function CuttingProgramDetail({ program, requirement, issues, variance, canIssue, canRaisePr, sources, actions }) {
  const t = useT("production");
  const tp = useT("procurement");

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-8">
        <RequirementCard requirement={requirement} canRaisePr={canRaisePr} actions={actions} />

        <VarianceCard variance={variance} />

        {canIssue ? <IssuePanel variance={variance} sources={sources} actions={actions} /> : null}

        <IssuesCard issues={issues} />
      </div>

      <div className="flex flex-col gap-4 lg:col-span-4">
        <Card>
          <CardHeader>
            <CardTitle>{t("cutting_program")}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <Field label={t("status")}>
              <StatusBadge status={program.status} label={t(`statuses.${program.status}`)} />
            </Field>
            <Field label={t("program_qty")}>{formatNumber(program.program_qty, { maximumFractionDigits: 2 })}</Field>
            <Field label={t("style")}>{program.style?.style_no ?? "—"}</Field>
            <Field label={t("order")}>
              {program.style?.order ? (
                <Link href={`/production/orders/${program.style.order.id}`} className="text-brand hover:underline">
                  {program.style.order.order_no}
                </Link>
              ) : "—"}
            </Field>
            <Field label={t("buyer")}>{program.style?.order?.buyer ?? "—"}</Field>
            <Field label={t("cutting_date")}><FormattedDateTime value={program.cutting_date} mode="date" /></Field>
            <Field label={t("factory")}>{program.factory?.name ?? "—"}</Field>
          </CardBody>
        </Card>

        <Card>
          <CardBody className="text-xs text-foreground-muted">{tp("material_requirement_hint")}</CardBody>
        </Card>
      </div>
    </div>
  );
}

function RequirementCard({ requirement, canRaisePr, actions }) {
  const tp = useT("procurement");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  function raise() {
    startTransition(async () => {
      const result = await actions.raiseRequisition();

      if (result?.status === "success") {
        toastManager.add({ title: result.data.pr_no, type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{tp("material_requirement")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        {requirement.lines.length === 0 ? (
          <p className="text-sm text-foreground-muted">{tp("no_bom_for_requirement")}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs text-foreground-muted">
                  <th className="py-2 pr-3 font-medium">{tp("item")}</th>
                  <th className="py-2 pr-3 text-right font-medium">{tp("required_qty")}</th>
                  <th className="py-2 pr-3 text-right font-medium">{tp("stock_available")}</th>
                  <th className="py-2 pr-3 text-right font-medium">{tp("already_booked")}</th>
                  <th className="py-2 pr-3 text-right font-medium">{tp("already_ordered")}</th>
                  <th className="py-2 text-right font-medium">{tp("net_requirement")}</th>
                </tr>
              </thead>
              <tbody>
                {requirement.lines.map((line) => (
                  <tr key={line.item_id} className="border-b border-border/60">
                    <td className="py-2 pr-3">
                      <div className="text-foreground">{line.item_name}</div>
                      <div className="text-xs text-foreground-muted">{line.item_code} · {line.uom?.code ?? ""}</div>
                    </td>
                    <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(line.required_qty, { maximumFractionDigits: 2 })}</td>
                    <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(line.stock_available, { maximumFractionDigits: 2 })}</td>
                    <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(line.already_booked, { maximumFractionDigits: 2 })}</td>
                    <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(line.already_ordered, { maximumFractionDigits: 2 })}</td>
                    <td className="py-2 text-right tabular-nums font-medium">
                      {Number(line.net_requirement) > 0
                        ? formatNumber(line.net_requirement, { maximumFractionDigits: 2 })
                        : <Badge variant="success">{tp("requirement_covered")}</Badge>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {canRaisePr ? (
          <div className="flex justify-end">
            <Button type="button" size="sm" loading={pending} onClick={raise}>
              {tp("raise_requirement_requisition")}
            </Button>
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}

function VarianceCard({ variance }) {
  const t = useT("production");

  if (variance.length === 0) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("variance_qty")}</CardTitle>
      </CardHeader>
      <CardBody className="overflow-x-auto">
        <table className="w-full min-w-[34rem] text-sm">
          <thead>
            <tr className="border-b border-border text-left text-xs text-foreground-muted">
              <th className="py-2 pr-3 font-medium">{t("item")}</th>
              <th className="py-2 pr-3 text-right font-medium">{t("required_qty")}</th>
              <th className="py-2 pr-3 text-right font-medium">{t("issued_qty")}</th>
              <th className="py-2 pr-3 text-right font-medium">{t("variance_qty")}</th>
              <th className="py-2 text-right font-medium">{t("variance_percent")}</th>
            </tr>
          </thead>
          <tbody>
            {variance.map((line) => (
              <tr key={line.item_id} className="border-b border-border/60">
                <td className="py-2 pr-3">
                  <div className="text-foreground">{line.item_name}</div>
                  <div className="text-xs text-foreground-muted">
                    {line.item_code}
                    {line.pending_approval ? <span className="ml-2 text-warning">{t("awaiting_approval")}</span> : null}
                  </div>
                </td>
                <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(line.required_qty, { maximumFractionDigits: 2 })}</td>
                <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(line.issued_qty, { maximumFractionDigits: 2 })}</td>
                <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(line.variance_qty, { maximumFractionDigits: 2 })}</td>
                <td className="py-2 text-right tabular-nums">{line.variance_percent}%</td>
              </tr>
            ))}
          </tbody>
        </table>
      </CardBody>
    </Card>
  );
}

function IssuePanel({ variance, sources, actions }) {
  const t = useT("production");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [itemId, setItemId] = useState(variance[0]?.item_id ?? "");
  const [quantity, setQuantity] = useState("");
  const [binId, setBinId] = useState("");
  const [reason, setReason] = useState("");
  const [errors, setErrors] = useState(null);

  const itemSources = sources[itemId] ?? [];
  const rollTracked = itemSources.some((source) => source.fabric_roll_id);
  const [rollId, setRollId] = useState("");

  function issue() {
    startTransition(async () => {
      const result = await actions.issue({
        item_id: itemId,
        quantity: rollTracked ? undefined : quantity,
        bin_id: rollTracked ? undefined : binId,
        fabric_roll_id: rollTracked ? rollId : undefined,
        reason: reason || undefined,
      });

      if (result?.status === "success") {
        toastManager.add({
          title: result.data.stock_moved
            ? t("material_issued", { item: result.data.item_name, number: result.data.item_code })
            : t("stock_not_moved"),
          type: result.data.stock_moved ? "success" : "warning",
        });
        setQuantity("");
        setReason("");
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
        <CardTitle>{t("issue_material")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        <p className="text-xs text-foreground-muted">{t("issue_hint")}</p>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
          <FormField label={t("item")} required error={errors?.item_id?.[0]}>
            {(fp) => (
              <Select
                {...fp}
                value={itemId}
                onValueChange={(value) => { setItemId(value); setRollId(""); setBinId(""); }}
                options={variance.map((line) => ({ value: line.item_id, label: `${line.item_name} (${line.item_code})` }))}
              />
            )}
          </FormField>

          {rollTracked ? (
            <FormField label={t("roll") ?? "Roll"} required error={errors?.fabric_roll_id?.[0]}>
              {(fp) => (
                <Select
                  {...fp}
                  value={rollId}
                  onValueChange={setRollId}
                  options={itemSources.map((source) => ({
                    value: source.fabric_roll_id,
                    label: `${source.roll_no} · ${formatNumber(source.available, { maximumFractionDigits: 2 })}`,
                  }))}
                />
              )}
            </FormField>
          ) : (
            <>
              <FormField label={t("issued_qty")} required error={errors?.quantity?.[0]}>
                {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={quantity} onChange={(e) => setQuantity(e.target.value)} />}
              </FormField>
              <FormField label={t("bin") ?? "Bin"} required error={errors?.bin_id?.[0]}>
                {(fp) => (
                  <Select
                    {...fp}
                    value={binId}
                    onValueChange={setBinId}
                    options={itemSources.map((source) => ({
                      value: source.bin_id,
                      label: `${source.bin?.code ?? ""} · ${formatNumber(source.available, { maximumFractionDigits: 2 })}`,
                    }))}
                  />
                )}
              </FormField>
            </>
          )}

          <FormField label={t("issue_reason")} error={errors?.reason?.[0]}>
            {(fp) => <Input {...fp} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={1000} />}
          </FormField>
        </div>

        {itemSources.length === 0 ? <Alert variant="warning">{t("no_issues")}</Alert> : null}

        <div className="flex justify-end">
          <Button
            type="button"
            loading={pending}
            disabled={!itemId || (rollTracked ? !rollId : !quantity || !binId)}
            onClick={issue}
          >
            {t("issue_material")}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

function IssuesCard({ issues }) {
  const t = useT("production");

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("material_issues")}</CardTitle>
      </CardHeader>
      <CardBody>
        {issues.length === 0 ? (
          <p className="text-sm text-foreground-muted">{t("no_issues")}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs text-foreground-muted">
                  <th className="py-2 pr-3 font-medium">{t("item")}</th>
                  <th className="py-2 pr-3 text-right font-medium">{t("issued_qty")}</th>
                  <th className="py-2 pr-3 font-medium">{t("bin") ?? "Bin"}</th>
                  <th className="py-2 pr-3 text-right font-medium">{t("variance_percent")}</th>
                  <th className="py-2 pr-3 font-medium">{t("status")}</th>
                  <th className="py-2 font-medium">{t("issued_at") ?? ""}</th>
                </tr>
              </thead>
              <tbody>
                {issues.map((issue) => (
                  <tr key={issue.id} className="border-b border-border/60">
                    <td className="py-2 pr-3">
                      <div className="text-foreground">{issue.item_name}</div>
                      <div className="text-xs text-foreground-muted">{issue.item_code}</div>
                    </td>
                    <td className="py-2 pr-3 text-right tabular-nums">{formatNumber(issue.quantity, { maximumFractionDigits: 2 })}</td>
                    <td className="py-2 pr-3">{issue.roll_no ?? issue.bin?.code ?? "—"}</td>
                    <td className="py-2 pr-3 text-right tabular-nums">{issue.variance_percent}%</td>
                    <td className="py-2 pr-3">
                      <StatusBadge status={issue.status} label={t(`issue_statuses.${issue.status}`)} />
                    </td>
                    <td className="py-2"><FormattedDateTime value={issue.issued_at} mode="date" /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
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

export { CuttingProgramDetail };
