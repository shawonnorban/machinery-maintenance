"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { getT } from "@/lib/i18n-server";

/*
 * Merchandise Desk → Master Data (AnnotechERP 06/01): the screen kit's
 * resources, and the two screens with their own shape — a fiber composition's
 * parts and a fabric type's garment parts.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(extra = {}) {
  revalidatePath("/merchandise-desk", "layout");
  return { status: "success", ...extra };
}

function body(formData, booleans) {
  const out = {};
  for (const [key, value] of formData.entries()) if (key !== "__intent") out[key] = value === "" ? null : value;
  for (const key of booleans) out[key] = formData.get(key) === "1";
  // A multi-pick ("office_ids") arrives as "a,b,c" from the form and goes to the API as a list.
  for (const key of Object.keys(out)) if (key.endsWith("_ids")) out[key] = out[key] ? String(out[key]).split(",").filter(Boolean) : [];
  return out;
}

/** TNA Task Rights → Records: remove one user's right to one task. */
async function removeTnaRight(id) {
  try {
    await apiFetch(`/merchandise/tna-rights/entries/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function createResource(resource, booleans, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}`, { method: "POST", body: JSON.stringify(body(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function updateResource(resource, booleans, id, previousState, formData) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "PATCH", body: JSON.stringify(body(formData, booleans)) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function deleteResource(resource, id) {
  try {
    await apiFetch(`/erp/${resource}/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** A fiber composition with its parts, new (`id` null) or edited. */
async function saveComposition(id, payload) {
  try {
    await apiFetch(id ? `/erp/fiber-compositions/${id}` : "/erp/fiber-compositions", { method: id ? "PATCH" : "POST", body: JSON.stringify(payload) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadGarmentParts(fabricTypeId) {
  try {
    return { status: "success", parts: await apiFetch(`/merchandise/fabric-types/${fabricTypeId}/garment-parts`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveGarmentParts(fabricTypeId, ids) {
  try {
    const parts = await apiFetch(`/merchandise/fabric-types/${fabricTypeId}/garment-parts`, { method: "PUT", body: JSON.stringify({ garment_part_ids: ids }) });
    return done({ parts });
  } catch (error) {
    return fail(error);
  }
}

/** A "pick one, tick the rest" list: an office's buyers, a buyer's offices, a user's buyers, a buyer's test labs. */
async function loadMapping(kind, subject) {
  try {
    return { status: "success", rows: await apiFetch(`/merchandise/mappings/${kind}/${subject}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveMapping(kind, subject, ids) {
  try {
    const rows = await apiFetch(`/merchandise/mappings/${kind}/${subject}`, { method: "PUT", body: JSON.stringify({ ids }) });
    return done({ rows });
  } catch (error) {
    return fail(error);
  }
}

async function loadSamples(buyer) {
  try {
    return { status: "success", rows: await apiFetch(`/merchandise/buyers/${buyer}/sample-types`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveSamples(buyer, samples) {
  try {
    const rows = await apiFetch(`/merchandise/buyers/${buyer}/sample-types`, { method: "PUT", body: JSON.stringify({ samples }) });
    return done({ rows });
  } catch (error) {
    return fail(error);
  }
}

/** Inquiry Rate Chart: an office's rates, saved as a whole; a new effective date adds a revision. */
async function saveRateChart(office, rates) {
  try {
    const rows = await apiFetch(`/merchandise/rate-chart/${office}`, { method: "PUT", body: JSON.stringify({ rates }) });
    return done({ rows });
  } catch (error) {
    return fail(error);
  }
}

/** A trims spec template with its item rules and controls, new (`id` null) or edited. */
async function saveTrimSpec(id, payload) {
  try {
    const row = await apiFetch(id ? `/erp/trim-spec-templates/${id}` : "/erp/trim-spec-templates", { method: id ? "PATCH" : "POST", body: JSON.stringify(payload) });
    return done({ row });
  } catch (error) {
    return fail(error);
  }
}

async function saveTemplateTasks(template, tasks) {
  try {
    const calendar = await apiFetch(`/merchandise/tna-templates/${template}/tasks`, { method: "PUT", body: JSON.stringify({ tasks }) });
    return done({ calendar });
  } catch (error) {
    return fail(error);
  }
}

async function copyTemplate(template) {
  try {
    const copy = await apiFetch(`/merchandise/tna-templates/${template}/copy`, { method: "POST", body: JSON.stringify({}) });
    const t = await getT("merchandise");
    return done({ message: t("master.tna.copied", { code: copy.code }) });
  } catch (error) {
    return fail(error);
  }
}

async function loadTnaRights(user) {
  try {
    return { status: "success", rows: await apiFetch(`/merchandise/tna-rights/${user}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveTnaRights(user, rights) {
  try {
    const rows = await apiFetch(`/merchandise/tna-rights/${user}`, { method: "PUT", body: JSON.stringify({ rights }) });
    return done({ rows });
  } catch (error) {
    return fail(error);
  }
}

/* Inquiry (AnnotechERP 06/05): moving an inquiry along, and a development style with its size ranges. */

async function moveInquiry(id, move) {
  try {
    const result = await apiFetch(`/merchandise/inquiries/${id}/${move}`, { method: "POST" });
    return done({ message: result.message, inquiry: result.inquiry });
  } catch (error) {
    return fail(error);
  }
}

async function saveStyle(id, payload) {
  try {
    const style = await apiFetch(id ? `/merchandise/styles/${id}` : "/merchandise/styles", { method: id ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ style });
  } catch (error) {
    return fail(error);
  }
}

async function deleteStyle(id) {
  try {
    await apiFetch(`/merchandise/styles/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function addSizeRange(styleId, payload) {
  try {
    const lines = await apiFetch(`/merchandise/styles/${styleId}/size-ranges`, { method: "POST", body: JSON.stringify(payload) });
    return done({ lines });
  } catch (error) {
    return fail(error);
  }
}

async function removeSizeRange(id) {
  try {
    await apiFetch(`/merchandise/style-size-ranges/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Item/Combo/Model Registration: a new item on a style's desk, or a change to one. */
async function saveStyleItem(styleId, itemId, payload) {
  try {
    const item = await apiFetch(itemId ? `/merchandise/style-items/${itemId}` : `/merchandise/styles/${styleId}/items`, { method: itemId ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ item });
  } catch (error) {
    return fail(error);
  }
}

/** Colour Mapping: the style's colour grid, saved together. */
async function saveStyleColours(styleId, payload) {
  try {
    const rows = await apiFetch(`/merchandise/styles/${styleId}/colours`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ rows });
  } catch (error) {
    return fail(error);
  }
}

/** Fabrication: the style's fabric list and knitting / YD instructions, saved together. */
async function saveStyleFabrication(styleId, payload) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/fabrication`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

/** The desk's tabs read their data when they open, without a page navigation. */
async function loadStyleColours(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/colours`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadStyleFabrication(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/fabrication`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveStyleServices(styleId, payload) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/services`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadStyleServices(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/services`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveStyleBom(styleId, payload) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/bom`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function approveStyleBom(styleId) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/bom/approve`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadStyleBom(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/bom`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveStyleFabricMapping(styleId, payload) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/fabric-mapping`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadStyleFabricMapping(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/fabric-mapping`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveOrderSizes(orderId, payload) {
  try {
    const data = await apiFetch(`/merchandise/orders/${orderId}/sizes`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadOrderConfirmationStyles(buyerId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/order-confirmation/styles?buyer=${encodeURIComponent(buyerId)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function confirmOrders(payload) {
  try {
    const data = await apiFetch("/merchandise/order-confirmation", { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadStyleOrders(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/orders`) };
  } catch (error) {
    return fail(error);
  }
}

async function createStyleOrder(styleId, payload) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/orders`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function updateStyleOrder(orderId, payload) {
  try {
    const data = await apiFetch(`/merchandise/style-orders/${orderId}`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function drawCallOff(projectionId, payload) {
  try {
    const data = await apiFetch(`/merchandise/projections/${projectionId}/call-offs`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveStylePacking(styleId, payload) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/packing`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadStylePacking(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/packing`) };
  } catch (error) {
    return fail(error);
  }
}

/** Pending Fiber Combination: one rate given its fibre combination template. */
async function assignFiberTemplate(rateId, template) {
  try {
    await apiFetch(`/erp/merch-fab-process-rates/${rateId}`, { method: "PATCH", body: JSON.stringify({ fiber_template: template }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Development Style Catalogue and its wizard (AnnotechERP 06/07). */
async function loadDevStyles() {
  try {
    const [index, inbox, sent] = await Promise.all([apiFetch("/merchandise/dev-styles"), apiFetch("/merchandise/dev-styles/inbox"), apiFetch("/merchandise/dev-styles/sent")]);
    return { status: "success", data: { ...index, inbox_rows: inbox, sent_rows: sent } };
  } catch (error) {
    return fail(error);
  }
}

async function loadStyleItems(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/items`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadDevReview(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/review`) };
  } catch (error) {
    return fail(error);
  }
}

async function devStyleAct(styleId, move, note) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/approval/${move}`, { method: "POST", body: JSON.stringify({ note: note || null }) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadStyleAttachments(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/attachments`) };
  } catch (error) {
    return fail(error);
  }
}

async function detachStyleFile(styleId, kind) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/attachments/${kind}`, { method: "DELETE" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadPackingDetails(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}/packing-details`) };
  } catch (error) {
    return fail(error);
  }
}

async function savePackingDetails(styleId, payload) {
  try {
    const data = await apiFetch(`/merchandise/styles/${styleId}/packing-details`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadStyle(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/styles/${styleId}`) };
  } catch (error) {
    return fail(error);
  }
}

/** Style Registration Entry (AnnotechERP 06/07). */
async function loadRegistrations() {
  try {
    return { status: "success", data: await apiFetch("/merchandise/style-registrations") };
  } catch (error) {
    return fail(error);
  }
}

async function loadRegistrationCandidates() {
  try {
    return { status: "success", data: await apiFetch("/merchandise/style-registrations/candidates") };
  } catch (error) {
    return fail(error);
  }
}

async function raiseRegistration(styleId) {
  try {
    const data = await apiFetch("/merchandise/style-registrations", { method: "POST", body: JSON.stringify({ style_id: styleId }) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadRegistration(id) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/style-registrations/${id}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveRegistration(id, payload) {
  try {
    const data = await apiFetch(`/merchandise/style-registrations/${id}`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function moveRegistration(id, move, note) {
  try {
    const path = move === "change" ? "change" : move;
    const data = await apiFetch(`/merchandise/style-registrations/${id}/${path}`, { method: "POST", body: JSON.stringify({ note: note || null }) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function discardRegistration(id) {
  try {
    await apiFetch(`/merchandise/style-registrations/${id}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

/** Print & Embroidery Rate Setup → Pendings: a rate confirmed (costable, and closed to changes). */
async function confirmEmbellishmentRate(id) {
  try {
    await apiFetch(`/erp/merch-embellishment-rates/${id}`, { method: "PATCH", body: JSON.stringify({ confirmed: true }) });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function dropEmbellishmentRate(id) {
  return deleteResource("merch-embellishment-rates", id);
}

async function saveCadSizes(recordId, payload) {
  try {
    const data = await apiFetch(`/merchandise/cad-records/${recordId}/sizes`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadBudget(orderId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/orders/${orderId}/budget`) };
  } catch (error) {
    return fail(error);
  }
}

/** Bill of Material lines the order's fabric booking and its style's BOM already say, to load onto the sheet. */
async function loadBudgetSources(orderId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/orders/${orderId}/budget/sources`) };
  } catch (error) {
    return fail(error);
  }
}

/** A trims item on a budget's accessory line: its code, unit, BOM consumption and what each supplier asks for it. */
async function loadBudgetItem(orderId, params) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/orders/${orderId}/budget/item?${new URLSearchParams(params)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveBudget(orderId, payload) {
  try {
    const data = await apiFetch(`/merchandise/orders/${orderId}/budget`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveBudgetActuals(budgetId, group, payload) {
  try {
    const data = await apiFetch(`/merchandise/budgets/${budgetId}/actuals/${group}`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function decideBudget(budgetId, payload) {
  try {
    const data = await apiFetch(`/merchandise/budgets/${budgetId}/approval`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveLabdipShades(programId, payload) {
  try {
    const data = await apiFetch(`/merchandise/labdip-programs/${programId}/shades`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function addSampleLine(programId, payload) {
  try {
    const data = await apiFetch(`/merchandise/sample-programs/${programId}/lines`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveSampleLines(programId, payload) {
  try {
    const data = await apiFetch(`/merchandise/sample-programs/${programId}/lines`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function savePrintLines(programId, payload) {
  try {
    const data = await apiFetch(`/merchandise/print-programs/${programId}/lines`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadSampleProduction() {
  try {
    return { status: "success", data: await apiFetch("/merchandise/sample-production") };
  } catch (error) {
    return fail(error);
  }
}

async function showSampleBatch(batchId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/sample-batches/${batchId}`) };
  } catch (error) {
    return fail(error);
  }
}

async function openSampleBatch(payload) {
  try {
    const data = await apiFetch("/merchandise/sample-batches", { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function addToSampleBatch(batchId, lineIds) {
  try {
    const data = await apiFetch(`/merchandise/sample-batches/${batchId}/lines`, { method: "POST", body: JSON.stringify({ line_ids: lineIds }) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function dropFromSampleBatch(batchId, lineId) {
  try {
    const data = await apiFetch(`/merchandise/sample-batches/${batchId}/lines/${lineId}`, { method: "DELETE" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveSampleBatch(batchId, payload) {
  try {
    const data = await apiFetch(`/merchandise/sample-batches/${batchId}`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function submitSampleBatch(batchId) {
  try {
    const data = await apiFetch(`/merchandise/sample-batches/${batchId}/submit`, { method: "POST", body: "{}" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function addChallanItem(challanId, payload) {
  try {
    const data = await apiFetch(`/merchandise/sample-challans/${challanId}/items`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveChallanItems(challanId, payload) {
  try {
    const data = await apiFetch(`/merchandise/sample-challans/${challanId}/items`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function setBulkShade(feedbackId, approved) {
  try {
    const data = await apiFetch(`/erp/merch-sample-feedback/${feedbackId}`, { method: "PATCH", body: JSON.stringify({ bulk_shade_approved: approved }) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function addMeasurePoint(chartId, payload) {
  try {
    const data = await apiFetch(`/merchandise/measure-charts/${chartId}/points`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveMeasurePoints(chartId, payload) {
  try {
    const data = await apiFetch(`/merchandise/measure-charts/${chartId}/points`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveFabricBooking(type, bookingNo, payload) {
  try {
    const data = await apiFetch(bookingNo ? `/merchandise/fabric-bookings/${encodeURIComponent(bookingNo)}` : `/merchandise/fabric-bookings?type=${type}`, { method: bookingNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteFabricBooking(bookingNo) {
  try {
    await apiFetch(`/merchandise/fabric-bookings/${encodeURIComponent(bookingNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function submitFabricBooking(bookingNo) {
  try {
    const data = await apiFetch(`/merchandise/fabric-bookings/${encodeURIComponent(bookingNo)}/submit`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveFabricRequisition(type, bookingNo, payload) {
  try {
    const data = await apiFetch(bookingNo ? `/merchandise/fabric-requisitions/${encodeURIComponent(bookingNo)}` : `/merchandise/fabric-requisitions?type=${type}`, { method: bookingNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteFabricRequisition(bookingNo) {
  try {
    await apiFetch(`/merchandise/fabric-requisitions/${encodeURIComponent(bookingNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function accRequisitionBom(orderId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-requisitions/bom?order_id=${encodeURIComponent(orderId)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadAccRequisition(reqNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-requisitions/${encodeURIComponent(reqNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveAccRequisition(reqNo, payload) {
  try {
    const data = await apiFetch(reqNo ? `/merchandise/acc-requisitions/${encodeURIComponent(reqNo)}` : "/merchandise/acc-requisitions", { method: reqNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function transitionAccRequisition(reqNo, action) {
  try {
    const data = await apiFetch(`/merchandise/acc-requisitions/${encodeURIComponent(reqNo)}/${encodeURIComponent(action)}`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteAccRequisition(reqNo) {
  try {
    await apiFetch(`/merchandise/acc-requisitions/${encodeURIComponent(reqNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadAccPurchaseOrder(poNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-purchase-orders/${encodeURIComponent(poNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveAccPurchaseOrder(poNo, payload) {
  try {
    const data = await apiFetch(poNo ? `/merchandise/acc-purchase-orders/${encodeURIComponent(poNo)}` : "/merchandise/acc-purchase-orders", { method: poNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteAccPurchaseOrder(poNo) {
  try {
    await apiFetch(`/merchandise/acc-purchase-orders/${encodeURIComponent(poNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadPoRequisitions(buyerId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-purchase-orders/requisitions?buyer=${encodeURIComponent(buyerId)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadAccReceipt(mrrNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-receipts/${encodeURIComponent(mrrNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveAccReceipt(mrrNo, payload) {
  try {
    const data = await apiFetch(mrrNo ? `/merchandise/acc-receipts/${encodeURIComponent(mrrNo)}` : "/merchandise/acc-receipts", { method: mrrNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function finalizeAccReceipt(mrrNo) {
  try {
    const data = await apiFetch(`/merchandise/acc-receipts/${encodeURIComponent(mrrNo)}/finalize`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteAccReceipt(mrrNo) {
  try {
    await apiFetch(`/merchandise/acc-receipts/${encodeURIComponent(mrrNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadReceivables(supplierId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-receipts/receivables?supplier=${encodeURIComponent(supplierId)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function returnFromReceipt(mrrNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-returns/receipts/${encodeURIComponent(mrrNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadAccReturn(challanNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-returns/${encodeURIComponent(challanNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveAccReturn(challanNo, payload) {
  try {
    const data = await apiFetch(challanNo ? `/merchandise/acc-returns/${encodeURIComponent(challanNo)}` : "/merchandise/acc-returns", { method: challanNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function finalizeAccReturn(challanNo) {
  try {
    const data = await apiFetch(`/merchandise/acc-returns/${encodeURIComponent(challanNo)}/finalize`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteAccReturn(challanNo) {
  try {
    await apiFetch(`/merchandise/acc-returns/${encodeURIComponent(challanNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadFabricOb(obNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/fabric-obs/${encodeURIComponent(obNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadFabricObHistory(obNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/fabric-obs/${encodeURIComponent(obNo)}/history`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveFabricOb(obNo, payload) {
  try {
    const data = await apiFetch(obNo ? `/merchandise/fabric-obs/${encodeURIComponent(obNo)}` : "/merchandise/fabric-obs", { method: obNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function transitionFabricOb(obNo, action) {
  try {
    const data = await apiFetch(`/merchandise/fabric-obs/${encodeURIComponent(obNo)}/${encodeURIComponent(action)}`, { method: "POST", body: JSON.stringify({}) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteFabricOb(obNo) {
  try {
    await apiFetch(`/merchandise/fabric-obs/${encodeURIComponent(obNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function fillPfabIndent(orderId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-indents/fill?order_id=${encodeURIComponent(orderId)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadPfabIndent(reqNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-indents/${encodeURIComponent(reqNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function savePfabIndent(reqNo, payload) {
  try {
    const data = await apiFetch(reqNo ? `/merchandise/pfab-indents/${encodeURIComponent(reqNo)}` : "/merchandise/pfab-indents", { method: reqNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function transitionPfabIndent(reqNo, action) {
  try {
    const data = await apiFetch(`/merchandise/pfab-indents/${encodeURIComponent(reqNo)}/${encodeURIComponent(action)}`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deletePfabIndent(reqNo) {
  try {
    await apiFetch(`/merchandise/pfab-indents/${encodeURIComponent(reqNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function pfabPoFromIndent(reqNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-pos/requisitions/${encodeURIComponent(reqNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadPfabPoRequisitions(buyerId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-pos/requisitions?buyer=${encodeURIComponent(buyerId)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadPfabPo(poNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-pos/${encodeURIComponent(poNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function savePfabPo(poNo, payload) {
  try {
    const data = await apiFetch(poNo ? `/merchandise/pfab-pos/${encodeURIComponent(poNo)}` : "/merchandise/pfab-pos", { method: poNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deletePfabPo(poNo) {
  try {
    await apiFetch(`/merchandise/pfab-pos/${encodeURIComponent(poNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function pfabMrrFromOrder(poNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-mrrs/receivables/${encodeURIComponent(poNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadPfabMrr(mrrNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-mrrs/${encodeURIComponent(mrrNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function savePfabMrr(mrrNo, payload) {
  try {
    const data = await apiFetch(mrrNo ? `/merchandise/pfab-mrrs/${encodeURIComponent(mrrNo)}` : "/merchandise/pfab-mrrs", { method: mrrNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function finalizePfabMrr(mrrNo) {
  try {
    const data = await apiFetch(`/merchandise/pfab-mrrs/${encodeURIComponent(mrrNo)}/finalize`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deletePfabMrr(mrrNo) {
  try {
    await apiFetch(`/merchandise/pfab-mrrs/${encodeURIComponent(mrrNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function pfabReturnFromReceipt(mrrNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-returns/receipts/${encodeURIComponent(mrrNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadPfabReturn(challanNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/pfab-returns/${encodeURIComponent(challanNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function savePfabReturn(challanNo, payload) {
  try {
    const data = await apiFetch(challanNo ? `/merchandise/pfab-returns/${encodeURIComponent(challanNo)}` : "/merchandise/pfab-returns", { method: challanNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function finalizePfabReturn(challanNo) {
  try {
    const data = await apiFetch(`/merchandise/pfab-returns/${encodeURIComponent(challanNo)}/finalize`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deletePfabReturn(challanNo) {
  try {
    await apiFetch(`/merchandise/pfab-returns/${encodeURIComponent(challanNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadFfabOb(obNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/ffab-obs/${encodeURIComponent(obNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveFfabOb(obNo, payload) {
  try {
    const data = await apiFetch(obNo ? `/merchandise/ffab-obs/${encodeURIComponent(obNo)}` : "/merchandise/ffab-obs", { method: obNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteFfabOb(obNo) {
  try {
    await apiFetch(`/merchandise/ffab-obs/${encodeURIComponent(obNo)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function loadClosingExceptions(kind, period) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/closings/${kind}/${encodeURIComponent(period)}/exceptions`) };
  } catch (error) {
    return fail(error);
  }
}

async function closeStockMonth(kind, payload) {
  try {
    const data = await apiFetch(`/merchandise/closings/${kind}`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    // A refused close still records the check and its exceptions.
    revalidatePath("/merchandise-desk", "layout");
    return fail(error);
  }
}

async function accBookingsForOrder(orderNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-bookings/orders/${encodeURIComponent(orderNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function bookAccessories(orderNo, payload) {
  try {
    const data = await apiFetch(`/merchandise/acc-bookings/orders/${encodeURIComponent(orderNo)}`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadAccBooking(bookingNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/acc-bookings/${encodeURIComponent(bookingNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function addAccBookingLine(bookingNo, payload) {
  try {
    const data = await apiFetch(`/merchandise/acc-bookings/${encodeURIComponent(bookingNo)}/lines`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function saveAccBookingLines(bookingNo, lines) {
  try {
    const data = await apiFetch(`/merchandise/acc-bookings/${encodeURIComponent(bookingNo)}/lines`, { method: "PUT", body: JSON.stringify({ lines }) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function trimSourcingForOrder(orderNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/trim-sourcings/orders/${encodeURIComponent(orderNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function createTrimSourcing(payload) {
  try {
    const data = await apiFetch("/merchandise/trim-sourcings", { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function updateTrimSourcing(id, payload) {
  try {
    const data = await apiFetch(`/merchandise/trim-sourcings/${encodeURIComponent(id)}`, { method: "PUT", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteTrimSourcing(id) {
  try {
    const data = await apiFetch(`/merchandise/trim-sourcings/${encodeURIComponent(id)}`, { method: "DELETE" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadTrimDyeing(programNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/trim-dyeing/${encodeURIComponent(programNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveTrimDyeing(programNo, payload) {
  try {
    const data = await apiFetch(programNo ? `/merchandise/trim-dyeing/${encodeURIComponent(programNo)}` : "/merchandise/trim-dyeing", { method: programNo ? "PUT" : "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function addTrimDyeingItem(programNo, payload) {
  try {
    const data = await apiFetch(`/merchandise/trim-dyeing/${encodeURIComponent(programNo)}/items`, { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function removeTrimDyeingItem(programNo, id) {
  try {
    const data = await apiFetch(`/merchandise/trim-dyeing/${encodeURIComponent(programNo)}/items/${encodeURIComponent(id)}`, { method: "DELETE" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function runMerchAi() {
  try {
    const data = await apiFetch("/merchandise/ai/run", { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function actMerchAiAlert(id, action) {
  try {
    const data = await apiFetch(`/merchandise/ai/alerts/${encodeURIComponent(id)}/${action}`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function previewMerchandiseReport(report, from) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/reports/${encodeURIComponent(report)}?from=${encodeURIComponent(from)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function loadTna(orderNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/tna-followup/${encodeURIComponent(orderNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function recalculateTna(orderNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/tna-followup/${encodeURIComponent(orderNo)}/recalculate`, { method: "POST" }) };
  } catch (error) {
    return fail(error);
  }
}

async function saveTnaMilestone(orderNo, id, payload) {
  try {
    const data = await apiFetch(`/merchandise/tna-followup/${encodeURIComponent(orderNo)}/milestones/${encodeURIComponent(id)}`, { method: "PUT", body: JSON.stringify(payload) });
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function loadImportBookings(orderId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/import-bookings?order_id=${encodeURIComponent(orderId)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function raiseImportBooking(payload) {
  try {
    const data = await apiFetch("/merchandise/import-bookings", { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function approveImportBooking(bookingNo) {
  try {
    const data = await apiFetch(`/merchandise/import-bookings/${encodeURIComponent(bookingNo)}/approve`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function removeImportBooking(bookingNo) {
  try {
    const data = await apiFetch(`/merchandise/import-bookings/${encodeURIComponent(bookingNo)}`, { method: "DELETE" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function loadBookingItems(bookingNo) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/fabric-booking-items/${encodeURIComponent(bookingNo)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function addBookingItem(bookingNo, payload) {
  try {
    const data = await apiFetch(`/merchandise/fabric-booking-items/${encodeURIComponent(bookingNo)}`, { method: "POST", body: JSON.stringify(payload) });
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function saveBookingItems(bookingNo, rows) {
  try {
    const data = await apiFetch(`/merchandise/fabric-booking-items/${encodeURIComponent(bookingNo)}`, { method: "PUT", body: JSON.stringify({ rows }) });
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

async function loadYarnSpecs(styleId, category) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/yarn-specs?style_id=${encodeURIComponent(styleId)}&category=${encodeURIComponent(category)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function saveYarnSpec(payload) {
  try {
    const data = await apiFetch("/merchandise/yarn-specs", { method: "POST", body: JSON.stringify(payload) });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function confirmYarnSpec(id) {
  try {
    const data = await apiFetch(`/merchandise/yarn-specs/${encodeURIComponent(id)}/confirm`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function releaseYarnSpec(id) {
  try {
    const data = await apiFetch(`/merchandise/yarn-specs/${encodeURIComponent(id)}/release`, { method: "POST" });
    return done({ data });
  } catch (error) {
    return fail(error);
  }
}

async function deleteYarnSpec(id) {
  try {
    await apiFetch(`/merchandise/yarn-specs/${encodeURIComponent(id)}`, { method: "DELETE" });
    return done();
  } catch (error) {
    return fail(error);
  }
}

async function searchYarnSpecConfirmation(filters) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/yarn-specs/confirmation?${new URLSearchParams(filters)}`) };
  } catch (error) {
    return fail(error);
  }
}

async function suggestFabricBooking(styleId) {
  try {
    return { status: "success", data: await apiFetch(`/merchandise/fabric-bookings/suggest?style_id=${encodeURIComponent(styleId)}`) };
  } catch (error) {
    return fail(error);
  }
}

export {
  createResource,
  updateResource,
  deleteResource,
  saveComposition,
  loadGarmentParts,
  saveGarmentParts,
  loadMapping,
  saveMapping,
  loadSamples,
  saveSamples,
  saveRateChart,
  saveTrimSpec,
  saveTemplateTasks,
  copyTemplate,
  loadTnaRights,
  saveTnaRights,
  moveInquiry,
  saveStyle,
  deleteStyle,
  addSizeRange,
  removeSizeRange,
  saveStyleItem,
  saveStyleColours,
  saveStyleFabrication,
  loadStyleColours,
  loadStyleFabrication,
  saveStyleServices,
  loadStyleServices,
  saveStyleBom,
  approveStyleBom,
  loadStyleBom,
  saveStyleFabricMapping,
  loadStyleFabricMapping,
  saveOrderSizes,
  loadOrderConfirmationStyles,
  confirmOrders,
  loadStyleOrders,
  createStyleOrder,
  updateStyleOrder,
  drawCallOff,
  saveStylePacking,
  loadStylePacking,
  assignFiberTemplate,
  loadDevStyles,
  loadStyleItems,
  loadDevReview,
  devStyleAct,
  loadStyleAttachments,
  detachStyleFile,
  loadPackingDetails,
  savePackingDetails,
  loadStyle,
  loadRegistrations,
  loadRegistrationCandidates,
  raiseRegistration,
  loadRegistration,
  saveRegistration,
  moveRegistration,
  discardRegistration,
  confirmEmbellishmentRate,
  dropEmbellishmentRate,
  saveCadSizes,
  loadBudget,
  loadBudgetSources,
  loadBudgetItem,
  saveBudget,
  saveBudgetActuals,
  decideBudget,
  saveLabdipShades,
  addSampleLine,
  saveSampleLines,
  savePrintLines,
  loadSampleProduction,
  showSampleBatch,
  openSampleBatch,
  addToSampleBatch,
  dropFromSampleBatch,
  saveSampleBatch,
  submitSampleBatch,
  addChallanItem,
  saveChallanItems,
  setBulkShade,
  addMeasurePoint,
  saveMeasurePoints,
  saveFabricBooking,
  deleteFabricBooking,
  suggestFabricBooking,
  submitFabricBooking,
  loadYarnSpecs,
  saveFabricRequisition,
  deleteFabricRequisition,
  loadImportBookings,
  loadTna,
  previewMerchandiseReport,
  recalculateTna,
  saveTnaMilestone,
  raiseImportBooking,
  approveImportBooking,
  removeImportBooking,
  loadBookingItems,
  addBookingItem,
  saveBookingItems,
  saveYarnSpec,
  confirmYarnSpec,
  releaseYarnSpec,
  deleteYarnSpec,
  searchYarnSpecConfirmation,
  accRequisitionBom,
  loadAccRequisition,
  saveAccRequisition,
  transitionAccRequisition,
  deleteAccRequisition,
  loadAccPurchaseOrder,
  saveAccPurchaseOrder,
  deleteAccPurchaseOrder,
  loadPoRequisitions,
  loadAccReceipt,
  saveAccReceipt,
  finalizeAccReceipt,
  deleteAccReceipt,
  loadReceivables,
  returnFromReceipt,
  loadAccReturn,
  saveAccReturn,
  finalizeAccReturn,
  deleteAccReturn,
  loadFabricOb,
  loadFabricObHistory,
  saveFabricOb,
  transitionFabricOb,
  deleteFabricOb,
  fillPfabIndent,
  loadPfabIndent,
  savePfabIndent,
  transitionPfabIndent,
  deletePfabIndent,
  pfabPoFromIndent,
  loadPfabPoRequisitions,
  loadPfabPo,
  savePfabPo,
  deletePfabPo,
  pfabMrrFromOrder,
  loadPfabMrr,
  savePfabMrr,
  finalizePfabMrr,
  deletePfabMrr,
  pfabReturnFromReceipt,
  loadPfabReturn,
  savePfabReturn,
  finalizePfabReturn,
  deletePfabReturn,
  loadFfabOb,
  saveFfabOb,
  deleteFfabOb,
  loadClosingExceptions,
  closeStockMonth,
  accBookingsForOrder,
  bookAccessories,
  loadAccBooking,
  addAccBookingLine,
  saveAccBookingLines,
  trimSourcingForOrder,
  createTrimSourcing,
  updateTrimSourcing,
  deleteTrimSourcing,
  loadTrimDyeing,
  saveTrimDyeing,
  addTrimDyeingItem,
  removeTrimDyeingItem,
  runMerchAi,
  actMerchAiAlert,
};
