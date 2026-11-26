import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { apiFetch } from "@/lib/api-server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

/**
 * Export Statement (AnnotechERP 35, from Norban's MD Sir Export Status), one
 * menu screen per route: the page header and the screen. Writing shows only
 * to whoever may: new statements (.create), an edit (.update — waiting for
 * approval unless .approve), a delete (.delete), Excel (.import / .export);
 * Column Setup needs .columns.manage.
 */
export default async function ExportStatementPage({ params, searchParams }) {
  const { screen: key } = await params;
  const query = (await searchParams) ?? {};
  const render = SCREENS[key];
  if (!render) notFound();

  const [t, locale, context, permissions] = await Promise.all([getT("exportstatement"), getLocale(), screenContext(`/export-statement/${key}`), apiFetch("/auth/permissions")]);
  const can = (perm) => permissions.permissions.includes(perm);
  const body = await render({ t, can, query, key });

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`screens.${key}`)}
        description={t(`descriptions.${key}`)}
        icon={context ? undefined : "FileSpreadsheet"}
        moduleCode="EXPORT_STATEMENT"
        locale={locale}
      />
      {body}
    </>
  );
}
