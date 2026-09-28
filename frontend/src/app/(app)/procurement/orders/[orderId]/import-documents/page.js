import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ImportDocumentsPanel } from "@/components/procurement/import-documents-panel";
import { getT } from "@/lib/i18n-server";
import {
  createProformaInvoice,
  confirmProformaInvoice,
  reviseProformaInvoice,
  createLetterOfCredit,
  openLetterOfCredit,
  amendLetterOfCredit,
  closeLetterOfCredit,
  createShipment,
  updateShipment,
  cancelShipment,
} from "./actions";

/** Mirrors `ImportDocumentApiController::forOrder` (Procurement spec §10). */
export default async function ImportDocumentsPage({ params }) {
  const { orderId } = await params;

  const [file, t, tc] = await Promise.all([
    apiFetch(`/purchase-orders/${orderId}/import-documents`),
    getT("procurement"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("purchase_orders"), href: "/procurement/orders" },
          { label: file.purchase_order.po_no, href: `/procurement/orders/${orderId}` },
          { label: t("import_documents") },
        ]}
        title={t("import_documents")}
        description={t("import_documents_hint")}
        actions={
          <Link href={`/procurement/orders/${orderId}`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <ImportDocumentsPanel
        file={file}
        actions={{
          createInvoice: createProformaInvoice.bind(null, orderId),
          confirmInvoice: confirmProformaInvoice.bind(null, orderId),
          reviseInvoice: reviseProformaInvoice.bind(null, orderId),
          createLetter: createLetterOfCredit.bind(null, orderId),
          openLetter: openLetterOfCredit.bind(null, orderId),
          amendLetter: amendLetterOfCredit.bind(null, orderId),
          closeLetter: closeLetterOfCredit.bind(null, orderId),
          createShipment: createShipment.bind(null, orderId),
          updateShipment: updateShipment.bind(null, orderId),
          cancelShipment: cancelShipment.bind(null, orderId),
        }}
      />
    </>
  );
}
