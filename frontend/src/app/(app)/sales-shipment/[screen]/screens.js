import { apiFetch } from "@/lib/api-server";
import { SalesShipmentSettings } from "@/components/sales-shipment/settings";
import { SalesOrderDesk } from "@/components/sales-shipment/sales-order";
import { AmendmentDesk } from "@/components/sales-shipment/amendment";
import { ConfirmationDesk, CompletionDesk, MonthCloseDesk } from "@/components/sales-shipment/registers";
import { ChallanDesk, ChallanReturnDesk } from "@/components/sales-shipment/challan";
import { BillDesk } from "@/components/sales-shipment/bill";
import { ShipmentPlanDesk } from "@/components/sales-shipment/shipment-plan";
import { BookingDesk, ContainerDesk, HandoverDesk, InvoiceDesk, PackingDesk, ShipmentConfirmDesk, ShipmentCloseDesk } from "@/components/sales-shipment/shipment";
import { DocDesk, CertificateDesk, AttachmentDesk } from "@/components/sales-shipment/export-docs";
import { SalesReports, OrderTracking, SalesAlerts } from "@/components/sales-shipment/reports";
import { ScDeliveryDesk, ScReceiptDesk, ScPendingDesk, ScReturnDesk, ScReconDesk } from "@/components/sales-shipment/subcon";
import * as actions from "./actions";

/*
 * Sales & Shipment (AnnotechERP 21). Operation (21/01): the Sales & Shipment
 * Settings. Order & Delivery (21/02): Sales Order Entry (list, entry, S/C
 * Programme), Sales Order Amendment, Order Confirmation, Delivery Challan
 * Issue, Delivery Challan Return, Delivery Bill Verification, Delivery
 * Completion and Sales Order Month Close — each its tabs switching in place,
 * a paper addressed by its own number (?so=SO-…, ?tab=sc-programme).
 * Sub-Contract (21/03): the S/C delivery and receipt registers, Pending,
 * the S/C return and the reconciliation. General (21/04): Shipment Planning.
 * Shipment (21/05): booking, container / vessel, document handover, invoice,
 * packing list, confirmation and closing. Export Documentation (21/06):
 * export documents (four screens by family), certificates, the final
 * document handover and shipment attachments. Report (21/07): the report
 * set, Order-wise Tracking (?so=SO-…) and Notifications & Alerts.
 */

const act = { read: actions.ssRead, write: actions.ssWrite };

const settings = async ({ can }) => {
  const data = await apiFetch("/sales-shipment/settings");
  return { body: () => <SalesShipmentSettings initial={data} canManage={can("salesshipment.settings.manage")} act={{ save: actions.saveSettings }} /> };
};

const salesOrder = async ({ can, query }) => {
  const [rows, options, programmes] = await Promise.all([apiFetch("/sales-shipment/orders"), apiFetch("/sales-shipment/options"), apiFetch("/sales-shipment/sc-programmes")]);
  return { body: () => <SalesOrderDesk initial={rows} programmes={programmes} options={options} canManage={can("salesshipment.order.manage")} query={query} act={act} /> };
};

const amendment = async ({ can, query }) => {
  const [rows, options] = await Promise.all([apiFetch("/sales-shipment/amendments"), apiFetch("/sales-shipment/options")]);
  return { body: () => <AmendmentDesk initial={rows} options={options} canManage={can("salesshipment.order.manage")} canApprove={can("salesshipment.order.approve")} query={query} act={act} /> };
};

const confirmation = async ({ can }) => {
  const [rows, options] = await Promise.all([apiFetch("/sales-shipment/confirmations"), apiFetch("/sales-shipment/options")]);
  return { body: () => <ConfirmationDesk initial={rows} options={options} canManage={can("salesshipment.order.manage")} canApprove={can("salesshipment.order.approve")} act={act} /> };
};

const challan = async ({ can, query }) => {
  const [rows, options] = await Promise.all([apiFetch("/sales-shipment/challans"), apiFetch("/sales-shipment/options")]);
  return { body: () => <ChallanDesk initial={rows} options={options} canManage={can("salesshipment.delivery.manage")} query={query} act={act} /> };
};

const challanReturn = async ({ can }) => {
  const [rows, options] = await Promise.all([apiFetch("/sales-shipment/challan-returns"), apiFetch("/sales-shipment/options")]);
  return { body: () => <ChallanReturnDesk initial={rows} options={options} canManage={can("salesshipment.delivery.manage")} act={act} /> };
};

const bill = async ({ can }) => {
  const [rows, options] = await Promise.all([apiFetch("/sales-shipment/bills"), apiFetch("/sales-shipment/options")]);
  return { body: () => <BillDesk initial={rows} options={options} canManage={can("salesshipment.bill.manage")} canApprove={can("salesshipment.bill.approve")} act={act} /> };
};

const completion = async () => {
  const [rows, options] = await Promise.all([apiFetch("/sales-shipment/completion"), apiFetch("/sales-shipment/options")]);
  return { body: () => <CompletionDesk initial={rows} options={options} act={act} /> };
};

const monthClose = async ({ can }) => {
  const rows = await apiFetch("/sales-shipment/month-close");
  return { body: () => <MonthCloseDesk initial={rows} canOverride={can("salesshipment.close.override")} act={act} /> };
};

const subcon = (path, Desk, extra = () => ({})) => async ({ can }) => {
  const [rows, options] = await Promise.all([apiFetch(path), apiFetch("/sales-shipment/options")]);
  return { body: () => <Desk initial={rows} options={options} canManage={can("salesshipment.subcon.manage")} {...extra(can)} act={act} /> };
};

const edDocs = (key, family) => async ({ can }) => {
  const rows = await apiFetch(`/sales-shipment/export-docs/${key}`);
  return { body: () => <DocDesk family={family} initial={rows} canManage={can("salesshipment.exportdoc.manage")} act={act} /> };
};

const SCREENS = {
  "sd-settings": settings,
  "sales-order": salesOrder,
  "so-amendment": amendment,
  "order-confirmation": confirmation,
  "delivery-chalan": challan,
  "delivery-challan-return": challanReturn,
  "check-bill": bill,
  "delivery-completion": completion,
  "so-month-closing": monthClose,
  "sc-delivery-list": subcon("/sales-shipment/sc-deliveries", ScDeliveryDesk),
  "sc-receive-list": subcon("/sales-shipment/sc-receipts", ScReceiptDesk),
  "sc-pending": subcon("/sales-shipment/sc-pending", ScPendingDesk),
  "sc-return": subcon("/sales-shipment/sc-returns", ScReturnDesk),
  "shipment-planning": async ({ can }) => {
    const [rows, options] = await Promise.all([apiFetch("/sales-shipment/shipments"), apiFetch("/sales-shipment/options")]);
    return { body: () => <ShipmentPlanDesk initial={rows} options={options} canManage={can("salesshipment.shipment.manage")} act={act} /> };
  },
  "shipment-booking": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/bookings");
    return { body: () => <BookingDesk initial={rows} canManage={can("salesshipment.shipment.manage")} act={act} /> };
  },
  "container-vessel": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/containers");
    return { body: () => <ContainerDesk initial={rows} canManage={can("salesshipment.shipment.manage")} act={act} /> };
  },
  "commercial-doc-handover": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/handovers");
    return { body: () => <HandoverDesk initial={rows} canManage={can("salesshipment.shipment.manage")} act={act} /> };
  },
  "shipment-invoice": async ({ can }) => {
    const [rows, options] = await Promise.all([apiFetch("/sales-shipment/invoices"), apiFetch("/sales-shipment/options")]);
    return { body: () => <InvoiceDesk initial={rows} options={options} canManage={can("salesshipment.shipment.manage")} act={act} /> };
  },
  "packing-list": async ({ can }) => {
    const [rows, options] = await Promise.all([apiFetch("/sales-shipment/packing-lists"), apiFetch("/sales-shipment/options")]);
    return { body: () => <PackingDesk initial={rows} options={options} canManage={can("salesshipment.shipment.manage")} act={act} /> };
  },
  "shipment-confirmation": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/confirmations-shipment");
    return { body: () => <ShipmentConfirmDesk initial={rows} canConfirm={can("salesshipment.shipment.confirm")} act={act} /> };
  },
  "shipment-closing": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/closings");
    return { body: () => <ShipmentCloseDesk initial={rows} canConfirm={can("salesshipment.shipment.confirm")} canReopen={can("salesshipment.shipment.reopen")} act={act} /> };
  },
  "export-documents": edDocs("export", "EXPORT"),
  "certificate-of-origin": edDocs("coo", "COO"),
  "gsp-rex": edDocs("gsp", "GSP"),
  "bl-awb": edDocs("blawb", "BLAWB"),
  "certificate-management": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/certificates");
    return { body: () => <CertificateDesk initial={rows} canManage={can("salesshipment.exportdoc.manage")} act={act} /> };
  },
  "document-handover": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/final-handovers");
    return { body: () => <HandoverDesk kind="FINAL" initial={rows} canManage={can("salesshipment.exportdoc.manage")} act={act} /> };
  },
  "shipment-attachments": async ({ can }) => {
    const rows = await apiFetch("/sales-shipment/shipment-attachments");
    return { body: () => <AttachmentDesk initial={rows} canManage={can("salesshipment.exportdoc.manage")} act={act} /> };
  },
  "sd-report": async () => {
    const options = await apiFetch("/sales-shipment/reports/options");
    return { body: () => <SalesReports options={options} act={act} /> };
  },
  "order-tracking": async ({ query }) => {
    const orders = await apiFetch("/sales-shipment/order-tracking");
    const no = typeof query?.so === "string" && orders.some((o) => o.value === query.so) ? query.so : null;
    const initial = no ? await apiFetch(`/sales-shipment/order-tracking/${encodeURIComponent(no)}`) : null;
    return { body: () => <OrderTracking orders={orders} initial={initial} act={act} /> };
  },
  "sd-alerts": async () => {
    const data = await apiFetch("/sales-shipment/alerts");
    return { body: () => <SalesAlerts initial={data} /> };
  },
  "sc-reconciliation": subcon("/sales-shipment/sc-reconciliations", ScReconDesk, (can) => ({ canReopen: can("salesshipment.subcon.reopen") })),
};

export { SCREENS };
