"use client";

import { useActionState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";
import { useT } from "@/lib/i18n";

/** Mirrors `account/index.blade.php`'s password card. */
function ChangePasswordForm({ action }) {
  const t = useT("account");
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <Card>
      <form action={dispatch}>
        <CardHeader>
          <CardTitle>{t("password")}</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-4">
          {state?.status === "success" ? <Alert variant="success">{t("password_changed")}</Alert> : null}
          {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

          <FormField label={t("current_password")} required error={state?.errors?.current_password?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="password" name="current_password" autoComplete="current-password" required />}
          </FormField>
          <FormField label={t("new_password")} required helperText={t("password_policy")} error={state?.errors?.password?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="password" name="password" autoComplete="new-password" required />}
          </FormField>
          <FormField label={t("confirm_password")} required>
            {(fieldProps) => <Input {...fieldProps} type="password" name="password_confirmation" autoComplete="new-password" required />}
          </FormField>

          <p className="rounded-sm bg-surface-muted p-3 text-xs text-foreground-muted">{t("password_change_signs_out")}</p>
        </CardBody>
        <CardFooter>
          <Button type="submit" loading={pending}>
            {t("change_password")}
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
}

export { ChangePasswordForm };
