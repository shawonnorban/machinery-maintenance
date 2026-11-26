import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { ApiClientForm } from "@/components/settings/api-client-form";
import { WebhookForm } from "@/components/webhooks/webhook-form";
import { ApiKeyActions } from "@/components/settings/api-key-actions";
import { WebhookHeaderActions } from "@/components/webhooks/webhook-header-actions";
import { DeliveriesTable } from "@/components/webhooks/deliveries-table";
import { findByRef, refsFor } from "@/lib/row-ref";
import * as integration from "./integration-actions";
import * as actions from "./actions";

/*
 * Settings → Integration Hub (AnnotechERP 03/02): endpoints, the activity log,
 * SMS gateways, bank integrations, and API keys & webhooks over the existing
 * engines — each in Brickland's layout.
 */

const opt = (value, label) => ({ value: String(value), label });
const labels = (h, base, keys) => Object.fromEntries(keys.map((k) => [k, h(`${base}.${k}`)]));

async function integrationEndpoints({ h, te }) {
  const rows = await apiFetch("/settings/integration/endpoints");
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }],
    body: () => (
      <RecordsGrid
        exportName="integration-endpoints"
        rows={rows}
        columns={[
          { key: "endpoint", label: h("endpoint.endpoint") },
          { key: "direction", label: h("endpoint.direction"), type: "badge", labels: labels(h, "endpoint.directions", ["INBOUND", "OUTBOUND"]) },
          { key: "address", label: h("endpoint.address"), type: "mono" },
          { key: "port", label: h("endpoint.port"), type: "number" },
          { key: "set_in", label: h("endpoint.set_in") },
          { key: "notes", label: h("endpoint.notes") },
          { key: "active", label: h("endpoint.status"), type: "status" },
        ]}
      />
    ),
  };
}

async function integrationActivity({ h, te, searchParams }) {
  const query = new URLSearchParams();
  for (const key of ["channel", "status", "from", "to"]) if (searchParams[key]) query.set(key, searchParams[key]);
  const rows = await apiFetch(`/settings/integration/activity${query.size ? `?${query}` : ""}`);
  const channels = labels(h, "activity.channels", ["SMS", "BANK", "MAIL", "WEBHOOK", "API", "DEVICE"]);
  const statuses = labels(h, "activity.statuses", ["SUCCEEDED", "FAILED"]);
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }],
    body: () => (
      <>
        <FilterBar
          basePath="/system-settings/integration-activity"
          values={searchParams}
          labels={{ show: h("activity.show"), reset: h("activity.reset") }}
          fields={[
            { name: "channel", label: h("activity.channel"), type: "select", options: Object.entries(channels).map(([v, l]) => opt(v, l)), placeholder: h("activity.all") },
            { name: "status", label: h("activity.status"), type: "select", options: Object.entries(statuses).map(([v, l]) => opt(v, l)), placeholder: h("activity.all") },
            { name: "from", label: h("activity.from_date"), type: "date" },
            { name: "to", label: h("activity.to_date"), type: "date" },
          ]}
        />
        <RecordsGrid
          exportName="integration-activity"
          rows={rows}
          columns={[
            { key: "occurred_at", label: h("activity.time"), type: "datetime" },
            { key: "channel", label: h("activity.channel"), type: "badge", labels: channels },
            { key: "event", label: h("activity.event"), type: "mono" },
            { key: "records", label: h("activity.records"), type: "number" },
            { key: "source", label: h("activity.source") },
            { key: "target", label: h("activity.target") },
            { key: "details", label: h("activity.details") },
            { key: "status", label: h("activity.status"), type: "badge", labels: statuses },
          ]}
        />
      </>
    ),
  };
}

const smsFields = (h) => [
  { name: "name", label: h("sms.name"), required: true, placeholder: "Main SMS" },
  { name: "provider", label: h("sms.provider"), type: "select", required: true, options: Object.entries(labels(h, "sms.providers", ["BULKSMSBD", "SSL_WIRELESS", "TWILIO", "GENERIC_HTTP"])).map(([v, l]) => opt(v, l)) },
  { name: "base_url", label: h("sms.base_url"), help: h("sms.base_url_help"), placeholder: "https://sms.example.com/send" },
  { name: "username", label: h("sms.username"), help: h("sms.username_help") },
  { name: "sender_id", label: h("sms.sender_id"), placeholder: "ANNOTECH" },
  { name: "api_key_env", label: h("sms.api_key_env"), required: true, placeholder: "SMS_API_KEY", help: h("sms.api_key_env_help") },
  { name: "is_default", label: h("sms.is_default"), type: "checkbox", help: h("sms.is_default_help") },
  { name: "active", label: h("sms.active"), type: "checkbox", default: true },
];

async function smsGateway({ h, te, tab }) {
  const rows = await apiFetch("/erp/sms-gateways");
  const providers = labels(h, "sms.providers", ["BULKSMSBD", "SSL_WIRELESS", "TWILIO", "GENERIC_HTTP"]);
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "test", label: h("sms.test_tab"), icon: "Send" },
      { key: "new", label: h("sms.new"), icon: "Plus" },
    ],
    body: () => {
      if (tab === "new") {
        return <EntryForm fields={smsFields(h)} action={actions.createResource.bind(null, "sms-gateways", ["is_default", "active"])} title={h("sms.new")} description={h("sms.hint")} submitLabel={h("sms.save")} successHref="/system-settings/sms-gateway?tab=records" />;
      }
      if (tab === "test") {
        return (
          <EntryForm
            fields={[
              { name: "gateway_id", label: h("sms.gateway"), type: "select", required: true, options: rows.filter((r) => r.active).map((r) => opt(r.id, r.name)), default: rows.find((r) => r.is_default)?.id },
              { name: "to", label: h("sms.test_to"), required: true, placeholder: "8801XXXXXXXXX" },
              { name: "message", label: h("sms.test_message"), type: "textarea", placeholder: h("sms.test_text") },
            ]}
            action={actions.testSms}
            title={h("sms.test_tab")}
            description={h("sms.hint")}
            submitLabel={h("sms.test_send")}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="sms-gateways"
          rows={rows}
          columns={[
            { key: "name", label: h("sms.name") },
            { key: "provider", label: h("sms.provider"), type: "badge", labels: providers },
            { key: "sender_id", label: h("sms.sender_id"), type: "mono" },
            { key: "api_key_env", label: h("sms.api_key_env"), type: "mono" },
            { key: "is_default", label: h("sms.is_default"), type: "yesno" },
            { key: "last_used_at", label: h("sms.last_used"), type: "datetime" },
            { key: "active", label: h("sms.active"), type: "status" },
          ]}
          edit={{ fields: smsFields(h), action: actions.updateResource.bind(null, "sms-gateways", ["is_default", "active"]), title: h("sms.edit"), submitLabel: h("sms.save") }}
          remove={actions.deleteResource.bind(null, "sms-gateways")}
        />
      );
    },
  };
}

const bankFields = (h) => [
  { name: "bank_name", label: h("bank.bank_name"), required: true, placeholder: "BRAC Bank PLC" },
  { name: "account_name", label: h("bank.account_name") },
  { name: "account_no", label: h("bank.account_no"), required: true },
  { name: "routing_no", label: h("bank.routing_no") },
  { name: "swift_code", label: h("bank.swift_code"), placeholder: "BRAKBDDH" },
  { name: "channel", label: h("bank.channel"), type: "select", required: true, options: Object.entries(labels(h, "bank.channels", ["API", "SFTP", "FILE"])).map(([v, l]) => opt(v, l)) },
  { name: "file_format", label: h("bank.file_format"), type: "select", options: Object.entries(labels(h, "bank.file_formats", ["NONE", "CSV", "MT940", "ISO20022"])).map(([v, l]) => opt(v, l)), default: "NONE" },
  { name: "endpoint", label: h("bank.endpoint"), help: h("bank.endpoint_help") },
  { name: "credential_env", label: h("bank.credential_env"), placeholder: "BRAC_API_TOKEN", help: h("sms.api_key_env_help") },
  { name: "active", label: h("bank.active"), type: "checkbox", default: true },
];

async function bankIntegration({ h, te, tab }) {
  const rows = await apiFetch("/erp/bank-integrations");
  return {
    tabs: [
      { key: "records", label: te("records"), icon: "Table2", count: rows.length },
      { key: "new", label: h("bank.new"), icon: "Plus" },
    ],
    body: () =>
      tab === "new" ? (
        <EntryForm fields={bankFields(h)} action={actions.createResource.bind(null, "bank-integrations", ["active"])} title={h("bank.new")} description={h("bank.hint")} submitLabel={h("bank.save")} columns={3} successHref="/system-settings/bank-integration?tab=records" />
      ) : (
        <RecordsGrid
          exportName="bank-integrations"
          rows={rows.map((r) => ({ ...r, test_ok: r.last_test_status ? r.last_test_status === "SUCCEEDED" : null }))}
          columns={[
            { key: "bank_name", label: h("bank.bank_name") },
            { key: "account_no", label: h("bank.account_no"), type: "mono" },
            { key: "swift_code", label: h("bank.swift_code"), type: "mono" },
            { key: "channel", label: h("bank.channel"), type: "badge", labels: labels(h, "bank.channels", ["API", "SFTP", "FILE"]) },
            { key: "file_format", label: h("bank.file_format"), type: "badge", labels: labels(h, "bank.file_formats", ["NONE", "CSV", "MT940", "ISO20022"]) },
            { key: "last_test_status", label: h("bank.last_test"), type: "badge", labels: labels(h, "bank.test_statuses", ["SUCCEEDED", "FAILED"]) },
            { key: "last_tested_at", label: h("bank.last_tested"), type: "datetime" },
            { key: "active", label: h("bank.active"), type: "status" },
          ]}
          rowAction={{ label: h("bank.test"), action: actions.testBank }}
          edit={{ fields: bankFields(h), action: actions.updateResource.bind(null, "bank-integrations", ["active"]), title: h("bank.edit"), submitLabel: h("bank.save"), columns: 3 }}
          remove={actions.deleteResource.bind(null, "bank-integrations")}
        />
      ),
  };
}

const SCREEN = "/system-settings/api-keys-webhooks";

/** A label over its value, as Brickland's detail cards show a record's facts. */
function Fact({ label, children }) {
  return (
    <div>
      <div className="text-xs text-foreground-muted">{label}</div>
      <div className="font-medium text-foreground">{children}</div>
    </div>
  );
}

/** Active / Inactive in Brickland's pill colours. */
function Live({ on, h }) {
  return (
    <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${on ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted"}`}>
      {on ? h("api.active") : h("api.inactive")}
    </span>
  );
}

async function apiKeysWebhooks({ h, te, tab, searchParams = {} }) {
  const [clients, hooks] = await Promise.all([apiFetch("/api-clients").catch(() => []), apiFetch("/webhooks").catch(() => [])]);
  // A key is named by its Client ID, a webhook by its URL — never the row id.
  const client = tab === "key" || tab === "edit-key" ? findByRef(clients, "client_id", searchParams.key) : null;
  const hook = tab === "webhook" || tab === "edit-webhook" ? findByRef(hooks, "url", searchParams.hook) : null;
  const keyRef = client ? refsFor(clients, "client_id").get(client.id) : null;
  const hookRef = hook ? refsFor(hooks, "url").get(hook.id) : null;
  const tabs = [
    { key: "records", label: h("keys_tab"), icon: "KeyRound", count: clients.length },
    { key: "webhooks", label: h("webhooks_tab"), icon: "Webhook", count: hooks.length },
    { key: "new-key", label: h("new_key"), icon: "Plus" },
    { key: "new-webhook", label: h("new_webhook"), icon: "Plus" },
  ];
  if (client) tabs.push({ key: tab, label: tab === "edit-key" ? h("api.edit_key") : client.name, icon: tab === "edit-key" ? "Pencil" : "Eye" });
  if (hook) tabs.push({ key: tab, label: tab === "edit-webhook" ? h("api.edit_webhook") : h("api.webhook"), icon: tab === "edit-webhook" ? "Pencil" : "Eye" });
  return {
    tabs,
    body: async () => {
      if (tab === "new-key") {
        const options = await apiFetch("/api-clients/form-options");
        return (
          <div className="rounded-lg border border-border bg-surface p-5">
            <ApiClientForm modules={options.modules} action={integration.createApiClient} doneHref={SCREEN} />
          </div>
        );
      }
      if (tab === "new-webhook") {
        const events = await apiFetch("/webhooks/events");
        return (
          <div className="rounded-lg border border-border bg-surface p-5">
            <WebhookForm eventOptions={events} action={integration.createWebhook} doneHref={`${SCREEN}?tab=webhooks`} />
          </div>
        );
      }
      if (client && tab === "edit-key") {
        const options = await apiFetch("/api-clients/form-options");
        return (
          <div className="rounded-lg border border-border bg-surface p-5">
            <p className="mb-4 text-sm text-foreground-muted">{h("api.narrowing_hint")}</p>
            <ApiClientForm client={client} modules={options.modules} action={integration.updateScopes.bind(null, client.id, keyRef)} />
          </div>
        );
      }
      if (client) {
        return (
          <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-border bg-surface p-5 text-sm">
              <div className="flex flex-wrap gap-6">
                <Fact label={h("api.name")}>{client.name}</Fact>
                <Fact label="Client ID">
                  <span className="font-mono text-xs">{client.client_id}</span>
                </Fact>
                <Fact label={h("api.status")}>
                  <Live on={Boolean(client.is_usable)} h={h} />
                </Fact>
                <Fact label={h("api.last_used")}>{client.last_used_at ? client.last_used_at.slice(0, 16).replace("T", " ") : "—"}</Fact>
                <Fact label={h("api.expires")}>{client.expires_at ? client.expires_at.slice(0, 10) : "—"}</Fact>
              </div>
              {client.is_usable ? (
                <div className="flex gap-2">
                  <a href={`${SCREEN}?tab=edit-key&key=${encodeURIComponent(keyRef)}`} className="inline-flex h-8 items-center gap-1.5 rounded-sm border border-border-strong px-3 text-sm font-medium hover:bg-surface-muted">
                    {h("api.edit_scopes")}
                  </a>
                  <ApiKeyActions client={client} rotate={integration.rotateClientSecret} />
                </div>
              ) : null}
            </div>
            <div className="rounded-lg border border-border bg-surface p-5">
              <p className="mb-3 text-sm font-semibold text-foreground">{h("api.scopes")}</p>
              <p className="font-mono text-xs leading-6 text-foreground">{client.scopes?.length ? client.scopes.join(", ") : "—"}</p>
            </div>
          </div>
        );
      }
      if (hook && tab === "edit-webhook") {
        const [endpoint, events] = await Promise.all([apiFetch(`/webhooks/${hook.id}`), apiFetch("/webhooks/events")]);
        return (
          <div className="rounded-lg border border-border bg-surface p-5">
            <WebhookForm endpoint={endpoint} eventOptions={events} action={integration.updateWebhook.bind(null, hook.id, hookRef)} />
          </div>
        );
      }
      if (hook) {
        const endpoint = await apiFetch(`/webhooks/${hook.id}`);
        return (
          <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-border bg-surface p-5 text-sm">
              <div className="flex flex-wrap gap-6">
                <Fact label={h("api.url")}>
                  <span className="font-mono text-xs break-all">{endpoint.url}</span>
                </Fact>
                <Fact label={h("api.status")}>
                  <Live on={endpoint.status === "ACTIVE"} h={h} />
                </Fact>
                <Fact label={h("api.events")}>{endpoint.events?.join(", ") || "—"}</Fact>
                <Fact label={h("api.failures")}>{endpoint.consecutive_failure_count}</Fact>
                {endpoint.disabled_reason ? (
                  <Fact label={h("api.disabled_reason")}>
                    <span className="text-danger">{endpoint.disabled_reason}</span>
                  </Fact>
                ) : null}
              </div>
              <WebhookHeaderActions
                endpoint={endpoint}
                editHref={`${SCREEN}?tab=edit-webhook&hook=${encodeURIComponent(hookRef)}`}
                actions={{ enableEndpoint: integration.enableEndpoint, pauseEndpoint: integration.pauseEndpoint, rotateSecret: integration.rotateWebhookSecret }}
              />
            </div>
            <div className="rounded-lg border border-border bg-surface p-5">
              <p className="mb-4 text-sm font-semibold text-foreground">{h("api.recent_deliveries")}</p>
              <DeliveriesTable deliveries={endpoint.recent_deliveries ?? []} endpointId={endpoint.id} redeliver={integration.redeliver} />
            </div>
          </div>
        );
      }
      if (tab === "webhooks") {
        return (
          <RecordsGrid
            exportName="webhooks"
            rows={hooks.map((w) => ({ ...w, live: w.status === "ACTIVE" }))}
            columns={[
              { key: "url", label: h("api.url"), type: "mono" },
              { key: "description", label: h("api.name") },
              { key: "subscriptions_count", label: h("api.events"), type: "number" },
              { key: "consecutive_failure_count", label: h("api.failures"), type: "number" },
              { key: "live", label: h("api.status"), type: "status" },
            ]}
            rowLink={{ label: h("api.open"), href: `${SCREEN}?tab=webhook&hook=`, refKey: "url" }}
            remove={integration.deleteEndpoint}
            deleteDescription={h("api.webhook_delete_hint")}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="api-keys"
          rows={clients.map((c) => ({ ...c, scope_count: c.scopes?.length ?? 0 }))}
          lockedField="revoked_at"
          columns={[
            { key: "name", label: h("api.name") },
            { key: "client_id", label: "Client ID", type: "mono" },
            { key: "scope_count", label: h("api.scopes"), type: "number" },
            { key: "last_used_at", label: h("api.last_used"), type: "datetime" },
            { key: "expires_at", label: h("api.expires"), type: "date" },
            { key: "is_usable", label: h("api.status"), type: "status" },
          ]}
          rowLink={{ label: h("api.open"), href: `${SCREEN}?tab=key&key=`, refKey: "client_id" }}
          remove={integration.revokeClient}
          deleteDescription={h("api.revoke_hint")}
        />
      );
    },
  };
}

/* ── AI Services: every AI feature's key and service (the user's decision of 2026-11-12) ── */

const AI_PROVIDERS = ["ANTHROPIC", "OPENAI", "GEMINI"];
const AI_FEATURES = ["FILE_READING", "ORDER_ANALYSIS"];
const AI_MODELS = { ANTHROPIC: "claude-opus-5-5", OPENAI: "", GEMINI: "" };

const aiServiceFields = (h, edit) => [
  { name: "provider", label: h("ai.provider"), type: "select", required: true, options: Object.entries(labels(h, "ai.providers", AI_PROVIDERS)).map(([v, l]) => opt(v, l)), default: "ANTHROPIC" },
  { name: "model", label: h("ai.model"), required: true, placeholder: AI_MODELS.ANTHROPIC, default: edit ? undefined : AI_MODELS.ANTHROPIC, help: h("ai.model_help") },
  { name: "api_key", label: h("ai.api_key"), type: "password", required: !edit, placeholder: edit ? h("ai.api_key_keep") : "sk-…", help: h("ai.api_key_help"), span: 2 },
  { name: "enabled", label: h("ai.enabled"), type: "checkbox", default: true },
];

async function aiServices({ h, te, tab }) {
  const [rows, features] = await Promise.all([apiFetch("/erp/ai-services"), apiFetch("/ai/features")]);
  const providers = labels(h, "ai.providers", AI_PROVIDERS);
  const shown = rows.map((r) => ({ ...r, test_label: r.last_test_ok === true ? h("ai.tested_ok") : r.last_test_ok === false ? h("ai.tested_fail") : h("ai.untested"), test_state: r.last_test_ok === true ? "OK" : r.last_test_ok === false ? "FAIL" : "NONE" }));
  const serviceOptions = [opt("", h("ai.none")), ...rows.map((r) => opt(r.id, `${providers[r.provider] ?? r.provider} · ${r.model}`))];
  return {
    tabs: [
      { key: "records", label: h("ai.services_tab"), icon: "Bot", count: rows.length },
      { key: "features", label: h("ai.features_tab"), icon: "Sparkles", count: features.length },
      { key: "new", label: h("ai.new"), icon: "Plus" },
    ],
    body: () => {
      if (tab === "new") {
        return <EntryForm fields={aiServiceFields(h, false)} action={actions.createResource.bind(null, "ai-services", ["enabled"])} title={h("ai.new")} description={h("ai.hint")} submitLabel={h("ai.save")} successHref="/system-settings/ai-services?tab=records" />;
      }
      if (tab === "features") {
        return (
          <RecordsGrid
            exportName="ai-features"
            rows={features.map((f) => ({ ...f, feature_label: h(`ai.features.${f.feature}`), service_label: f.service ? `${providers[f.service] ?? f.service} · ${f.service_model}` : h("ai.none") }))}
            columns={[
              { key: "feature_label", label: h("ai.feature") },
              { key: "service_label", label: h("ai.service") },
              { key: "auto_run", label: h("ai.auto_run"), type: "yesno" },
              { key: "enabled", label: h("ai.enabled"), type: "status" },
            ]}
            edit={{
              fields: [
                { name: "service_id", label: h("ai.service"), type: "select", options: serviceOptions, help: h("ai.service_help") },
                { name: "auto_run", label: h("ai.auto_run"), type: "checkbox", help: h("ai.auto_run_help") },
                { name: "enabled", label: h("ai.enabled"), type: "checkbox" },
              ],
              action: actions.updateResource.bind(null, "ai-features", ["auto_run", "enabled"]),
              title: h("ai.edit_feature"),
              submitLabel: h("ai.save"),
            }}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="ai-services"
          rows={shown}
          columns={[
            { key: "provider", label: h("ai.provider"), type: "badge", labels: providers },
            { key: "model", label: h("ai.model"), type: "mono" },
            { key: "key_hint", label: h("ai.key"), type: "mono" },
            { key: "test_state", label: h("ai.last_test"), type: "badge", labels: { OK: h("ai.tested_ok"), FAIL: h("ai.tested_fail"), NONE: h("ai.untested") }, tones: { OK: "success", FAIL: "danger", NONE: "muted" } },
            { key: "last_test_message", label: h("ai.test_message") },
            { key: "last_tested_at", label: h("ai.tested_at"), type: "datetime" },
            { key: "enabled", label: h("ai.enabled"), type: "status" },
          ]}
          rowAction={{ label: h("ai.test"), action: actions.testAiService }}
          edit={{ fields: aiServiceFields(h, true), action: actions.updateResource.bind(null, "ai-services", ["enabled"]), title: h("ai.edit"), submitLabel: h("ai.save") }}
          remove={actions.deleteResource.bind(null, "ai-services")}
        />
      );
    },
  };
}

const HUB_SCREENS = {
  "integration-endpoints": integrationEndpoints,
  "integration-activity": integrationActivity,
  "sms-gateway": smsGateway,
  "bank-integration": bankIntegration,
  "api-keys-webhooks": apiKeysWebhooks,
  "ai-services": aiServices,
};

export { HUB_SCREENS };
