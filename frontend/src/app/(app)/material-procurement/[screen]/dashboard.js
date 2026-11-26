import { apiFetch } from "@/lib/api-server";
import { ProcurementDashboard } from "@/components/materialprocurement/dashboard";

/*
 * Material Procurement → DASHBOARD → Procurement Dashboard (AnnotechERP 08/02).
 * New requisition and Delivery tracking show only to whoever may open them.
 */

async function procurementDashboard({ permissions }) {
  const data = await apiFetch("/material-procurement/procurement-dashboard");
  return (
    <ProcurementDashboard
      data={data}
      canRequisition={permissions.includes("material.requisition.manage")}
      canTrack={permissions.includes("material.followup.monitor")}
    />
  );
}

const DASHBOARD_SCREENS = { "procurement-dashboard": procurementDashboard };

export { DASHBOARD_SCREENS };
