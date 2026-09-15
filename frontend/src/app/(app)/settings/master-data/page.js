import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card } from "@/components/ui/card";

// Mirrors MasterDataRegistry::GROUPS' order and lang/en/masterdata.php's group labels.
const GROUP_ORDER = ["organisation", "asset", "breakdown", "maintenance", "inventory", "cost"];
const GROUP_LABELS = {
  organisation: "Organisation",
  asset: "Machines",
  breakdown: "Breakdowns",
  maintenance: "Maintenance",
  inventory: "Spare parts",
  cost: "Cost",
};

/** Mirrors `MasterDataController::index` — one screen for two dozen lists, grouped the same way the registry itself groups them. */
export default async function MasterDataIndexPage() {
  const types = await apiFetch("/master-data");

  const grouped = GROUP_ORDER.map((group) => ({
    group,
    label: GROUP_LABELS[group] ?? group,
    types: types.filter((t) => t.group === group),
  })).filter((g) => g.types.length > 0);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Settings" }, { label: "Master Data" }]} title="Master Data" description="The reference lists the rest of the system is written in." />

      <div className="flex flex-col gap-6">
        {grouped.map((section) => (
          <div key={section.group}>
            <h2 className="mb-3 text-sm font-semibold tracking-wide text-foreground-muted uppercase">{section.label}</h2>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {section.types.map((type) => (
                <Link key={type.key} href={`/settings/master-data/${type.key}`}>
                  <Card className="p-4 transition-shadow hover:shadow-sm">
                    <div className="flex items-center justify-between">
                      <span className="font-medium text-foreground">{type.title}</span>
                      <span className="tabular text-xs text-foreground-muted">{type.count}</span>
                    </div>
                  </Card>
                </Link>
              ))}
            </div>
          </div>
        ))}
      </div>
    </>
  );
}
