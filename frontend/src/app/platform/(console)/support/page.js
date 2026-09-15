import Link from "next/link";
import { platformApiFetch } from "@/lib/platform-api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { RelativeTime } from "@/components/ui/relative-time";
import { CloseGrantButton } from "@/components/platform/close-grant-button";
import { closeSupportGrant } from "../tenants/[companyId]/actions";

/** Every open support grant, across every customer (mirrors `desk/support.blade.php`). */
export default async function PlatformSupportPage() {
  const [grants, me] = await Promise.all([platformApiFetch("/support-grants?active=true"), platformApiFetch("/auth/me")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Support access" }]}
        title="Support access"
        description="Every audited, time-boxed grant into a customer's data that is open right now."
      />

      <Card>
        <CardBody className="flex flex-col divide-y divide-border p-0">
          {grants.length === 0 ? (
            <p className="p-5 text-sm text-foreground-muted">No support access is open anywhere right now.</p>
          ) : (
            grants.map((grant) => (
              <div key={grant.id} className="flex flex-wrap items-center justify-between gap-3 p-5">
                <div>
                  <p className="text-sm font-medium text-foreground">
                    <Link href={`/platform/tenants/${grant.company.id}`} className="text-brand hover:underline">
                      {grant.company.name}
                    </Link>{" "}
                    <Badge variant="warning">Active</Badge>
                  </p>
                  <p className="text-xs text-foreground-muted">
                    {grant.holder.name} · {grant.reason}
                  </p>
                  <p className="text-xs text-foreground-subtle">
                    Expires <RelativeTime value={grant.expires_at} />
                  </p>
                </div>
                {grant.holder.id === me.id ? (
                  <CloseGrantButton grantId={grant.id} action={closeSupportGrant.bind(null, grant.company.id)} />
                ) : null}
              </div>
            ))
          )}
        </CardBody>
      </Card>
    </>
  );
}
