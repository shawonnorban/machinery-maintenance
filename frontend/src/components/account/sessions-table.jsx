"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { EmptyState } from "@/components/ui/empty-state";
import { useToastManager } from "@/components/ui/toast";

/**
 * Mirrors `account/index.blade.php`'s "Signed-in devices" card — every
 * browser session this account holds. Unlike the Blade page, there is no
 * "this device" exemption here: a Blade request arrives carrying its own
 * session to compare against, but this app authenticates with a bearer
 * token instead, which is never itself one of these rows — every listed
 * session is offered a "Sign out" button.
 */
function SessionsTable({ sessions, actions }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runRevoke(sessionId) {
    startTransition(async () => {
      const result = await actions.revokeSession(sessionId);
      if (result?.status === "success") {
        toastManager.add({ title: "Device signed out", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Signed-in devices</CardTitle>
      </CardHeader>
      <CardBody className="p-0">
        {sessions.length === 0 ? (
          <div className="p-5">
            <EmptyState title="No sessions to show." description="A cookie-session driver keeps no index of where you're signed in." />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-xs font-medium text-foreground-muted">
                <tr>
                  <th className="px-5 py-2.5 text-left">Device</th>
                  <th className="px-5 py-2.5 text-left">IP address</th>
                  <th className="px-5 py-2.5 text-left">Last active</th>
                  <th className="px-5 py-2.5 text-right"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {sessions.map((session) => (
                  <tr key={session.id}>
                    <td className="px-5 py-3">{session.agent}</td>
                    <td className="px-5 py-3 text-foreground-muted">{session.ip_address ?? "—"}</td>
                    <td className="px-5 py-3 text-foreground-muted">
                      <FormattedDateTime value={session.last_activity} />
                    </td>
                    <td className="px-5 py-3 text-right">
                      <Button size="sm" variant="outline" loading={pending} onClick={() => runRevoke(session.id)}>
                        Sign out
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

export { SessionsTable };
