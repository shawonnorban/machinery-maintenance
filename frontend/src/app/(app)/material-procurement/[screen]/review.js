import { apiFetch } from "@/lib/api-server";
import { PriceReview } from "@/components/materialprocurement/price-review";
import * as actions from "./actions";

/*
 * Material Procurement → PRICE REVIEW & APPROVAL (AnnotechERP 08/05): one
 * screen; its decisions are dialogs and it reads itself again in place.
 */

const reload = actions.reviewAction.bind(null, "load");

async function priceReview() {
  const data = await apiFetch("/material-procurement/price-review");
  return <PriceReview data={data} act={actions.reviewAction} reload={reload} />;
}

const REVIEW_SCREENS = { "price-review": priceReview };

export { REVIEW_SCREENS };
