/**
 * Matches `CustomFieldValueApiController::PERMISSIONS`'s keys — the only
 * `fieldable_type`s the values endpoint knows how to gate. Kept in its own
 * plain module (no "use client") so a Server Component can import it
 * directly — a named export re-exported from a "use client" file resolves
 * to an opaque client reference on the server side, not the real value.
 */
const FIELDABLE_TYPES = ["asset", "work_order", "vendor", "breakdown"];

export { FIELDABLE_TYPES };
