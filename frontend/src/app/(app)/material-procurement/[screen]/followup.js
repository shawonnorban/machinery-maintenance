import { apiFetch } from "@/lib/api-server";
import {
  AssignedMaterials,
  DeliveryTracking,
} from "@/components/materialprocurement/followup";
import * as actions from "./actions";

/*
 * Material Procurement → APPROVED MATERIAL & FOLLOW-UP (AnnotechERP 08/06):
 * Assigned Materials (its scope and the opened line switch in place) and
 * Delivery Tracking.
 */

async function assignedMaterials() {
  const data = await apiFetch("/material-procurement/assigned-materials");
  return <AssignedMaterials data={data} act={actions.followupAction} />;
}

async function deliveryTracking() {
  const data = await apiFetch("/material-procurement/delivery-tracking");
  return <DeliveryTracking data={data} />;
}

const FOLLOWUP_SCREENS = {
  "assigned-materials": assignedMaterials,
  "delivery-tracking": deliveryTracking,
};

export { FOLLOWUP_SCREENS };
