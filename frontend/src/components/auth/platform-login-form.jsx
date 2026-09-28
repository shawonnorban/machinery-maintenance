"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Mail, Lock } from "lucide-react";
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

      <FormField label="Email address" required>
        {(fieldProps) => (
          <div className="relative">
            <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              {...fieldProps}
              type="email"
              name="email"
              autoComplete="username"
              placeholder="you@company.com"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
              className="pl-9"
            />
          </div>
        )}
      </FormField>

      <FormField label="Password" required>
        {(fieldProps) => (
          <div className="relative">
            <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input
              {...fieldProps}
              type="password"
              name="password"
              autoComplete="current-password"
              placeholder="Enter your password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
              className="pl-9"
            />
          </div>
        )}
      </FormField>

      <Button
        type="submit"
        loading={submitting}
        className="mt-2 h-12 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-500 text-base font-semibold text-white hover:from-emerald-600 hover:to-cyan-600 focus-visible:ring-emerald-500/50"
      >
        Log in
      </Button>
    </form>
  );
}

export { PlatformLoginForm };
