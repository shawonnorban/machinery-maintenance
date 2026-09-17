"use client";

import { useActionState, useEffect, useTransition } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * What this person is actually trained on — mirrors `technicians/form.
 * blade.php`'s own skills card, shown only once a technician exists (a
 * skill belongs to a record, not to a draft that hasn't been saved yet).
 * Separate from the area they cover (`TechnicianForm`'s own factory/
 * department/production-line fields): a dyeing technician may hold an
 * electrical certificate, and the person to send to a tripped panel at
 * 2am is whoever has it, not whoever is nearest.
 */
function TechnicianSkills({ technicianId, skills, proficiencies, actions }) {
  const t = useT("technician");
  const tc = useT("common");
  const router = useRouter();
  const [removing, startRemoveTransition] = useTransition();
  const toastManager = useToastManager();

  function runRemove(skillId) {
    startRemoveTransition(async () => {
      const result = await actions.removeSkill(technicianId, skillId);
      if (result?.status === "success") {
        toastManager.add({ title: t("skill_removed"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("skills")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        <p className="text-sm text-foreground-muted">{t("skills_hint")}</p>

        {skills.length === 0 ? (
          <p className="text-sm text-foreground-muted">{t("no_skills")}</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {skills.map((skill) => (
              <span key={skill.id} className="inline-flex items-center gap-2 rounded-sm border border-border bg-surface-muted px-3 py-1.5 text-sm text-foreground">
                {skill.skill_name}
                <span className="text-xs text-foreground-muted">{t(`proficiency_${skill.proficiency?.toLowerCase()}`)}</span>
                <button
                  type="button"
                  onClick={() => runRemove(skill.id)}
                  disabled={removing}
                  className="text-danger hover:text-danger/80 disabled:opacity-50"
                  aria-label={tc("delete")}
                >
                  &times;
                </button>
              </span>
            ))}
          </div>
        )}

        <AddSkillForm technicianId={technicianId} proficiencies={proficiencies} action={actions.addSkill} />
      </CardBody>
    </Card>
  );
}

function AddSkillForm({ technicianId, proficiencies, action }) {
  const t = useT("technician");
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action.bind(null, technicianId), null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("skill_added"), type: "success" });
      router.refresh();
    }
    // toastManager/router are deliberately excluded — an unstable
    // reference in this array re-fires the effect every render once
    // state first becomes "success", stacking duplicate toasts/refreshes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="grid grid-cols-1 gap-3 border-t border-border pt-4 sm:grid-cols-[1fr_180px_auto] sm:items-end">
      <FormField label={t("skill_name")} required error={state?.errors?.skill_name?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="skill_name" placeholder={t("skill_example")} maxLength={255} required />}
      </FormField>
      <FormField label={t("proficiency")} required>
        {(fieldProps) => (
          <Select
            {...fieldProps}
            name="proficiency"
            defaultValue={proficiencies[0]}
            options={proficiencies.map((p) => ({ value: p, label: t(`proficiency_${p.toLowerCase()}`) }))}
          />
        )}
      </FormField>
      <Button type="submit" variant="outline" loading={pending}>
        {t("add_skill")}
      </Button>
      {state?.status === "error" && !state.errors ? <p className="col-span-full text-sm text-danger">{state.message}</p> : null}
    </form>
  );
}

export { TechnicianSkills };
