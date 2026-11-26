"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save, Search } from "lucide-react";
import { Card, CardBody, CardFooter } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const SOURCE_VARIANT = { COMPANY: "brand", FACTORY: "info", PLATFORM: "neutral" };

function toText(type, value) {
  if (type === "BOOL") return value ? "true" : "false";
  if (type === "LIST") return Array.isArray(value) ? value.join(",") : (value ?? "");
  return value === null || value === undefined ? "" : String(value);
}

/**
 * System Parameter Update (AnnotechERP 02/01): Brickland's Records grid —
 * Group, Key, Parameter, Value, Type, where the value comes from, and who
 * changed it last — over the company's settings. A value is saved row by row;
 * "Reset to default" drops the company's own answer.
 */
function SystemParameters({ parameters, factoryId = null, updateParameter, resetParameter }) {
  const t = useT("access");
  const ts = useT("settings");
  const router = useRouter();
  const toastManager = useToastManager();
  const [query, setQuery] = useState("");
  const [drafts, setDrafts] = useState({});
  const [busyKey, setBusyKey] = useState(null);
  const [, startTransition] = useTransition();

  const groupLabel = (group) => (ts(`groups.${group}`) === `groups.${group}` ? group : ts(`groups.${group}`));

  const rows = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return parameters;
    return parameters.filter((row) =>
      [row.group, groupLabel(row.group), row.key, row.name, toText(row.value_type, row.value)].some((text) => String(text).toLowerCase().includes(needle)),
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [parameters, query]);

  function draftOf(row) {
    return row.key in drafts ? drafts[row.key] : toText(row.value_type, row.value);
  }

  function setDraft(row, value) {
    setDrafts((current) => {
      const next = { ...current, [row.key]: value };
      if (value === toText(row.value_type, row.value)) delete next[row.key];
      return next;
    });
  }

  function run(row, call, message) {
    setBusyKey(row.key);
    startTransition(async () => {
      const result = await call();
      setBusyKey(null);
      if (result?.status === "success") {
        setDrafts((current) => {
          const next = { ...current };
          delete next[row.key];
          return next;
        });
        toastManager.add({ title: t(message, { name: row.name }), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.errors?.value?.[0] ?? result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardBody className="flex flex-wrap items-center gap-3 border-b border-border py-3">
        <div className="relative min-w-[200px] flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder={t("search_parameters")} className="pl-9" />
        </div>
      </CardBody>
      <CardBody className="overflow-x-auto p-0">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-primary text-left text-primary-foreground">
              <th className="px-3 py-2.5 font-semibold">{t("sl")}</th>
              {["group", "key", "name", "value", "type", "source", "updated"].map((column) => (
                <th key={column} className="px-3 py-2.5 font-semibold whitespace-nowrap">
                  {t(`param_fields.${column}`)}
                </th>
              ))}
              <th className="px-3 py-2.5 text-right font-semibold">{t("action")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => {
              const dirty = row.key in drafts;
              return (
                <tr key={row.key} className={dirty ? "bg-warning-subtle/40" : "border-b border-border last:border-0"}>
                  <td className="px-3 py-2 text-foreground-muted">{index + 1}</td>
                  <td className="px-3 py-2 whitespace-nowrap">{groupLabel(row.group)}</td>
                  <td className="max-w-56 px-3 py-2 font-mono text-xs break-all whitespace-normal">{row.key}</td>
                  <td className="max-w-72 min-w-56 px-3 py-2 whitespace-normal">
                    <div className="font-medium text-foreground">{row.name}</div>
                    {row.description ? (
                      <div className="line-clamp-2 text-xs text-foreground-muted" title={row.description}>
                        {row.description}
                      </div>
                    ) : null}
                  </td>
                  <td className="px-3 py-2">
                    {row.editable ? (
                      <ValueInput row={row} value={draftOf(row)} onChange={(value) => setDraft(row, value)} t={t} />
                    ) : (
                      <span className="text-foreground-muted">{toText(row.value_type, row.value) || "—"}</span>
                    )}
                  </td>
                  <td className="px-3 py-2 whitespace-nowrap">{t(`param_types.${row.value_type}`)}</td>
                  <td className="px-3 py-2">
                    <Badge variant={SOURCE_VARIANT[row.level] ?? "neutral"}>{t(`param_sources.${row.level}`)}</Badge>
                  </td>
                  <td className="px-3 py-2 text-xs whitespace-nowrap text-foreground-muted">
                    {row.updated_at ? (
                      <>
                        <FormattedDateTime value={row.updated_at} />
                        {row.updated_by ? <div>{row.updated_by}</div> : null}
                      </>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="px-3 py-2 text-right whitespace-nowrap">
                    {row.editable ? (
                      <>
                        <Button
                          size="sm"
                          variant="outline"
                          disabled={!dirty}
                          loading={busyKey === row.key}
                          onClick={() => run(row, () => updateParameter(row.key, drafts[row.key], factoryId), "param_saved")}
                        >
                          <Save />
                        </Button>
                        {row.level === (factoryId ? "FACTORY" : "COMPANY") ? (
                          <Button
                            size="sm"
                            variant="ghost"
                            aria-label={t("reset_to_default")}
                            title={t("reset_to_default")}
                            onClick={() => run(row, () => resetParameter(row.key, factoryId), "param_reset")}
                          >
                            <RotateCcw />
                          </Button>
                        ) : null}
                      </>
                    ) : null}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </CardBody>
      <CardFooter>
        <p className="text-sm text-foreground-muted">{t("param_note")}</p>
      </CardFooter>
    </Card>
  );
}

function ValueInput({ row, value, onChange, t }) {
  if (row.value_type === "BOOL") {
    return (
      <label className="flex items-center gap-2 text-sm">
        <Checkbox checked={value === "true"} onCheckedChange={(checked) => onChange(checked ? "true" : "false")} aria-label={row.name} />
        {value === "true" ? t("on") : t("off")}
      </label>
    );
  }
  if (row.value_type === "ENUM") {
    return <Select value={value} onValueChange={onChange} options={(row.allowed_values ?? []).map((v) => ({ value: v, label: v }))} className="min-w-40" aria-label={row.name} />;
  }
  const numeric = row.value_type === "INT" || row.value_type === "DECIMAL";
  return (
    <Input
      value={value}
      onChange={(event) => onChange(event.target.value)}
      type={numeric ? "number" : "text"}
      step={row.value_type === "DECIMAL" ? "0.01" : undefined}
      className={numeric ? "w-28" : "min-w-40"}
      aria-label={row.name}
    />
  );
}

export { SystemParameters };
