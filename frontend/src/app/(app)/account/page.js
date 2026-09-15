import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { ChangePasswordForm } from "@/components/account/change-password-form";
import { SessionsTable } from "@/components/account/sessions-table";
import { TokensTable } from "@/components/account/tokens-table";
import { changePassword, revokeSession, revokeToken } from "./actions";

/**
 * Mirrors `account/index.blade.php` — the one screen a person owns rather
 * than administers (SRS 50.2). No permission gate: every route behind it
 * acts on the account of whoever is asking, so this page carries no
 * `permission` entry in the sidebar config and isn't reached from it at
 * all — the same as the Blade version, opened from the name in the
 * topbar corner (`UserMenu`), not from Settings (where other people's
 * accounts are administered).
 */
export default async function AccountPage() {
  const [me, sessions, tokens] = await Promise.all([
    apiFetch("/auth/me"),
    apiFetch("/auth/sessions"),
    apiFetch("/auth/tokens"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Your account" }]} title="Your account" description={me.user?.email} />

      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <ChangePasswordForm action={changePassword} />
        </div>

        <SessionsTable sessions={sessions} actions={{ revokeSession }} />
        <TokensTable tokens={tokens} actions={{ revokeToken }} />
      </div>
    </>
  );
}
