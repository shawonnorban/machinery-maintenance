"use client";

import { useMemo, useState, useTransition } from "react";
import { Check, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Add Requisition's order picker (user, 2026-11-24): the user's open Order
 * Summary orders with no requisition yet — style · season · JOB No (or "No
 * JOB No yet") · buyer · SSL — and OK fills a requisition row from the
 * picked one: Factory, Buyer, Brand, Season, Dept, Style, Order Type, JOB No,
 * Contract No, Delivery Date, PO-wise RFI/SD, Yarn Booking Date, Artwork,
 * Item, Wash Type, Quantity (Ticket), PAC and FOB Price — each one the order
 * has a value in locked on the row (changed in Order Summary), an empty one
 * typed here. Picking it again adds another row of the
 * same style (its other parts).
 */

function OrderPicker({ orders, rows, act, addPicked, skipAgain = [] }) {
  const t = useT("prebudget");
  const toast = useToastManager();
  const [chosen, setChosen] = useState("");
  const [pending, start] = useTransition();
  const onPage = useMemo(() => new Set(rows.map((r) => r.order?.id).filter(Boolean)), [rows]);
  const options = useMemo(
    () =>
      orders.map((o) => ({
        value: o.id,
        label: [o.style ?? "—", o.season, o.job_no ?? t("order.no_job"), o.buyer, `SSL ${o.ssl}`, o.rfi ? `RFI ${o.rfi}` : null].filter(Boolean).join(" · ") + (onPage.has(o.id) ? ` (${t("order.on_page")})` : ""),
      })),
    [orders, onPage, t],
  );

  const ok = () =>
    start(async () => {
      const res = await act("pickOrder", chosen);
      if (res?.status !== "success") {
        const first = res?.errors ? Object.values(res.errors)[0]?.[0] : null;
        toast.add({ title: first ?? res?.message ?? t("order.v.gone"), type: "danger" });
        return;
      }
      const { order, values: all, artwork_url, locked: allLocked, warning } = res.data;
      // The order already on the page: this row is another part — it takes no quantity and price (they stay on the first row).
      const again = onPage.has(order.id);
      const values = again ? Object.fromEntries(Object.entries(all).filter(([k]) => !skipAgain.includes(k))) : all;
      const locked = again ? allLocked.filter((k) => !skipAgain.includes(k)) : allLocked;
      addPicked({ values, artwork_url, order: { id: order.id, ssl: order.ssl, style: order.style, season: order.season, job_no: order.job_no, buyer: order.buyer, locked } });
      toast.add({ title: t("order.added", { style: order.style ?? "—" }), description: t("order.hint"), type: "success" });
      // An order value the requisition lists do not hold (a Buyer missing from the master…): said now, not first on Complete.
      if (warning) toast.add({ title: warning, type: "warning", timeout: 15000 });
    });

  return (
    <div className="flex min-w-0 items-center gap-2 md:ml-2">
      <span className="font-semibold text-foreground-muted">{t("order.label")}</span>
      <div className="w-[min(440px,60vw)]">
        <Select
          size="sm"
          options={options}
          value={chosen || null}
          onValueChange={(v) => setChosen(v ?? "")}
          placeholder={orders.length ? t("order.placeholder") : t("order.none")}
          aria-label={t("order.label")}
          disabled={!orders.length}
        />
      </div>
      <Button type="button" size="sm" onClick={ok} disabled={!chosen || pending}>
        {pending ? <Loader2 className="animate-spin" /> : <Check />} {t("order.ok")}
      </Button>
      <span className="text-[11px] text-foreground-muted">{t("order.count", { n: orders.length })}</span>
    </div>
  );
}

export { OrderPicker };
