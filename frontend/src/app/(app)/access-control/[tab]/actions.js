"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function refresh() {
  revalidatePath("/access-control", "layout");
}

function firstError(error) {
  return (
    (error.errors ? Object.values(error.errors)[0]?.[0] : null) ?? error.message
  );
}

/** Role Definition's "Add Role" — the grid's (type, previousState, formData) shape. */
async function createRole(type, previousState, formData) {
  try {
    const body = Object.fromEntries(formData.entries());
    for (const flag of ["is_admin", "active"]) body[flag] = body[flag] === "1";
    await apiFetch("/roles", { method: "POST", body: JSON.stringify(body) });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function saveRoles(type, changes) {
  const results = [];
  for (const { id, payload } of changes) {
    try {
      // A cleared number cell arrives as "" — the API wants null for "no rank".
      const body = Object.fromEntries(
        Object.entries(payload).map(([key, value]) => [
          key,
          value === "" ? null : value,
        ]),
      );
      await apiFetch(`/roles/${id}`, {
        method: "PATCH",
        body: JSON.stringify(body),
      });
      results.push({ id, status: "success" });
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      results.push({ id, status: "error", message: firstError(error) });
    }
  }
  refresh();
  return { results };
}

async function deleteRole(type, id) {
  try {
    await apiFetch(`/roles/${id}`, { method: "DELETE" });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Role Menu Assignment's Save: the whole matrix for one role. */
async function saveGrants(roleId, grants) {
  try {
    await apiFetch(`/access/roles/${roleId}/menu-grants`, {
      method: "PUT",
      body: JSON.stringify({ grants }),
    });
    refresh();
    revalidatePath("/", "layout");
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Menu Definition's Save: one PATCH per edited menu. */
async function saveMenus(changes) {
  const results = [];
  for (const { id, payload } of changes) {
    try {
      await apiFetch(`/access/menus/${id}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
      });
      results.push({ id, status: "success" });
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      results.push({ id, status: "error", message: firstError(error) });
    }
  }
  refresh();
  revalidatePath("/", "layout");
  return { results };
}

/** Password Change: an administrator types somebody's new password (the useActionState shape). */
async function setPassword(previousState, formData) {
  try {
    await apiFetch(`/users/${formData.get("user_id")}/password`, {
      method: "POST",
      body: JSON.stringify({
        password: formData.get("password"),
        password_confirmation: formData.get("password_confirmation"),
        force_change: formData.get("force_change") === "1",
      }),
    });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Module Registration's Save: one PATCH per edited module (this company's override). */
async function saveModules(type, changes) {
  return saveEach(
    changes,
    (id, payload) =>
      apiFetch(`/access/modules/${id}`, {
        method: "PATCH",
        body: JSON.stringify(numbers(payload, ["sort_order"])),
      }),
    true,
  );
}

/**
 * Lookup Table Master: one grid for tables ("lookups") and one for the chosen
 * table's values ("values/<tableId>"), so the type key says which endpoint.
 */
function lookupPath(type) {
  return type === "lookups"
    ? "/access/lookups"
    : `/access/lookups/${type.slice("values/".length)}/values`;
}

async function createLookupRow(type, previousState, formData) {
  try {
    const body = Object.fromEntries(formData.entries());
    body.active = body.active === undefined ? true : body.active === "1";
    await apiFetch(lookupPath(type), {
      method: "POST",
      body: JSON.stringify(numbers(body, ["sort_order"])),
    });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

async function saveLookupRows(type, changes) {
  // The code is fixed once issued; the grid shows it but never sends it back.
  return saveEach(changes, (id, { code, ...payload }) =>
    apiFetch(`${lookupPath(type)}/${id}`, {
      method: "PATCH",
      body: JSON.stringify(numbers(payload, ["sort_order"])),
    }),
  );
}

async function deleteLookupRow(type, id) {
  try {
    await apiFetch(`${lookupPath(type)}/${id}`, { method: "DELETE" });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** System Parameter Update: one value at the company level, or one factory's own. */
async function updateParameter(key, value, factoryId = null) {
  try {
    await apiFetch(`/settings/${key}`, {
      method: "PUT",
      body: JSON.stringify(factoryId ? { value, factory_id: factoryId } : { value }),
    });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Drops the company's own value (or a factory's), so the level above answers again. */
async function resetParameter(key, factoryId = null) {
  try {
    await apiFetch(factoryId ? `/settings/${key}?factory_id=${factoryId}` : `/settings/${key}`, { method: "DELETE" });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** Category Access Permission's Save: the whole list for one role. */
async function saveCategoryPermissions(roleId, permissions) {
  try {
    await apiFetch(`/access/roles/${roleId}/category-permissions`, {
      method: "PUT",
      body: JSON.stringify({ permissions }),
    });
    refresh();
    return { status: "success" };
  } catch (error) {
    return fail(error);
  }
}

/** A cleared number cell arrives as "" — the API wants null (or the field left out). */
function numbers(payload, keys) {
  const out = { ...payload };
  for (const key of keys)
    if (key in out)
      out[key] = out[key] === "" || out[key] === null ? null : Number(out[key]);
  return out;
}

async function saveEach(changes, call, sidebar = false) {
  const results = [];
  for (const { id, payload } of changes) {
    try {
      await call(id, payload);
      results.push({ id, status: "success" });
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      results.push({ id, status: "error", message: firstError(error) });
    }
  }
  refresh();
  if (sidebar) revalidatePath("/", "layout");
  return { results };
}

export {
  createRole,
  saveRoles,
  deleteRole,
  saveGrants,
  saveMenus,
  setPassword,
  saveModules,
  createLookupRow,
  saveLookupRows,
  deleteLookupRow,
  updateParameter,
  resetParameter,
  saveCategoryPermissions,
};
