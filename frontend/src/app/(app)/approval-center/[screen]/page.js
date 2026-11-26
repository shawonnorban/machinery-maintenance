import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Approval Center (AnnotechERP 04), one Brickland screen per route: the page
 * header (Home › Modules › Approval Center › Screen, "NN of 10") and the
 * screen's own card. These screens have no tab strip, as in Brickland.
 */
export default async function ApprovalCenterScreenPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = await searchParams;
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, te, locale, context] = await Promise.all([getT("approval"), getT("erp"), getLocale(), screenContext(`/approval-center/${key}`)]);
  const body = await render({ t, te, key, searchParams: query, locale });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`center.screens.${key}`)}
        description={t(`center.descriptions.${key}`)}
        icon={context ? undefined : "CheckCircle2"}
        moduleCode="APPROVAL"
        locale={locale}
      />
      {body}
    </>
  );
}
