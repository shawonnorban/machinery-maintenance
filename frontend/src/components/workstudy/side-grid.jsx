"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, ListChecks, Plus, RotateCcw, Save, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { Modal } from "@/components/ui/modal";
import { EntryForm } from "@/components/erp/entry-form";
import { useT } from "@/lib/i18n";
import { EditGridTable, exportCsv, keyed, unkeyed } from "./edit-grid";

/**
 * Work Study → Responsible Owner Mapping and Resource Capability Profile's
 * Product Criticality-Line grid (AnnotechERP 14/01): a list on the left with
 * how many lines each holds; pick one and its grid loads beside it — nothing
 * is read until you do — then edit and Save the whole grid. `blank` given,
 * lines can be added and removed; without it the grid's lines are fixed.
 * `ns` is the lang namespace its words and `footerKey` come from (it needs
 * master.grid.*, master.save, master.saved, master.loading, master.search).
 * `picker` adds Brickland's "Search By" card (a select and Load) above both
 * panels; `selectedKey` makes the footer count the ticked lines.
 * `newForm` puts a New button on the right panel that opens a dialog filing a
 * record under the picked item (Employment Type's "New — Staff"): `{title,
 * description, fields, action, submitLabel, parentField}`, the picked key
 * going in as `parentField`; the grid reloads after it saves.
 *
 * @param {{ listTitle: string, items: {key: string, label: string, count: number}[], listAction?: React.ReactNode, title: string, hint: string, emptyTitle: string, empty: string, gridEmpty: {title: string, text: string}, footerKey: string, columns: object[], blank?: object, load: Function, save: Function, canManage: boolean, exportName: string }} props
 */
function SideGrid({ listTitle, items, listAction = null, title, hint, emptyTitle, empty, gridEmpty, footerKey, columns, blank, load, save, canManage, exportName, picker, selectedKey, addLabel, newForm, ns = "workstudy" }) {
  const t = useT(ns);
  const router = useRouter();
  const toastManager = useToastManager();
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(null);
  const [loaded, setLoaded] = useState([]);
  const [rows, setRows] = useState([]);
  const [dirty, setDirty] = useState(false);
  const [loading, setLoading] = useState(false);
  const [pending, startTransition] = useTransition();
  const shown = useMemo(() => items.filter((i) => i.label.toLowerCase().includes(query.trim().toLowerCase())), [items, query]);
  // A tree list indents with leading dashes; the title drops them.
  const pickedLabel = items.find((i) => i.key === picked)?.label?.replace(/^(— )+/, "");
  const [choice, setChoice] = useState("");
  const [adding, setAdding] = useState(false);

  const change = (next) => {
    setRows(next);
    setDirty(true);
  };

  function pick(key) {
    setPicked(key);
    setRows([]);
    setDirty(false);
    setLoading(true);
    // Nothing can be added until the lines are in, or the load would overwrite it.
    load(key)
      .then((result) => {
        if (result?.status === "success") {
          setLoaded(result.rows);
          setRows(keyed(result.rows));
        }
      })
      .finally(() => setLoading(false));
  }

  function submit() {
    startTransition(async () => {
      const result = await save(picked, unkeyed(rows));
      if (result?.status === "success") {
        setLoaded(result.rows);
        setRows(keyed(result.rows));
        setDirty(false);
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  const addButton = blank ? (
    <Button variant="outline" onClick={() => change([...rows, ...keyed([blank])])}>
      <Plus /> {addLabel ?? t("master.grid.add")}
    </Button>
  ) : null;

  const grid = (
    <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
      <div className="self-start rounded-lg border border-border bg-surface">
        <div className="flex items-center gap-2 px-4 pt-4 pb-3">
          <span className="truncate text-[15px] font-semibold">{listTitle}</span>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold">{items.length}</span>
          <span className="ml-auto">{canManage ? listAction : null}</span>
        </div>
        <div className="relative px-4 pb-3">
          <Search className="pointer-events-none absolute top-[18px] left-7 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("master.search")} className="pl-9" />
        </div>
        <ul className="border-t border-border">
          {shown.map((i) => (
            <li key={i.key}>
              <button type="button" onClick={() => pick(i.key)} className={cn("flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-surface-muted", picked === i.key && "bg-brand-subtle font-semibold text-brand")}>
                <span className="min-w-0">
                  <span className="block truncate">{i.label}</span>
                  {i.hint ? <span className="block text-xs font-normal text-foreground-muted">{i.hint}</span> : null}
                </span>
                <span className="tabular-nums text-foreground-muted">{i.count}</span>
              </button>
            </li>
          ))}
        </ul>
      </div>

      <div className="min-w-0 self-start rounded-lg border border-border bg-surface">
        <div className="flex items-start justify-between gap-3 px-5 pt-4 pb-3">
          <div className="flex min-w-0 gap-3">
            <ListChecks className="mt-0.5 size-5 shrink-0 text-foreground-muted" />
            <div className="min-w-0">
              <h2 className="text-base font-semibold">{pickedLabel ? `${title} — ${pickedLabel}` : title}</h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
            </div>
          </div>
          {picked && !loading ? (
            <div className="flex shrink-0 items-center gap-2">
              {dirty ? <span className="rounded-sm bg-warning-subtle px-2 py-0.5 text-xs font-semibold text-warning">{t("master.grid.unsaved")}</span> : null}
              <Button variant="outline" onClick={() => exportCsv(`${exportName}-${pickedLabel}`, columns, rows)}>
                <Download /> {t("master.grid.export")}
              </Button>
              {canManage ? addButton : null}
              {canManage && newForm ? (
                <Button variant="outline" onClick={() => setAdding(true)}>
                  <Plus /> {newForm.buttonLabel}
                </Button>
              ) : null}
            </div>
          ) : null}
        </div>
        {!picked ? (
          <div className="flex flex-col items-center gap-2 px-5 py-14 text-center">
            <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
              <ListChecks className="size-5" />
            </span>
            <p className="text-sm font-semibold">{emptyTitle}</p>
            <p className="max-w-md text-[13px] text-foreground-muted">{empty}</p>
          </div>
        ) : loading ? (
          <p className="border-t border-border px-5 py-10 text-center text-sm text-foreground-muted">{t("master.loading")}</p>
        ) : (
          <>
            <EditGridTable columns={columns} rows={rows} set={(k, f, v) => change(rows.map((r) => (r._k === k ? { ...r, [f]: v } : r)))} remove={(k) => change(rows.filter((r) => r._k !== k))} canManage={canManage} empty={gridEmpty} removeLabel={t("master.grid.remove")} />
            <div className="flex flex-wrap items-center gap-3 border-t border-border p-4">
              {canManage ? addButton : null}
              <p className="mr-auto text-[13px] text-foreground-muted">{t(footerKey, { count: rows.length, selected: selectedKey ? rows.filter((r) => r[selectedKey]).length : rows.length })}</p>
              {canManage ? (
                <>
                  <Button
                    variant="outline"
                    onClick={() => {
                      setRows(keyed(loaded));
                      setDirty(false);
                    }}
                    disabled={pending || !dirty}
                  >
                    <RotateCcw /> {t("master.grid.revert")}
                  </Button>
                  <Button onClick={submit} loading={pending} disabled={!dirty}>
                    <Save /> {t("master.save")}
                  </Button>
                </>
              ) : null}
            </div>
          </>
        )}
      </div>
    </div>
  );

  const dialog = newForm ? (
    <Modal open={adding} onOpenChange={setAdding} title={`${newForm.title} — ${pickedLabel ?? ""}`} className="max-w-2xl">
      {adding ? (
        <EntryForm
          key={picked}
          fields={[{ name: newForm.parentField, type: "hidden", default: picked }, ...newForm.fields]}
          action={newForm.action}
          title={newForm.formTitle}
          description={newForm.description}
          submitLabel={newForm.submitLabel}
          columns={3}
          dialog
          onDone={() => {
            setAdding(false);
            pick(picked);
          }}
        />
      ) : null}
    </Modal>
  ) : null;

  if (!picker) return dialog ? (
    <>
      {grid}
      {dialog}
    </>
  ) : grid;
  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-lg border border-border bg-surface px-5 py-4">
        <h2 className="text-[13px] font-semibold">{picker.title}</h2>
        <div className="mt-2 flex flex-wrap items-end gap-3">
          <label className="flex min-w-72 flex-1 flex-col gap-1.5 sm:max-w-md">
            <span className="text-[13px] font-semibold">{picker.label}</span>
            <Select value={choice || picked || ""} onValueChange={(v) => setChoice(v ?? "")} options={items.map((i) => ({ value: i.key, label: i.label }))} placeholder={picker.placeholder} />
          </label>
          <Button variant="outline" onClick={() => choice && pick(choice)} disabled={!choice}>
            <ListChecks /> {picker.load}
          </Button>
        </div>
      </div>
      {grid}
    </div>
  );
}

export { SideGrid };
