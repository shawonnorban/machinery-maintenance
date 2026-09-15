"use client";

import { startTransition, useActionState, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import { Trash2 } from "lucide-react";

/**
 * Mirrors `inventory::parts._compatibility.blade.php` — two different
 * questions the store needs at two in the morning: whether the machine can
 * be repaired at all (FITS), and whether it's repaired tonight or on
 * Sunday when the supplier opens (SUBSTITUTE).
 */
function CompatibilityTab({ compatibility, assetModels, otherParts, addAction, deleteAction }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runDelete(id) {
    startTransition(async () => {
      const result = await deleteAction(id);
      if (result?.status === "success") {
        toastManager.add({ title: "Removed", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <Card className="divide-y divide-border">
        {compatibility.length === 0 ? (
          <p className="p-4 text-sm text-foreground-muted">Nothing recorded yet.</p>
        ) : (
          compatibility.map((row) => (
            <div key={row.id} className="flex items-center justify-between gap-3 p-4">
              <div className="flex items-center gap-2 text-sm">
                {row.compatibility_type === "FITS" ? (
                  <>
                    <Badge variant="info">Fits</Badge>
                    <span>{row.asset_model?.model}</span>
                  </>
                ) : (
                  <>
                    <Badge variant="neutral">Substitute</Badge>
                    <span>
                      {row.substitute_for?.part_number}{" "}
                      <span className="text-foreground-muted">{row.substitute_for?.name}</span>
                    </span>
                  </>
                )}
              </div>
              <Button variant="ghost" size="icon" aria-label="Remove" loading={pending} onClick={() => runDelete(row.id)}>
                <Trash2 className="text-danger" />
              </Button>
            </div>
          ))
        )}
      </Card>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FitsForm assetModels={assetModels} action={addAction} />
        <SubstituteForm otherParts={otherParts} action={addAction} />
      </div>
    </div>
  );
}

function FitsForm({ assetModels, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const [assetModelId, setAssetModelId] = useState("");

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("compatibility_type", "FITS");
    formData.set("asset_model_id", assetModelId);
    startTransition(() => dispatch(formData));
  }

  return (
    <Card className="p-4">
      <form onSubmit={handleSubmit} className="flex flex-col gap-3">
        <FormField label="Fits machine model" required error={state?.errors?.asset_model_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={assetModelId}
              onValueChange={setAssetModelId}
              options={assetModels.map((m) => ({ value: m.id, label: [m.manufacturer, m.model].filter(Boolean).join(" ") }))}
              placeholder="Select a model"
            />
          )}
        </FormField>
        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
        <div>
          <Button type="submit" size="sm" variant="outline" loading={pending} disabled={!assetModelId}>
            Add fit
          </Button>
        </div>
      </form>
    </Card>
  );
}

function SubstituteForm({ otherParts, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const [substituteForPartId, setSubstituteForPartId] = useState("");

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("compatibility_type", "SUBSTITUTE");
    formData.set("substitute_for_part_id", substituteForPartId);
    startTransition(() => dispatch(formData));
  }

  return (
    <Card className="p-4">
      <form onSubmit={handleSubmit} className="flex flex-col gap-3">
        <FormField label="Substitutes for" required error={state?.errors?.substitute_for_part_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={substituteForPartId}
              onValueChange={setSubstituteForPartId}
              options={otherParts.map((p) => ({ value: p.id, label: `${p.part_number} — ${p.name}` }))}
              placeholder="Select a part"
            />
          )}
        </FormField>
        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
        <div>
          <Button type="submit" size="sm" variant="outline" loading={pending} disabled={!substituteForPartId}>
            Add substitute
          </Button>
        </div>
      </form>
    </Card>
  );
}

export { CompatibilityTab };
