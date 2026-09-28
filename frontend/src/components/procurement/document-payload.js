/**
 * Shared by the procurement server actions: the header fields a form posts
 * plus its `lines` JSON, as the API's request body. Empty strings become
 * absent keys so "not chosen" never arrives as "".
 */
function documentPayload(formData, headerKeys) {
  const payload = {};

  for (const key of headerKeys) {
    const value = formData.get(key);
    if (value !== null && value !== "") payload[key] = value;
  }

  const lines = formData.get("lines");
  payload.lines = lines ? JSON.parse(lines) : [];

  return payload;
}

export { documentPayload };
