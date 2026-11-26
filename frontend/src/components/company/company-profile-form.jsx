"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Save } from "lucide-react";
import { Card, CardBody, CardDescription, CardFooter, CardHeader, CardTitle, CardTitleGroup } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const FIELDS = [
  { name: "name", required: true },
  { name: "legal_name" },
  { name: "phone" },
  { name: "email", type: "email" },
  { name: "website" },
  { name: "trade_license_no" },
  { name: "tin" },
  { name: "bin" },
  { name: "registration_no" },
  { name: "registration_date", type: "date" },
  { name: "address", multiline: true },
];

/**
 * Company Profile (AnnotechERP 01/01): the one company record — no Add, only
 * edit and Save. The code is shown, not edited: it is in every number issued.
 */
function CompanyProfileForm({ profile, action }) {
  const t = useT("company");
  const router = useRouter();
  const toastManager = useToastManager();
  const [values, setValues] = useState(() => Object.fromEntries(FIELDS.map((field) => [field.name, profile[field.name] ?? ""])));
  const [state, dispatch, pending] = useActionState(action, null);

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("saved"), type: "success" });
      router.refresh();
    } else if (state?.status === "error" && !state.errors) {
      toastManager.add({ title: state.message, type: "danger" });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function submit(event) {
    event.preventDefault();
    const formData = new FormData();
    for (const [name, value] of Object.entries(values)) formData.set(name, value ?? "");
    startTransition(() => dispatch(formData));
  }

  return (
    <Card>
      <form onSubmit={submit}>
        <CardHeader>
          <CardTitleGroup>
            <CardTitle>
              {profile.name} <span className="ml-2 font-mono text-sm text-foreground-muted">{profile.code}</span>
            </CardTitle>
            <CardDescription>{t("descriptions.company-profile")}</CardDescription>
          </CardTitleGroup>
        </CardHeader>
        <CardBody className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {FIELDS.map((field) => (
            <div key={field.name} className={field.multiline ? "sm:col-span-2 lg:col-span-3" : undefined}>
              <FormField label={t(`profile.${field.name}`)} required={field.required} error={state?.errors?.[field.name]?.[0]}>
                {(fieldProps) =>
                  field.multiline ? (
                    <Textarea {...fieldProps} value={values[field.name]} onChange={(e) => setValues((v) => ({ ...v, [field.name]: e.target.value }))} />
                  ) : (
                    <Input
                      {...fieldProps}
                      type={field.type ?? "text"}
                      value={values[field.name]}
                      onChange={(e) => setValues((v) => ({ ...v, [field.name]: e.target.value }))}
                    />
                  )
                }
              </FormField>
            </div>
          ))}
        </CardBody>
        <CardFooter className="flex justify-end">
          <Button type="submit" size="sm" loading={pending}>
            <Save /> {t("save")}
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
}

export { CompanyProfileForm };
