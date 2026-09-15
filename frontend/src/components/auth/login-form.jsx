"use client";

import { useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  async function handleSubmit(event) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);

    // Read from the DOM form itself, not the `email`/`password` state:
    // browser/password-manager autofill sets the input's value directly
    // and does not reliably fire React's onChange, so the controlled
    // state can still read empty even though the field is visibly filled
    // — a submit built from that stale state sent an empty payload and
    // came back as "The email field is required."
    const formData = new FormData(event.currentTarget);

    try {
      const response = await fetch("/api/auth/login", {
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

      // `proxy.js` sends an unauthenticated visitor here with `?from=` set
      // to whatever they were trying to reach (a scanned QR code's landing
      // page, most often) — honouring it is what lets that flow return the
      // technician to the exact page they scanned instead of stranding
      // them on the dashboard. Only a same-origin path is trusted: a bare
      // `/...` and not `//...` (a scheme-relative URL is still an open
      // redirect to another host).
      const from = searchParams.get("from");
      router.push(from && from.startsWith("/") && !from.startsWith("//") ? from : "/");
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

export { LoginForm };
