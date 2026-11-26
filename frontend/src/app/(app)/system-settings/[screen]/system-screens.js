import { apiFetch } from "@/lib/api-server";
import { getT } from "@/lib/i18n-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ActionCard } from "@/components/erp/action-card";
import { NotificationMatrix } from "@/components/settings/notification-matrix";
import { RestorePanel } from "@/components/settings/restore-panel";
import { HealthCards } from "@/components/settings/health-cards";
import * as actions from "./actions";

/*
 * Settings → System & Maintenance (AnnotechERP 03/04): notification defaults,
 * scheduled jobs, backup & restore, system health and data retention — each in
 * Brickland's layout.
 */

const opt = (value, label) => ({ value: String(value), label });
const pick = (s, base, keys) => Object.fromEntries(keys.map((k) => [k, s(`${base}.${k}`)]));

async function systemT() {
  const [ts, tn] = await Promise.all([getT("settings"), getT("notification")]);
  return { s: (k, params) => ts(`system.${k}`, params), tn };
}

/** An event's name in the reader's language; the code, made readable, when none is written. */
function eventLabel(tn, event) {
  const key = `event_${event.toLowerCase()}`;
  const label = tn(key);
  return label === key ? event.charAt(0) + event.slice(1).toLowerCase().replaceAll("_", " ") : label;
}

function bytes(n) {
  if (n === null || n === undefined) return null;
  const units = ["B", "KB", "MB", "GB"];
  let value = n;
  let i = 0;
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024;
    i++;
  }
  return `${Math.round(value * 10) / 10} ${units[i]}`;
}

/**
 * A cron expression as a person would say it, for the patterns the schedule
 * actually uses; anything else is shown as written. The schedule is written in
 * server time, so a daily or weekly job's clock time and day are read from its
 * next run, which the server already gives in the company's time zone.
 */
function describeCron(expression, nextRunAt, s) {
  const [minute, hour, dom, month, dow] = expression.split(" ");
  const local = nextRunAt ? { time: nextRunAt.slice(11, 16), day: new Date(`${nextRunAt.slice(0, 10)}T00:00:00Z`).getUTCDay() } : null;
  if (dom !== "*" || month !== "*" || !local) return expression;
  if (minute === "*" && hour === "*" && dow === "*") return s("jobs_every_minute");
  const step = /^\*\/(\d+)$/.exec(minute);
  if (step && hour === "*" && dow === "*") return s("jobs_every_minutes", { n: step[1] });
  if (minute === "0" && hour === "*" && dow === "*") return s("jobs_hourly_top");
  if (/^\d+$/.test(minute) && hour === "*" && dow === "*") return s("jobs_hourly", { m: minute });
  if (/^\d+$/.test(minute) && /^\d+$/.test(hour) && dow === "*") return s("jobs_daily", { time: local.time });
  if (/^\d+$/.test(minute) && /^\d+$/.test(hour) && /^\d$/.test(dow)) return s("jobs_weekly", { day: s(`weekdays.${local.day}`), time: local.time });
  return expression;
}

async function notificationPreferences({ te, tab }) {
  const [{ s, tn }, rows] = await Promise.all([systemT(), apiFetch("/settings/notification-defaults")]);
  return {
    defaultTab: "preferences",
    tabs: [
      { key: "preferences", label: s("notify.tab"), icon: "BellRing" },
      { key: "records", label: te("records"), icon: "Table2", count: rows.filter((r) => r.customised).length },
    ],
    body: () =>
      tab === "records" ? (
        <RecordsGrid
          exportName="notification-defaults"
          rows={rows.filter((r) => r.customised).map((r) => ({ ...r, event: eventLabel(tn, r.event_type) }))}
          columns={[
            { key: "event", label: s("notify.event") },
            { key: "event_type", label: s("notify.code"), type: "mono" },
            { key: "email", label: s("notify.email"), type: "yesno" },
            { key: "sms", label: s("notify.sms"), type: "yesno" },
            { key: "whatsapp", label: s("notify.whatsapp"), type: "yesno" },
            { key: "locked", label: s("notify.locked"), type: "yesno" },
            { key: "updated_at", label: s("updated"), type: "datetime" },
          ]}
          remove={actions.resetNotificationDefault}
          deleteDescription={s("notify.reset_hint")}
        />
      ) : (
        <NotificationMatrix rows={rows} save={actions.saveNotificationDefaults} />
      ),
  };
}

async function scheduledJobs({ te, tab, searchParams }) {
  const { s } = await systemT();
  const jobs = await apiFetch("/settings/scheduled-jobs");
  const statuses = pick(s, "jobs_statuses", ["RUNNING", "SUCCEEDED", "FAILED"]);
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: jobs.length },
      { key: "history", label: s("jobs_history"), icon: "History" },
    ],
    body: async () => {
      if (tab === "history") {
        const query = new URLSearchParams();
        for (const key of ["command", "status"]) if (searchParams[key]) query.set(key, searchParams[key]);
        const runs = await apiFetch(`/settings/scheduled-jobs/runs${query.size ? `?${query}` : ""}`);
        return (
          <>
            <FilterBar
              basePath="/system-settings/scheduled-jobs"
              keep={{ tab: "history" }}
              values={searchParams}
              labels={{ show: s("show"), reset: te("reset") }}
              fields={[
                { name: "command", label: s("jobs_command"), type: "select", options: jobs.map((j) => opt(j.command, j.command)), placeholder: s("all") },
                { name: "status", label: s("jobs_status"), type: "select", options: Object.entries(statuses).map(([v, l]) => opt(v, l)), placeholder: s("all") },
              ]}
            />
            <RecordsGrid
              exportName="scheduled-job-runs"
              rows={runs}
              columns={[
                { key: "started_at", label: s("jobs_started"), type: "datetime" },
                { key: "command", label: s("jobs_command"), type: "mono" },
                { key: "finished_at", label: s("jobs_finished"), type: "datetime" },
                { key: "duration_ms", label: s("jobs_duration"), type: "number" },
                { key: "exit_code", label: s("jobs_exit"), type: "number" },
                { key: "status", label: s("jobs_status"), type: "badge", labels: statuses },
              ]}
            />
          </>
        );
      }
      return (
        <RecordsGrid
          exportName="scheduled-jobs"
          rows={jobs.map((j) => ({ ...j, when: describeCron(j.expression, j.next_run_at, s) }))}
          columns={[
            { key: "command", label: s("jobs_command"), type: "mono" },
            { key: "description", label: s("jobs_description") },
            { key: "when", label: s("jobs_when") },
            { key: "next_run_at", label: s("jobs_next"), type: "datetime" },
            { key: "last_run_at", label: s("jobs_last"), type: "datetime" },
            { key: "last_duration_ms", label: s("jobs_duration"), type: "number" },
            { key: "last_status", label: s("jobs_status"), type: "badge", labels: statuses },
          ]}
        />
      );
    },
  };
}

async function backupRestore({ te, tab }) {
  const { s } = await systemT();
  const [backups, restores] = await Promise.all([apiFetch("/settings/backups"), apiFetch("/settings/restores").catch(() => null)]);
  const busy = backups.some((b) => b.status === "QUEUED" || b.status === "RUNNING");
  const backupStatuses = pick(s, "backup.statuses", ["QUEUED", "RUNNING", "READY", "FAILED", "EXPIRED"]);
  const restoreStatuses = pick(s, "backup.restore_statuses", ["INVALID", "PREVIEWED", "QUEUED", "RUNNING", "APPLIED", "FAILED"]);
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: backups.length },
      ...(restores ? [{ key: "restore", label: s("backup.restore_tab"), icon: "History", count: restores.length }] : []),
      { key: "new", label: s("backup.new"), icon: "Plus" },
    ],
    body: () => {
      if (tab === "new") {
        return (
          <ActionCard
            title={s("backup.new")}
            description={s("backup.new_hint")}
            buttonLabel={s("backup.now")}
            icon="DatabaseBackup"
            action={actions.createBackup}
            doneText={s("backup.queued")}
            disabled={busy}
            note={busy ? s("backup.busy") : undefined}
          />
        );
      }
      if (tab === "restore" && restores) {
        return (
          <>
            <RestorePanel apply={actions.applyRestore} pending={restores.find((r) => r.status === "PREVIEWED") ?? null} />
            <RecordsGrid
              exportName="restores"
              rows={restores}
              columns={[
                { key: "created_at", label: s("backup.uploaded"), type: "datetime" },
                { key: "original_name", label: s("backup.file") },
                { key: "backup_created_at", label: s("backup.taken"), type: "datetime" },
                { key: "rows", label: s("backup.rows"), type: "number" },
                { key: "applied_at", label: s("backup.applied"), type: "datetime" },
                { key: "error", label: s("backup.error") },
                { key: "status", label: s("backup.status"), type: "badge", labels: restoreStatuses },
              ]}
            />
          </>
        );
      }
      return (
        <RecordsGrid
          exportName="backups"
          rows={backups.map((b) => ({ ...b, size: bytes(b.size_bytes), download: b.downloadable ? `/api/settings-backups/${b.id}/download` : null }))}
          columns={[
            { key: "created_at", label: s("backup.created"), type: "datetime" },
            { key: "kind", label: s("backup.kind"), type: "badge", labels: pick(s, "backup.kinds", ["MANUAL", "PRE_RESTORE"]) },
            { key: "tables", label: s("backup.tables"), type: "number" },
            { key: "rows", label: s("backup.rows"), type: "number" },
            { key: "size", label: s("backup.size") },
            { key: "finished_at", label: s("backup.finished"), type: "datetime" },
            { key: "status", label: s("backup.status"), type: "badge", labels: backupStatuses },
            { key: "download", label: s("backup.file"), type: "download" },
          ]}
        />
      );
    },
  };
}

async function systemHealth() {
  const { s } = await systemT();
  const cards = await apiFetch("/settings/system-health");
  return {
    defaultTab: "overview",
    tabs: [{ key: "overview", label: s("health.tab"), icon: "HeartPulse" }],
    body: () => (
      <HealthCards
        cards={cards}
        labels={{
          names: pick(s, "health.names", ["database", "cache", "queue", "storage", "scheduler", "mail", "sms", "backup", "app"]),
          statuses: pick(s, "health.statuses", ["OK", "WARNING", "DOWN"]),
          overall: pick(s, "health.overall", ["OK", "WARNING", "DOWN"]),
        }}
      />
    ),
  };
}

async function dataRetention({ te, tab }) {
  const { s } = await systemT();
  const rows = await apiFetch("/settings/retention");
  const categories = pick(
    s,
    "retention.categories",
    rows.map((r) => r.category),
  );
  return {
    defaultTab: "policies",
    tabs: [
      { key: "policies", label: s("retention.policies"), icon: "SlidersHorizontal" },
      { key: "records", label: s("retention.preview"), icon: "Table2", count: rows.length },
    ],
    body: () => {
      if (tab === "records") {
        const due = rows.reduce((sum, r) => sum + r.due, 0);
        return (
          <>
            <ActionCard
              title={s("retention.purge_title")}
              description={s("retention.purge_hint", { count: due })}
              buttonLabel={s("retention.purge_now")}
              icon="Trash2"
              action={actions.purgeRetention}
              doneText={s("retention.purged")}
              disabled={due === 0}
              confirm={{ title: s("retention.confirm_title"), description: s("retention.confirm_text", { count: due }), confirmLabel: s("retention.purge_now") }}
            />
            <RecordsGrid
              exportName="data-retention"
              rows={rows.map((r) => ({ ...r, name: categories[r.category] }))}
              columns={[
                { key: "name", label: s("retention.category") },
                { key: "keep_days", label: s("retention.keep_days"), type: "number" },
                { key: "cutoff", label: s("retention.cutoff"), type: "date" },
                { key: "due", label: s("retention.due"), type: "number" },
                { key: "last_purged_at", label: s("retention.last_purged"), type: "datetime" },
                { key: "last_purged_count", label: s("retention.last_count"), type: "number" },
                { key: "active", label: s("retention.active"), type: "status" },
              ]}
            />
          </>
        );
      }
      const fields = rows.flatMap((r) => [
        {
          name: `${r.category}__keep_days`,
          label: `${categories[r.category]} — ${s("retention.keep_days")}`,
          type: "number",
          required: true,
          help: s("retention.days_help", { fallback: r.default_days, min: r.minimum_days }),
        },
        { name: `${r.category}__active`, label: s("retention.active_label"), type: "checkbox", help: s(`retention.what.${r.category}`) },
      ]);
      const initial = Object.fromEntries(
        rows.flatMap((r) => [
          [`${r.category}__keep_days`, String(r.keep_days)],
          [`${r.category}__active`, r.active],
        ]),
      );
      return (
        <EntryForm
          fields={fields}
          initial={initial}
          action={actions.saveRetention.bind(
            null,
            rows.map((r) => r.category),
          )}
          title={s("retention.policies")}
          description={s("retention.form_hint")}
          submitLabel={s("retention.save")}
          successHref="/system-settings/data-retention?tab=records"
        />
      );
    },
  };
}

const SYSTEM_SCREENS = {
  "notification-preferences": notificationPreferences,
  "scheduled-jobs": scheduledJobs,
  "backup-restore": backupRestore,
  "system-health": systemHealth,
  "data-retention": dataRetention,
};

export { SYSTEM_SCREENS };
