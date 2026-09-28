"use client";

import { startTransition, useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const INPUT_TYPES = ["PASS_FAIL", "NUMERIC", "TEXT", "CHOICE", "PHOTO", "SIGNATURE"];

/** Mirrors `templates/show.blade.php`'s items table and its own inline add-check form. */
function TemplateVersionPanel({ templateId, version, inputTypes = INPUT_TYPES, canEdit, actions }) {
  const t = useT("maintenance");
  const router = useRouter();
  const [pending, startPending] = useTransition();
  const [removing, setRemoving] = useState(null);
  const toastManager = useToastManager();

  const editable = canEdit && version?.status === "DRAFT";

  function runRemove() {
    startPending(async () => {
      const result = await actions.removeItem(templateId, version.id, removing.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("item_removed"), type: "success" });
        setRemoving(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader className="items-center">
        <CardTitle>{t("checks")}</CardTitle>
        {version ? (
          <div className="ml-auto flex items-center gap-2 text-sm">
            <span className="text-foreground-muted">{t("version_label", { number: version.version_number })}</span>
            <StatusBadge status={version.status} label={t(`status_${version.status?.toLowerCase()}`)} />
          </div>
        ) : null}
      </CardHeader>
      <CardBody>
        {!version || version.items.length === 0 ? (
          <EmptyState title={t("no_checks")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-xs font-medium text-foreground-muted">
                <tr>
                  <th className="w-10 px-2 py-2 text-left">#</th>
                  <th className="px-2 py-2 text-left">{t("label")}</th>
                  <th className="px-2 py-2 text-left">{t("input_type")}</th>
                  <th className="px-2 py-2 text-left">{t("tolerance")}</th>
                  <th className="px-2 py-2 text-right"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {version.items.map((item) => (
                  <tr key={item.id}>
                    <td className="px-2 py-2 text-foreground-muted">{item.sequence}</td>
                    <td className="px-2 py-2">
                      {item.label}
                      {item.help_text ? <div className="text-xs text-foreground-muted">{item.help_text}</div> : null}
                    </td>
                    <td className="px-2 py-2 text-xs">
                      {t(`input_${item.input_type?.toLowerCase()}`)}
                      {item.unit ? <span className="text-foreground-muted"> ({item.unit})</span> : null}
                    </td>
                    <td className="px-2 py-2 text-xs text-foreground-muted">
                      {item.tolerance_min !== null || item.tolerance_max !== null
                        ? `${item.tolerance_min ?? "−∞"} … ${item.tolerance_max ?? "∞"}`
                        : "—"}
                    </td>
                    <td className="px-2 py-2">
                      <div className="flex justify-end gap-1">
                        {item.required ? <Badge variant="info">{t("required")}</Badge> : null}
                        {item.is_safety_item ? <Badge variant="danger">{t("safety")}</Badge> : null}
                        {editable ? (
                          <button
                            type="button"
                            onClick={() => setRemoving(item)}
                            className="rounded-sm px-2 text-xs text-danger hover:bg-danger-subtle"
                          >
                            {t("remove")}
                          </button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {editable ? (
          <div className="mt-4 border-t border-border pt-4">
            <AddItemForm templateId={templateId} versionId={version.id} inputTypes={inputTypes} action={actions.addItem} />
          </div>
        ) : null}
      </CardBody>

      <ConfirmDialog
        open={Boolean(removing)}
        onOpenChange={() => setRemoving(null)}
        title={t("remove_check_confirm", { label: removing?.label })}
        confirmLabel={t("remove")}
        loading={pending}
        onConfirm={runRemove}
      />
    </Card>
  );
}

function AddItemForm({ templateId, versionId, inputTypes, action }) {
  const t = useT("maintenance");
  const tc = useT("common");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action.bind(null, templateId, versionId), null);
  const [inputType, setInputType] = useState("PASS_FAIL");
  const [required, setRequired] = useState(true);
  const [isSafetyItem, setIsSafetyItem] = useState(false);
  const toastManager = useToastManager();

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    formData.set("required", required ? "1" : "0");
    formData.set("is_safety_item", isSafetyItem ? "1" : "0");
    startTransition(() => dispatch(formData));
  }

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("item_saved"), type: "success" });
      router.refresh();
    }
    // toastManager/router are deliberately excluded — an unstable
    // reference in this array re-fires the effect every render once
    // state first becomes "success", stacking duplicate toasts/refreshes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-3 sm:grid-cols-6">
      {state?.status === "error" ? <p className="col-span-full text-sm text-danger">{state.message}</p> : null}

      <div className="sm:col-span-2">
        <label className="mb-1 block text-xs font-medium text-foreground">{t("add_check")}</label>
        <Input name="label" required maxLength={500} placeholder={t("check_example_placeholder")} />
      </div>
      <div className="sm:col-span-1">
        <label className="mb-1 block text-xs font-medium text-foreground">{t("input_type")}</label>
        <Select
          name="input_type"
          value={inputType}
          onValueChange={setInputType}
          options={inputTypes.map((type) => ({ value: type, label: t(`input_${type.toLowerCase()}`) }))}
        />
      </div>
      <div className="sm:col-span-1">
        <label className="mb-1 block text-xs font-medium text-foreground">{t("unit")}</label>
        <Input name="unit" maxLength={32} placeholder={t("unit_example_placeholder")} />
      </div>
      <div className="sm:col-span-1">
        <label className="mb-1 block text-xs font-medium text-foreground">{t("min")}</label>
        <Input name="tolerance_min" type="number" step="0.0001" />
      </div>
      <div className="sm:col-span-1">
        <label className="mb-1 block text-xs font-medium text-foreground">{t("max")}</label>
        <Input name="tolerance_max" type="number" step="0.0001" />
      </div>

      <div className="flex items-center gap-4 sm:col-span-4">
        <label className="flex items-center gap-2 text-sm text-foreground">
          <Checkbox checked={required} onCheckedChange={setRequired} /> {t("required")}
        </label>
        <label className="flex items-center gap-2 text-sm text-foreground">
          <Checkbox checked={isSafetyItem} onCheckedChange={setIsSafetyItem} /> {t("safety_item")}
        </label>
      </div>
      <div className="flex justify-end sm:col-span-2">
        <Button type="submit" size="sm" loading={pending}>
          {tc("save")}
        </Button>
      </div>
      <p className="col-span-full text-xs text-foreground-muted">{t("safety_hint")}</p>
    </form>
  );
}

export { TemplateVersionPanel };
