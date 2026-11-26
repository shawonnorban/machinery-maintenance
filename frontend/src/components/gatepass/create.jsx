"use client";

import { useMemo, useState, useTransition } from "react";
import { printDocument } from "@/lib/print-document";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  Barcode,
  CircleX,
  ListChecks,
  Plus,
  Printer,
  Save,
  Search,
  Send,
  Trash2,
  UserRound,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import {
  card,
  day,
  dash,
  qty,
  today,
  dropError,
  messagesOf,
  firstMessage,
  Field,
  useGp,
} from "./common";

/*
 * Gate Pass → Create Gate Pass (AnnotechERP 11/03), as Brickland lays it
 * out: 1. category (Main for an employee, Item for goods) and type (OUT & IN
 * or OUT ONLY), with the number it will take and the date; 2. general
 * information — buyer, order, style and PO for goods, the purpose, the
 * destination (whose type, address and contact fill in), the expected
 * return; 3. who takes it out — an employee found by card number, or anyone
 * else with name and mobile; 4. the items (goods only); 5. remarks and
 * documents. "Still needed" lists what Submit for Approval waits for; a draft
 * saves at any point. Saved, it opens Gate Pass List. A draft sent back for
 * amendment opens here to be changed.
 */

const blankLine = () => ({ item_id: "", unit: "", quantity: "", remarks: "" });

function initialForm(options, draft) {
  const item = options.categories.find((c) => c.kind === "ITEM");
  if (!draft) {
    return {
      category_id: item?.value ?? options.categories[0]?.value ?? "",
      pass_type: "RETURNABLE",
      gp_date: today(),
      buyer_id: "",
      order_id: "",
      style: "",
      po_no: "",
      purpose_id: "",
      location_id: "",
      destination_text: "",
      expected_return: "",
      carrier_type: "EMPLOYEE",
      carrier_name: "",
      carrier_company: "",
      carrier_mobile: "",
      carrier_nid: "",
      vehicle_no: "",
      carrier_remarks: "",
      remarks: "",
      documents: "",
      lines: [blankLine()],
    };
  }
  const v = (x) => x ?? "";
  return {
    category_id: draft.category_id,
    pass_type: draft.pass_type,
    gp_date: draft.date,
    buyer_id: v(draft.buyer_id),
    order_id: v(draft.order_id),
    style: v(draft.style),
    po_no: v(draft.po_no),
    purpose_id: v(draft.purpose_id),
    location_id: v(draft.location_id),
    destination_text: v(draft.destination_text),
    expected_return: v(draft.expected_return),
    carrier_type: draft.carrier_type,
    carrier_name: draft.employee ? "" : v(draft.carrier_name),
    carrier_company: v(draft.carrier_company),
    carrier_mobile: draft.employee ? "" : v(draft.carrier_mobile),
    carrier_nid: v(draft.carrier_nid),
    vehicle_no: v(draft.vehicle_no),
    carrier_remarks: v(draft.carrier_remarks),
    remarks: v(draft.remarks),
    documents: v(draft.documents),
    lines: draft.lines.length
      ? draft.lines.map((l) => ({
          item_id: l.item_id,
          unit: l.unit,
          quantity: String(l.out),
          remarks: v(l.remarks),
        }))
      : [blankLine()],
  };
}

/** A boxed radio group, as Brickland draws the category, type and carrier choices. */
function Choice({ name, value, options, onChange, className }) {
  return (
    <div
      role="radiogroup"
      className={cn(
        "flex flex-col gap-2 rounded-lg border border-border bg-surface-muted px-3 py-2.5",
        className,
      )}
    >
      {options.map((o) => (
        <label
          key={o.value}
          className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
        >
          <input
            type="radio"
            name={name}
            value={o.value}
            checked={value === o.value}
            onChange={() => onChange(o.value)}
            className="size-4 accent-[var(--brand)]"
          />
          {o.label}
        </label>
      ))}
    </div>
  );
}

function Card({ title, tone, children, className }) {
  const head = {
    brand: "bg-brand-subtle text-brand",
    success: "bg-success-subtle text-success",
    warning: "bg-warning-subtle text-warning",
  }[tone ?? "brand"];
  return (
    <section className={cn(card, "min-w-0 overflow-hidden", className)}>
      {title ? (
        <h2 className={cn("px-4 py-2.5 text-sm font-semibold", head)}>
          {title}
        </h2>
      ) : null}
      <div className="p-4">{children}</div>
    </section>
  );
}

/**
 * Create Gate Pass — or change a draft (`draft`).
 *
 * @param {{ options: object, act: Function, draft?: object }} props
 */
function CreateGatePass({ options, act, draft }) {
  const { g, unitName } = useGp();
  const f = (k, p) => g(`f.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [form, setForm] = useState(() => initialForm(options, draft));
  const [cardNo, setCardNo] = useState(draft?.employee?.card_no ?? "");
  const [employee, setEmployee] = useState(draft?.employee ?? null);
  const [cardError, setCardError] = useState("");
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const [finding, startFinding] = useTransition();

  const kind =
    options.categories.find((c) => c.value === form.category_id)?.kind ??
    "ITEM";
  const isItem = kind === "ITEM";
  const returnable = form.pass_type === "RETURNABLE";
  const carrier = isItem ? form.carrier_type : "EMPLOYEE";
  const location = options.locations.find((l) => l.value === form.location_id);
  const orders = options.orders.filter(
    (o) => !form.buyer_id || o.buyer_id === form.buyer_id,
  );
  const set = (k) => (x) => {
    setForm((all) => ({ ...all, [k]: x ?? "" }));
    setErrors((all) => dropError(all, k));
  };
  const setLine = (i, patch) =>
    setForm((all) => ({
      ...all,
      lines: all.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)),
    }));

  // What Brickland's "Still needed" lists — Submit for Approval waits for it.
  const filledLines = form.lines.filter(
    (l) => l.item_id && l.unit && Number(l.quantity) > 0,
  );
  const missing = useMemo(() => {
    const out = [];
    if (!form.purpose_id) out.push(f("purpose"));
    if (isItem && !form.location_id) out.push(f("destination"));
    if (isItem && returnable && !form.expected_return)
      out.push(f("expected_return"));
    if (carrier === "EMPLOYEE" && !employee)
      out.push(g("search_it", { field: f("card_no") }));
    if (carrier === "NON_EMPLOYEE" && !form.carrier_name.trim())
      out.push(f("person_name"));
    if (carrier === "NON_EMPLOYEE" && !form.carrier_mobile.trim())
      out.push(f("mobile"));
    if (isItem && filledLines.length === 0) out.push(f("items"));
    return out;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form, employee, isItem, returnable, carrier, filledLines.length]);

  const findEmployee = () =>
    startFinding(async () => {
      setCardError("");
      setEmployee(null);
      if (!cardNo.trim()) return;
      const res = await act("employee", cardNo.trim());
      if (res?.status !== "success") return setCardError(firstMessage(res));
      setEmployee(res.data);
      setErrors((all) => dropError(all, "employee_id"));
    });

  const payload = (submit) => ({
    ...form,
    employee_id: carrier === "EMPLOYEE" ? (employee?.id ?? null) : null,
    lines: isItem ? form.lines : [],
    submit,
  });
  const save = (submit) =>
    startTransition(async () => {
      setErrors({});
      const res = draft
        ? await act("update", draft.gp_no, payload(submit))
        : await act("save", payload(submit));
      if (res?.status !== "success") {
        setErrors(messagesOf(res));
        const first = firstMessage(res);
        if (first) toastManager.add({ title: first, type: "error" });
        return;
      }
      toastManager.add({
        title: g(submit ? "toast.submitted" : "toast.saved", {
          no: res.data.gp_no,
        }),
        type: "success",
      });
      router.push("/gate-pass/gp-list");
    });

  const err = (k) => errors[k]?.[0];
  const reset = () => {
    setForm(initialForm(options, draft));
    setCardNo(draft?.employee?.card_no ?? "");
    setEmployee(draft?.employee ?? null);
    setCardError("");
    setErrors({});
  };
  // The pass as it stands on the form, before it is saved — the same paper as a saved pass.
  const preview = () => {
    const name = (list, id) => list.find((o) => o.value === id)?.label ?? null;
    printDocument({
      title: g("print.title"),
      docNo: draft?.gp_no ?? options.next_no,
      subtitle: g(`type_long.${form.pass_type}`),
      meta: [
        [f("date"), day(form.gp_date)],
        [f("category"), name(options.categories, form.category_id)],
        [f("purpose"), name(options.purposes, form.purpose_id)],
        [f("destination"), location?.label ?? form.destination_text],
        [
          f("expected_return"),
          isItem && returnable && form.expected_return
            ? day(form.expected_return)
            : null,
        ],
        [
          g("panel.carrier"),
          carrier === "EMPLOYEE"
            ? employee
              ? `${employee.name} (${employee.card_no})`
              : null
            : form.carrier_name,
        ],
      ],
      sections: isItem
        ? [
            {
              columns: [
                { label: g("col.sl"), align: "center", width: "7%" },
                { label: f("item") },
                { label: f("unit") },
                { label: f("quantity"), align: "right" },
                { label: f("remarks") },
              ],
              rows: filledLines.map((l, i) => [
                i + 1,
                name(options.items, l.item_id),
                unitName(l.unit),
                qty(l.quantity),
                l.remarks,
              ]),
            },
          ]
        : [],
      notes: [form.remarks],
      signatures: ["prepared", "approved", "security", "receiver"].map((k) =>
        g(`print.${k}`),
      ),
    });
  };

  let n = 0;
  const sec = (k) => g(`sec.${k}`, { n: ++n });
  const pick = (k, list, placeholder) => (
    <Select
      value={form[k]}
      onValueChange={set(k)}
      options={[{ value: "", label: placeholder }, ...list]}
      placeholder={placeholder}
      aria-label={f(k === "location_id" ? "destination" : k.replace("_id", ""))}
    />
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end">
        <Link
          href="/gate-pass/gp-list"
          className={buttonVariants({ size: "sm" })}
        >
          <ListChecks className="size-4" aria-hidden />
          {g("btn.list")}
        </Link>
      </div>

      <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <Card title={sec("category")}>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label={f("category")} required>
              <Choice
                name="category"
                value={form.category_id}
                onChange={set("category_id")}
                options={options.categories}
              />
            </Field>
            <Field label={f("type")} required>
              <Choice
                name="pass_type"
                value={form.pass_type}
                onChange={set("pass_type")}
                options={["RETURNABLE", "NON_RETURNABLE"].map((x) => ({
                  value: x,
                  label: g(`type_long.${x}`),
                }))}
              />
            </Field>
          </div>
        </Card>
        <Card>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field
              label={
                <>
                  {f("gp_no")} <span className="text-danger">{f("auto")}</span>
                </>
              }
            >
              <div className="flex h-10 items-center gap-2 rounded-lg border border-border bg-surface-muted px-3 font-mono text-sm font-semibold">
                <Barcode className="size-4 text-foreground-muted" aria-hidden />
                {draft?.gp_no ?? options.next_no}
              </div>
            </Field>
            <Field label={f("date")} required error={err("gp_date")}>
              <Input
                type="date"
                value={form.gp_date}
                onChange={(ev) => set("gp_date")(ev.target.value)}
                aria-label={f("date")}
              />
            </Field>
          </div>
        </Card>
      </div>

      <Card title={sec("general")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {isItem ? (
            <>
              <Field label={f("buyer")} error={err("buyer_id")}>
                <Select
                  value={form.buyer_id}
                  onValueChange={(x) =>
                    setForm((all) => ({
                      ...all,
                      buyer_id: x ?? "",
                      order_id:
                        x &&
                        options.orders.find((o) => o.value === all.order_id)
                          ?.buyer_id !== x
                          ? ""
                          : all.order_id,
                    }))
                  }
                  options={[
                    { value: "", label: f("buyer_ph") },
                    ...options.buyers,
                  ]}
                  placeholder={f("buyer_ph")}
                  aria-label={f("buyer")}
                />
              </Field>
              <Field label={f("order")} error={err("order_id")}>
                <Select
                  value={form.order_id}
                  onValueChange={(x) => {
                    const o = options.orders.find((y) => y.value === x);
                    setForm((all) => ({
                      ...all,
                      order_id: x ?? "",
                      ...(o
                        ? {
                            buyer_id: o.buyer_id ?? all.buyer_id,
                            style: o.style ?? all.style,
                            po_no: o.po_no ?? all.po_no,
                          }
                        : {}),
                    }));
                  }}
                  options={[{ value: "", label: f("order_ph") }, ...orders]}
                  placeholder={f("order_ph")}
                  aria-label={f("order")}
                />
              </Field>
              <Field label={f("style")} error={err("style")}>
                <Input
                  value={form.style}
                  onChange={(ev) => set("style")(ev.target.value)}
                  aria-label={f("style")}
                />
              </Field>
              <Field label={f("po")} error={err("po_no")}>
                <Input
                  value={form.po_no}
                  onChange={(ev) => set("po_no")(ev.target.value)}
                  aria-label={f("po")}
                />
              </Field>
            </>
          ) : null}
          <Field label={f("purpose")} required error={err("purpose_id")}>
            {pick("purpose_id", options.purposes, f("select"))}
          </Field>
          <Field
            label={f("destination")}
            required={isItem}
            error={err("location_id")}
            hint={options.locations.length ? undefined : f("no_locations")}
          >
            {pick("location_id", options.locations, f("select"))}
          </Field>
          {!isItem ? (
            <Field
              label={f("destination_text")}
              error={err("destination_text")}
            >
              <Input
                value={form.destination_text}
                onChange={(ev) => set("destination_text")(ev.target.value)}
                aria-label={f("destination_text")}
              />
            </Field>
          ) : null}
          {isItem && returnable ? (
            <Field
              label={f("expected_return")}
              required
              error={err("expected_return")}
            >
              <Input
                type="date"
                value={form.expected_return}
                min={form.gp_date}
                onChange={(ev) => set("expected_return")(ev.target.value)}
                aria-label={f("expected_return")}
              />
            </Field>
          ) : null}
          {[
            ["location_type", location?.type],
            ["address", location?.address],
            ["contact_person", location?.contact_person],
            ["contact_no", location?.mobile],
          ].map(([k, v]) => (
            <Field key={k} label={f(k)}>
              <Input value={v ?? ""} readOnly tabIndex={-1} aria-label={f(k)} />
            </Field>
          ))}
        </div>
      </Card>

      <Card title={sec("carrier")} tone="success">
        <div className="flex flex-col gap-4">
          {isItem ? (
            <Field label={f("carrier_type")} required className="max-w-xl">
              <Choice
                name="carrier_type"
                value={form.carrier_type}
                onChange={set("carrier_type")}
                className="flex-row gap-6"
                options={["EMPLOYEE", "NON_EMPLOYEE"].map((x) => ({
                  value: x,
                  label: g(`carrier.${x}`),
                }))}
              />
            </Field>
          ) : null}
          {carrier === "EMPLOYEE" ? (
            <div className="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
              <Field
                label={f("card_no")}
                required
                error={cardError || err("employee_id")}
                hint={f("card_hint")}
              >
                <div className="flex gap-2">
                  <Input
                    value={cardNo}
                    onChange={(ev) => {
                      setCardNo(ev.target.value);
                      setEmployee(null);
                    }}
                    onKeyDown={(ev) => {
                      if (ev.key === "Enter") {
                        ev.preventDefault();
                        findEmployee();
                      }
                    }}
                    placeholder={f("card_ph")}
                    aria-label={f("card_no")}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    onClick={findEmployee}
                    loading={finding}
                    aria-label={g("btn.search")}
                  >
                    <Search className="size-4" aria-hidden />
                  </Button>
                </div>
              </Field>
              {employee ? (
                <div className="flex items-start gap-3 rounded-lg border border-border bg-surface-muted p-3">
                  <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-brand">
                    <UserRound className="size-6" aria-hidden />
                  </span>
                  <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                    {[
                      ["name", employee.name],
                      ["employee_id", employee.employee_code],
                      ["department", employee.department],
                      ["designation", employee.designation],
                      ["mobile", employee.mobile],
                    ].map(([k, v]) => (
                      <div key={k} className="contents">
                        <dt className="text-foreground-muted">{f(k)}</dt>
                        <dd className="font-semibold text-foreground">
                          {dash(v)}
                        </dd>
                      </div>
                    ))}
                  </dl>
                </div>
              ) : null}
            </div>
          ) : (
            <div className="grid max-w-3xl gap-4 sm:grid-cols-2">
              {[
                ["carrier_name", "person_name", true],
                ["carrier_company", "company", false],
                ["carrier_mobile", "mobile", true],
                ["carrier_nid", "nid", false],
              ].map(([k, label, required]) => (
                <Field
                  key={k}
                  label={f(label)}
                  required={required}
                  error={err(k)}
                >
                  <Input
                    value={form[k]}
                    onChange={(ev) => set(k)(ev.target.value)}
                    placeholder={
                      k === "carrier_mobile" ? "01XXXXXXXXX" : undefined
                    }
                    aria-label={f(label)}
                  />
                </Field>
              ))}
            </div>
          )}
          {isItem ? (
            <div className="grid max-w-3xl gap-4 sm:grid-cols-2">
              <Field label={f("vehicle_no")} error={err("vehicle_no")}>
                <Input
                  value={form.vehicle_no}
                  onChange={(ev) => set("vehicle_no")(ev.target.value)}
                  aria-label={f("vehicle_no")}
                />
              </Field>
              <Field
                label={f("carrier_remarks")}
                error={err("carrier_remarks")}
              >
                <Input
                  value={form.carrier_remarks}
                  onChange={(ev) => set("carrier_remarks")(ev.target.value)}
                  aria-label={f("carrier_remarks")}
                />
              </Field>
            </div>
          ) : null}
        </div>
      </Card>

      {isItem ? (
        <Card title={sec("items")}>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className="w-12 px-3 py-2.5 text-left text-xs font-semibold uppercase">
                    {g("col.sl")}
                  </th>
                  {[
                    ["item", true],
                    ["unit", true],
                    ["quantity", true],
                    ["remarks", false],
                  ].map(([k, req]) => (
                    <th
                      key={k}
                      className="px-3 py-2.5 text-left text-xs font-semibold uppercase"
                    >
                      {f(k)}
                      {req ? <span className="text-danger"> *</span> : null}
                    </th>
                  ))}
                  <th className="px-3 py-2.5 text-right text-xs font-semibold uppercase">
                    {f("action")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {form.lines.map((l, i) => (
                  <tr key={i} className="border-t border-border align-top">
                    <td className="px-3 py-2.5">{i + 1}</td>
                    <td className="min-w-64 px-2 py-2">
                      <Select
                        value={l.item_id}
                        onValueChange={(x) => {
                          const it = options.items.find((o) => o.value === x);
                          setLine(i, {
                            item_id: x ?? "",
                            unit: it?.unit ?? l.unit,
                          });
                          setErrors((all) =>
                            dropError(all, `lines.${i}.item_id`),
                          );
                        }}
                        options={[
                          { value: "", label: f("select") },
                          ...options.items,
                        ]}
                        placeholder={f("select")}
                        aria-label={f("row_item", { n: i + 1 })}
                      />
                      {err(`lines.${i}.item_id`) ? (
                        <p className="mt-1 text-xs text-danger">
                          {err(`lines.${i}.item_id`)}
                        </p>
                      ) : options.items.length ? null : (
                        <p className="mt-1 text-xs text-foreground-muted">
                          {f("no_items")}
                        </p>
                      )}
                    </td>
                    <td className="w-36 px-2 py-2">
                      <Select
                        value={l.unit}
                        onValueChange={(x) => setLine(i, { unit: x ?? "" })}
                        options={[
                          { value: "", label: f("select") },
                          ...options.units.map((u) => ({
                            value: u,
                            label: unitName(u),
                          })),
                        ]}
                        placeholder={f("select")}
                        aria-label={f("row_unit", { n: i + 1 })}
                      />
                      {err(`lines.${i}.unit`) ? (
                        <p className="mt-1 text-xs text-danger">
                          {err(`lines.${i}.unit`)}
                        </p>
                      ) : null}
                    </td>
                    <td className="w-36 px-2 py-2">
                      <Input
                        type="number"
                        min="0"
                        step="any"
                        value={l.quantity}
                        onChange={(ev) => {
                          setLine(i, { quantity: ev.target.value });
                          setErrors((all) =>
                            dropError(all, `lines.${i}.quantity`),
                          );
                        }}
                        aria-label={f("row_qty", { n: i + 1 })}
                      />
                      {err(`lines.${i}.quantity`) ? (
                        <p className="mt-1 text-xs text-danger">
                          {err(`lines.${i}.quantity`)}
                        </p>
                      ) : null}
                    </td>
                    <td className="px-2 py-2">
                      <Input
                        value={l.remarks}
                        onChange={(ev) =>
                          setLine(i, { remarks: ev.target.value })
                        }
                        aria-label={f("row_remarks", { n: i + 1 })}
                      />
                    </td>
                    <td className="px-3 py-2 text-right">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={form.lines.length === 1}
                        onClick={() =>
                          setForm((all) => ({
                            ...all,
                            lines: all.lines.filter((_, j) => j !== i),
                          }))
                        }
                        aria-label={f("remove_row", { n: i + 1 })}
                        className="text-danger"
                      >
                        <Trash2 className="size-4" aria-hidden />
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {err("lines") ? (
            <p className="mt-2 text-xs text-danger">{err("lines")}</p>
          ) : null}
          <Button
            type="button"
            size="sm"
            className="mt-3"
            onClick={() =>
              setForm((all) => ({ ...all, lines: [...all.lines, blankLine()] }))
            }
          >
            <Plus className="size-4" aria-hidden />
            {g("btn.add_item")}
          </Button>
        </Card>
      ) : null}

      <Card title={sec("remarks")} tone="warning">
        <div className="grid gap-4 md:grid-cols-2">
          <Field label={f("remarks")} error={err("remarks")}>
            <Textarea
              rows={3}
              value={form.remarks}
              onChange={(ev) => set("remarks")(ev.target.value)}
              aria-label={f("remarks")}
            />
          </Field>
          <Field label={f("documents")} error={err("documents")}>
            <Textarea
              rows={3}
              value={form.documents}
              onChange={(ev) => set("documents")(ev.target.value)}
              placeholder={f("documents_ph")}
              aria-label={f("documents")}
            />
          </Field>
        </div>
      </Card>

      {missing.length ? (
        <p className="rounded-lg border border-warning/30 bg-warning-subtle px-4 py-2.5 text-sm text-warning">
          {g("still_needed", { list: missing.join(", ") })}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button variant="outline" className="text-danger" onClick={reset}>
          <CircleX className="size-4" aria-hidden />
          {g("btn.cancel")}
        </Button>
        <div className="flex flex-wrap gap-2">
          <Button
            variant="outline"
            loading={pending}
            onClick={() => save(false)}
          >
            <Save className="size-4" aria-hidden />
            {g("btn.draft")}
          </Button>
          <Button
            loading={pending}
            disabled={missing.length > 0}
            onClick={() => save(true)}
          >
            <Send className="size-4" aria-hidden />
            {g("btn.submit")}
          </Button>
          <Button variant="outline" onClick={preview}>
            <Printer className="size-4" aria-hidden />
            {g("btn.preview")}
          </Button>
        </div>
      </div>
    </div>
  );
}

export { CreateGatePass };
