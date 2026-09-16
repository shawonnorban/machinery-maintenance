"use client";

import { useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Mail, Lock, Ticket } from "lucide-react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";

// One demo login per role a reviewer most needs to compare: the person who
// can see everything, the floor supervisor who only reports what they spot,
// and the technician who repairs it — not all twelve seeded roles, which
// would turn a quick demo click into another list to read.
const DEMO_ACCOUNTS = [
  { role: "Company Owner", email: "owner@delta.test" },
  { role: "Line Chief", email: "linechief@delta.test" },
  { role: "Technician", email: "technician@delta.test" },
];
const DEMO_PASSWORD = "password123";

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

  function fillDemoAccount(demoEmail) {
    setEmail(demoEmail);
    setPassword(DEMO_PASSWORD);
  }

  return (
    <div className="flex flex-col gap-4">
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

      {/* Same shared demo password as `DemoTenantSeeder`/`DemoCustomerSeeder`
          (see DEPLOYMENT-GUIDE.md §4 on why that's fine for a walkthrough
          and not something to leave running beside real customer accounts).
          Three roles, not all twelve seeded ones: the reviewer decides what
          a given screen looks like from the top, the floor, and the repair
          bench, and a longer list would read as noise, not choice. */}
      <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div className="flex items-center gap-2 text-sm font-semibold tracking-wide text-amber-800 uppercase">
          <Ticket className="size-4" />
          Try a demo account
        </div>

        <div className="mt-3 flex flex-col gap-2">
          {DEMO_ACCOUNTS.map((account) => (
            <button
              key={account.email}
              type="button"
              onClick={() => fillDemoAccount(account.email)}
              className="flex items-center justify-between gap-3 rounded-lg border border-amber-100 bg-white px-3.5 py-2.5 text-left transition-colors hover:border-amber-300"
            >
              <span className="flex flex-col">
                <span className="text-sm font-semibold text-foreground">{account.role}</span>
                <span className="font-mono text-xs text-amber-700">{account.email}</span>
              </span>
              <span className="shrink-0 text-xs font-semibold text-amber-600">Click to use</span>
            </button>
          ))}
        </div>

        <p className="mt-3 text-xs text-amber-700">Password for all accounts: {DEMO_PASSWORD}</p>
      </div>
    </div>
  );
}

export { LoginForm };
