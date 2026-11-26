import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import {
  TnaInstances,
  TnaMappedUsers,
} from "@/components/access/tna-event-tabs";
import * as forms from "./form-actions";

/*
 * Access Control → TNA Task Status Setup (AnnotechERP 02/04), Brickland's
 * layout: Records (every calendar event, Edit opening the "Edit TNA event"
 * tab by its code) | TNA Task Status (raise and move instances) | Mapped User
 * List | New TNA event.
 */

const LABEL_KEYS = [
  "list",
  "instances",
  "instances_for",
  "instances_hint",
  "pick_event",
  "pick_event_hint",
  "raise",
  "none",
  "none_hint",
  "count",
  "reference",
  "owner",
  "plan_date",
  "actual_date",
  "status",
  "saved",
  "revert",
  "save_list",
  "users_title",
  "users_hint",
  "event",
  "choose_event",
  "select_all",
  "user",
  "login",
  "department",
];

async function tnaTaskStatus({ t, te, tab, searchParams }) {
  const n = (key, params) => t(`tna.${key}`, params);
  const [rows, options] = await Promise.all([
    apiFetch("/access/tna-events"),
    apiFetch("/access/tna-events/options"),
  ]);
  const basePath = "/access-control/tna-task-status";
  // "Edit TNA event" is reached by the event's code (`?tab=edit&id=TNA-030`), never its key.
  const editing =
    tab === "edit"
      ? (rows.find((r) => r.code === searchParams.id) ?? null)
      : null;
  const statusOptions = options.statuses.map((s) => ({
    value: s,
    label: n(`statuses.${s}`),
  }));
  const fields = (self) => [
    {
      name: "code",
      label: n("f.code"),
      required: true,
      placeholder: "TNA-190",
    },
    { name: "name", label: n("f.name"), required: true },
    { name: "task_message", label: n("f.task_message"), span: 2 },
    {
      name: "sort_order",
      label: n("f.sort_order"),
      type: "number",
      required: true,
      default: 1,
    },
    {
      name: "parent_id",
      label: n("f.parent_id"),
      type: "select",
      options: options.parents.filter((p) => p.value !== self),
    },
    {
      name: "section_id",
      label: n("f.section_id"),
      type: "select",
      options: options.sections,
    },
    {
      name: "assign_to",
      label: n("f.assign_to"),
      type: "select",
      options: options.users,
    },
    {
      name: "execution_by",
      label: n("f.execution_by"),
      type: "select",
      options: options.users,
    },
    {
      name: "default_status",
      label: n("f.default_status"),
      type: "select",
      options: statusOptions,
      help: n("f.default_status_help"),
    },
    {
      name: "offset_days",
      label: n("f.offset_days"),
      type: "number",
      help: n("f.offset_days_help"),
    },
    {
      name: "is_repeat",
      label: n("f.is_repeat"),
      type: "checkbox",
      help: n("f.is_repeat_help"),
    },
    {
      name: "is_buyer_feedback",
      label: n("f.is_buyer_feedback"),
      type: "checkbox",
    },
    { name: "active", label: n("f.active"), type: "checkbox", default: true },
  ];
  const labels = Object.fromEntries(LABEL_KEYS.map((k) => [k, n(k)]));
  labels.statuses = Object.fromEntries(
    options.statuses.map((s) => [s, n(`statuses.${s}`)]),
  );

  return {
    count: rows.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "status", label: n("tabs.status"), icon: "CalendarCheck" },
      { key: "users", label: n("tabs.users"), icon: "Users" },
      editing
        ? { key: "edit", label: n("tabs.edit"), icon: "Pencil" }
        : { key: "new", label: n("tabs.new"), icon: "Plus" },
    ],
    body: async () => {
      if (tab === "status") {
        return (
          <TnaInstances
            events={rows}
            users={options.users}
            statuses={options.statuses}
            call={forms.tnaCall}
            labels={labels}
          />
        );
      }
      if (tab === "users")
        return (
          <TnaMappedUsers events={rows} call={forms.tnaCall} labels={labels} />
        );
      if (tab === "new" || editing) {
        return (
          <EntryForm
            key={editing?.id ?? "new"}
            fields={fields(editing?.id)}
            initial={editing}
            action={
              editing
                ? forms.updateTnaEvent.bind(null, editing.id)
                : forms.createTnaEvent
            }
            title={n(editing ? "tabs.edit" : "tabs.new")}
            description={
              editing ? n("edit_hint", { code: editing.code }) : n("new_hint")
            }
            submitLabel={n(editing ? "update" : "save")}
            columns={3}
            successHref={`${basePath}?tab=records`}
            cancelHref={editing ? `${basePath}?tab=records` : undefined}
            cancelLabel={editing ? te("cancel") : undefined}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="tna-task-status"
          rows={rows.map((r) => ({
            ...r,
            rb: `${r.is_repeat ? n("yes_r") : "–"} ${r.is_buyer_feedback ? n("yes_b") : "–"}`,
          }))}
          columns={[
            { key: "code", label: n("col.code"), type: "mono" },
            { key: "name", label: n("col.name") },
            { key: "task_message", label: n("col.task_message") },
            { key: "sort_order", label: n("col.order"), type: "number" },
            { key: "parent", label: n("col.parent") },
            { key: "section", label: n("col.section") },
            { key: "assign_to_name", label: n("col.assign_to") },
            { key: "offset_days", label: n("col.offset"), type: "number" },
            { key: "rb", label: n("col.rb") },
            { key: "raised", label: n("col.raised"), type: "number" },
            { key: "active", label: n("col.status"), type: "status" },
          ]}
          edit={{
            href: `${basePath}?tab=edit&id=`,
            action: forms.updateTnaEvent,
            title: n("tabs.edit"),
            submitLabel: n("update"),
          }}
        />
      );
    },
  };
}

export { tnaTaskStatus };
