import Link from "next/link";
import { getLocale } from "next-intl/server";
import { Building, Building2, CalendarDays, Cog, FolderOpen, ImageOff, Layers, Network, Pencil, Plus, Rows3, Users, GitFork } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { cn } from "@/lib/utils";
import { buttonVariants } from "@/components/ui/button";
import { StatusPill } from "@/components/erp/status-pill";

/**
 * Company Management → Dashboard (AnnotechERP UI parity): Brickland's module
 * landing — today's date, seven stat cards, then the Company Profile, Factory
 * Setup and Building List summaries, each opening its own screen.
 */
export default async function CompanyDashboardPage() {
  const [t, tn, te, locale, stats, profile, factories, buildings] = await Promise.all([
    getT("company"),
    getT("nav"),
    getT("erp"),
    getLocale(),
    apiFetch("/company/dashboard"),
    apiFetch("/company/profile").catch(() => null),
    apiFetch("/factories?per_page=100").catch(() => []),
    apiFetch("/master-data/buildings?per_page=10").catch(() => []),
  ]);
  const d = (key, params) => t(`dashboard.${key}`, params);
  const factory = factories[0] ?? null;
  const today = new Date();
  const dateText = new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { day: "2-digit", month: "short", year: "numeric" }).format(today);
  const dayText = new Intl.DateTimeFormat(locale === "bn" ? "bn-BD" : "en-GB", { weekday: "long" }).format(today);

  const cards = [
    { label: d("total_employee"), value: stats.employees.total, sub: d("active_count", { count: stats.employees.active }), icon: Users, tone: "bg-indigo-50 text-indigo-600" },
    { label: d("total_machine"), value: stats.machines.total, sub: d("active_count", { count: stats.machines.active }), icon: Cog, tone: "bg-rose-50 text-rose-500" },
    { label: d("total_building"), value: stats.buildings, icon: Building, tone: "bg-orange-50 text-orange-600" },
    { label: d("total_floor"), value: stats.floors, icon: Layers, tone: "bg-emerald-50 text-emerald-600" },
    { label: d("total_department"), value: stats.departments, icon: Network, tone: "bg-indigo-50 text-indigo-600" },
    { label: d("total_section"), value: stats.sections, icon: GitFork, tone: "bg-pink-50 text-pink-500" },
    { label: d("total_line"), value: stats.lines, icon: Rows3, tone: "bg-amber-50 text-amber-600" },
  ];

  return (
    <>
      <div className="flex flex-col gap-3 pb-5">
        <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-[13px] text-foreground-muted">
          <Link href="/" className="hover:text-foreground">
            {tn("home")}
          </Link>
          <span className="text-foreground-subtle">›</span>
          <span>{t("module")}</span>
          <span className="text-foreground-subtle">›</span>
          <span className="text-foreground">{tn("dashboard")}</span>
        </nav>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-4">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-600 [&_svg]:size-5">
              <Building2 />
            </span>
            <div>
              <h1 className="text-[26px] leading-tight font-bold tracking-tight text-foreground">{t("module")}</h1>
              <p className="mt-1 text-sm text-foreground-muted">{d("description")}</p>
            </div>
          </div>
          <div className="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-2.5">
            <span className="flex size-9 items-center justify-center rounded-md bg-brand-subtle text-brand">
              <CalendarDays className="size-4" />
            </span>
            <span className="leading-tight">
              <span className="block text-sm font-semibold text-foreground">{dateText}</span>
              <span className="block text-xs text-foreground-muted">{dayText}</span>
            </span>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <div key={card.label} className="rounded-lg border border-border bg-surface p-5">
            <div className="flex items-center gap-3">
              <span className={cn("flex size-9 items-center justify-center rounded-md [&_svg]:size-[18px]", card.tone)}>
                <card.icon />
              </span>
              <span className="text-sm font-semibold text-foreground">{card.label}</span>
            </div>
            <p className="mt-4 text-2xl font-bold text-foreground tabular-nums">{card.value}</p>
            {card.sub ? <p className="mt-1 text-xs text-foreground-muted">{card.sub}</p> : null}
          </div>
        ))}
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <SummaryCard title={t("tabs.company-profile")} icon={<Building2 />} tone="bg-orange-50 text-orange-600" action={<EditLink href="/company-management/company-profile" label={te("edit")} />}>
          <div className="flex gap-4">
            <Placeholder url={profile?.logo_url} label={d("no_image")} />
            <dl className="grid flex-1 grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
              <Row label={t("profile.name")} value={profile?.name} />
              <Row label={d("company_code")} value={profile?.code} />
              <Row label={t("profile.address")} value={profile?.address} />
              <Row label={d("mobile")} value={profile?.phone} />
              <Row label={t("profile.email")} value={profile?.email} />
              <Row label={t("profile.website")} value={profile?.website} />
              <Row label={t("profile.trade_license_no")} value={profile?.trade_license_no} />
              <Row label={t("profile.tin")} value={profile?.tin} />
              <Row label={t("profile.bin")} value={profile?.bin} />
              <dt className="text-foreground-muted">{d("status")}</dt>
              <dd>
                <StatusPill active={profile?.status === "ACTIVE"} label={t(`status.${profile?.status ?? "INACTIVE"}`)} />
              </dd>
            </dl>
          </div>
        </SummaryCard>

        <SummaryCard title={d("factory_setup")} icon={<FolderOpen />} tone="bg-emerald-50 text-emerald-600" action={<EditLink href="/company-management/factory" label={te("edit")} />}>
          {factory ? (
            <div className="flex gap-4">
              <Placeholder label={d("no_image")} />
              <dl className="grid flex-1 grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <Row label={d("factory_name")} value={factory.name} />
                <Row label={d("factory_code")} value={factory.code} />
                <Row label={t("fields.address")} value={factory.address} />
                <Row label={t("fields.contact")} value={factory.contact} />
                <Row label={d("established")} value={factory.established_date} />
                <Row label={d("factory_area")} value={factory.area_sqft ? `${Number(factory.area_sqft).toLocaleString()} sq ft` : null} />
                <dt className="text-foreground-muted">{d("status")}</dt>
                <dd>
                  <StatusPill active={factory.status !== "INACTIVE"} label={t(`status.${factory.status === "INACTIVE" ? "INACTIVE" : "ACTIVE"}`)} />
                </dd>
              </dl>
            </div>
          ) : (
            <p className="py-6 text-center text-sm text-foreground-muted">{d("no_factory")}</p>
          )}
        </SummaryCard>

        <SummaryCard
          title={d("building_list")}
          icon={<Building />}
          tone="bg-indigo-50 text-indigo-600"
          action={
            <Link href="/company-management/building" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {d("add_building")}
            </Link>
          }
        >
          {buildings.length === 0 ? (
            <p className="py-6 text-center text-sm text-foreground-muted">{d("no_building")}</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    <th className="px-3 py-2 text-xs font-semibold">#</th>
                    <th className="px-3 py-2 text-xs font-semibold">{d("building_code")}</th>
                    <th className="px-3 py-2 text-xs font-semibold">{d("building_name")}</th>
                    <th className="px-3 py-2 text-right text-xs font-semibold">{d("floors")}</th>
                    <th className="px-3 py-2 text-xs font-semibold">{d("status")}</th>
                  </tr>
                </thead>
                <tbody>
                  {buildings.map((b, i) => (
                    <tr key={b.id} className="border-b border-border last:border-0">
                      <td className="px-3 py-2 text-foreground-muted">{i + 1}</td>
                      <td className="px-3 py-2 font-medium">{b.code}</td>
                      <td className="px-3 py-2">{b.name}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{b.number_of_floors ?? b.floors_registered ?? "—"}</td>
                      <td className="px-3 py-2">
                        <StatusPill active={(b.status ?? "ACTIVE") === "ACTIVE"} label={t(`status.${b.status === "INACTIVE" ? "INACTIVE" : "ACTIVE"}`)} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </SummaryCard>
      </div>
    </>
  );
}

function SummaryCard({ title, icon, tone, action, children }) {
  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
        <div className="flex items-center gap-3">
          <span className={cn("flex size-9 items-center justify-center rounded-md [&_svg]:size-[18px]", tone)}>{icon}</span>
          <h2 className="text-base font-semibold text-foreground">{title}</h2>
        </div>
        {action}
      </div>
      <div className="p-5">{children}</div>
    </div>
  );
}

function EditLink({ href, label }) {
  return (
    <Link href={href} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
      <Pencil /> {label}
    </Link>
  );
}

function Placeholder({ url, label }) {
  return url ? (
    // eslint-disable-next-line @next/next/no-img-element -- a tenant-hosted logo
    <img src={url} alt="" className="size-24 shrink-0 rounded-lg border border-border object-contain" />
  ) : (
    <div className="flex size-24 shrink-0 flex-col items-center justify-center gap-1 rounded-lg border border-border bg-surface-muted text-xs text-foreground-muted">
      <ImageOff className="size-5" />
      {label}
    </div>
  );
}

function Row({ label, value }) {
  return (
    <>
      <dt className="text-foreground-muted">{label}</dt>
      <dd className="text-foreground">{value || "—"}</dd>
    </>
  );
}

