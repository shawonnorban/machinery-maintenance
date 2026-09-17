"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { X, UserPlus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Shown only while assignment is meaningful — matches AssignTechnicians' own terminal-state rule (ADR-003) by simply not offering it past IN_PROGRESS. */
const ASSIGNABLE_STATUSES = ["SCHEDULED", "ASSIGNED", "IN_PROGRESS", "ON_HOLD"];

function AssignTechnicianControl({ status, workOrderId, assignments, technicians, assign, unassign }) {
  const t = useT("work_order");
  const tc = useT("common");
  const [open, setOpen] = useState(false);
  const [technicianId, setTechnicianId] = useState("");
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  if (!ASSIGNABLE_STATUSES.includes(status)) {
    return assignments.length > 0 ? (
      <div className="flex flex-wrap gap-1">
        {assignments.map((a) => (
          <Badge key={a.technician_id} variant="neutral">
            {a.technician_name}
          </Badge>
        ))}
      </div>
    ) : null;
  }

  function run(fn, ...args) {
    startTransition(async () => {
      const result = await fn(workOrderId, ...args);
      if (result?.status === "success") {
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  const assignedIds = new Set(assignments.map((a) => a.technician_id));
  const available = technicians.filter((t) => !assignedIds.has(t.id));

  return (
    <div className="flex flex-wrap items-center gap-2">
      {assignments.map((a) => (
        <Badge key={a.technician_id} variant="neutral" className="gap-1">
          {a.technician_name}
          <button type="button" onClick={() => run(unassign, a.technician_id)} className="hover:text-danger" aria-label={t("unassign_aria", { name: a.technician_name })}>
            <X className="size-3" />
          </button>
        </Badge>
      ))}

      <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
        <UserPlus className="size-4" /> {t("assign")}
      </Button>

      <Modal open={open} onOpenChange={setOpen} title={t("assign_technician_title")} description={t("assign_technician_hint")}>
        <div className="flex flex-col gap-4">
          <FormField label={t("technician")} required>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={technicianId}
                onValueChange={setTechnicianId}
                placeholder={t("select_a_technician")}
                options={available.map((tech) => ({
                  value: tech.id,
                  label: tech.covers_location ? `${tech.name} (${t("covers_this_area_short")})` : tech.name,
                }))}
              />
            )}
          </FormField>

          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setOpen(false)}>
              {tc("cancel")}
            </Button>
            <Button
              loading={pending}
              disabled={!technicianId}
              onClick={() => {
                run(assign, technicianId);
                setOpen(false);
                setTechnicianId("");
              }}
            >
              {t("assign")}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

export { AssignTechnicianControl };
