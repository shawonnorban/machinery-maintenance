import Link from "next/link";
import { LifeBuoy } from "lucide-react";
import { platformApiFetch } from "@/lib/platform-api-server";
import { RelativeTime } from "@/components/ui/relative-time";
import { PlatformHeader, Panel, PTable, fmtStamp } from "@/components/platform/ui";
import { CloseGrantButton } from "@/components/platform/close-grant-button";
import { closeSupportGrant } from "../tenants/[companyId]/actions";

/** Every open support grant, across every customer. */
export default async function PlatformSupportPage() {
  const [grants, me] = await Promise.all([platformApiFetch("/support-grants?active=true"), platformApiFetch("/auth/me")]);

  return (
    <>
      <PlatformHeader
        breadcrumb={[{ label: "Support access" }]}
        title="Support access"
        description="Every audited, time-boxed grant into a customer's data that is open right now."
        icon={<LifeBuoy />}
        tone="info"
      />
      <Panel title="Open grants" subtitle={`${grants.length} open — a grant closes by itself when it expires`} icon={LifeBuoy}>
        <PTable
          rows={grants}
          rowKey={(g) => g.id}
          empty="No support access is open anywhere right now."
          columns={[
            {
              key: "company",
              label: "Customer",
              render: (g) => (
                <Link href={`/platform/tenants/${g.company.id}?tab=support`} className="font-semibold text-brand hover:underline">
                  {g.company.name}
                </Link>
              ),
            },
            { key: "holder", label: "Held by", render: (g) => g.holder.name },
            { key: "reason", label: "Reason", render: (g) => <span className="text-foreground-muted">{g.reason}</span> },
            {
              key: "expires",
              label: "Expires",
              render: (g) => (
                <span className="text-[13px]">
                  {fmtStamp(g.expires_at)}
                  <span className="block text-xs text-foreground-muted">
                    <RelativeTime value={g.expires_at} />
                  </span>
                </span>
              ),
            },
            { key: "act", label: "", align: "right", render: (g) => (g.holder.id === me.id ? <CloseGrantButton grantId={g.id} action={closeSupportGrant.bind(null, g.company.id)} /> : null) },
          ]}
        />
      </Panel>
    </>
  );
}
