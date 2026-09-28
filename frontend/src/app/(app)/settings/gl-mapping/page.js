import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { GlMappingPanel } from "@/components/finance/gl-mapping-panel";
import { getT } from "@/lib/i18n-server";
import { saveGlMapping } from "./actions";

/** Mirrors `GlMappingApiController::index` (Shared Systems spec §2). */
export default async function GlMappingPage() {
  const [screen, t, tn] = await Promise.all([
    apiFetch("/gl-mappings"),
    getT("finance"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("gl_mapping") }]}
        title={t("gl_mapping")}
        description={t("gl_mapping_hint")}
      />

      <GlMappingPanel
        mappings={screen.mappings}
        accounts={screen.accounts}
        canManage={screen.can_manage}
        onSave={saveGlMapping}
      />
    </>
  );
}
