"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

/** Mirrors `LoginForm` exactly, against `/api/platform-auth/login` instead. */
function PlatformLoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);

    const formData = new FormData(event.currentTarget);

    try {
      const response = await fetch("/api/platform-auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: formData.get("email"),
          password: formData.get("password"),
        }),
      });
      const body = await response.json();

      if (!response.ok) {
        throw new Error(body.message ?? "Login failed");
      }

      router.push("/platform");
      router.refresh();
    } catch (submitError) {
      setError(submitError.message);
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} method="post" className="flex flex-col gap-4">
      {error ? <Alert variant="danger">{error}</Alert> : null}

      <FormField label="Email" required>
        {(fieldProps) => (
          <Input
            {...fieldProps}
            type="email"
            name="email"
            autoComplete="username"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        )}
      </FormField>

      <FormField label="Password" required>
        {(fieldProps) => (
          <Input
            {...fieldProps}
            type="password"
            name="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
          />
        )}
      </FormField>

      <Button type="submit" loading={submitting} className="mt-2">
        Sign in
      </Button>
    </form>
  );
}

export { PlatformLoginForm };
