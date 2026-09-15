import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { FileClaimForm } from "@/components/vendor/file-claim-form";
import { ClaimsList } from "@/components/vendor/claims-list";
import { fileClaim, decideClaim } from "./actions";

const TYPE_LABELS = { MANUFACTURER: "Manufacturer", EXTENDED: "Extended", SERVICE: "Service" };

/** Mirrors `warranties/show.blade.php` (`WarrantyApiController::show`). */
export default async function WarrantyDetailPage({ params }) {
  const { warrantyId } = await params;
  const warranty = await apiFetch(`/warranties/${warrantyId}`);

  const isActive = warranty.status === "ACTIVE" && warranty.days_remaining >= 0;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Vendors" }, { label: "Warranties", href: "/vendors/warranties" }, { label: warranty.asset?.asset_code ?? "Warranty" }]}
        title={warranty.asset?.asset_code ?? "Warranty"}
        description={warranty.asset?.name}
      />

      {isActive ? (
        <Alert
          variant="success"
          title={`Covered by ${warranty.vendor?.name ?? "an unnamed vendor"} until ${warranty.end_date}${warranty.days_remaining <= 30 ? ` — ${warranty.days_remaining} days remaining` : ""}`}
        />
      ) : (
        <Alert variant="info" title="Not covered." />
      )}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="flex flex-col gap-4">
          <Card>
            <CardBody>
              <dl className="grid grid-cols-2 gap-y-2 text-sm">
                <dt className="text-foreground-muted">Vendor</dt>
                <dd className="text-foreground">
                  {warranty.vendor ? (
                    <Link href={`/vendors/${warranty.vendor.id}/edit`} className="text-brand hover:underline">
                      {warranty.vendor.name}
                    </Link>
                  ) : (
                    "Unnamed vendor"
                  )}
                </dd>
                <dt className="text-foreground-muted">Type</dt>
                <dd className="text-foreground">{TYPE_LABELS[warranty.warranty_type] ?? warranty.warranty_type}</dd>
                <dt className="text-foreground-muted">Reference</dt>
                <dd className="text-foreground">{warranty.reference ?? "—"}</dd>
                <dt className="text-foreground-muted">Start date</dt>
                <dd className="text-foreground">{warranty.start_date}</dd>
                <dt className="text-foreground-muted">End date</dt>
                <dd className="text-foreground">{warranty.end_date}</dd>
                <dt className="text-foreground-muted">Coverage</dt>
                <dd className="text-foreground">{warranty.coverage ?? "—"}</dd>
                <dt className="text-foreground-muted">Exclusions</dt>
                <dd className="text-foreground">{warranty.exclusions ?? "—"}</dd>
              </dl>
            </CardBody>
          </Card>

          {warranty.can_manage ? (
            <Card>
              <CardHeader>
                <CardTitle>File a claim</CardTitle>
              </CardHeader>
              <CardBody>
                <FileClaimForm action={fileClaim.bind(null, warrantyId)} />
              </CardBody>
            </Card>
          ) : null}
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Claims</CardTitle>
          </CardHeader>
          <CardBody>
            <ClaimsList
              claims={warranty.claims ?? []}
              canDecide={warranty.can_manage}
              decideAction={decideClaim.bind(null, warrantyId)}
            />
          </CardBody>
        </Card>
      </div>
    </>
  );
}
