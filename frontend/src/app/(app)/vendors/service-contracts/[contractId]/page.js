import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { StatusBadge } from "@/components/ui/status-badge";
import { RenewContractForm } from "@/components/vendor/renew-contract-form";
import { CancelContractForm } from "@/components/vendor/cancel-contract-form";
import { renewContract, cancelContract } from "./actions";

/** Mirrors `contracts/show.blade.php` (`ServiceContractApiController::show`). */
export default async function ServiceContractDetailPage({ params }) {
  const { contractId } = await params;
  const contract = await apiFetch(`/service-contracts/${contractId}`);

  const scopeLabel = contract.asset
    ? `${contract.asset.asset_code} — ${contract.asset.name}`
    : contract.factory
      ? contract.factory.name
      : `${contract.assets?.length ?? 0} machines`;

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: "Vendors" },
          { label: "Service contracts", href: "/vendors/service-contracts" },
          { label: contract.contract_number },
        ]}
        title={contract.contract_number}
        description={contract.contract_type}
      />

      {contract.status === "ACTIVE" && contract.days_remaining >= 0 && contract.days_remaining <= 60 ? (
        <Alert variant="warning" title={`${contract.days_remaining} days remaining — ends ${contract.end_date}`} />
      ) : null}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardBody>
            <dl className="grid grid-cols-2 gap-y-2 text-sm">
              <dt className="text-foreground-muted">Vendor</dt>
              <dd className="text-foreground">
                <Link href={`/vendors/${contract.vendor?.id}/edit`} className="text-brand hover:underline">
                  {contract.vendor?.name}
                </Link>
              </dd>
              <dt className="text-foreground-muted">Status</dt>
              <dd><StatusBadge status={contract.status} /></dd>
              <dt className="text-foreground-muted">Scope</dt>
              <dd className="text-foreground">{scopeLabel}</dd>
              <dt className="text-foreground-muted">Start date</dt>
              <dd className="text-foreground">{contract.start_date}</dd>
              <dt className="text-foreground-muted">End date</dt>
              <dd className="text-foreground">{contract.end_date}</dd>
              <dt className="text-foreground-muted">Renewal reminder</dt>
              <dd className="text-foreground">{contract.renewal_date ?? "—"}</dd>
              <dt className="text-foreground-muted">Value</dt>
              <dd className="text-foreground">{contract.value === null ? "—" : `${contract.value} ${contract.currency ?? ""}`}</dd>
              <dt className="text-foreground-muted">Visits per year</dt>
              <dd className="text-foreground">{contract.visits_per_year ?? "—"}</dd>
              <dt className="text-foreground-muted">Response time</dt>
              <dd className="text-foreground">{contract.response_time_hours ? `${contract.response_time_hours}h` : "—"}</dd>
              <dt className="text-foreground-muted">Coverage</dt>
              <dd className="text-foreground">{contract.coverage ?? "—"}</dd>
              {contract.renewed_from_contract_id ? (
                <>
                  <dt className="text-foreground-muted">Renewed from</dt>
                  <dd className="text-foreground">
                    <Link href={`/vendors/service-contracts/${contract.renewed_from_contract_id}`} className="text-brand hover:underline">
                      {contract.renewed_from_contract_number}
                    </Link>
                  </dd>
                </>
              ) : null}
            </dl>
          </CardBody>
        </Card>

        <div className="flex flex-col gap-4">
          {contract.can_manage ? (
            <>
              <Card>
                <CardHeader>
                  <CardTitle>Renew</CardTitle>
                </CardHeader>
                <CardBody>
                  <RenewContractForm contract={contract} action={renewContract.bind(null, contractId)} />
                </CardBody>
              </Card>

              <Card>
                <CardBody>
                  <CancelContractForm action={cancelContract.bind(null, contractId)} />
                </CardBody>
              </Card>
            </>
          ) : null}

          {contract.assets?.length ? (
            <Card>
              <CardHeader>
                <CardTitle>Covers</CardTitle>
              </CardHeader>
              <CardBody>
                <ul className="flex flex-col gap-1 text-sm">
                  {contract.assets.map((asset) => (
                    <li key={asset.id}>
                      {asset.asset_code} — {asset.name}
                    </li>
                  ))}
                </ul>
              </CardBody>
            </Card>
          ) : null}
        </div>
      </div>
    </>
  );
}
