import Link from "next/link";
import { ShieldCheck, Building2, Wallet, LifeBuoy, Globe } from "lucide-react";
import { PlatformLoginForm } from "@/components/auth/platform-login-form";

export const metadata = { title: "Platform sign in — Annotech RMG" };

const HIGHLIGHTS = [
  { icon: Building2, text: "Every customer, their contract and their status" },
  { icon: Wallet, text: "Invoices, payments, and what the business is owed" },
  { icon: LifeBuoy, text: "Support tickets and audited, time-boxed access" },
  { icon: Globe, text: "Domain verification for every customer's own address" },
];

/**
 * Same split-screen design as the tenant `/login` (on direct request — the
 * console used to run a separate dark/amber chrome to be "deliberately
 * distinguishable," but this project's own colour tokens now do that job
 * consistently everywhere instead). Its own door regardless: a platform
 * admin's token carries no `company_id` at all (`PlatformAuthApiController`),
 * so the form still posts to `/api/platform-auth/login`, not `/api/auth/
 * login` — only the page around it is shared design.
 */
export default function PlatformLoginPage() {
  return (
    <div className="grid min-h-dvh grid-cols-1 bg-background lg:grid-cols-2">
      <div className="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-brand to-brand-hover px-12 py-12 text-brand-foreground lg:flex">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0 opacity-[0.07]"
          style={{
            backgroundImage: "radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0)",
            backgroundSize: "28px 28px",
          }}
        />

        <div className="relative flex items-center gap-2.5">
          <div className="flex size-9 items-center justify-center rounded-sm bg-white/15">
            <ShieldCheck className="size-5" />
          </div>
          <span className="text-lg font-semibold">Annotech RMG</span>
        </div>

        <div className="relative flex max-w-md flex-col gap-8">
          <div className="flex flex-col gap-3">
            <h1 className="text-3xl font-semibold text-balance">Every customer, one console.</h1>
            <p className="text-sm text-white/80">
              The superadmin console — onboarding, billing, support, and domains, across every company on the
              platform.
            </p>
          </div>

          <ul className="flex flex-col gap-3.5">
            {HIGHLIGHTS.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-3 text-sm">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-sm bg-white/15">
                  <Icon className="size-4" />
                </span>
                {text}
              </li>
            ))}
          </ul>
        </div>

        <p className="relative text-xs text-white/60">A Product by Data State Ltd</p>
      </div>

      <div className="flex flex-col justify-between px-6 py-10 sm:px-12 lg:px-16">
        <div className="flex items-center gap-2.5 lg:hidden">
          <div className="flex size-8 items-center justify-center rounded-sm bg-brand text-brand-foreground">
            <ShieldCheck className="size-4" />
          </div>
          <span className="text-base font-semibold text-foreground">Annotech RMG</span>
        </div>

        <div className="flex flex-1 flex-col items-center justify-center">
          <div className="w-full max-w-sm">
            <h2 className="text-xl font-semibold text-foreground">Platform sign in</h2>
            <p className="mt-1 mb-6 text-sm text-foreground-muted">Sign in with your platform account.</p>
            <PlatformLoginForm />

            <p className="mt-6 text-center text-sm text-foreground-muted">
              Not platform staff?{" "}
              <Link href="/login" className="font-medium text-brand hover:underline">
                Sign in as a customer
              </Link>
            </p>
          </div>
        </div>

        <p className="text-center text-xs text-foreground-subtle lg:text-right">App Version : 2.0</p>
      </div>
    </div>
  );
}
