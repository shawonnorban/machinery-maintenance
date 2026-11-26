import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Quality Tally (AnnotechERP 36), one screen per route: the page header and
 * the screen. Sold separately — while the platform has not switched it on
 * for the company every qtally.* permission is refused and the app shell
 * shows "not enabled" here (SetupGuard). Cutting, sewing and quality each
 * have their own menu and screens, seen by their own manager and QCs only.
 */
export default async function QualityTallyPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("qtally"), getLocale(), screenContext(`/quality-tally/${key}`), apiFetch("/auth/permissions")]);
  const can = (perm) => permissions.permissions.includes(perm);
  // Another section's address typed in: not theirs, so not there.
  const body = await render({ t, can, query, key, locale }).catch((e) => {
    if (e instanceof ApiError && e.status === 403) return null;
    throw e;
  });
  if (body === null) notFound();

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`screens.${key}`)}
        description={t(`descriptions.${key}`)}
        icon={context ? undefined : "ClipboardCheck"}
        moduleCode="QUALITY_TALLY"
        locale={locale}
      />
      {body}
    </>
  );
}
