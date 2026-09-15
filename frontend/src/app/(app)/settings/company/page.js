import { Gauge, Boxes, Wrench, ClipboardList, BellRing, CreditCard, Globe, Factory as FactoryIcon, SlidersHorizontal } from "lucide-react";
import { cn } from "@/lib/utils";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { SettingRow } from "@/components/settings/setting-row";
import { FactoryPicker } from "@/components/settings/factory-picker";
import { CompanyLogoForm } from "@/components/settings/company-logo-form";
import { updateSetting, resetSetting, uploadLogo, removeLogo } from "./actions";

const GROUP_LABELS = {
  metrics: "Metrics",
  inventory: "Inventory",
  maintenance: "Maintenance",
  work_order: "Work orders",
  notification: "Notifications",
  subscription: "Subscription",
  locale: "Locale",
  factory: "Factory defaults",
};

const GROUP_ICONS = {
  metrics: Gauge,
  inventory: Boxes,
  maintenance: Wrench,
  work_order: ClipboardList,
  notification: BellRing,
  subscription: CreditCard,
  locale: Globe,
  factory: FactoryIcon,
};

// A different tint per group rather than one repeated blue badge down the
// whole page — purely a scanning aid (SRS 20 groups have no meaningful
// "severity" of their own to tie a tone to), so the cycle is arbitrary but
// fixed per group key, not randomised per render.
const GROUP_TONE = {
  metrics: "bg-info-subtle text-info",
  inventory: "bg-brand-subtle text-brand-hover",
  maintenance: "bg-warning-subtle text-warning",
  work_order: "bg-success-subtle text-success",
  notification: "bg-danger-subtle text-danger",
  subscription: "bg-brand-subtle text-brand-hover",
  locale: "bg-info-subtle text-info",
  factory: "bg-success-subtle text-success",
};

/**
 * How this company wants the product to behave (SRS 20, ADR-054) — mirrors
 * `CompanySettingsController::index` grouping definitions by their key's
 * first segment, one card per group.
 */
export default async function CompanySettingsPage({ searchParams }) {
  const params = await searchParams;

  const [factories, definitions, settings, me] = await Promise.all([
    apiFetch("/factories?per_page=100"),
    apiFetch("/settings/definitions"),
    apiFetch(`/settings${params.factory_id ? `?factory_id=${params.factory_id}` : ""}`),
    apiFetch("/auth/me"),
  ]);

  const factory = factories.find((f) => f.id === params.factory_id) ?? null;
  const byKey = new Map(settings.map((row) => [row.key, row]));

  const groups = new Map();
  for (const definition of definitions) {
    const group = definition.key.split(".")[0];
    if (!groups.has(group)) groups.set(group, []);
    groups.get(group).push(definition);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Company settings" }]}
        title="Company settings"
        description="How this company wants the product to behave. A setting can be answered once for the company and again for a single factory — each row shows where its current answer comes from."
      />

      <div className="mb-6 flex flex-col gap-4">
        <CompanyLogoForm logoUrl={me.company?.logo_url} actions={{ upload: uploadLogo, remove: removeLogo }} />

        <div className="flex w-full flex-col gap-1.5 sm:w-64">
          <span className="text-xs font-medium text-foreground-muted">Viewing</span>
          <FactoryPicker factories={factories} value={factory?.id} />
        </div>
      </div>

      <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-2">
        {[...groups.entries()].map(([group, groupDefinitions]) => {
          const Icon = GROUP_ICONS[group] ?? SlidersHorizontal;

          return (
            <Card key={group}>
              <CardHeader>
                <div className="flex items-center gap-2.5">
                  <span className={cn("flex size-8 shrink-0 items-center justify-center rounded-full", GROUP_TONE[group] ?? GROUP_TONE.inventory)}>
                    <Icon className="size-4" />
                  </span>
                  <CardTitle>{GROUP_LABELS[group] ?? group}</CardTitle>
                </div>
              </CardHeader>
              <CardBody>
                {groupDefinitions.map((definition) => {
                  const row = byKey.get(definition.key);

                  return (
                    <SettingRow
                      key={definition.key}
                      definition={definition}
                      value={row?.value ?? definition.default_value}
                      level={row?.level ?? "PLATFORM"}
                      editableHere={row?.editable_here ?? false}
                      factoryId={factory?.id ?? null}
                      updateAction={updateSetting.bind(null, definition.key, factory?.id ?? null)}
                      resetAction={factory ? resetSetting.bind(null, definition.key, factory.id) : undefined}
                    />
                  );
                })}
              </CardBody>
            </Card>
          );
        })}
      </div>
    </>
  );
}
