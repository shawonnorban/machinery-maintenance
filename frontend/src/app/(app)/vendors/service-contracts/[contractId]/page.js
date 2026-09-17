import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { StatusBadge } from "@/components/ui/status-badge";
import { RenewContractForm } from "@/components/vendor/renew-contract-form";
import { CancelContractForm } from "@/components/vendor/cancel-contract-form";
import { getT } from "@/lib/i18n-server";
import { renewContract, cancelContract } from "./actions";

/** Mirrors `contracts/show.blade.php` (`ServiceContractApiController::show`). */
export default async function ServiceContractDetailPage({ params }) {
  const { contractId } = await params;
  const [contract, t] = await Promise.all([
    apiFetch(`/service-contracts/${contractId}`),
    getT("vendor"),
  ]);

  const scopeLabel = contract.asset
    ? `${contract.asset.asset_code} — ${contract.asset.name}`
    : contract.factory
      ? contract.factory.name
      : t("machines_count", { count: contract.assets?.length ?? 0 });

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("vendors") },
          { label: t("contracts"), href: "/vendors/service-contracts" },
          { label: contract.contract_number },
        ]}
        title={contract.contract_number}
        description={t(`contract_type_${contract.contract_type?.toLowerCase()}`)}
      />

      {contract.status === "ACTIVE" && contract.days_remaining >= 0 && contract.days_remaining <= 60 ? (
        <Alert variant="warning" title={t("days_remaining_ends", { days: contract.days_remaining, date: contract.end_date })} />
      ) : null}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardBody>
            <dl className="grid grid-cols-2 gap-y-2 text-sm">
              <dt className="text-foreground-muted">{t("vendor")}</dt>
              <dd className="text-foreground">
                <Link href={`/vendors/${contract.vendor?.id}/edit`} className="text-brand hover:underline">
                  {contract.vendor?.name}
                </Link>
              </dd>
              <dt className="text-foreground-muted">{t("status")}</dt>
              <dd><StatusBadge status={contract.status} label={t(`contract_status_${contract.status?.toLowerCase()}`)} /></dd>
              <dt className="text-foreground-muted">{t("scope")}</dt>
              <dd className="text-foreground">{scopeLabel}</dd>
              <dt className="text-foreground-muted">{t("start_date")}</dt>
              <dd className="text-foreground">{contract.start_date}</dd>
              <dt className="text-foreground-muted">{t("end_date")}</dt>
              <dd className="text-foreground">{contract.end_date}</dd>
              <dt className="text-foreground-muted">{t("renewal_date")}</dt>
              <dd className="text-foreground">{contract.renewal_date ?? "—"}</dd>
              <dt className="text-foreground-muted">{t("value")}</dt>
              <dd className="text-foreground">{contract.value === null ? "—" : `${contract.value} ${contract.currency ?? ""}`}</dd>
              <dt className="text-foreground-muted">{t("visits_per_year")}</dt>
              <dd className="text-foreground">{contract.visits_per_year ?? "—"}</dd>
              <dt className="text-foreground-muted">{t("response_time_hours")}</dt>
              <dd className="text-foreground">{contract.response_time_hours ? `${contract.response_time_hours}h` : "—"}</dd>
              <dt className="text-foreground-muted">{t("coverage")}</dt>
              <dd className="text-foreground">{contract.coverage ?? "—"}</dd>
              {contract.renewed_from_contract_id ? (
                <>
                  <dt className="text-foreground-muted">{t("renewed_from")}</dt>
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
                  <CardTitle>{t("renew")}</CardTitle>
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
                <CardTitle>{t("covers")}</CardTitle>
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
