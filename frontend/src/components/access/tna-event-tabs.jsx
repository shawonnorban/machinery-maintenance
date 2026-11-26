"use client";

import { useState, useTransition } from "react";
import { CalendarCheck, Plus, RotateCcw, Save, Users } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";

/*
 * Access Control → TNA Task Status Setup (AnnotechERP 02/04), Brickland's two
 * working tabs. TNA Task Status: the events with how often each has been
 * raised; pick one and its instances load beside it — raise one against a
 * style with its owner and plan date, move each through Pending, In
 * Progress, Completed (today becomes its actual date), Delayed or Cancelled;
 * Revert, Save. Mapped User List: pick an event, tick who is notified when it
 * is raised. Nothing reloads the page.
 */

const firstError = (result) =>
  Object.values(result?.errors ?? {})[0]?.[0] ?? result?.message;

function Empty({ icon: Icon, title, hint }) {
  return (
    <div className="flex flex-col items-center gap-2 px-5 py-14 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <Icon className="size-5" aria-hidden />
      </span>
      <p className="text-sm font-semibold text-foreground">{title}</p>
      <p className="max-w-md text-[13px] text-foreground-muted">{hint}</p>
    </div>
  );
}

/**
 * @param {{ events: object[], users: {value: string, label: string}[], statuses: string[], call: Function, labels: object }} props
 */
function TnaInstances({ events, users, statuses, call, labels: l }) {
  const toastManager = useToastManager();
  const [raised, setRaised] = useState(
    Object.fromEntries(events.map((e) => [e.id, e.raised])),
  );
  const [event, setEvent] = useState(null);
  const [saved, setSaved] = useState([]);
  const [rows, setRows] = useState([]);
  const [form, setForm] = useState(null);
  const [pending, startTransition] = useTransition();
  const statusOptions = statuses.map((s) => ({
    value: s,
    label: l.statuses[s],
  }));

  const pick = (e) =>
    startTransition(async () => {
      const res = await call("instances", e.id);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setEvent(e);
      setSaved(res.data);
      setRows(res.data);
      setForm(null);
    });
  const raise = () => {
    if (!form.reference.trim()) return;
    setRows((cur) => [
      {
        id: null,
        reference: form.reference.trim(),
        owner_id: form.owner_id || null,
        plan_date: form.plan_date || null,
        status: event.default_status ?? "PENDING",
        actual_date: null,
      },
      ...cur,
    ]);
    setForm(null);
  };
  const save = () =>
    startTransition(async () => {
      const res = await call("saveInstances", event.id, {
        rows: rows.map((r) => ({
          id: r.id ?? undefined,
          reference: r.reference,
          owner_id: r.owner_id,
          plan_date: r.plan_date,
          status: r.status,
        })),
      });
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setSaved(res.data);
      setRows(res.data);
      setRaised((cur) => ({ ...cur, [event.id]: res.data.length }));
      toastManager.add({ title: l.saved, type: "success" });
    });
  const set = (i, patch) =>
    setRows((cur) => cur.map((r, j) => (j === i ? { ...r, ...patch } : r)));

  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <section className="rounded-lg border border-border bg-surface">
        <h2 className="flex items-center justify-between border-b border-border px-4 py-3 text-sm font-semibold text-foreground">
          {l.list}
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs text-foreground-muted">
            {events.length}
          </span>
        </h2>
        <ul className="max-h-[36rem] overflow-y-auto p-2">
          {events.map((e) => (
            <li key={e.id}>
              <button
                type="button"
                onClick={() => pick(e)}
                className={cn(
                  "flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-surface-muted",
                  event?.id === e.id && "bg-brand-subtle text-brand",
                )}
              >
                <span className="min-w-0">
                  <span className="block truncate font-semibold">{e.name}</span>
                  <span className="block text-xs text-foreground-muted">
                    {e.code}
                  </span>
                </span>
                <span className="text-xs tabular-nums text-foreground-muted">
                  {raised[e.id] ?? 0}
                </span>
              </button>
            </li>
          ))}
        </ul>
      </section>

      <section className="rounded-lg border border-border bg-surface">
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {event
                ? l.instances_for.replace(":event", event.name)
                : l.instances}
            </h2>
            <p className="text-[13px] text-foreground-muted">
              {l.instances_hint}
            </p>
          </div>
          {event ? (
            <Button
              size="sm"
              onClick={() =>
                setForm({ reference: "", owner_id: "", plan_date: "" })
              }
            >
              <Plus className="size-4" aria-hidden /> {l.raise}
            </Button>
          ) : null}
        </div>
        {!event ? (
          <Empty
            icon={CalendarCheck}
            title={l.pick_event}
            hint={l.pick_event_hint}
          />
        ) : (
          <div className="flex flex-col gap-4 p-5">
            {form ? (
              <div className="grid items-end gap-3 rounded-lg border border-brand/30 bg-brand-subtle/40 p-4 sm:grid-cols-[1fr_1fr_12rem_auto]">
                <label className="flex flex-col gap-1.5">
                  <span className="text-[13px] font-semibold text-foreground">
                    {l.reference}
                    <span className="text-danger"> *</span>
                  </span>
                  <Input
                    value={form.reference}
                    placeholder="STY-4471"
                    onChange={(ev) =>
                      setForm((f) => ({ ...f, reference: ev.target.value }))
                    }
                    aria-label={l.reference}
                  />
                </label>
                <label className="flex flex-col gap-1.5">
                  <span className="text-[13px] font-semibold text-foreground">
                    {l.owner}
                  </span>
                  <Select
                    value={form.owner_id}
                    onValueChange={(x) =>
                      setForm((f) => ({ ...f, owner_id: x ?? "" }))
                    }
                    options={users}
                    aria-label={l.owner}
                  />
                </label>
                <label className="flex flex-col gap-1.5">
                  <span className="text-[13px] font-semibold text-foreground">
                    {l.plan_date}
                  </span>
                  <Input
                    type="date"
                    value={form.plan_date}
                    onChange={(ev) =>
                      setForm((f) => ({ ...f, plan_date: ev.target.value }))
                    }
                    aria-label={l.plan_date}
                  />
                </label>
                <Button onClick={raise} disabled={!form.reference.trim()}>
                  <Plus className="size-4" aria-hidden /> {l.raise}
                </Button>
              </div>
            ) : null}
            {rows.length === 0 ? (
              <Empty
                icon={CalendarCheck}
                title={l.none.replace(":event", event.name)}
                hint={l.none_hint}
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[720px] text-sm">
                  <thead className="bg-brand text-left text-brand-foreground">
                    <tr>
                      {[
                        "reference",
                        "owner",
                        "plan_date",
                        "status",
                        "actual_date",
                      ].map((k) => (
                        <th
                          key={k}
                          className="px-3 py-2.5 text-xs font-semibold uppercase"
                        >
                          {l[k]}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r, i) => (
                      <tr
                        key={r.id ?? `new-${i}`}
                        className="border-t border-border"
                      >
                        <td className="px-3 py-2 font-semibold">
                          {r.reference}
                        </td>
                        <td className="w-56 px-3 py-2">
                          <Select
                            value={r.owner_id ?? ""}
                            onValueChange={(x) =>
                              set(i, { owner_id: x || null })
                            }
                            options={users}
                            aria-label={`${r.reference} — ${l.owner}`}
                          />
                        </td>
                        <td className="w-44 px-3 py-2">
                          <Input
                            type="date"
                            value={r.plan_date ?? ""}
                            onChange={(ev) =>
                              set(i, { plan_date: ev.target.value || null })
                            }
                            aria-label={`${r.reference} — ${l.plan_date}`}
                          />
                        </td>
                        <td className="w-44 px-3 py-2">
                          <Select
                            value={r.status}
                            onValueChange={(x) =>
                              set(i, { status: x || "PENDING" })
                            }
                            options={statusOptions}
                            aria-label={`${r.reference} — ${l.status}`}
                          />
                        </td>
                        <td className="px-3 py-2 whitespace-nowrap text-foreground-muted">
                          {r.actual_date ?? "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3.5">
              <p className="max-w-2xl text-[13px] text-foreground-muted">
                {l.count.replace(":count", rows.length)}
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  onClick={() => setRows(saved)}
                  disabled={pending}
                >
                  <RotateCcw className="size-4" aria-hidden /> {l.revert}
                </Button>
                <Button onClick={save} loading={pending}>
                  <Save className="size-4" aria-hidden /> {l.save_list}
                </Button>
              </div>
            </div>
          </div>
        )}
      </section>
    </div>
  );
}

/**
 * @param {{ events: object[], call: Function, labels: object }} props
 */
function TnaMappedUsers({ events, call, labels: l }) {
  const toastManager = useToastManager();
  const [event, setEvent] = useState("");
  const [saved, setSaved] = useState(null);
  const [rows, setRows] = useState([]);
  const [pending, startTransition] = useTransition();

  const pick = (id) =>
    startTransition(async () => {
      setEvent(id ?? "");
      if (!id) return setSaved(null);
      const res = await call("users", id);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setSaved(res.data);
      setRows(res.data);
    });
  const save = () =>
    startTransition(async () => {
      const res = await call("saveUsers", event, {
        users: rows.filter((r) => r.selected).map((r) => r.user_id),
      });
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setSaved(res.data);
      setRows(res.data);
      toastManager.add({ title: l.saved, type: "success" });
    });
  const all = rows.length > 0 && rows.every((r) => r.selected);

  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="p-5">
        <div className="flex items-start gap-3">
          <Users className="mt-0.5 size-5 text-foreground-muted" aria-hidden />
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {l.users_title}
            </h2>
            <p className="text-[13px] text-foreground-muted">{l.users_hint}</p>
          </div>
        </div>
        <div className="mt-4 flex max-w-md flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">
            {l.event}
            <span className="ml-0.5 text-danger">*</span>
          </span>
          <Select
            value={event}
            onValueChange={pick}
            placeholder={l.choose_event}
            options={events.map((e) => ({
              value: e.id,
              label: `${e.name} (${e.code})`,
            }))}
            aria-label={l.event}
          />
        </div>
      </div>
      {!saved ? (
        <div className="border-t border-border">
          <Empty icon={Users} title={l.pick_event} hint={l.pick_event_hint} />
        </div>
      ) : (
        <>
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  <th className="w-24 px-4 py-3 text-center text-[13px] font-semibold">
                    <Checkbox
                      checked={all}
                      onCheckedChange={(c) =>
                        setRows((cur) =>
                          cur.map((r) => ({ ...r, selected: c === true })),
                        )
                      }
                      aria-label={l.select_all}
                    />
                  </th>
                  <th className="px-4 py-3 text-[13px] font-semibold">
                    {l.user}
                  </th>
                  <th className="px-4 py-3 text-[13px] font-semibold">
                    {l.login}
                  </th>
                  <th className="px-4 py-3 text-[13px] font-semibold">
                    {l.department}
                  </th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr
                    key={r.user_id}
                    className="border-b border-border last:border-0"
                  >
                    <td className="px-4 py-2.5 text-center">
                      <Checkbox
                        checked={r.selected}
                        onCheckedChange={(c) =>
                          setRows((cur) =>
                            cur.map((x, j) =>
                              j === i ? { ...x, selected: c === true } : x,
                            ),
                          )
                        }
                        aria-label={`${r.name} — ${l.user}`}
                      />
                    </td>
                    <td className="px-4 py-2.5 font-semibold">{r.name}</td>
                    <td className="px-4 py-2.5">{r.login}</td>
                    <td className="px-4 py-2.5">{r.department ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between gap-2 border-t border-border px-5 py-3.5">
            <span className="text-[13px] text-foreground-muted">
              {rows.filter((r) => r.selected).length} / {rows.length}
            </span>
            <div className="flex gap-2">
              <Button
                variant="outline"
                onClick={() => setRows(saved)}
                disabled={pending}
              >
                <RotateCcw className="size-4" aria-hidden /> {l.revert}
              </Button>
              <Button onClick={save} loading={pending}>
                <Save className="size-4" aria-hidden /> {l.save_list}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

export { TnaInstances, TnaMappedUsers };
