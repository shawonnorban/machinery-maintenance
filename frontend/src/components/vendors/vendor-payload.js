/**
 * The JSON body `VendorApiController::validated()` expects, built from the
 * form's FormData. Shared by the create and edit server actions.
 *
 * `supply_categories` is the one multi-valued field, which is why this is
 * no longer a bare `Object.fromEntries(formData)` — that keeps only the
 * last checkbox ticked.
 */
function optional(formData, key) {
  const value = formData.get(key);
  return value === null || value === "" ? null : value;
}

function vendorPayload(formData) {
  return {
    name: formData.get("name"),
    code: formData.get("code"),
    vendor_type: formData.get("vendor_type"),
    status: formData.get("status"),
    approval_status: formData.get("approval_status") || "PENDING",
    contact_name: optional(formData, "contact_name"),
    phone: optional(formData, "phone"),
    email: optional(formData, "email"),
    address: optional(formData, "address"),
    country_of_origin: optional(formData, "country_of_origin"),
    tax_reference: optional(formData, "tax_reference"),
    bin: optional(formData, "bin"),
    trade_license_no: optional(formData, "trade_license_no"),
    bank_id: optional(formData, "bank_id"),
    bank_branch: optional(formData, "bank_branch"),
    bank_account_no: optional(formData, "bank_account_no"),
    bank_swift: optional(formData, "bank_swift"),
    supply_categories: formData.getAll("supply_categories"),
    default_currency: optional(formData, "default_currency"),
    default_payment_type_id: optional(formData, "default_payment_type_id"),
    default_payment_terms: optional(formData, "default_payment_terms"),
    notes: optional(formData, "notes"),
  };
}

export { vendorPayload };
