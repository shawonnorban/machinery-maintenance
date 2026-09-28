"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

/**
 * A buyer order (Master Data spec §6).
 *
 * The order number is typed, not generated: it is the buyer's, and a
 * factory refers to it by that name. Styles and the bill of materials are
 * added on the order's own page afterwards — one form asking for all three
 * at once would be a form nobody finishes.
 */
function OrderForm({ factories, action }) {
  const t = useT("production");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [factoryId, setFactoryId] = useState(factories.length === 1 ? factories[0].id : "");
  const [orderNo, setOrderNo] = useState("");
  const [buyer, setBuyer] = useState("");
  const [orderQty, setOrderQty] = useState("");
  const [orderDate, setOrderDate] = useState(new Date().toISOString().slice(0, 10));
  const [shipDate, setShipDate] = useState("");
  const [notes, setNotes] = useState("");

  const errors = state?.errors ?? {};

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.set("factory_id", factoryId);
    formData.set("order_no", orderNo);
    formData.set("buyer", buyer);
    formData.set("order_qty", orderQty);
    formData.set("order_date", orderDate);
    formData.set("ship_date", shipDate);
    formData.set("notes", notes);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" ? <Alert variant="danger">{state.message}</Alert> : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("order")}</CardTitle>
        </CardHeader>
        <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label={t("factory")} required error={errors.factory_id?.[0]}>
            {(fp) => (
              <Select
                {...fp}
                value={factoryId}
                onValueChange={setFactoryId}
                options={factories.map((f) => ({ value: f.id, label: f.name }))}
                placeholder={t("factory")}
              />
            )}
          </FormField>

          <FormField label={t("order_no")} required error={errors.order_no?.[0]}>
            {(fp) => <Input {...fp} value={orderNo} onChange={(e) => setOrderNo(e.target.value)} maxLength={64} />}
          </FormField>

          <FormField label={t("buyer")} required error={errors.buyer?.[0]}>
            {(fp) => <Input {...fp} value={buyer} onChange={(e) => setBuyer(e.target.value)} maxLength={160} />}
          </FormField>

          <FormField label={t("order_qty")} required error={errors.order_qty?.[0]}>
            {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={orderQty} onChange={(e) => setOrderQty(e.target.value)} />}
          </FormField>

          <FormField label={t("order_date")} error={errors.order_date?.[0]}>
            {(fp) => <Input {...fp} type="date" value={orderDate} onChange={(e) => setOrderDate(e.target.value)} />}
          </FormField>

          <FormField label={t("ship_date")} error={errors.ship_date?.[0]}>
            {(fp) => <Input {...fp} type="date" value={shipDate} onChange={(e) => setShipDate(e.target.value)} />}
          </FormField>

          <div className="sm:col-span-2">
            <FormField label={t("notes")} error={errors.notes?.[0]}>
              {(fp) => <Textarea {...fp} rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} />}
            </FormField>
          </div>
        </CardBody>
      </Card>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending} disabled={!factoryId || !orderNo || !buyer}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { OrderForm };
