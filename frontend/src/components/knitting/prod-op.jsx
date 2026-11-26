"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Download, Plus, Save, Scale, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  num,
  qtyFmt,
  messagesOf,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  orNull,
} from "./yarn-common";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Operator Roll
 * Production Entry: Grey Fabric Roll Production (pick the station; each of
 * its machines is a tab; weigh each roll onto the card the loading plan
 * booked for the shift), Tab Settings for Machine(s) (tick the machines a
 * station shows) and Save Station (the station itself). Nothing reloads.
 */

const BASE = "/knit-production/roll-production-operator";

function useOp() {
  const t = useT("knitting");
  return (k, p) => t(`op.${k}`, p);
}

const pick = (list, ph) => [{ value: "", label: ph }, ...list];
const QTONE = {
  PENDING: "DRAFT",
  PASSED: "RECEIVED",
  HOLD: "WAITING_APPROVAL",
  REJECTED: "REJECTED",
};

function OpProduction({ options, initial, canManage, act }) {
  const c = useOp();
  const tq = useT("knitting");
  const toastManager = useToastManager();
  const [station, setStation] = useState(initial);
  const [tab, setTab] = useState(initial?.machines?.[0]?.machine_id ?? "");
  const [pickedCard, setPickedCard] = useState({});
  const blankRoll = (s) => ({
    weight: "",
    deducted: "",
    shift_id: str(s?.current_shift ?? options.current_shift),
    operator_id: str(s?.operator_id),
    comments: "",
  });
  const [roll, setRoll] = useState(() => blankRoll(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const machine = station?.machines?.find((m) => m.machine_id === tab);
  const cardNo = machine?.card?.kc_no ?? pickedCard[tab] ?? "";
  const cardInfo =
    machine?.card ?? options.cards.find((x) => x.kc_no === cardNo) ?? null;

  function load(code) {
    if (!code) {
      setStation(null);
      return;
    }
    startTransition(async () => {
      const res = await act(`stations/${encodeURIComponent(code)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setStation(res.data);
      setTab(res.data.machines[0]?.machine_id ?? "");
      setRoll(blankRoll(res.data));
      setErrors(null);
      window.history.replaceState(
        null,
        "",
        `?station=${encodeURIComponent(code)}`,
      );
    });
  }
  function save() {
    if (!(num(roll.weight) > 0)) return setErrors([c("line.need_weight")]);
    startTransition(async () => {
      const res = await act(
        `stations/${encodeURIComponent(station.code)}/rolls`,
        "POST",
        {
          machine_id: tab,
          kc_no: cardNo || null,
          weight: orNull(roll.weight),
          deducted: orNull(roll.deducted),
          shift_id: roll.shift_id || null,
          operator_id: roll.operator_id || null,
          comments: roll.comments || null,
        },
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setStation(res.data);
      setRoll((r) => ({ ...r, weight: "", deducted: "", comments: "" }));
      setErrors(null);
      toastManager.add({ title: res.data.message, type: "success" });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <p className="text-[13px] text-foreground-muted">
          {c("hint.production")}
        </p>
        <Field label={c("f.station")} className="mt-3 max-w-md">
          <PSelect
            value={station?.code ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(
              options.stations.filter((s) => s.active),
              c("ph.select"),
            )}
            disabled={pending}
            aria-label={c("f.station")}
          />
        </Field>
      </section>
      {options.stations.length === 0 ? (
        <section className={card}>
          <NoLines
            title={c("empty.stations")}
            hint={c("empty.stations_hint")}
          />
        </section>
      ) : null}
      {station && station.machines.length === 0 ? (
        <Alert variant="info">{c("empty.machines")}</Alert>
      ) : null}
      {station && station.machines.length ? (
        <>
          <div
            className="flex flex-wrap gap-1 border-b border-border"
            role="tablist"
          >
            {station.machines.map((m) => (
              <button
                key={m.machine_id}
                type="button"
                role="tab"
                aria-selected={tab === m.machine_id}
                onClick={() => (setTab(m.machine_id), setErrors(null))}
                className={cn(
                  "-mb-px border-b-2 px-4 py-2 text-sm font-medium",
                  tab === m.machine_id
                    ? "border-brand text-brand"
                    : "border-transparent text-foreground-muted hover:text-foreground",
                )}
              >
                {m.machine}
                <span className="ml-1.5 text-xs text-foreground-muted">
                  {[m.dia, m.gauge].filter(Boolean).join(" x ")}
                </span>
              </button>
            ))}
          </div>
          <div className="grid items-start gap-4 lg:grid-cols-2">
            <section className={cn(card, "p-5")}>
              <div className="flex items-start justify-between gap-3">
                <h3 className="text-base font-semibold text-foreground">
                  {c("sec.card")}
                </h3>
                {machine?.card ? (
                  <StatusChip
                    status={machine.planned ? "APPROVED" : "WAITING_APPROVAL"}
                    label={machine.card.kc_no}
                  />
                ) : null}
              </div>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {machine?.card
                  ? machine.planned
                    ? c("hint.planned")
                    : c("hint.running")
                  : c("hint.no_card")}
              </p>
              {!machine?.card ? (
                <Field label={c("f.card")} className="mt-3">
                  <PSelect
                    value={pickedCard[tab] ?? ""}
                    onValueChange={(v) =>
                      setPickedCard((p) => ({ ...p, [tab]: v ?? "" }))
                    }
                    options={pick(
                      options.cards.map((x) => ({
                        value: x.kc_no,
                        label: [x.kc_no, x.buyer_style_order, x.colour]
                          .filter(Boolean)
                          .join(" · "),
                      })),
                      c("ph.card"),
                    )}
                    aria-label={c("f.card")}
                  />
                </Field>
              ) : null}
              {cardInfo ? (
                <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                  {[
                    ["buyer", cardInfo.buyer_style_order],
                    ["fabric", cardInfo.fabric],
                    ["colour", cardInfo.colour],
                    [
                      "construction",
                      [cardInfo.machine_dia, cardInfo.gauge]
                        .filter(Boolean)
                        .join(" x "),
                    ],
                    ["yarn", cardInfo.yarn_details],
                  ].map(([k, v]) => (
                    <div key={k} className={k === "yarn" ? "col-span-2" : ""}>
                      <dt className="text-xs text-foreground-muted">
                        {c(`card.${k}`)}
                      </dt>
                      <dd className="font-medium text-foreground">
                        {v || "—"}
                      </dd>
                    </div>
                  ))}
                  <div className="col-span-2 mt-1 grid grid-cols-3 gap-2">
                    {["assigned", "rolled", "left"].map((k) => (
                      <div
                        key={k}
                        className="rounded-lg border border-border px-3 py-2"
                      >
                        <div className="text-xs text-foreground-muted">
                          {c(`card.${k}`)}
                        </div>
                        <div className="text-base font-semibold tabular-nums">
                          {qtyFmt.format(cardInfo[k])}
                        </div>
                      </div>
                    ))}
                  </div>
                </dl>
              ) : null}
            </section>
            <section className={cn(card, "p-5")}>
              <h3 className="text-base font-semibold text-foreground">
                {c("sec.roll")}
              </h3>
              <div className="mt-3 grid gap-4 sm:grid-cols-2">
                <Field label={c("f.weight")} required>
                  <Input
                    type="number"
                    min="0"
                    step="any"
                    value={roll.weight}
                    onChange={(e) =>
                      setRoll((r) => ({ ...r, weight: e.target.value }))
                    }
                    className="h-12 text-lg"
                    aria-label={c("f.weight")}
                  />
                </Field>
                <Field label={c("f.deducted")}>
                  <Input
                    type="number"
                    min="0"
                    step="any"
                    value={roll.deducted}
                    onChange={(e) =>
                      setRoll((r) => ({ ...r, deducted: e.target.value }))
                    }
                    className="h-12 text-lg"
                    aria-label={c("f.deducted")}
                  />
                </Field>
                <Field label={c("f.shift")}>
                  <PSelect
                    value={roll.shift_id}
                    onValueChange={(v) =>
                      setRoll((r) => ({ ...r, shift_id: v ?? "" }))
                    }
                    options={pick(options.shifts, c("ph.select"))}
                    aria-label={c("f.shift")}
                  />
                </Field>
                <Field label={c("f.operator")}>
                  <PSelect
                    value={roll.operator_id}
                    onValueChange={(v) =>
                      setRoll((r) => ({ ...r, operator_id: v ?? "" }))
                    }
                    options={pick(options.employees, c("ph.select"))}
                    aria-label={c("f.operator")}
                  />
                </Field>
                <Field label={c("f.comments")} className="sm:col-span-2">
                  <Input
                    value={roll.comments}
                    onChange={(e) =>
                      setRoll((r) => ({ ...r, comments: e.target.value }))
                    }
                    maxLength={300}
                    aria-label={c("f.comments")}
                  />
                </Field>
              </div>
              {errors ? (
                <Alert variant="danger" className="mt-3">
                  {errors.join(" · ")}
                </Alert>
              ) : null}
              {canManage ? (
                <Button
                  className="mt-4 w-full"
                  size="lg"
                  onClick={save}
                  loading={pending}
                  disabled={!cardNo}
                >
                  <Scale /> {c("btn.save_roll")}
                </Button>
              ) : null}
            </section>
          </div>
          <section className={card}>
            <h3 className="px-5 pt-4 text-base font-semibold text-foreground">
              {c("sec.today")}
            </h3>
            <div className="mt-3 overflow-x-auto">
              {!machine?.rolls?.length ? (
                <NoLines title={c("empty.rolls")} hint="" />
              ) : (
                <table className="w-full min-w-[700px] text-sm">
                  <thead className="bg-brand text-brand-foreground">
                    <tr>
                      {[
                        ["roll"],
                        ["at"],
                        ["shift"],
                        ["operator"],
                        ["weight", true],
                        ["deducted", true],
                        ["net", true],
                        ["quality"],
                      ].map(([k, right]) => (
                        <th key={k} className={cn(th, right && "text-right")}>
                          {c(`col.${k}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {machine.rolls.map((r) => (
                      <tr key={r.roll_code} className="border-t border-border">
                        <td className="px-2 py-2 font-semibold">
                          {r.roll_code}
                        </td>
                        <td className="px-2 py-2">{r.at}</td>
                        <td className="px-2 py-2">{r.shift ?? "—"}</td>
                        <td className="px-2 py-2">{r.operator ?? "—"}</td>
                        <td className="px-2 py-2 text-right tabular-nums">
                          {qtyFmt.format(r.weight)}
                        </td>
                        <td className="px-2 py-2 text-right tabular-nums">
                          {qtyFmt.format(r.deducted)}
                        </td>
                        <td className="px-2 py-2 text-right font-semibold tabular-nums">
                          {qtyFmt.format(r.net)}
                        </td>
                        <td className="px-2 py-2">
                          <StatusChip
                            status={QTONE[r.quality]}
                            label={tq(`prod.quality.${r.quality}`)}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </section>
        </>
      ) : null}
    </div>
  );
}

function OpSettings({ options, canManage, act }) {
  const c = useOp();
  const toastManager = useToastManager();
  const [stations, setStations] = useState(options.stations);
  const [q, setQ] = useState("");
  const [picked, setPicked] = useState("");
  const [station, setStation] = useState(null);
  const [ticks, setTicks] = useState([]);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = stations.filter(
    (s) =>
      !q ||
      [s.value, s.name, s.operator]
        .join(" ")
        .toLowerCase()
        .includes(q.toLowerCase()),
  );
  const floors = Object.fromEntries(
    options.floors.map((f) => [f.value, f.label]),
  );

  function load(code = picked) {
    if (!code) return;
    startTransition(async () => {
      const res = await act(`stations/${encodeURIComponent(code)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setStation(res.data);
      setPicked(code);
      setTicks(res.data.machine_ids);
      setErrors(null);
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act(
        `stations/${encodeURIComponent(station.code)}`,
        "PUT",
        {
          code: station.code,
          name: station.name,
          operator_id: station.operator_id,
          active: station.active,
          machine_ids: options.machines
            .map((m) => m.value)
            .filter((id) => ticks.includes(id)),
        },
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setStation(res.data);
      setTicks(res.data.machine_ids);
      setStations((all) =>
        all.map((s) =>
          s.value === res.data.code
            ? { ...s, machines: res.data.machine_ids.length }
            : s,
        ),
      );
      setErrors(null);
      toastManager.add({
        title: c("done.station", { no: res.data.code }),
        type: "success",
      });
    });
  }

  return (
    <div className="grid items-start gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
      <section className={cn(card, "p-5")}>
        <h3 className="text-sm font-semibold text-foreground">
          {c("sec.search")}
        </h3>
        <Field label={c("f.station")} className="mt-3">
          <PSelect
            value={picked}
            onValueChange={(v) => setPicked(v ?? "")}
            options={pick(stations, c("ph.select"))}
            aria-label={c("f.station")}
          />
        </Field>
        <div className="mt-3 flex gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => load()}
            disabled={!picked || pending}
          >
            <Download /> {c("btn.load")}
          </Button>
          {canManage ? (
            <Link
              href={`${BASE}?tab=station`}
              className={buttonVariants({ variant: "outline", size: "sm" })}
            >
              <Plus /> {c("btn.new")}
            </Link>
          ) : null}
        </div>
        <h3 className="mt-5 text-sm font-semibold text-foreground">
          {c("sec.stations")}
        </h3>
        <div className="relative mt-2">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={c("ph.search", { count: stations.length })}
            className="pl-9"
            aria-label={c("f.search")}
          />
        </div>
        <div className="mt-2 overflow-hidden rounded-lg border border-border">
          {shown.length === 0 ? (
            <p className="px-3 py-6 text-center text-sm text-foreground-muted">
              {c("empty.no_match", { q })}
            </p>
          ) : (
            shown.map((s) => (
              <button
                key={s.value}
                type="button"
                onClick={() => load(s.value)}
                className={cn(
                  "flex w-full items-center justify-between border-t border-border px-3 py-2 text-left text-sm first:border-t-0 hover:bg-surface-muted",
                  station?.code === s.value && "bg-brand-subtle",
                )}
              >
                <span>
                  <span className="font-semibold">{s.value}</span>{" "}
                  <span className="text-foreground-muted">{s.name}</span>
                </span>
                <span className="text-xs text-foreground-muted">
                  {s.machines}
                </span>
              </button>
            ))
          )}
        </div>
        <p className="mt-2 text-xs text-foreground-muted">
          {c("count", { n: shown.length, total: stations.length })}
        </p>
      </section>
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {c("tabs.settings")}
          {station ? ` — ${station.code}` : ""}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {c("hint.settings")}
        </p>
        {!station ? (
          <NoLines
            title={c("hint.pick_station")}
            hint={c("hint.pick_station_more")}
          />
        ) : (
          <>
            <h3 className="mt-4 text-sm font-semibold text-foreground">
              {c("sec.machines")}
            </h3>
            <div className="mt-2 overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    <th className={th} />
                    <th className={th}>{c("col.machine")}</th>
                    <th className={th}>{c("col.dia_gauge")}</th>
                    <th className={th}>{c("col.floor")}</th>
                  </tr>
                </thead>
                <tbody>
                  {options.machines.map((m) => (
                    <tr key={m.value} className="border-t border-border">
                      <td className="px-3 py-2">
                        <Checkbox
                          checked={ticks.includes(m.value)}
                          onCheckedChange={(v) =>
                            setTicks((t) =>
                              v
                                ? [...t, m.value]
                                : t.filter((x) => x !== m.value),
                            )
                          }
                          disabled={!canManage}
                          aria-label={m.label}
                        />
                      </td>
                      <td className="px-2 py-2 font-semibold">{m.label}</td>
                      <td className="px-2 py-2">
                        {[m.dia, m.gauge].filter(Boolean).join(" x ") || "—"}
                      </td>
                      <td className="px-2 py-2">{floors[m.floor_id] ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {errors ? (
              <Alert variant="danger" className="mt-3">
                {errors.join(" · ")}
              </Alert>
            ) : null}
            {canManage ? (
              <div className="mt-4 flex justify-end">
                <Button onClick={save} loading={pending}>
                  <Save /> {c("btn.save_machines")}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </section>
    </div>
  );
}

function OpStation({ options, canManage, act }) {
  const c = useOp();
  const toastManager = useToastManager();
  const blank = { code: "", name: "", operator_id: "", active: true };
  const [doc, setDoc] = useState(null);
  const [form, setForm] = useState(blank);
  const [stations, setStations] = useState(options.stations);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (patch) => setForm((f) => ({ ...f, ...patch }));

  function open(code) {
    if (!code) return (setDoc(null), setForm(blank));
    startTransition(async () => {
      const res = await act(`stations/${encodeURIComponent(code)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDoc(res.data);
      setForm({
        code: res.data.code,
        name: res.data.name,
        operator_id: str(res.data.operator_id),
        active: res.data.active,
      });
      setErrors(null);
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act(
        doc ? `stations/${encodeURIComponent(doc.code)}` : "stations",
        doc ? "PUT" : "POST",
        { ...form, operator_id: form.operator_id || null },
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDoc(res.data);
      setForm({
        code: res.data.code,
        name: res.data.name,
        operator_id: str(res.data.operator_id),
        active: res.data.active,
      });
      setStations((all) => [
        ...all.filter((s) => s.value !== res.data.code),
        {
          value: res.data.code,
          label: `${res.data.code} — ${res.data.name}`,
          name: res.data.name,
          machines: res.data.machine_ids.length,
          active: res.data.active,
        },
      ]);
      setErrors(null);
      toastManager.add({
        title: c("done.station", { no: res.data.code }),
        type: "success",
      });
    });
  }

  return (
    <section className={cn(card, "max-w-3xl p-5")}>
      <h2 className="text-base font-semibold text-foreground">
        {doc ? doc.code : c("tabs.station")}
      </h2>
      <p className="mt-0.5 text-[13px] text-foreground-muted">
        {c("hint.station")}
      </p>
      <Field label={c("f.existing")} className="mt-4">
        <PSelect
          value={doc?.code ?? ""}
          onValueChange={(v) => open(v ?? "")}
          options={pick(stations, c("ph.new"))}
          disabled={pending}
          aria-label={c("f.existing")}
        />
      </Field>
      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <Field label={c("f.code")} required>
          <Input
            value={form.code}
            onChange={(e) => set({ code: e.target.value.toUpperCase() })}
            maxLength={24}
            disabled={!canManage}
            aria-label={c("f.code")}
          />
        </Field>
        <Field label={c("f.name")} required>
          <Input
            value={form.name}
            onChange={(e) => set({ name: e.target.value })}
            maxLength={120}
            disabled={!canManage}
            aria-label={c("f.name")}
          />
        </Field>
        <Field label={c("f.operator")}>
          <PSelect
            value={form.operator_id}
            onValueChange={(v) => set({ operator_id: v ?? "" })}
            options={pick(options.employees, c("ph.select"))}
            disabled={!canManage}
            aria-label={c("f.operator")}
          />
        </Field>
        <div className="flex items-center gap-2 pt-7">
          <Checkbox
            checked={form.active}
            onCheckedChange={(v) => set({ active: Boolean(v) })}
            disabled={!canManage}
            aria-label={c("f.active")}
          />
          <span className="text-[13px] font-semibold">{c("f.active")}</span>
        </div>
      </div>
      {errors ? (
        <Alert variant="danger" className="mt-3">
          {errors.join(" · ")}
        </Alert>
      ) : null}
      {canManage ? (
        <div className="mt-4 flex justify-end">
          <Button onClick={save} loading={pending}>
            <Save /> {c("btn.save_station")}
          </Button>
        </div>
      ) : null}
    </section>
  );
}

export { OpProduction, OpSettings, OpStation };
