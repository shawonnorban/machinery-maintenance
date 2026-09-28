/**
 * The JSON body `ItemApiController::validated()` expects, built from the
 * form's FormData. Shared by the create and edit server actions so a field
 * cannot be added to one and forgotten in the other.
 *
 * Spec blocks (fabric/yarn/chemical) are posted as dotted keys
 * (`fabric.gsm`) and nested here; a block is only sent when the form showed
 * it, so switching an item away from FABRIC leaves the stored spec alone.
 */
const SPEC_BLOCKS = {
  fabric: ["fabric_type", "composition", "gsm", "width", "construction", "finish", "shrinkage", "pantone", "color_fastness"],
  yarn: ["count", "type", "fiber", "twist", "color"],
  chemical: ["expiry_tracking", "hazard_info", "batch_tracking"],
};

const BOOLEAN_SPEC_FIELDS = new Set(["expiry_tracking", "batch_tracking"]);

function optional(formData, key) {
  const value = formData.get(key);
  return value === null || value === "" ? null : value;
}

function itemPayload(formData) {
  const payload = {
    item_code: formData.get("item_code"),
    name: formData.get("name"),
    description: optional(formData, "description"),
    item_department_id: optional(formData, "item_department_id"),
    category_id: optional(formData, "category_id"),
    sub_category_id: optional(formData, "sub_category_id"),
    detail_category_id: optional(formData, "detail_category_id"),
    uom_id: formData.get("uom_id"),
    purchase_uom_id: optional(formData, "purchase_uom_id"),
    model: optional(formData, "model"),
    brand: optional(formData, "brand"),
    manufacturer_id: optional(formData, "manufacturer_id"),
    origin: optional(formData, "origin"),
    default_store_id: optional(formData, "default_store_id"),
    hs_code_id: optional(formData, "hs_code_id"),
    default_vendor_id: optional(formData, "default_vendor_id"),
    minimum_stock: formData.get("minimum_stock") || "0",
    reorder_level: formData.get("reorder_level") || "0",
    is_returnable: formData.get("is_returnable") === "1",
    hazardous: formData.get("hazardous") === "1",
  };

  // Absent (not null) lets the department's default decide on create.
  for (const key of ["item_type", "tracking_mode"]) {
    const value = optional(formData, key);
    if (value !== null) payload[key] = value;
  }

  for (const [block, fields] of Object.entries(SPEC_BLOCKS)) {
    if (formData.get(`${block}._present`) !== "1") continue;
    payload[block] = {};
    for (const field of fields) {
      const key = `${block}.${field}`;
      payload[block][field] = BOOLEAN_SPEC_FIELDS.has(field) ? formData.get(key) === "1" : optional(formData, key);
    }
  }

  return payload;
}

export { itemPayload };
