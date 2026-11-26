import { apiFetch } from "@/lib/api-server";
import { AvailableRequirements, QuotationHistory, SupplierDashboard } from "@/components/materialprocurement/supplier-portal";
import * as actions from "./actions";

/*
 * Material Procurement → SUPPLIER PORTAL (AnnotechERP 08/04): the Supplier
 * Dashboard, Available Requirements and Quotation History of a supplier's
 * portal login; a quotation is submitted in a dialog, and the screen reads
 * itself again in place.
 */

const quote = actions.portalAction.bind(null, "quote");
const reloadDashboard = actions.portalAction.bind(null, "dashboard");
const reloadRequirements = actions.portalAction.bind(null, "requirements");
const reloadQuotations = actions.portalAction.bind(null, "quotations");

async function supplierDashboard() {
  const [data, requirements] = await Promise.all([apiFetch("/material-procurement/portal/dashboard"), apiFetch("/material-procurement/portal/requirements")]);
  return <SupplierDashboard data={data} requirements={requirements} quote={quote} reloadDashboard={reloadDashboard} />;
}

async function availableRequirements() {
  const data = await apiFetch("/material-procurement/portal/requirements");
  return <AvailableRequirements data={data} quote={quote} reload={reloadRequirements} />;
}

async function quotationHistory() {
  const data = await apiFetch("/material-procurement/portal/quotations");
  return <QuotationHistory data={data} quote={quote} reload={reloadQuotations} />;
}

const PORTAL_SCREENS = { "supplier-dashboard": supplierDashboard, "available-requirements": availableRequirements, "quotation-history": quotationHistory };

export { PORTAL_SCREENS };
