"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Eraser, Plus, Save, Trash2 } from "lucide-react";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const TYPES = ["TEXT", "NUMBER", "SELECT", "YESNO", "DATE"];
const blankRule = () => ({ item_category: "", item_name: "", buyer_id: "" });
const blankControl = () => ({ name: "", control_type: "TEXT", options: "", required: false });

function fromTemplate(row) {
  return row
    ? {
        id: row.id,
        code: row.code,
        name: row.name,
        active: row.active,
        item_rules: row.item_rules.length ? row.item_rules.map((r) => ({ item_category: r.item_category ?? "", item_name: r.item_name ?? "", buyer_id: r.buyer_id ?? "" })) : [blankRule()],
        controls: row.controls.length ? row.controls.map((c) => ({ name: c.name, control_type: c.control_type, options: c.options ?? "", required: c.required })) : [blankControl()],
      }
    : { id: null, code: "", name: "", active: true, item_rules: [blankRule()], controls: [blankControl()] };
}

/**
 * Trims & Accessories Spec Setup → Spec. Configuration (AnnotechERP 06/01):
 * a spec template, where it applies (item category, item and buyer) and the
 * columns its specification sheet carries. Pick an existing template to edit
 * it, or start blank.
 *
 * @param {{ templates: object[], buyers: {value: string, label: string}[], save: Function, canManage: boolean }} props
 */
function TrimSpecEditor({ templates, buyers, save, canManage }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const [form, setForm] = useState(() => fromTemplate(null));
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const types = TYPES.map((v) => ({ value: v, label: t(`master.opt.control_type.${v}`) }));

  const setLine = (list, i, field, value) => setForm((f) => ({ ...f, [list]: f[list].map((x, j) => (j === i ? { ...x, [field]: value } : x)) }));
  const addLine = (list, blank) => setForm((f) => ({ ...f, [list]: [...f[list], blank()] }));
  const dropLine = (list, i) => setForm((f) => ({ ...f, [list]: f[list].filter((_, j) => j !== i) }));

  function submit() {
    startTransition(async () => {
      const payload = {
        code: form.code,
        name: form.name,
        active: form.active,
        item_rules: form.item_rules.filter((r) => r.item_category || r.item_name || r.buyer_id).map((r) => ({ item_category: r.item_category || null, item_name: r.item_name || null, buyer_id: r.buyer_id || null })),
        controls: form.controls.filter((c) => c.name.trim()).map((c) => ({ name: c.name.trim(), control_type: c.control_type, options: c.options || null, required: c.required })),
      };
      const result = await save(form.id, payload);
      if (result?.status === "success") {
        setErrors({});
        setForm(fromTemplate(result.row));
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ?? {});
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  const err = (key) => (errors[key] ? <span className="text-xs text-danger">{errors[key][0]}</span> : null);

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("master.spec.title")}</CardTitle>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{t("master.spec.hint")}</p>
      </CardHeader>
      <CardBody className="flex flex-col gap-5">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{t("master.spec.existing")}</span>
            <Select
              value={form.id ?? ""}
              onValueChange={(v) => setForm(fromTemplate(templates.find((x) => x.id === v) ?? null))}
              options={[{ value: "", label: t("master.spec.new_blank") }, ...templates.map((x) => ({ value: x.id, label: `${x.code} — ${x.name}` }))]}
            />
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">
              {t("master.f.template_code")}
              <span className="ml-0.5 text-danger">*</span>
            </span>
            <Input value={form.code} onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} placeholder="SPEC-LBL" disabled={!canManage} />
            {err("code")}
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">
              {t("master.f.template_name")}
              <span className="ml-0.5 text-danger">*</span>
            </span>
            <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="STOCKMANN - Care Label" disabled={!canManage} />
            {err("name")}
          </label>
          <label className="flex items-center gap-2 self-end pb-2 text-sm">
            <Checkbox checked={form.active} onCheckedChange={(c) => setForm((f) => ({ ...f, active: c === true }))} disabled={!canManage} /> {t("master.f.active")}
          </label>
        </div>

        <section className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold">{t("master.spec.rules")}</h3>
            {canManage ? (
              <Button variant="outline" size="sm" onClick={() => addLine("item_rules", blankRule)}>
                <Plus /> {t("master.spec.add_rule")}
              </Button>
            ) : null}
          </div>
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-primary text-left text-primary-foreground">
                  <th className="px-3 py-2 font-semibold">{t("master.f.category_plain")}</th>
                  <th className="px-3 py-2 font-semibold">{t("master.f.item_name")}</th>
                  <th className="px-3 py-2 font-semibold">{t("master.f.buyer")}</th>
                  <th className="w-10 px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {form.item_rules.map((r, i) => (
                  <tr key={i} className="border-b border-border last:border-0">
                    <td className="px-3 py-1.5">
                      <Input value={r.item_category} onChange={(e) => setLine("item_rules", i, "item_category", e.target.value)} placeholder="Label" disabled={!canManage} />
                    </td>
                    <td className="px-3 py-1.5">
                      <Input value={r.item_name} onChange={(e) => setLine("item_rules", i, "item_name", e.target.value)} placeholder="Care Label" disabled={!canManage} />
                    </td>
                    <td className="px-3 py-1.5">
                      <div className="w-56">
                        <Select value={r.buyer_id} onValueChange={(v) => setLine("item_rules", i, "buyer_id", v ?? "")} options={[{ value: "", label: t("master.spec.any_buyer") }, ...buyers]} disabled={!canManage} />
                      </div>
                    </td>
                    <td className="px-3 py-1.5">
                      {canManage && form.item_rules.length > 1 ? (
                        <Button variant="ghost" size="sm" onClick={() => dropLine("item_rules", i)} aria-label={t("master.remove")}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold">{t("master.spec.controls")}</h3>
            {canManage ? (
              <Button variant="outline" size="sm" onClick={() => addLine("controls", blankControl)}>
                <Plus /> {t("master.spec.add_control")}
              </Button>
            ) : null}
          </div>
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-primary text-left text-primary-foreground">
                  <th className="px-3 py-2 font-semibold">{t("master.f.control_name")}</th>
                  <th className="px-3 py-2 font-semibold">{t("master.f.control_type")}</th>
                  <th className="px-3 py-2 font-semibold">{t("master.f.control_options")}</th>
                  <th className="px-3 py-2 text-center font-semibold">{t("master.f.required")}</th>
                  <th className="w-10 px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {form.controls.map((c, i) => (
                  <tr key={i} className="border-b border-border last:border-0">
                    <td className="px-3 py-1.5">
                      <Input value={c.name} onChange={(e) => setLine("controls", i, "name", e.target.value)} placeholder="Fibre content" disabled={!canManage} />
                      {err(`controls.${i}.name`)}
                    </td>
                    <td className="px-3 py-1.5">
                      <div className="w-40">
                        <Select value={c.control_type} onValueChange={(v) => v && setLine("controls", i, "control_type", v)} options={types} disabled={!canManage} />
                      </div>
                    </td>
                    <td className="px-3 py-1.5">
                      <Input value={c.options} onChange={(e) => setLine("controls", i, "options", e.target.value)} placeholder={t("master.spec.options_hint")} disabled={!canManage || c.control_type !== "SELECT"} />
                      {err(`controls.${i}.options`)}
                    </td>
                    <td className="px-3 py-1.5 text-center">
                      <Checkbox checked={c.required} onCheckedChange={(v) => setLine("controls", i, "required", v === true)} disabled={!canManage} aria-label={t("master.f.required")} />
                    </td>
                    <td className="px-3 py-1.5">
                      {canManage && form.controls.length > 1 ? (
                        <Button variant="ghost" size="sm" onClick={() => dropLine("controls", i)} aria-label={t("master.remove")}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {canManage ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setForm(fromTemplate(null))} disabled={pending}>
              <Eraser /> {t("master.spec.clear")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {t("master.spec.save")}
            </Button>
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}

export { TrimSpecEditor };
