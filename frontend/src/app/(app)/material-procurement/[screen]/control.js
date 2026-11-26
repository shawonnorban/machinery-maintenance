import { apiFetch } from "@/lib/api-server";
import {
  ProcurementNotifications,
  AuditTrail,
} from "@/components/materialprocurement/control";
import * as actions from "./actions";

/*
 * Material Procurement → CONTROL (AnnotechERP 08/07): Notifications (read and
 * Mark all read in place) and the permanent Audit Trail.
 */

async function notifications() {
  const data = await apiFetch(
    "/material-procurement/procurement-notifications",
  );
  return <ProcurementNotifications data={data} act={actions.controlAction} />;
}

async function auditTrail() {
  const data = await apiFetch("/material-procurement/procurement-audit-trail");
  return <AuditTrail data={data} />;
}

const CONTROL_SCREENS = {
  "procurement-notifications": notifications,
  "procurement-audit-trail": auditTrail,
};

export { CONTROL_SCREENS };
