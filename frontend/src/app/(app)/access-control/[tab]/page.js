import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { getT } from "@/lib/i18n-server";
import { screenContext } from "@/lib/navigation";
import { SCREENS } from "./screens";

function field(t, name, type, extra = {}, group = "fields") {
  return {
    name,
    type,
    label: t(`${group}.${name}`),
    required: false,
    options: [],
    option_labels: {},
    in_list: true,
    default: null,
    auto: false,
    ...extra,
  };
}

/** The inline grids of the Lookup Tables tab (tables and their values). */
function lookupSchemas(t) {
  const table = (name, type, extra = {}) =>
    field(t, name, type, extra, "lookup_fields");
  const value = (name, type, extra = {}) =>
    field(t, name, type, extra, "value_fields");
  return {
    tableSchema: {
      fields: [
        table("code", "CODE", { required: true }),
        table("name", "TEXT", { required: true }),
        table("description", "TEXT"),
        table("active", "BOOLEAN", { default: true }),
      ],
      computed: [],
    },
    valueSchema: {
      fields: [
        value("code", "CODE", { required: true }),
        value("name", "TEXT", { required: true }),
        value("name_bn", "TEXT"),
        value("sort_order", "NUMBER"),
        value("active", "BOOLEAN", { default: true }),
      ],
      computed: [],
    },
  };
}

/**
 * Access Control (AnnotechERP 02/01), one Brickland screen per route: the
 * page header (Home › Modules › Access Control › Screen, "NN of MM"), the
 * screen's tab strip (`?tab=`), and the chosen tab's body (screens.js).
 */
export default async function AccessControlScreenPage({
  params,
  searchParams,
}) {
  const { tab: screenKey } = await params;
  const query = await searchParams;
  const render = SCREENS[screenKey];
  if (!render) notFound();

  const [t, tu, te, ts, locale, context] = await Promise.all([
    getT("access"),
    getT("user"),
    getT("erp"),
    getT("settings"),
    getLocale(),
    screenContext(`/access-control/${screenKey}`),
  ]);
  const groupLabel = (group) =>
    ts(`groups.${group}`) === `groups.${group}` ? group : ts(`groups.${group}`);

  const screen = await render({
    t,
    tu,
    te,
    tab: query.tab ?? "records",
    searchParams: query,
    locale,
    groupLabel,
    ...lookupSchemas(t),
  });
  const active = screen.tabs.some((tab) => tab.key === query.tab)
    ? query.tab
    : "records";
  const body = await screen.body();

  return (
    <>
      <ErpPageHeader
        context={context}
        title={context ? undefined : t(`tabs.${screenKey}`)}
        description={t(`descriptions.${screenKey}`)}
        icon={context ? undefined : "ShieldCheck"}
        moduleCode="ACCESS"
        locale={locale}
      />
      <ScreenTabs
        basePath={`/access-control/${screenKey}`}
        active={active}
        tabs={screen.tabs}
      />
      {body}
    </>
  );
}
