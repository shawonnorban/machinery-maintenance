"use server";

import { revalidatePath } from "next/cache";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";

/*
 * The entry forms and Edit dialogs of Access Control's Brickland screens
 * (AnnotechERP UI parity). Every action has the `(…ids, previousState,
 * formData)` shape the shared EntryForm and RecordsGrid expect and answers
 * `{status}`.
 */

function fail(error) {
  if (error instanceof ApiError) {
    return { status: "error", message: error.message, errors: error.errors };
  }
  throw error;
}

function done(sidebar = false) {
  revalidatePath("/access-control", "layout");
  if (sidebar) revalidatePath("/", "layout");
  return { status: "success" };
}

/** FormData → JSON body: listed checkboxes become booleans, listed numbers numbers, "" null. */
function body(formData, { booleans = [], numbers = [], omit = [] } = {}) {
  const out = {};
  for (const [key, value] of formData.entries()) {
    if (omit.includes(key)) continue;
    out[key] = value === "" ? null : value;
  }
  for (const key of booleans) out[key] = formData.get(key) === "1";
  for (const key of numbers)
    if (out[key] !== undefined && out[key] !== null)
      out[key] = Number(out[key]);
  return out;
}

async function send(path, method, payload, sidebar = false) {
  try {
    await apiFetch(path, {
      method,
      body: payload === undefined ? undefined : JSON.stringify(payload),
    });
    return done(sidebar);
  } catch (error) {
    return fail(error);
  }
}

// Module Registration / Menu Definition: this company's overrides.
async function updateModule(id, previousState, formData) {
  return send(
    `/access/modules/${id}`,
    "PATCH",
    body(formData, { booleans: ["active"], numbers: ["sort_order"] }),
    true,
  );
}

async function updateMenu(id, previousState, formData) {
  return send(
    `/access/menus/${id}`,
    "PATCH",
    body(formData, {
      booleans: ["active", "open_new_window"],
      numbers: ["sort_order"],
    }),
    true,
  );
}

// Lookup Table Master.
async function createLookupTable(previousState, formData) {
  return send(
    "/access/lookups",
    "POST",
    body(formData, { booleans: ["active"] }),
  );
}

async function updateLookupTable(id, previousState, formData) {
  return send(
    `/access/lookups/${id}`,
    "PATCH",
    body(formData, { booleans: ["active"], omit: ["code"] }),
  );
}

async function deleteLookupTable(id) {
  return send(`/access/lookups/${id}`, "DELETE");
}

// System Parameter Update: the company's value of one key.
async function updateParameterValue(key, previousState, formData) {
  return send(`/settings/${key}`, "PUT", {
    value: formData.get("value") ?? "",
  });
}

// ...or one factory's own value of it.
async function updateFactoryParameterValue(factoryId, key, previousState, formData) {
  return send(`/settings/${key}`, "PUT", {
    value: formData.get("value") ?? "",
    factory_id: factoryId,
  });
}

// Role Definition.
async function createRoleEntry(previousState, formData) {
  return send(
    "/roles",
    "POST",
    body(formData, { booleans: ["is_admin", "active"], numbers: ["rank"] }),
  );
}

async function updateRoleEntry(id, previousState, formData) {
  return send(
    `/roles/${id}`,
    "PATCH",
    body(formData, {
      booleans: ["is_admin", "active"],
      numbers: ["rank"],
      omit: ["code"],
    }),
  );
}

async function deleteRoleEntry(id) {
  return send(`/roles/${id}`, "DELETE");
}

// User Account Registration → User Roles: one more role (optionally for one factory), or one less.
async function assignRole(userId, previousState, formData) {
  return send(`/users/${userId}/roles`, "POST", {
    role_id: Number(formData.get("role_id")),
    factory_id: formData.get("factory_id") || null,
  });
}

async function removeRoleAssignment(userId, assignmentId) {
  return send(`/users/${userId}/roles/${assignmentId}`, "DELETE");
}

// User Account Registration's Remove: ends the membership of this company.
async function removeUserAccount(id) {
  return send(`/users/${id}`, "DELETE");
}

// Role Menu Assignment: one menu's flags for one role. The row key is "roleId-menuId".
const FLAGS = ["V", "C", "E", "D", "A", "P", "X"];

async function saveMenuGrantRow(key, previousState, formData) {
  const [roleId, ...rest] = String(key).split("-");
  const menuId = rest.join("-");
  return send(
    `/access/roles/${roleId}/menu-grants/${menuId}`,
    "PUT",
    { flags: FLAGS.filter((flag) => formData.get(`flag_${flag}`) === "1") },
    true,
  );
}

async function createMenuGrant(previousState, formData) {
  const roleId = formData.get("role_id");
  const menuId = formData.get("menu_id");
  if (!roleId || !menuId) {
    return {
      status: "error",
      message: "",
      errors: {
        ...(!roleId ? { role_id: ["Role is required."] } : {}),
        ...(!menuId ? { menu_id: ["Menu is required."] } : {}),
      },
    };
  }
  return send(
    `/access/roles/${roleId}/menu-grants/${menuId}`,
    "PUT",
    { flags: FLAGS.filter((flag) => formData.get(`flag_${flag}`) === "1") },
    true,
  );
}

// User Account Registration.
const ACCOUNT_BOOLEANS = [
  "must_change_password",
  "cannot_change_password",
  "password_never_expires",
];

async function createUserAccount(previousState, formData) {
  const payload = body(formData, {
    booleans: ACCOUNT_BOOLEANS,
    omit: ["primary_role"],
  });
  payload.roles = formData.get("primary_role")
    ? [Number(formData.get("primary_role"))]
    : [];
  return send("/users", "POST", payload);
}

/** Edit keeps the user's other roles: the primary one is put first, the rest stay as assigned. */
async function updateUserAccount(id, previousState, formData) {
  try {
    const current = await apiFetch(`/users/${id}`);
    const primary = formData.get("primary_role")
      ? Number(formData.get("primary_role"))
      : null;
    const others = (current.role_assignments ?? [])
      .map((a) => a.role_id)
      .filter((roleId) => roleId !== primary);
    const payload = body(formData, {
      booleans: ACCOUNT_BOOLEANS,
      omit: ["primary_role", "email"],
    });
    payload.roles = [...(primary ? [primary] : []), ...new Set(others)];
    await apiFetch(`/users/${id}`, {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
    return done();
  } catch (error) {
    return fail(error);
  }
}

// Password Change: an administrator sets somebody's password.
async function changeAccountPassword(previousState, formData) {
  const userId = formData.get("user_id");
  if (!userId)
    return {
      status: "error",
      message: "",
      errors: { user_id: ["Account is required."] },
    };
  return send(`/users/${userId}/password`, "POST", {
    password: formData.get("password"),
    password_confirmation: formData.get("password_confirmation"),
    force_change: formData.get("force_change") === "1",
  });
}

// Category Access Permission: one role × category row. The row key is "roleId:categoryId".
const CATEGORY_TICKS = ["can_view", "can_create", "can_edit", "can_approve"];

async function saveCategoryRow(key, previousState, formData) {
  const [roleId, categoryId] = String(key).split(":");
  return send(
    `/access/roles/${roleId}/category-permissions/${categoryId}`,
    "PUT",
    body(formData, { booleans: CATEGORY_TICKS }),
  );
}

async function deleteCategoryRow(key) {
  const [roleId, categoryId] = String(key).split(":");
  return send(
    `/access/roles/${roleId}/category-permissions/${categoryId}`,
    "PUT",
    { can_view: false, can_create: false, can_edit: false, can_approve: false },
  );
}

async function createCategoryPermission(previousState, formData) {
  const roleId = formData.get("role_id");
  const categoryId = formData.get("item_category_id");
  if (!roleId || !categoryId) {
    return {
      status: "error",
      message: "",
      errors: {
        ...(!roleId ? { role_id: ["Role is required."] } : {}),
        ...(!categoryId ? { item_category_id: ["Category is required."] } : {}),
      },
    };
  }
  return send(
    `/access/roles/${roleId}/category-permissions/${categoryId}`,
    "PUT",
    body(formData, {
      booleans: CATEGORY_TICKS,
      omit: ["role_id", "item_category_id"],
    }),
  );
}

// Mappings (02/02): one set of actions for every kind; the checkbox fields are flag_<name>.
async function createMapping(kind, flags, previousState, formData) {
  const payload = body(formData, {
    booleans: [...flags.map((f) => `flag_${f}`), "is_default"],
    numbers: ["sort_order", "amount"],
  });
  return send(`/access/mappings/${kind}`, "POST", payload);
}

async function updateMapping(kind, flags, id, previousState, formData) {
  const payload = body(formData, {
    booleans: [...flags.map((f) => `flag_${f}`), "is_default"],
    numbers: ["sort_order", "amount"],
    omit: ["subject", "target"],
  });
  return send(`/access/mappings/${kind}/${id}`, "PATCH", payload);
}

async function deleteMapping(kind, id) {
  return send(`/access/mappings/${kind}/${id}`, "DELETE");
}

async function saveMappingMatrix(kind, subjectId, rows) {
  return send(`/access/mappings/${kind}/subjects/${subjectId}`, "PUT", {
    rows,
  });
}

// TNA Task Status Setup (02/04): the events, their raised instances and who is notified.
const TNA_BOOLEANS = ["is_repeat", "is_buyer_feedback", "active"];

async function createTnaEvent(previousState, formData) {
  return send(
    "/access/tna-events",
    "POST",
    body(formData, {
      booleans: TNA_BOOLEANS,
      numbers: ["sort_order", "offset_days"],
    }),
  );
}

async function updateTnaEvent(id, previousState, formData) {
  return send(
    `/access/tna-events/${id}`,
    "PATCH",
    body(formData, {
      booleans: TNA_BOOLEANS,
      numbers: ["sort_order", "offset_days"],
    }),
  );
}

/** A read or a list save the TNA tabs make in place, answering the data. */
async function tnaCall(op, id, payload) {
  const path = { instances: `instances`, users: `users` }[
    op.replace(/^save/, "").toLowerCase()
  ];
  try {
    const data = await apiFetch(
      `/access/tna-events/${id}/${path}`,
      op.startsWith("save")
        ? { method: "PUT", body: JSON.stringify(payload) }
        : { method: "GET" },
    );
    if (op.startsWith("save")) revalidatePath("/access-control", "layout");
    return { status: "success", data };
  } catch (error) {
    return fail(error);
  }
}

// User Company Mapping: memberships, not mapping rows.
async function createCompanyMapping(previousState, formData) {
  return send(
    "/access/user-companies",
    "POST",
    body(formData, { booleans: ["is_default"] }),
  );
}

async function updateCompanyMapping(id, previousState, formData) {
  return send(`/access/user-companies/${id}`, "PATCH", {
    is_default: formData.get("is_default") === "1",
  });
}

async function deleteCompanyMapping(id) {
  return send(`/access/user-companies/${id}`, "DELETE");
}

/** The per-user tab: ticked companies are joined (or kept), unticked ones left, one default. */
async function saveCompanyMatrix(userId, rows) {
  try {
    for (const row of rows) {
      if (row.selected && !row.membership_id) {
        await apiFetch("/access/user-companies", {
          method: "POST",
          body: JSON.stringify({
            user_id: userId,
            company_id: row.target_id,
            is_default: Boolean(row.is_default),
          }),
        });
      } else if (
        row.selected &&
        row.membership_id &&
        Boolean(row.is_default) !== Boolean(row.was_default)
      ) {
        await apiFetch(`/access/user-companies/${row.membership_id}`, {
          method: "PATCH",
          body: JSON.stringify({ is_default: Boolean(row.is_default) }),
        });
      } else if (!row.selected && row.membership_id && !row.current) {
        await apiFetch(`/access/user-companies/${row.membership_id}`, {
          method: "DELETE",
        });
      }
    }
    return done();
  } catch (error) {
    return fail(error);
  }
}

// The screen kit's resources (/erp/{resource}): one set of actions for every Records + New X screen.
async function createResource(resource, booleans, previousState, formData) {
  return send(`/erp/${resource}`, "POST", body(formData, { booleans }));
}

async function updateResource(resource, booleans, id, previousState, formData) {
  return send(`/erp/${resource}/${id}`, "PATCH", body(formData, { booleans }));
}

async function deleteResource(resource, id) {
  return send(`/erp/${resource}/${id}`, "DELETE");
}

export {
  updateModule,
  updateMenu,
  createLookupTable,
  updateLookupTable,
  deleteLookupTable,
  updateParameterValue,
  updateFactoryParameterValue,
  removeUserAccount,
  assignRole,
  removeRoleAssignment,
  createRoleEntry,
  updateRoleEntry,
  deleteRoleEntry,
  saveMenuGrantRow,
  createMenuGrant,
  createUserAccount,
  updateUserAccount,
  changeAccountPassword,
  saveCategoryRow,
  deleteCategoryRow,
  createCategoryPermission,
  createMapping,
  updateMapping,
  deleteMapping,
  saveMappingMatrix,
  createCompanyMapping,
  updateCompanyMapping,
  deleteCompanyMapping,
  saveCompanyMatrix,
  createTnaEvent,
  updateTnaEvent,
  tnaCall,
  createResource,
  updateResource,
  deleteResource,
};
