/**
 * A Machinery record's address segment: its readable number
 * (`CUT-DHK-00401`, `WO-DHK-202609-00008`), never the ULID — the API binds
 * either (App\Shared\Concerns\ResolvesByReference). Falls back to the id
 * only when a payload does not carry the number.
 *
 * @param {object|null|undefined} record
 * @param {string} field the number's key, e.g. "asset_code"
 */
function refOf(record, field) {
  const value = record?.[field] || record?.id;
  return value ? encodeURIComponent(value) : "";
}

export { refOf };
