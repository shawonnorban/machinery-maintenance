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
import { formatStatus } from "@/components/ui/status-badge";

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
  return (
    <div className="flex flex-wrap items-center gap-2">
      {status === "REPORTED" ? (
        <SimpleTransition label="Acknowledge" action={actions.acknowledge} breakdownId={breakdownId} />
      ) : null}

      {["ACKNOWLEDGED", "ASSIGNED"].includes(status) ? (
        <AssignModal technicians={technicians} action={actions.assign.bind(null, breakdownId)} />
      ) : null}

      {/* Mirrors `_actions.blade.php`'s own condition exactly: response time
          (the walk to the machine) is a different question from repair time,
          so it's offered separately from — and before — starting the repair. */}
      {isOpen && !arrivalRecorded && status !== "REPORTED" ? (
        <SimpleTransition label="Record arrival" variant="outline" action={actions.arrive} breakdownId={breakdownId} />
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
        <SimpleTransition label="Reopen repair" action={actions.startRepair} breakdownId={breakdownId} />
      ) : null}

      {status === "IN_REPAIR" ? (
        <>
          <HoldModal holdReasons={holdReasons} action={actions.hold.bind(null, breakdownId)} />
          <SimpleTransition label="Complete repair" variant="success" action={actions.completeRepair} breakdownId={breakdownId} />
        </>
      ) : null}

      {status === "ON_HOLD" ? <SimpleTransition label="Resume" action={actions.resume} breakdownId={breakdownId} /> : null}

      {status === "REPAIRED" ? (
        <SimpleTransition label="Resume production" variant="success" action={actions.resumeProduction} breakdownId={breakdownId} />
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
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  async function confirm() {
    setPending(true);
    const result = await action(breakdownId);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: `${label} recorded`, type: "success" });
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
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  async function confirm() {
    setPending(true);
    const result = await action(breakdownId, workOrderId);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: "Repair started", type: "success" });
      setOpen(false);
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <>
      <Button size="sm" onClick={() => setOpen(true)}>
        Start work order
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title="Start this work order?"
        description="Marks it in progress and moves this breakdown to IN_REPAIR."
        confirmLabel="Start"
        destructive={false}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

/** Toasts the new work order's own number, mirroring the web flash message (`breakdown.work_order_raised`) rather than a generic "recorded" confirmation. */
function RaiseWorkOrderButton({ breakdownId, action }) {
  const [open, setOpen] = useState(false);
  const [pending, setPending] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  async function confirm() {
    setPending(true);
    const result = await action(breakdownId);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: `Work order ${result.workOrderNumber} raised`, type: "success" });
      setOpen(false);
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
        Raise work order
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title="Raise a repair work order?"
        description="Puts every technician on this line's roster onto a new work order for it — any of them can then open it and start."
        confirmLabel="Raise"
        destructive={false}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

function AssignModal({ technicians, action }) {
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);
  const [technicianId, setTechnicianId] = useState("");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Assigned", type: "success" });
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
        Assign
      </Button>
      <Modal open={open} onOpenChange={setOpen} title="Assign">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label="Technician" required error={state?.errors?.technician_id?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={technicianId}
                onValueChange={setTechnicianId}
                options={technicians.map((t) => ({ value: t.id, label: `${t.name} (${t.employee_id})` }))}
                placeholder="Select a technician"
              />
            )}
          </FormField>
          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" loading={pending} disabled={!technicianId}>
              Assign
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function HoldModal({ holdReasons, action }) {
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);
  const [reasonCode, setReasonCode] = useState("");
  const [notes, setNotes] = useState("");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "On hold", type: "success" });
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
        Hold
      </Button>
      <Modal open={open} onOpenChange={setOpen} title="Hold">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label="Reason" required error={state?.errors?.reason_code?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={reasonCode}
                onValueChange={setReasonCode}
                options={holdReasons.map((r) => ({ value: r, label: formatStatus(r) }))}
                placeholder="Select a reason"
              />
            )}
          </FormField>
          <FormField label="Notes" error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
          </FormField>
          <p className="text-xs text-foreground-muted">Time on hold is tracked separately from repair time.</p>
          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="outline" loading={pending} disabled={!reasonCode}>
              Hold
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function CloseModal({ failureCodes, rootCauses, action }) {
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
      toastManager.add({ title: "Closed", type: "success" });
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
        Close
      </Button>
      <Modal open={open} onOpenChange={setOpen} title="Close" description="A failure code and root cause are required — the failure-analysis reports are built from exactly these two fields." className="max-w-2xl">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Failure code" required error={state?.errors?.failure_code_id?.[0]}>
              {(fieldProps) => (
                <Select
                  {...fieldProps}
                  value={failureCodeId}
                  onValueChange={setFailureCodeId}
                  options={failureCodes.map((c) => ({ value: c.id, label: c.name }))}
                  placeholder="Select a failure code"
                />
              )}
            </FormField>
            <FormField label="Root cause" required error={state?.errors?.root_cause_id?.[0]}>
              {(fieldProps) => (
                <Select
                  {...fieldProps}
                  value={rootCauseId}
                  onValueChange={setRootCauseId}
                  options={rootCauses.map((c) => ({ value: c.id, label: c.name }))}
                  placeholder="Select a root cause"
                />
              )}
            </FormField>
          </div>

          <FormField label="Corrective action" error={state?.errors?.corrective_action?.[0]}>
            {(fieldProps) => (
              <Textarea {...fieldProps} value={correctiveAction} onChange={(e) => setCorrectiveAction(e.target.value)} rows={2} maxLength={5000} />
            )}
          </FormField>

          <FormField label="Preventive action" error={state?.errors?.preventive_action?.[0]}>
            {(fieldProps) => (
              <Textarea {...fieldProps} value={preventiveAction} onChange={(e) => setPreventiveAction(e.target.value)} rows={2} maxLength={5000} />
            )}
          </FormField>

          <FormField label="Closure notes" error={state?.errors?.closure_notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} value={closureNotes} onChange={(e) => setClosureNotes(e.target.value)} rows={2} maxLength={5000} />}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" loading={pending} disabled={!failureCodeId || !rootCauseId}>
              Close
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function CancelModal({ action }) {
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);
  const [reason, setReason] = useState("");
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Cancelled", type: "success" });
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
        Cancel
      </Button>
      <Modal open={open} onOpenChange={setOpen} title="Cancel breakdown">
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <FormField label="Reason" required error={state?.errors?.cancellation_reason?.[0]} helperText="Why this report doesn't stand — a false alarm, a duplicate, or the machine turned out fine.">
            {(fieldProps) => <Input {...fieldProps} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={255} required />}
          </FormField>
          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Back
            </Button>
            <Button type="submit" variant="danger" loading={pending} disabled={!reason.trim()}>
              Cancel breakdown
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

export { BreakdownActions };
