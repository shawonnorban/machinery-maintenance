import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Dev Sample Status (AnnotechERP 31, from Norban), one menu screen per
 * route: the page header and the screen. Writing shows only to whoever may:
 * new samples (.create), an edit (.update), a delete (.delete); the Pivot
 * Report needs .report, Column Setup .columns.manage.
 */
export default async function DevSamplePage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("devsample"), getLocale(), screenContext(`/dev-sample-status/${key}`), apiFetch("/auth/permissions")]);
  const can = (perm) => permissions.permissions.includes(perm);
  const body = await render({ t, can, query, key });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`screens.${key}`)}
        description={t(`descriptions.${key}`)}
        icon={context ? undefined : "FlaskConical"}
        moduleCode="DEV_SAMPLE"
        locale={locale}
      />
      {body}
    </>
  );
}
