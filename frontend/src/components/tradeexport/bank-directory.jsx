"use client";

import { Fragment, useState } from "react";
import { ChevronDown, ChevronRight, Pencil, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { EntryForm } from "@/components/erp/entry-form";
import { useT } from "@/lib/i18n";

/**
 * Trade & Export → Bank Directory (AnnotechERP 22/01): Brickland's Bank List —
 * each bank with ▸ to show its branches, Edit Bank and + New Branch, both in
 * a dialog; a branch is edited from its own row.
 *
 * `filters` (the Searching Data box) sits under + Add New, as Brickland lays it out.
 *
 * @param {{ banks: object[], countries: {value: string, label: string}[], create: Function, update: Function, canManage: boolean, filters?: React.ReactNode }} props
 */
function BankDirectory({ banks, countries, create, update, canManage, filters = null }) {
  const t = useT("tradeexport");
  const m = (k, p) => t(`master.${k}`, p);
  const [open, setOpen] = useState(() => new Set());
  const [dialog, setDialog] = useState(null);

  const bankFields = (bank) => [
    { name: "code", label: m("f.bank_code"), required: true, default: bank?.code },
    { name: "name", label: m("f.bank_name"), required: true, default: bank?.name },
    { name: "name_bn", label: m("f.bank_name_bn"), default: bank?.name_bn },
    { name: "prefix", label: m("f.prefix"), required: true, default: bank?.prefix },
    { name: "is_local", label: m("f.is_local"), type: "checkbox", default: bank ? bank.is_local : true },
    { name: "country_id", label: m("f.country"), type: "select", required: true, options: countries, default: bank?.country_id, placeholder: m("select_country") },
    { name: "swift_code", label: m("f.swift"), required: true, default: bank?.swift_code },
    { name: "iban", label: m("f.iban"), default: bank?.iban },
    { name: "call_center", label: m("f.call_center"), default: bank?.call_center },
    { name: "web_url", label: m("f.web_url"), default: bank?.web_url },
    { name: "active", label: m("f.is_active_short"), type: "checkbox", default: bank ? bank.active : true },
  ];
  const branchFields = (bankId, branch) => [
    ...(branch ? [] : [{ name: "bank_id", type: "hidden", default: bankId }]),
    { name: "code", label: m("f.branch_code"), required: true, default: branch?.code },
    { name: "name", label: m("f.branch_name"), required: true, default: branch?.name },
    { name: "address", label: m("f.address"), default: branch?.address },
    { name: "swift_code", label: m("f.swift"), default: branch?.swift_code },
    { name: "routing_no", label: m("f.routing_no"), default: branch?.routing_no },
    { name: "contact_person", label: m("f.contact_person"), default: branch?.contact_person },
    { name: "phone", label: m("f.phone"), default: branch?.phone },
    { name: "email", label: m("f.email"), default: branch?.email },
    { name: "active", label: m("f.is_active_short"), type: "checkbox", default: branch ? branch.active : true },
  ];
  const toggle = (id) => setOpen((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });

  return (
    <div className="flex flex-col gap-3">
      {canManage ? (
        <div>
          <Button onClick={() => setDialog({ title: m("bank.new"), fields: bankFields(null), action: create.bind(null, "tx-banks", ["is_local", "active"]) })}>
            <Plus /> {m("bank.add")}
          </Button>
        </div>
      ) : null}
      {filters ? <div key="filters">{filters}</div> : null}
      <div className="rounded-lg border border-border bg-surface">
        <div className="px-5 pt-4 pb-3 text-[15px] font-semibold">{m("bank.list")}</div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                <th className="w-10 px-3 py-2.5" />
                {canManage ? <th className="px-3 py-2.5 font-semibold">{m("f.action")}</th> : null}
                <th className="px-3 py-2.5 font-semibold">{m("f.bank_code_col")}</th>
                <th className="px-3 py-2.5 font-semibold">{m("f.bank_name")}</th>
                <th className="px-3 py-2.5 font-semibold">{m("f.swift")}</th>
                <th className="px-3 py-2.5 font-semibold">{m("f.active")}</th>
              </tr>
            </thead>
            <tbody>
              {banks.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-foreground-muted">{m("bank.none")}</td>
                </tr>
              ) : null}
              {banks.map((bank) => (
                <Fragment key={bank.id}>
                  <tr className="border-b border-border">
                    <td className="px-3 py-1.5">
                      <Button variant="ghost" size="sm" onClick={() => toggle(bank.id)} aria-label={m("bank.branches")}>
                        {open.has(bank.id) ? <ChevronDown /> : <ChevronRight />}
                      </Button>
                    </td>
                    {canManage ? (
                      <td className="px-3 py-1.5">
                        <div className="flex gap-2">
                          <Button variant="outline" size="sm" onClick={() => setDialog({ title: m("bank.edit"), fields: bankFields(bank), action: update.bind(null, "tx-banks", ["is_local", "active"], bank.id) })}>
                            <Pencil /> {m("bank.edit")}
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => setDialog({ title: m("bank.branch_entry", { bank: bank.name }), fields: branchFields(bank.id, null), action: create.bind(null, "tx-bank-branches", ["active"]) })}>
                            <Plus /> {m("bank.new_branch")}
                          </Button>
                        </div>
                      </td>
                    ) : null}
                    <td className="px-3 py-2 font-mono text-[13px]">{bank.code}</td>
                    <td className="px-3 py-2">{bank.name}</td>
                    <td className="px-3 py-2 font-mono text-[13px]">{bank.swift_code}</td>
                    <td className="px-3 py-2">{bank.active ? "Y" : "N"}</td>
                  </tr>
                  {open.has(bank.id) ? (
                    <tr className="border-b border-border bg-surface-muted/50">
                      <td />
                      <td colSpan={canManage ? 5 : 4} className="px-3 py-2">
                        {bank.branches.length === 0 ? (
                          <p className="py-2 text-[13px] text-foreground-muted">{m("bank.no_branches")}</p>
                        ) : (
                          <table className="w-full text-[13px]">
                            <thead>
                              <tr className="text-left text-foreground-muted">
                                {["branch_code", "branch_name", "address", "swift", "routing_no", "contact_person", "phone", "email", "active"].map((k) => (
                                  <th key={k} className="px-2 py-1.5 font-semibold">{m(`f.${k}`)}</th>
                                ))}
                                {canManage ? <th /> : null}
                              </tr>
                            </thead>
                            <tbody>
                              {bank.branches.map((br) => (
                                <tr key={br.id} className="border-t border-border">
                                  <td className="px-2 py-1.5 font-mono">{br.code}</td>
                                  <td className="px-2 py-1.5">{br.name}</td>
                                  <td className="px-2 py-1.5">{br.address}</td>
                                  <td className="px-2 py-1.5 font-mono">{br.swift_code}</td>
                                  <td className="px-2 py-1.5 font-mono">{br.routing_no}</td>
                                  <td className="px-2 py-1.5">{br.contact_person}</td>
                                  <td className="px-2 py-1.5">{br.phone}</td>
                                  <td className="px-2 py-1.5">{br.email}</td>
                                  <td className="px-2 py-1.5">{br.active ? "Y" : "N"}</td>
                                  {canManage ? (
                                    <td className="px-2 py-1.5 text-right">
                                      <Button variant="ghost" size="sm" onClick={() => setDialog({ title: m("bank.branch_entry", { bank: bank.name }), fields: branchFields(bank.id, br), action: update.bind(null, "tx-bank-branches", ["active"], br.id) })} aria-label={m("bank.edit_branch")}>
                                        <Pencil />
                                      </Button>
                                    </td>
                                  ) : null}
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        )}
                      </td>
                    </tr>
                  ) : null}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      {dialog ? (
        <Modal open onOpenChange={(o) => !o && setDialog(null)} title={dialog.title} className="max-w-2xl">
          <EntryForm dialog fields={dialog.fields} action={dialog.action} submitLabel={m("save")} onDone={() => setDialog(null)} />
        </Modal>
      ) : null}
    </div>
  );
}

export { BankDirectory };
