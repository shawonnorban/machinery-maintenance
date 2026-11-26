import { getLocale } from "next-intl/server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";

/**
 * The ERP page header for a page that knows its own address (Machinery &
 * Maintenance's pages, which predate the Brickland screens): Home › Modules ›
 * Module › Screen, the screen's icon, its "NN of MM" place, the title, one
 * line of description and the page's buttons.
 *
 * A page the menu does not list itself (an asset's detail, a create form)
 * passes the list it belongs to as `parentUrl`: the header then shows that
 * screen's icon and module, and the breadcrumb runs through it (and any
 * `trail` crumbs, e.g. the asset an edit form belongs to) to `title`.
 *
 * @param {{ url?: string, parentUrl?: string, trail?: { label: string, href?: string }[], title?: React.ReactNode, crumb?: string, description?: string, actions?: React.ReactNode }} props
 */
async function ScreenHeader({ url, parentUrl, trail = [], title, crumb, description, actions }) {
  const [locale, t] = await Promise.all([getLocale(), getT("nav")]);
  const own = url ? await screenContext(url) : null;
  if (own) {
    return <ErpPageHeader context={own} title={title} description={description} actions={actions} locale={locale} />;
  }
  const parent = parentUrl ? await screenContext(parentUrl) : null;
  const name = (node) => (locale === "bn" && node?.name_bn) || node?.name;
  const breadcrumb = [
    { label: t("home"), href: "/" },
    { label: t("modules") },
    ...(parent ? [{ label: name(parent.module) }, { label: name(parent.screen), href: parent.screen.url }] : []),
    ...trail,
    // A title with a badge in it is a node; the breadcrumb takes plain text.
    { label: crumb ?? title },
  ];
  return <ErpPageHeader title={title} description={description} icon={parent?.screen?.icon} moduleCode={parent?.module?.code} breadcrumb={breadcrumb} actions={actions} locale={locale} />;
}

export { ScreenHeader };
