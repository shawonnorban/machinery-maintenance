import Link from "next/link";
import { MapPin, Boxes } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { EmptyState } from "@/components/ui/empty-state";
import { getT } from "@/lib/i18n-server";

/**
 * Where a scanned location QR code lands — replaces Blade's
 * `scan/location.blade.php`. Lists every machine currently standing there,
 * which is how a stock-take or an audit walk is performed (Data
 * Dictionary 5.3); each row links straight into that asset's own detail
 * page, no intermediate pick-a-machine step.
 */
export default async function ScanLocationPage({ params }) {
  const { code } = await params;
  const [t, ta] = await Promise.all([getT("scan"), getT("asset")]);

  let data;
  try {
    data = await apiFetch(`/scan/locations/${code}`);
  } catch (error) {
    if (error instanceof ApiError && (error.status === 404 || error.status === 403)) {
      return <NotFound t={t} />;
    }
    throw error;
  }

  const { location, assets } = data;

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("scan") }]} title={location.name} description={location.full_path} />

      <div className="mx-auto flex max-w-lg flex-col gap-4">
        <p className="text-sm font-medium text-foreground-muted">
          {t(assets.length === 1 ? "machine_here_singular" : "machines_here_plural", { count: assets.length })}
        </p>

        {assets.length === 0 ? (
          <Card>
            <CardBody>
              <EmptyState icon={<Boxes />} title={t("no_assets_here")} description={t("no_assets_here_hint")} />
            </CardBody>
          </Card>
        ) : (
          <Card>
            <CardBody className="flex flex-col divide-y divide-border p-0">
              {assets.map((asset) => (
                <Link
                  key={asset.id}
                  href={`/assets/${asset.id}`}
                  className="flex items-center justify-between gap-3 px-5 py-3 hover:bg-surface-muted"
                >
                  <div>
                    <p className="text-sm font-medium text-brand">{asset.asset_code}</p>
                    <p className="text-xs text-foreground-muted">
                      {asset.name}
                      {asset.type ? ` · ${asset.type}` : ""}
                    </p>
                  </div>
                  <StatusBadge status={asset.status} label={ta(`status_${asset.status?.toLowerCase()}`)} />
                </Link>
              ))}
            </CardBody>
          </Card>
        )}
      </div>
    </>
  );
}

function NotFound({ t }) {
  return (
    <div className="mx-auto max-w-lg">
      <Card>
        <CardBody>
          <EmptyState icon={<MapPin />} title={t("location_not_found_title")} description={t("location_not_found_hint")} />
        </CardBody>
      </Card>
    </div>
  );
}
