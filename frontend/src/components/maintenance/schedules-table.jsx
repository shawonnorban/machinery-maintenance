"use client";

import { startTransition, useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import { Button } from "@/components/ui/button";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";

const STATUS_OPTIONS = [
  { value: "", label: "Open (due & overdue)" },
  { value: "OVERDUE", label: "Overdue" },
  { value: "COMPLETED", label: "Completed" },
  { value: "SKIPPED", label: "Skipped" },
  { value: "CANCELLED", label: "Cancelled" },
];

/** Mirrors `ScheduleController::index` — one concrete occurrence of a plan. */
function SchedulesTable({ schedules, meta, page, status, actions }) {
  const router = useRouter();
  const [pending, startPending] = useTransition();
  const [modal, setModal] = useState(null);
  const toastManager = useToastManager();

  function navigate(next) {
    const params = new URLSearchParams({
      page: String(next.page ?? page),
      status: next.status ?? status,
    });
    for (const [key, value] of [...params.entries()]) {
      if (!value) params.delete(key);
    }
    router.push(`/maintenance/schedule?${params.toString()}`);
  }

  function runComplete(schedule) {
    startPending(async () => {
      const result = await actions.completeSchedule(schedule.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Marked complete", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap gap-3">
        <div className="w-full max-w-[220px]">
          <Select options={STATUS_OPTIONS} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
        </div>
      </div>

      <DataTable
        columns={[
          {
            key: "asset",
            header: "Asset",
            render: (s) => (
              <div>
                <Link href={`/assets/${s.asset?.id}`} className="font-medium text-brand hover:underline">
                  {s.asset?.asset_code}
                </Link>
                <div className="text-xs text-foreground-muted">{s.plan_name}</div>
              </div>
            ),
          },
          {
            key: "due_at",
            header: "Due",
            render: (s) => (
              <span className={s.is_overdue ? "font-semibold text-danger" : undefined}>
                <FormattedDateTime value={s.due_at} mode="date" />
              </span>
            ),
          },
          { key: "status", header: "Status", render: (s) => <StatusBadge status={s.status} /> },
        ]}
        rows={schedules}
        rowKey={(s) => s.id}
        emptyTitle="Nothing due."
        rowActions={(s) =>
          ["PLANNED", "DUE", "OVERDUE", "IN_PROGRESS"].includes(s.status)
            ? [
                { label: "Complete", onSelect: () => runComplete(s) },
                { label: "Reschedule", onSelect: () => setModal({ type: "reschedule", schedule: s }) },
                { label: "Skip", destructive: true, onSelect: () => setModal({ type: "skip", schedule: s }) },
              ]
            : []
        }
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />

      {modal?.type === "skip" ? (
        <SkipModal schedule={modal.schedule} action={actions.skipSchedule} onClose={() => setModal(null)} />
      ) : null}
      {modal?.type === "reschedule" ? (
        <RescheduleModal schedule={modal.schedule} action={actions.rescheduleSchedule} onClose={() => setModal(null)} />
      ) : null}
    </div>
  );
}

function SkipModal({ schedule, action, onClose }) {
  const [state, dispatch, pending] = useActionState(action.bind(null, schedule.id), null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Schedule skipped", type: "success" });
      onClose();
      router.refresh();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(() => dispatch(formData));
  }

  return (
    <Modal open onOpenChange={onClose} title={`Skip ${schedule.asset?.asset_code}?`}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <FormField label="Reason" required error={state?.errors?.skipped_reason?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="skipped_reason" required />}
        </FormField>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="danger" loading={pending}>
            Skip
          </Button>
        </div>
      </form>
    </Modal>
  );
}

function RescheduleModal({ schedule, action, onClose }) {
  const [state, dispatch, pending] = useActionState(action.bind(null, schedule.id), null);
  const [dueAt, setDueAt] = useState(schedule.due_at ? schedule.due_at.slice(0, 10) : "");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Schedule rescheduled", type: "success" });
      onClose();
      router.refresh();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("due_at", dueAt);
    formData.set("rescheduled_reason", event.currentTarget.rescheduled_reason.value);
    startTransition(() => dispatch(formData));
  }

  return (
    <Modal open onOpenChange={onClose} title={`Reschedule ${schedule.asset?.asset_code}?`}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <FormField label="New due date" required error={state?.errors?.due_at?.[0]}>
          {() => <DatePicker value={dueAt} onChange={setDueAt} />}
        </FormField>
        <FormField label="Reason" required error={state?.errors?.rescheduled_reason?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="rescheduled_reason" required />}
        </FormField>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" loading={pending}>
            Reschedule
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { SchedulesTable };
