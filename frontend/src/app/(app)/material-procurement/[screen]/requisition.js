import { apiFetch } from "@/lib/api-server";
import { CreateRequisition, MyRequisitions } from "@/components/materialprocurement/requisition";
import { SupplierLogins } from "@/components/materialprocurement/supplier-logins";
import * as actions from "./actions";

/*
 * Material Procurement → PURCHASE REQUISITION (AnnotechERP 08/03). Create
 * Requisition: the choice of By Order or Direct, then the form; a draft
 * opens straight into it by its number (`?draft=PR-2026-00007`). My
 * Requisitions: every requisition, one opened in place (`?open=PR-…`).
 * CONTROL → Supplier Portal Access (08/07): the suppliers' portal logins.
 */

const saveRequisition = actions.requisitionAction.bind(null, "save");
const safe = async (path) => {
  try {
    return await apiFetch(path);
  } catch {
    return null;
  }
};

async function createRequisition({ searchParams, permissions }) {
  const [options, draft] = await Promise.all([apiFetch("/material-procurement/requisitions/options"), searchParams.draft ? safe(`/material-procurement/requisitions/${encodeURIComponent(searchParams.draft)}`) : null]);
  const editable = draft?.editable ? draft : null;
  return <CreateRequisition key={editable?.pr_no ?? "new"} options={options} draft={editable} canManage={permissions.includes("material.requisition.manage")} save={saveRequisition} />;
}

async function myRequisitions({ searchParams, permissions }) {
  const rows = await apiFetch("/material-procurement/requisitions");
  return <MyRequisitions initial={rows} initialOpen={searchParams.open} canManage={permissions.includes("material.requisition.manage")} act={actions.requisitionAction} />;
}

async function supplierPortalAccess() {
  const data = await apiFetch("/material-procurement/supplier-logins");
  return <SupplierLogins initial={data} act={actions.supplierLoginAction} />;
}

const REQUISITION_SCREENS = { "create-requisition": createRequisition, "my-requisitions": myRequisitions, "supplier-portal-access": supplierPortalAccess };

export { REQUISITION_SCREENS };
