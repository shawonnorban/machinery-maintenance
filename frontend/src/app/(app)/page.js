import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { getT } from "@/lib/i18n-server";
import { getLocale } from "next-intl/server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { EmptyState } from "@/components/ui/empty-state";
import { LayoutDashboard } from "lucide-react";
import { HomeDashboard } from "@/components/home/dashboard";

/** A part this account may not see 403s (or its module is off) — left out, not an error. */
async function part(path) {
  try {
    return await apiFetch(path);
  } catch (error) {
    if (error instanceof ApiError && [403, 404].includes(error.status)) return null;
    throw error;
  }
}

/**
 * The home page, shown right after signing in (user, 2026-11-28): Order
 * Summary and Pre-Budget at a glance. Machinery's dashboard moved to
 * /machinery-dashboard. Each part shows only to those its module's own
 * dashboard answers; more can be added here later.
 */
export default async function HomePage() {
  const [os, pb, me, t, tn, locale] = await Promise.all([part("/order-summary/dashboard"), part("/pre-budget/dashboard"), apiFetch("/auth/me").catch(() => null), getT("dashboard"), getT("nav"), getLocale()]);

  return (
    <>
      <ErpPageHeader title={t("dashboard")} description={t("home.description")} icon="LayoutDashboard" breadcrumb={[{ label: tn("home"), href: "/" }, { label: t("dashboard") }]} locale={locale} />
      {os || pb ? (
        <HomeDashboard os={os} pb={pb} name={me?.user?.name ?? me?.name ?? ""} now={new Date().toISOString()} />
      ) : (
        <EmptyState icon={<LayoutDashboard />} title={t("home.nothing")} description={t("home.nothing_hint")} />
      )}
    </>
  );
}
