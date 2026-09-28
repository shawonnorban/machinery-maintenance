import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { DeclarationDetail } from "@/components/compliance/declaration-detail";
import { getT } from "@/lib/i18n-server";
import { issueDeclaration, closeDeclaration, cancelDeclaration } from "./actions";

/** Mirrors `BondComplianceApiController::showDeclaration` (Shared Systems spec §3.2). */
export default async function DeclarationPage({ params }) {
  const { declarationId } = await params;

  const [declaration, t, tc] = await Promise.all([
    apiFetch(`/utilisation-declarations/${declarationId}`),
    getT("compliance"),
    getT("common"),
  ]);

  const back = declaration.licence
    ? `/compliance/bond-licences/${declaration.licence.id}`
    : "/compliance/bond-licences";

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("compliance") },
          { label: t("bond_licences"), href: "/compliance/bond-licences" },
          ...(declaration.licence ? [{ label: declaration.licence.licence_no, href: back }] : []),
          { label: declaration.ud_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {declaration.ud_no}
            <StatusBadge
              status={declaration.status}
              label={t(`declaration_statuses.${declaration.status}`)}
            />
          </span>
        }
        description={t("declaration_hint")}
        actions={
          <Link href={back} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <DeclarationDetail
        declaration={declaration}
        actions={{
          issue: issueDeclaration.bind(null, declarationId),
          close: closeDeclaration.bind(null, declarationId),
          cancel: cancelDeclaration.bind(null, declarationId),
        }}
      />
    </>
  );
}
