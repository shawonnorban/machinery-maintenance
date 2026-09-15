"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";

/**
 * Mirrors `account/index.blade.php`'s "API tokens" card — every live
 * bearer token this account holds, across both the Sanctum and legacy
 * schemes (API 3's migration window). Not shown at all while empty,
 * matching the Blade page's own `@if ($tokens->isNotEmpty())` guard —
 * this section only exists once there is something in it to manage.
 *
 * One thing the Blade list never had to handle: this app authenticates
 * with a bearer token, so the very token that loaded this page is always
 * one of these rows. `is_current` (set server-side) marks it and hides
 * its own revoke button — revoking it would end this session mid-click.
 */
function TokensTable({ tokens, actions }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  if (tokens.length === 0) {
    return null;
  }

  function runRevoke(tokenId) {
    startTransition(async () => {
      const result = await actions.revokeToken(tokenId);
      if (result?.status === "success") {
        toastManager.add({ title: "Token revoked", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>API tokens</CardTitle>
      </CardHeader>
      <CardBody className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="text-xs font-medium text-foreground-muted">
              <tr>
                <th className="px-5 py-2.5 text-left">Token</th>
                <th className="px-5 py-2.5 text-left">Last used</th>
                <th className="px-5 py-2.5 text-left">Expires</th>
                <th className="px-5 py-2.5 text-right"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {tokens.map((token) => (
                <tr key={token.id}>
                  <td className="px-5 py-3">
                    {token.name}
                    {/* The last four characters, so revoking one of six can be told apart without ever showing one in full again. */}
                    <span className="ml-1 text-xs text-foreground-muted">…{token.last_four}</span>
                    {token.is_current ? <Badge variant="info" className="ml-2">This session</Badge> : null}
                  </td>
                  <td className="px-5 py-3 text-foreground-muted">
                    <FormattedDateTime value={token.last_used_at} fallback="—" />
                  </td>
                  <td className="px-5 py-3 text-foreground-muted">
                    <FormattedDateTime value={token.expires_at} fallback="Never" />
                  </td>
                  <td className="px-5 py-3 text-right">
                    {!token.is_current ? (
                      <Button size="sm" variant="outline" loading={pending} onClick={() => runRevoke(token.id)}>
                        Revoke
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardBody>
    </Card>
  );
}

export { TokensTable };
