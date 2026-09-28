"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { TIMEZONES } from "@/lib/timezones";
import { useT } from "@/lib/i18n";

const TIMEZONE_OPTIONS = TIMEZONES.map((tz) => ({ value: tz, label: tz }));

/** Mirrors `factories/_form.blade.php` — `code` is create-only, immutable after (it's how everything else references this factory). Runs inside `FactoryFormModal`; `onDone` closes it once the save actually succeeds. */
function FactoryForm({ factory = null, action, onDone, onCancel }) {
  const t = useT("settings");
  const tc = useT("common");
  const isEdit = factory !== null;
  const router = useRouter();
  const toastManager = useToastManager();
  const [state, dispatch, pending] = useActionState(action, null);
  const [name, setName] = useState(factory?.name ?? "");
  const [code, setCode] = useState(factory?.code ?? "");
  const [address, setAddress] = useState(factory?.address ?? "");
  const [timezone, setTimezone] = useState(factory?.timezone ?? "Asia/Dhaka");

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: isEdit ? t("factory_updated", { name }) : t("factory_created", { name }), type: "success" });
      queueMicrotask(() => onDone?.());
      router.refresh();
    }
    // toastManager/router/onDone are deliberately excluded — see every
    // other form in this app for why including them re-fires this effect
    // every render once state first becomes "success".
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    if (!isEdit) formData.set("code", code);
    formData.set("name", name);
    if (address) formData.set("address", address);
    formData.set("timezone", timezone);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("code")} required helperText={isEdit ? t("code_readonly_hint") : t("code_hint_short")} error={state?.errors?.code?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={code} onChange={(e) => setCode(e.target.value)} disabled={isEdit} maxLength={5} required />}
        </FormField>

        <FormField label={t("factory_name")} required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
        </FormField>

        <FormField label={t("timezone")} required error={state?.errors?.timezone?.[0]}>
          {(fieldProps) => <Select {...fieldProps} value={timezone} onValueChange={setTimezone} options={TIMEZONE_OPTIONS} />}
        </FormField>
      </div>

      <FormField label={t("address")} error={state?.errors?.address?.[0]}>
        {(fieldProps) => <Textarea {...fieldProps} value={address} onChange={(e) => setAddress(e.target.value)} rows={2} maxLength={2000} />}
      </FormField>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {isEdit ? t("save_changes") : t("create_factory")}
        </Button>
      </div>
    </form>
  );
}

export { FactoryForm };
