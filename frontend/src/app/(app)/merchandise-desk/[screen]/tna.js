import { apiFetch } from "@/lib/api-server";
import { TnaFollowupDesk } from "@/components/merchandise/tna-followup-desk";
import * as actions from "./actions";

/*
 * Merchandise Desk → T&A Followup (AnnotechERP 06/12): Merchandising TNA
 * Follow-up — one working page, the order named in the url by its order no.
 */

async function tnaFollowup({ canManage, searchParams = {} }) {
  const orders = await apiFetch("/merchandise/tna-followup/orders");
  const chosen = orders.some((o) => o.value === searchParams.order) ? searchParams.order : "";
  const initial = chosen ? await apiFetch(`/merchandise/tna-followup/${encodeURIComponent(chosen)}`) : null;
  return {
    tabs: [],
    body: async () => (
      <TnaFollowupDesk
        key={chosen || "none"}
        orders={orders}
        initialOrder={chosen}
        initial={initial}
        canManage={canManage}
        actions={{ load: actions.loadTna, recalc: actions.recalculateTna, save: actions.saveTnaMilestone }}
      />
    ),
  };
}

const TNA_SCREENS = {
  "tna-followup-marchandising": tnaFollowup,
};

/** The T&A calendar is recalculated and recorded under its own permission. */
const TNA_MANAGE = {
  "tna-followup-marchandising": "merchandise.tna.manage",
};

export { TNA_SCREENS, TNA_MANAGE };
