"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * The action bar for a breakdown's current status — mirrors
 * `breakdown::breakdowns._actions.blade.php`'s condition set exactly
 * (`Breakdown::TRANSITIONS`), status-driven rather than server-computed
 * `can_*` flags: only one status is ever current, so which buttons render
 * is a pure function of it, the same simplification the four transitions
 * already wired here used before this.
 */
function BreakdownActions({
  status,
  breakdownId,
  technicians,
  failureCodes,
  rootCauses,
  holdReasons,
  isOpen,
  isTerminal,
  arrivalRecorded,
  hasOpenWorkOrder,
  workOrderId,
  workOrderStatus,
  actions,
}) {
  const t = useT("breakdown");

  return (
    <div className="flex flex-wrap items-center gap-2">
      {status === "REPORTED" ? (
        <SimpleTransition label={t("acknowledge")} action={actions.acknowledge} breakdownId={breakdownId} />
      ) : null}

      {["ACKNOWLEDGED", "ASSIGNED"].includes(status) ? (
        <AssignModal technicians={technicians} action={actions.assign.bind(null, breakdownId)} />
      ) : null}

      {/* Mirrors `_actions.blade.php`'s own condition exactly: response time
          (the walk to the machine) is a different question from repair time,
          so it's offered separately from — and before — starting the repair. */}
      {isOpen && !arrivalRecorded && status !== "REPORTED" ? (
        <SimpleTransition label={t("record_arrival")} variant="outline" action={actions.arrive} breakdownId={breakdownId} />
      ) : null}

      {/* Starting from ACKNOWLEDGED/ASSIGNED now happens by starting the
          linked *work order* instead — the line's whole roster lands on it
          the moment it's raised (`RaiseBreakdownWorkOrder::assignRoster()`),
          so whoever opens it and starts it is the one telling the
          breakdown its repair has begun (`WorkOrderApiController::start()`
          syncs the breakdown itself in the same request). */}
      {workOrderId !== null && workOrderStatus === "ASSIGNED" ? (
        <StartWorkOrderButton breakdownId={breakdownId} workOrderId={workOrderId} action={actions.startWorkOrder} />
      ) : null}

      {/* Kept only for REPAIRED → IN_REPAIR: reopening a repair marked done
          that turned out not to be has no work-order-side action to route
          through (its own work order never left IN_PROGRESS). */}
      {status === "REPAIRED" ? (
        <SimpleTransition label={t("reopen_repair")} action={actions.startRepair} breakdownId={breakdownId} />
      ) : null}

      {status === "IN_REPAIR" ? (
        <>
          <HoldModal holdReasons={holdReasons} action={actions.hold.bind(null, breakdownId)} />
          <SimpleTransition label={t("complete_repair")} variant="success" action={actions.completeRepair} breakdownId={breakdownId} />
        </>
      ) : null}

      {status === "ON_HOLD" ? <SimpleTransition label={t("resume")} action={actions.resume} breakdownId={breakdownId} /> : null}

      {status === "REPAIRED" ? (
        <SimpleTransition label={t("resume_production")} variant="success" action={actions.resumeProduction} breakdownId={breakdownId} />
      ) : null}

      {["REPAIRED", "PRODUCTION_RESUMED"].includes(status) ? (
        <CloseModal failureCodes={failureCodes} rootCauses={rootCauses} action={actions.close.bind(null, breakdownId)} />
      ) : null}

      {/* The normal way a repair job gets going now — Acknowledge, then
          this, which puts the whole line's roster on the new work order at
          once (`RaiseBreakdownWorkOrder::assignRoster()`), not a single
          manager pick. Still also covers a genuine second pass after the
          first work order has closed. Offered while the breakdown isn't
          terminal and there's no open work order already — the API refuses
          a second concurrent one (`RaiseBreakdownWorkOrder`'s own guard),
          so this hides the button instead of letting every click past the
          first one 409. */}
      {!isTerminal && !hasOpenWorkOrder ? (
        <RaiseWorkOrderButton action={actions.raiseWorkOrder} breakdownId={breakdownId} />
      ) : null}

      {["REPORTED", "ACKNOWLEDGED", "ASSIGNED", "IN_REPAIR", "ON_HOLD"].includes(status) ? (
        <CancelModal action={actions.cancel.bind(null, breakdownId)} />
      ) : null}
    </div>
  );
}

function SimpleTransition({ label, breakdownId, action, variant = "primary" }) {
  const t = useT("breakdown");
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  async function confirm() {
    setPending(true);
    const result = await action(breakdownId);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: t("action_recorded", { action: label }), type: "success" });
      setOpen(false);
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <>
      <Button size="sm" variant={variant} onClick={() => setOpen(true)}>
        {label}
      </Button>
      <ConfirmDialog open={open} onOpenChange={setOpen} title={`${label}?`} confirmLabel={label} destructive={false} loading={pending} onConfirm={confirm} />
    </>
  );
}

/** Starts the linked work order directly from this page — the click that also moves the breakdown itself to IN_REPAIR (see `startWorkOrder`'s own docblock). */
function StartWorkOrderButton({ breakdownId, workOrderId, action }) {
  const t = useT("breakdown");
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  async function confirm() {
    setPending(true);
    const result = await action(breakdownId, workOrderId);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: t("repair_started_message"), type: "success" });
      setOpen(false);
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <>
      <Button size="sm" onClick={() => setOpen(true)}>
        {t("start_work_order")}
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={t("start_work_order_confirm_title")}
        description={t("start_work_order_description")}
        confirmLabel={t("start")}
        destructive={false}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

/** Toasts the new work order's own number, mirroring the web flash message (`breakdown.work_order_raised`) rather than a generic "recorded" confirmation. */
function RaiseWorkOrderButton({ breakdownId, action }) {
  const t = useT("breakdown");
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  async function confirm() {
    setPending(true);
    const result = await action(breakdownId);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: t("raised_toast", { number: result.workOrderNumber }), type: "success" });
      setOpen(false);
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
        {t("raise_work_order")}
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={t("raise_work_order_confirm_title")}
        description={t("raise_work_order_description")}
        confirmLabel={t("raise")}
        destructive={false}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

function AssignModal({ technicians, action }) {
  const t = useT("breakdown");
  const tc = useT("common");
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);
  const [technicianId, setTechnicianId] = useState("");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("assigned_message"), type: "success" });
      queueMicrotask(() => setOpen(false));
      router.refresh();
    }
    // toastManager/router are deliberately excluded: neither is a stable
    // reference across renders here, so including them re-fires this
    // effect (and its own router.refresh()) every render once state first
    // becomes "success" — an infinite loop, confirmed live as a stack of
    // duplicate "Assigned" toasts and a real "Maximum update depth
    // exceeded" crash.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("technician_id", technicianId);
    startTransition(() => dispatch(formData));
  }

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
        {t("assign")}
      </Button>
      <Modal open={open} onOpenChange={setOpen} title={t("assign")}>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label={t("technician")} required error={state?.errors?.technician_id?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={technicianId}
                onValueChange={setTechnicianId}
                options={technicians.map((tech) => ({ value: tech.id, label: `${tech.name} (${tech.employee_id})` }))}
                placeholder={t("select_technician")}
              />
            )}
          </FormField>
          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              {tc("cancel")}
            </Button>
            <Button type="submit" loading={pending} disabled={!technicianId}>
              {t("assign")}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function HoldModal({ holdReasons, action }) {
  const t = useT("breakdown");
  const tc = useT("common");
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);
  const [reasonCode, setReasonCode] = useState("");
  const [notes, setNotes] = useState("");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("held_message"), type: "success" });
      queueMicrotask(() => setOpen(false));
      router.refresh();
    }
    // See AssignModal's own comment on why toastManager/router are left
    // out of the dependency array.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("reason_code", reasonCode);
    if (notes) formData.set("notes", notes);
    startTransition(() => dispatch(formData));
  }

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
        {t("hold")}
      </Button>
      <Modal open={open} onOpenChange={setOpen} title={t("hold")}>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label={t("hold_reason")} required error={state?.errors?.reason_code?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={reasonCode}
                onValueChange={setReasonCode}
                options={holdReasons.map((r) => ({ value: r, label: t(`hold_reason_${r.toLowerCase()}`) }))}
                placeholder={t("select_reason")}
              />
            )}
          </FormField>
          <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
          </FormField>
          <p className="text-xs text-foreground-muted">{t("hold_time_note")}</p>
          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              {tc("cancel")}
            </Button>
            <Button type="submit" variant="outline" loading={pending} disabled={!reasonCode}>
              {t("hold")}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function CloseModal({ failureCodes, rootCauses, action }) {
  const t = useT("breakdown");
  const tc = useT("common");
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);
  const [failureCodeId, setFailureCodeId] = useState("");
  const [rootCauseId, setRootCauseId] = useState("");
  const [correctiveAction, setCorrectiveAction] = useState("");
  const [preventiveAction, setPreventiveAction] = useState("");
  const [closureNotes, setClosureNotes] = useState("");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("closed_message"), type: "success" });
      queueMicrotask(() => setOpen(false));
      router.refresh();
    }
    // See AssignModal's own comment on why toastManager/router are left
    // out of the dependency array.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("failure_code_id", failureCodeId);
    formData.set("root_cause_id", rootCauseId);
    if (correctiveAction) formData.set("corrective_action", correctiveAction);
    if (preventiveAction) formData.set("preventive_action", preventiveAction);
    if (closureNotes) formData.set("closure_notes", closureNotes);
    startTransition(() => dispatch(formData));
  }

  return (
    <>
      <Button size="sm" variant="secondary" onClick={() => setOpen(true)}>
        {t("close")}
      </Button>
      <Modal open={open} onOpenChange={setOpen} title={t("close")} description={t("close_description")} className="max-w-2xl">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label={t("failure_code")} required error={state?.errors?.failure_code_id?.[0]}>
              {(fieldProps) => (
                <Select
                  {...fieldProps}
                  value={failureCodeId}
                  onValueChange={setFailureCodeId}
                  options={failureCodes.map((c) => ({ value: c.id, label: c.name }))}
                  placeholder={t("select_failure_code")}
                />
              )}
            </FormField>
            <FormField label={t("root_cause")} required error={state?.errors?.root_cause_id?.[0]}>
              {(fieldProps) => (
                <Select
                  {...fieldProps}
                  value={rootCauseId}
                  onValueChange={setRootCauseId}
                  options={rootCauses.map((c) => ({ value: c.id, label: c.name }))}
                  placeholder={t("select_root_cause")}
                />
              )}
            </FormField>
          </div>

          <FormField label={t("corrective_action")} error={state?.errors?.corrective_action?.[0]}>
            {(fieldProps) => (
              <Textarea {...fieldProps} value={correctiveAction} onChange={(e) => setCorrectiveAction(e.target.value)} rows={2} maxLength={5000} />
            )}
          </FormField>

          <FormField label={t("preventive_action")} error={state?.errors?.preventive_action?.[0]}>
            {(fieldProps) => (
              <Textarea {...fieldProps} value={preventiveAction} onChange={(e) => setPreventiveAction(e.target.value)} rows={2} maxLength={5000} />
            )}
          </FormField>

          <FormField label={t("closure_notes")} error={state?.errors?.closure_notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} value={closureNotes} onChange={(e) => setClosureNotes(e.target.value)} rows={2} maxLength={5000} />}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              {tc("cancel")}
            </Button>
            <Button type="submit" loading={pending} disabled={!failureCodeId || !rootCauseId}>
              {t("close")}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function CancelModal({ action }) {
  const t = useT("breakdown");
  const tc = useT("common");
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);
  const [reason, setReason] = useState("");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("cancelled_message"), type: "success" });
      queueMicrotask(() => setOpen(false));
      router.refresh();
    }
    // See AssignModal's own comment on why toastManager/router are left
    // out of the dependency array.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("cancellation_reason", reason);
    startTransition(() => dispatch(formData));
  }

  return (
    <>
      <Button size="sm" variant="outline" className="sm:ml-auto" onClick={() => setOpen(true)}>
        {t("cancel")}
      </Button>
      <Modal open={open} onOpenChange={setOpen} title={t("cancel_breakdown")}>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label={t("cancellation_reason")} required error={state?.errors?.cancellation_reason?.[0]} helperText={t("cancel_reason_hint")}>
            {(fieldProps) => <Input {...fieldProps} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={255} required />}
          </FormField>
          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              {tc("back")}
            </Button>
            <Button type="submit" variant="danger" loading={pending} disabled={!reason.trim()}>
              {t("cancel_breakdown")}
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

export { BreakdownActions };
