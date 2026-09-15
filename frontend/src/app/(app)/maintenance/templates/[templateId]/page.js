import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { StatusBadge } from "@/components/ui/status-badge";
import { TemplateHeaderActions } from "@/components/maintenance/template-header-actions";
import { TemplateVersionPanel } from "@/components/maintenance/template-version-panel";
import { startDraft, publishVersion, addItem, removeItem } from "./actions";

/** Mirrors `TemplateController::show`/`::version`. */
export default async function MaintenanceTemplateDetailPage({ params, searchParams }) {
  const { templateId } = await params;
  const sp = await searchParams;
  const versionId = sp.version;

  const [template, options] = await Promise.all([
    apiFetch(`/maintenance-templates/${templateId}${versionId ? `?version=${versionId}` : ""}`),
    apiFetch("/maintenance-templates/form-options"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Maintenance" }, { label: "Templates", href: "/maintenance/templates" }, { label: template.name }]}
        title={template.name}
        description={template.code}
        actions={<TemplateHeaderActions template={template} actions={{ startDraft, publishVersion }} />}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div className="lg:col-span-9">
          <TemplateVersionPanel
            templateId={templateId}
            version={template.version}
            inputTypes={options.input_types}
            canEdit={template.is_editable}
            actions={{ addItem, removeItem }}
          />
        </div>

        <div className="flex flex-col gap-4 lg:col-span-3">
          <Card>
            <CardBody>
              <h2 className="mb-3 text-sm font-medium text-foreground">Versions</h2>
              <div className="flex flex-col gap-1">
                {template.versions.map((v) => (
                  <Link
                    key={v.id}
                    href={`/maintenance/templates/${templateId}?version=${v.id}`}
                    className={cn(
                      "flex items-center justify-between rounded-sm px-2 py-1.5 text-sm",
                      v.id === template.version?.id ? "bg-brand-subtle text-brand-hover" : "text-foreground-muted hover:bg-surface-muted",
                    )}
                  >
                    <span>Version {v.version_number}</span>
                    <StatusBadge status={v.status} />
                  </Link>
                ))}
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="flex flex-col gap-2 text-sm">
              <div className="flex justify-between">
                <span className="text-foreground-muted">Asset type</span>
                <span className="text-foreground">{template.asset_type ?? "—"}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-foreground-muted">Maintenance type</span>
                <span className="text-foreground">{template.maintenance_type ?? "—"}</span>
              </div>
              {template.version?.estimated_duration_minutes ? (
                <div className="flex justify-between">
                  <span className="text-foreground-muted">Duration</span>
                  <span className="text-foreground">{template.version.estimated_duration_minutes} min</span>
                </div>
              ) : null}
              {template.version?.published_at ? (
                <div className="flex justify-between">
                  <span className="text-foreground-muted">Published</span>
                  <span className="text-foreground">{template.version.published_at.slice(0, 10)}</span>
                </div>
              ) : null}
              {!template.is_editable ? (
                <p className="mt-2 border-t border-border pt-2 text-xs text-foreground-muted">
                  A platform checklist — shared with every tenant, editable by none. Clone it to write your own wording.
                </p>
              ) : null}
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}
