/**
 * Brickland's words for a screen, built from its noun (AnnotechERP UI parity):
 * "Save colour", "Update colour", "Edit colour", "Remove this colour?", the
 * row's "Remove colour Solid" and "Solid will be removed …". `te` is the
 * screen kit's ("erp") translator; `nameKey` is the column whose value names a
 * row — Brickland uses the first.
 */
function nounLabels(te, noun, nameKey) {
  return {
    save: te("save_noun", { noun }),
    update: te("update_noun", { noun }),
    edit: te("edit_noun", { noun }),
    create: te("new_noun", { noun }),
    remove: { title: te("remove_title", { noun }), confirm: te("remove"), aria: te("remove_aria", { noun }), text: te("remove_text", { name: ":name" }), nameKey },
    rewriting: (name) => te("rewriting", { name }),
    // Brickland's empty list: "No colour records yet" — "Add the first one on the New colour tab."
    empty: { title: te("empty_title", { noun }), text: te("empty_text", { tab: te("new_noun", { noun }) }) },
  };
}

/**
 * The same words as plain data for a client component (functions cannot cross
 * from a server component): "Rewriting :name …" keeps its placeholder.
 */
function screenWords(te, noun, secondary = "reset") {
  const w = nounLabels(te, noun);
  // What stands beside Save on a New tab: Reset, or — as some Brickland modules have it — Cancel (back to the list) or Clear.
  return { save: w.save, update: w.update, edit: w.edit, cancel: te("cancel"), clear: te("clear"), secondary, rewriting: te("rewriting", { name: ":name" }), remove: w.remove, empty: w.empty };
}

export { nounLabels, screenWords };
