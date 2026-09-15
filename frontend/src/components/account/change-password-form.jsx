"use client";

import { useActionState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";

/** Mirrors `account/index.blade.php`'s password card. */
function ChangePasswordForm({ action }) {
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <Card>
      <form action={dispatch}>
        <CardHeader>
          <CardTitle>Password</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-4">
          {state?.status === "success" ? <Alert variant="success">Password changed.</Alert> : null}
          {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

          <FormField label="Current password" required error={state?.errors?.current_password?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="password" name="current_password" autoComplete="current-password" required />}
          </FormField>
          <FormField label="New password" required helperText="At least 10 characters, and checked against known-breached passwords." error={state?.errors?.password?.[0]}>
            {(fieldProps) => <Input {...fieldProps} type="password" name="password" autoComplete="new-password" required />}
          </FormField>
          <FormField label="Confirm new password" required>
            {(fieldProps) => <Input {...fieldProps} type="password" name="password_confirmation" autoComplete="new-password" required />}
          </FormField>

          <p className="rounded-sm bg-surface-muted p-3 text-xs text-foreground-muted">
            Changing your password signs out every other device and token this account holds — this one stays signed in.
          </p>
        </CardBody>
        <CardFooter>
          <Button type="submit" loading={pending}>
            Change password
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
}

export { ChangePasswordForm };
