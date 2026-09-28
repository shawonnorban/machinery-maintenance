import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { FileClaimForm } from "@/components/vendor/file-claim-form";
import { ClaimsList } from "@/components/vendor/claims-list";
import { getT } from "@/lib/i18n-server";
import { fileClaim, decideClaim } from "./actions";

const TYPE_KEYS = { MANUFACTURER: "type_manufacturer", EXTENDED: "type_extended", SERVICE: "type_service_warranty" };

/** Mirrors `warranties/show.blade.php` (`WarrantyApiController::show`). */
export default async function WarrantyDetailPage({ params }) {
  const { warrantyId } = await params;
  const [warranty, t] = await Promise.all([
    apiFetch(`/warranties/${warrantyId}`),
    getT("vendor"),
  ]);

  const isActive = warranty.status === "ACTIVE" && warranty.days_remaining >= 0;

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("vendors") },
          { label: t("warranties"), href: "/vendors/warranties" },
          { label: warranty.asset?.asset_code ?? t("warranty_title_fallback") },
        ]}
        title={warranty.asset?.asset_code ?? t("warranty_title_fallback")}
        description={warranty.asset?.name}
      />

      {isActive ? (
        <Alert
          variant="success"
          title={
            t("covered_by_warranty", { vendor: warranty.vendor?.name ?? t("unnamed_vendor"), until: warranty.end_date }) +
            (warranty.days_remaining <= 30 ? t("days_remaining_suffix", { days: warranty.days_remaining }) : "")
          }
        />
      ) : (
        <Alert variant="info" title={t("not_covered")} />
      )}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="flex flex-col gap-4">
          <Card>
            <CardBody>
              <dl className="grid grid-cols-2 gap-y-2 text-sm">
                <dt className="text-foreground-muted">{t("vendor")}</dt>
                <dd className="text-foreground">
                  {warranty.vendor ? (
                    <Link href={`/vendors/${warranty.vendor.id}/edit`} className="text-brand hover:underline">
                      {warranty.vendor.name}
                    </Link>
                  ) : (
                    t("unnamed_vendor")
                  )}
                </dd>
                <dt className="text-foreground-muted">{t("type")}</dt>
                <dd className="text-foreground">{t(TYPE_KEYS[warranty.warranty_type] ?? warranty.warranty_type)}</dd>
                <dt className="text-foreground-muted">{t("reference")}</dt>
                <dd className="text-foreground">{warranty.reference ?? "—"}</dd>
                <dt className="text-foreground-muted">{t("start_date")}</dt>
                <dd className="text-foreground">{warranty.start_date}</dd>
                <dt className="text-foreground-muted">{t("end_date")}</dt>
                <dd className="text-foreground">{warranty.end_date}</dd>
                <dt className="text-foreground-muted">{t("coverage")}</dt>
                <dd className="text-foreground">{warranty.coverage ?? "—"}</dd>
                <dt className="text-foreground-muted">{t("exclusions")}</dt>
                <dd className="text-foreground">{warranty.exclusions ?? "—"}</dd>
              </dl>
            </CardBody>
          </Card>

          {warranty.can_manage ? (
            <Card>
              <CardHeader>
                <CardTitle>{t("file_claim")}</CardTitle>
              </CardHeader>
              <CardBody>
                <FileClaimForm action={fileClaim.bind(null, warrantyId)} />
              </CardBody>
            </Card>
          ) : null}
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t("claims")}</CardTitle>
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
