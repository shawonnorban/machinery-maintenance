/**
 * A record's readable reference in a URL — its title, not its internal id:
 * `?tab=sheet&style=MOOD-SHIRT-327632.01` rather than `&id=01m4qh9w…`.
 * Spaces become hyphens and anything a URL would escape is dropped; two rows
 * with the same title are told apart as `NAME~2`, `NAME~3` in list order.
 * The server page and the client grid build the same reference from the same
 * rows, so a link made on one side resolves on the other.
 */
function slug(value) {
  const text = String(value ?? "")
    .trim()
    .replace(/\s+/g, "-")
    .replace(/[^\p{L}\p{N}._-]+/gu, "")
    .replace(/-+/g, "-");
  return text || "-";
}

/** id → reference for every row, numbering repeated titles. */
function refsFor(rows, key, idKey = "id") {
  const seen = {};
  const refs = new Map();
  for (const row of rows) {
    const base = slug(row[key]);
    seen[base] = (seen[base] ?? 0) + 1;
    refs.set(row[idKey], seen[base] > 1 ? `${base}~${seen[base]}` : base);
  }
  return refs;
}

/** The row a reference names, or null. */
function findByRef(rows, key, ref, idKey = "id") {
  if (!ref) return null;
  const refs = refsFor(rows, key, idKey);
  return rows.find((row) => refs.get(row[idKey]) === ref) ?? null;
}

export { slug, refsFor, findByRef };
