"use client";

import { useEffect, useRef, useState, useTransition } from "react";
import { CalendarRange, CheckCheck, Save, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import {
  Card,
  Chip,
  Files,
  Empty,
  Field,
  Footer,
  NumberCell,
  keepQuery,
  blank,
  asInput,
  num,
  th,
} from "./costing";

/*
 * Work Study → Floor Operations (AnnotechERP 14/07), the sheets Brickland
 * edits in place: Sewing Prod Adjustment (every line on a floor for one hour
 * of a day, recorded beside corrected), the Wash Requisition List (each
 * requisition moved a step by Take Action and Apply) and Man Minutes
 * Adjustment (every reason a line's people were booked away, the general
 * shift and each overtime hour). Each reads on Refresh / Search without a
 * reload — the url keeps what it was read for — with PDF and Excel.
 */

const BASE = "/api/work-study/floor-ops";
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;
const fmtDay = (d) =>
  d
    ? new Date(`${d}T00:00:00`).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      })
    : "—";
const thousands = (v) => Number(v || 0).toLocaleString("en-US");
const isDay = (v) => /^\d{4}-\d{2}-\d{2}$/.test(v ?? "");

function useFo() {
  const t = useT("workstudy");
  return {
    t,
    o: (k, p) => t(`fo.${k}`, p),
    d: (k, p) => t(`cost.day.${k}`, p),
    files: (k) => t(`cost.${k}`),
  };
}

function ErrorLine({ error }) {
  return error ? (
    <p className="mx-5 mb-4 rounded-md border border-danger/30 bg-danger-subtle px-3 py-2 text-sm text-danger">
      {error}
    </p>
  ) : null;
}

function FloorField({ options, floor, onChange, d }) {
  return (
    <Field label={d("floor")}>
      <Select
        value={floor}
        onValueChange={(v) => onChange(v ?? "")}
        options={options.floors.map((x) => ({ value: x.code, label: x.label }))}
        placeholder={d("floor_ph")}
        aria-label={d("floor")}
      />
    </Field>
  );
}

/* ---------------- Sewing Prod Adjustment ---------------- */

function OutputSheet({ options, act, canManage, initial = {} }) {
  const { o, d, files } = useFo();
  const toastManager = useToastManager();
  const [floor, setFloor] = useState(
    options.floors.some((x) => x.code === initial.floor) ? initial.floor : "",
  );
  const [date, setDate] = useState(isDay(initial.date) ? initial.date : "");
  const [hour, setHour] = useState(
    options.hours.map(String).includes(initial.hour) ? initial.hour : "",
  );
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [saved, setSaved] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  function loaded(data) {
    setRows(data.rows);
    setSaved(data.rows);
    setDirty(false);
  }
  function refresh(fl = floor, dt = date, hr = hour) {
    if (!fl || !dt || !hr) return;
    setError(null);
    startTransition(async () => {
      const res = await act("output", { floor: fl, date: dt, hour: hr });
      if (res?.status === "success") {
        loaded(res.data);
        setShown({ floor: fl, date: dt, hour: hr });
        keepQuery({ floor: fl, date: dt, hour: hr });
      } else setError(firstError(res));
    });
  }
  function save() {
    setError(null);
    const clean = rows.map((r) => ({
      planning_line_id: r.planning_line_id,
      buyer_id: r.buyer_id || null,
      order_no: blank(r.order_no) ? null : r.order_no,
      item_name: blank(r.item_name) ? null : r.item_name,
      prod: blank(r.prod) ? null : Number(r.prod),
    }));
    startTransition(async () => {
      const res = await act("saveOutput", { ...shown, rows: clean });
      if (res?.status === "success") {
        loaded(res.data);
        toastManager.add({ title: d("saved"), type: "success" });
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && floor && date && hour) {
      opened.current = true;
      refresh(floor, date, hour);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const adjusted = saved
    .filter((r) => r.id)
    .reduce((s, r) => s + num(r.prod) - num(r.recorded), 0);
  const summary = rows
    ? o("output.summary", {
        date: fmtDay(shown?.date),
        hour: shown?.hour,
        lines: rows.length,
        pcs: thousands(adjusted),
      })
    : null;
  const buyers = [{ value: "", label: "—" }, ...options.buyers];

  return (
    <Card
      icon={CalendarRange}
      title={o("output.title")}
      hint={o("output.hint")}
      right={
        rows ? (
          <>
            <Chip>{o("output.count", { count: rows.length })}</Chip>
            {rows.length ? (
              <Files sheet="output" query={shown} c={files} base={BASE} />
            ) : null}
          </>
        ) : null
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <FloorField options={options} floor={floor} onChange={setFloor} d={d} />
        <Field label={d("date")}>
          <Input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            aria-label={d("date")}
          />
        </Field>
        <Field label={o("output.hour")}>
          <Select
            value={hour}
            onValueChange={(v) => setHour(v ?? "")}
            options={options.hours.map((h) => ({
              value: String(h),
              label: String(h),
            }))}
            placeholder={o("output.hour_ph")}
            aria-label={o("output.hour")}
          />
        </Field>
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => refresh()}
          loading={pending && !dirty}
          disabled={!floor || !date || !hour}
        >
          <Search className="size-4" aria-hidden /> {d("refresh")}
        </Button>
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={o("output.pick_title")} text={d("pick")} />
      ) : !rows.length ? (
        <Empty title={o("output.empty")} text={d("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1100px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "line",
                  "buyer",
                  "order_no",
                  "item_name",
                  "recorded",
                  "prod",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["recorded", "prod"].includes(k) && "text-right",
                    )}
                  >
                    {o(`output.col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.planning_line_id}
                  className="border-b border-border last:border-0"
                >
                  <td className="px-3 py-1.5 text-xs font-medium">{r.line}</td>
                  <td className="px-2 py-1.5">
                    <div className="w-64">
                      <Select
                        value={r.buyer_id ?? ""}
                        onValueChange={(v) => set(i, "buyer_id", v || null)}
                        options={buyers}
                        disabled={!canManage}
                        aria-label={`${o("output.col.buyer")} ${r.line}`}
                      />
                    </div>
                  </td>
                  <td className="px-2 py-1.5">
                    <Input
                      value={r.order_no ?? ""}
                      onChange={(e) => set(i, "order_no", e.target.value)}
                      disabled={!canManage}
                      className="h-8 w-52"
                      aria-label={`${o("output.col.order_no")} ${r.line}`}
                    />
                  </td>
                  <td className="px-2 py-1.5">
                    <Input
                      value={r.item_name ?? ""}
                      onChange={(e) => set(i, "item_name", e.target.value)}
                      disabled={!canManage}
                      className="h-8 w-52"
                      aria-label={`${o("output.col.item_name")} ${r.line}`}
                    />
                  </td>
                  <td className="px-3 py-1.5 text-right tabular-nums">
                    {thousands(r.recorded)}
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex justify-end">
                      <NumberCell
                        value={asInput(r.prod)}
                        onChange={(v) => set(i, "prod", v)}
                        disabled={!canManage}
                        label={`${o("output.col.prod")} ${r.line}`}
                      />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer
        text={
          summary
            ? `${summary} — ${o("output.footer")}`
            : `${o("output.pick_title")} ${o("output.footer")}`
        }
      >
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows?.length || !dirty}
          >
            <Save className="size-4" aria-hidden /> {d("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/* ---------------- Wash Requisition List ---------------- */

const WASH_FILTERS = ["status", "buyer", "no", "style", "order", "from", "to"];

function WashList({ options, act, canManage, initial = {} }) {
  const { o, files } = useFo();
  const w = (k, p) => o(`wash.${k}`, p);
  const toastManager = useToastManager();
  const [filters, setFilters] = useState(() => ({
    ...Object.fromEntries(WASH_FILTERS.map((k) => [k, initial[k] ?? ""])),
    status: initial.status || "OPEN",
  }));
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [open, setOpen] = useState(0);
  const [chosen, setChosen] = useState({});
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const [applying, startApply] = useTransition();

  function search(f = filters) {
    setError(null);
    startTransition(async () => {
      const res = await act("wash", f);
      if (res?.status === "success") {
        setRows(res.data.rows);
        setOpen(res.data.open);
        setChosen({});
        setShown(f);
        keepQuery(f);
      } else setError(firstError(res));
    });
  }
  function apply() {
    const picked = Object.entries(chosen)
      .filter(([, a]) => a)
      .map(([id, action]) => ({ id, action }));
    if (!picked.length) {
      setError(w("nothing"));
      return;
    }
    setError(null);
    startApply(async () => {
      const res = await act("washAction", { rows: picked });
      if (res?.status === "success") {
        toastManager.add({
          title: w("applied", { count: picked.length }),
          type: "success",
        });
        search(shown ?? filters);
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current) {
      opened.current = true;
      search(filters);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const query = shown
    ? Object.fromEntries(Object.entries(shown).filter(([, v]) => v))
    : {};
  const summary = rows ? w("summary", { count: rows.length, open }) : null;
  const actions = [
    { value: "", label: "—" },
    ...options.wash_actions.map((a) => ({ value: a, label: w(`action.${a}`) })),
  ];

  return (
    <Card
      icon={CalendarRange}
      title={w("title")}
      hint={w("hint")}
      right={
        rows && rows.length ? (
          <Files sheet="wash" query={query} c={files} base={BASE} />
        ) : null
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label={w("filter.status")}>
          <Select
            value={filters.status}
            onValueChange={(v) => setF("status", v || "OPEN")}
            options={[
              { value: "ALL", label: w("filter.all") },
              { value: "OPEN", label: w("filter.open") },
              ...options.wash_statuses.map((s) => ({
                value: s,
                label: w(`status.${s}`),
              })),
            ]}
            aria-label={w("filter.status")}
          />
        </Field>
        <Field label={w("filter.buyer")}>
          <Select
            value={filters.buyer}
            onValueChange={(v) => setF("buyer", v)}
            options={[
              { value: "", label: w("filter.buyer_ph") },
              ...options.buyers,
            ]}
            aria-label={w("filter.buyer")}
          />
        </Field>
        {["no", "style", "order"].map((k) => (
          <Field key={k} label={w(`filter.${k}`)}>
            <Input
              value={filters[k]}
              onChange={(e) => setF(k, e.target.value)}
              aria-label={w(`filter.${k}`)}
            />
          </Field>
        ))}
        {[
          ["from", "date_from"],
          ["to", "date_to"],
        ].map(([k, label]) => (
          <Field key={k} label={w(`filter.${label}`)}>
            <Input
              type="date"
              value={filters[k]}
              onChange={(e) => setF(k, e.target.value)}
              aria-label={w(`filter.${label}`)}
            />
          </Field>
        ))}
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button variant="outline" onClick={() => search()} loading={pending}>
          <Search className="size-4" aria-hidden /> {w("search")}
        </Button>
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={w("empty")} text={w("pick")} />
      ) : !rows.length ? (
        <Empty title={w("empty")} text={w("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1180px] text-sm [&_td]:whitespace-nowrap">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "requisition_no",
                  "requisition_date",
                  "buyer",
                  "style_no",
                  "order_no",
                  "shipment_date",
                  "order_qty",
                  "start_date",
                  "close_date",
                  "remarks",
                  "status",
                  ...(canManage ? ["action"] : []),
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(th, k === "order_qty" && "text-right")}
                  >
                    {w(`col.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} className="border-b border-border last:border-0">
                  <td className="px-3 py-2 text-xs font-medium">
                    {r.requisition_no}
                  </td>
                  <td className="px-3 py-2 text-xs">
                    {fmtDay(r.requisition_date)}
                  </td>
                  <td className="px-3 py-2 text-xs">{r.buyer ?? "—"}</td>
                  <td className="px-3 py-2 text-xs">{r.style_no}</td>
                  <td className="px-3 py-2 text-xs">{r.order_no}</td>
                  <td className="px-3 py-2 text-xs">
                    {fmtDay(r.shipment_date)}
                  </td>
                  <td className="px-3 py-2 text-right text-xs tabular-nums">
                    {r.order_qty === null ? "—" : thousands(r.order_qty)}
                  </td>
                  <td className="px-3 py-2 text-xs">{fmtDay(r.start_date)}</td>
                  <td className="px-3 py-2 text-xs">{fmtDay(r.close_date)}</td>
                  <td className="max-w-[16rem] truncate px-3 py-2 text-xs">
                    {r.remarks || "—"}
                  </td>
                  <td className="px-3 py-2 text-xs">
                    {w(`status.${r.status}`)}
                  </td>
                  {canManage ? (
                    <td className="px-2 py-1.5">
                      {r.status === "COMPLETE" ? (
                        <span className="px-1 text-xs text-foreground-muted">
                          —
                        </span>
                      ) : (
                        <div className="w-44">
                          <Select
                            value={chosen[r.id] ?? ""}
                            onValueChange={(v) =>
                              setChosen((c) => ({ ...c, [r.id]: v || "" }))
                            }
                            options={actions}
                            aria-label={`${w("col.action")} ${r.requisition_no}`}
                          />
                        </div>
                      )}
                    </td>
                  ) : null}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer text={summary ? `${summary} — ${w("footer")}` : w("footer")}>
        {canManage ? (
          <Button
            onClick={apply}
            loading={applying}
            disabled={!rows?.length || !Object.values(chosen).some(Boolean)}
          >
            <CheckCheck className="size-4" aria-hidden /> {w("apply")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

/* ---------------- Man Minutes Adjustment ---------------- */

function MinuteSheet({ options, act, canManage, initial = {} }) {
  const { o, d, files } = useFo();
  const m = (k, p) => o(`mm.${k}`, p);
  const toastManager = useToastManager();
  const [floor, setFloor] = useState(
    options.floors.some((x) => x.code === initial.floor) ? initial.floor : "",
  );
  const [date, setDate] = useState(isDay(initial.date) ? initial.date : "");
  const [line, setLine] = useState(initial.line ?? "");
  const [shown, setShown] = useState(null);
  const [rows, setRows] = useState(null);
  const [saved, setSaved] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const floorId = options.floors.find((x) => x.code === floor)?.value;

  function loaded(data) {
    setRows(data.rows);
    setSaved(data.rows);
    setDirty(false);
  }
  function refresh(fl = floor, dt = date, ln = line) {
    if (!fl || !dt || !ln) return;
    setError(null);
    startTransition(async () => {
      const res = await act("minutes", { floor: fl, line: ln, date: dt });
      if (res?.status === "success") {
        loaded(res.data);
        setShown({ floor: fl, line: ln, date: dt });
        keepQuery({ floor: fl, line: ln, date: dt });
      } else setError(firstError(res));
    });
  }
  function save() {
    setError(null);
    const clean = rows.map((r) => ({
      reason: r.reason,
      used_op: num(r.used_op),
      used_aso: num(r.used_aso),
      slots: Object.fromEntries(
        Object.entries(r.slots).map(([s, v]) => [
          s,
          { op: num(v.op), aso: num(v.aso) },
        ]),
      ),
    }));
    startTransition(async () => {
      const res = await act("saveMinutes", { ...shown, rows: clean });
      if (res?.status === "success") {
        loaded(res.data);
        toastManager.add({ title: d("saved"), type: "success" });
      } else setError(firstError(res));
    });
  }
  const opened = useRef(false);
  useEffect(() => {
    if (!opened.current && floor && date && line) {
      opened.current = true;
      refresh(floor, date, line);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = (i, key, value) => {
    setRows((all) => all.map((r, j) => (j === i ? { ...r, [key]: value } : r)));
    setDirty(true);
  };
  const setSlot = (i, slot, key, value) => {
    setRows((all) =>
      all.map((r, j) =>
        j === i
          ? {
              ...r,
              slots: { ...r.slots, [slot]: { ...r.slots[slot], [key]: value } },
            }
          : r,
      ),
    );
    setDirty(true);
  };
  const otSum = (r, key) =>
    Object.values(r.slots).reduce((s, v) => s + num(v[key]), 0);
  const booked = saved.filter((r) => r.id);
  const summary = rows
    ? m("summary", {
        line: shown?.line,
        date: fmtDay(shown?.date),
        reasons: rows.length,
        op: thousands(booked.reduce((s, r) => s + r.used_op + r.ot_op, 0)),
        aso: thousands(booked.reduce((s, r) => s + r.used_aso + r.ot_aso, 0)),
      })
    : null;

  return (
    <Card
      icon={CalendarRange}
      title={m("title")}
      hint={m("hint")}
      right={
        <>
          <Chip>{m("count", { count: booked.length })}</Chip>
          {rows && booked.length ? (
            <Files sheet="minutes" query={shown} c={files} base={BASE} />
          ) : null}
        </>
      }
    >
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <FloorField
          options={options}
          floor={floor}
          onChange={(v) => {
            setFloor(v);
            setLine("");
          }}
          d={d}
        />
        <Field label={d("date")}>
          <Input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            aria-label={d("date")}
          />
        </Field>
        <Field label={o("line")}>
          <Select
            value={line}
            onValueChange={(v) => setLine(v ?? "")}
            options={options.lines
              .filter((l) => l.floor_id === floorId)
              .map((l) => ({ value: l.code, label: l.code }))}
            placeholder={o("line_ph")}
            aria-label={o("line")}
          />
        </Field>
      </div>
      <div className="px-5 pt-4 pb-5">
        <Button
          variant="outline"
          onClick={() => refresh()}
          loading={pending && !dirty}
          disabled={!floor || !date || !line}
        >
          <Search className="size-4" aria-hidden /> {d("refresh")}
        </Button>
      </div>
      <ErrorLine error={error} />
      {!rows ? (
        <Empty title={m("pick_title")} text={d("pick")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[2400px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className={th}>{m("col.reason")}</th>
                {["used_op", "used_aso", "ot_op", "ot_aso"].map((k) => (
                  <th key={k} className={cn(th, "text-right")}>
                    {m(`col.${k}`)}
                  </th>
                ))}
                {options.slots.flatMap((s) =>
                  ["op", "aso"].map((k) => (
                    <th
                      key={`${s.value}-${k}`}
                      className={cn(th, "text-right")}
                    >
                      {`${s.label} ${m(`col.${k}`)}`}
                    </th>
                  )),
                )}
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr
                  key={r.reason}
                  className="border-b border-border last:border-0"
                >
                  <td className="whitespace-nowrap px-3 py-1.5 text-xs font-medium">
                    {r.reason}
                  </td>
                  {["used_op", "used_aso"].map((k) => (
                    <td key={k} className="px-2 py-1.5">
                      <div className="flex justify-end">
                        <NumberCell
                          value={asInput(r[k])}
                          onChange={(v) => set(i, k, v)}
                          disabled={!canManage}
                          label={`${m(`col.${k}`)} ${r.reason}`}
                          w="w-20"
                        />
                      </div>
                    </td>
                  ))}
                  {["op", "aso"].map((k) => (
                    <td
                      key={k}
                      className="px-3 py-1.5 text-right font-semibold tabular-nums"
                    >
                      {otSum(r, k)}
                    </td>
                  ))}
                  {options.slots.flatMap((s) =>
                    ["op", "aso"].map((k) => (
                      <td key={`${s.value}-${k}`} className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <NumberCell
                            value={asInput(r.slots[s.value]?.[k])}
                            onChange={(v) => setSlot(i, s.value, k, v)}
                            disabled={!canManage}
                            label={`${s.label} ${m(`col.${k}`)} ${r.reason}`}
                            w="w-16"
                          />
                        </div>
                      </td>
                    )),
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Footer
        text={
          summary
            ? `${summary} — ${m("footer")}`
            : `${m("pick_title")} ${m("footer")}`
        }
      >
        {canManage ? (
          <Button
            onClick={save}
            loading={pending && dirty}
            disabled={!rows || !dirty}
          >
            <Save className="size-4" aria-hidden /> {d("save")}
          </Button>
        ) : null}
      </Footer>
    </Card>
  );
}

export { OutputSheet, WashList, MinuteSheet };
