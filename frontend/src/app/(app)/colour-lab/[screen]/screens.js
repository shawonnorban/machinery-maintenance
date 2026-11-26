import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { buttonVariants } from "@/components/ui/button";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import {
  CertificateForm,
  LabReports,
  TrialList,
  TrialEntry,
  FabricTestList,
  FabricTestEntry,
} from "@/components/colourlab/lab";
import * as actions from "./actions";

/*
 * Colour Lab → Operations (AnnotechERP 16/01): Test Certificate Directory,
 * Chemical Trial Workbench, Certificate Issue Register and In-House Fabric
 * Testing — each laid out as Brickland lays it out. Every screen returns its
 * tabs and its body.
 */

const options = () => apiFetch("/colour-lab/options");
const text = (v) => (typeof v === "string" && v.trim() ? v.trim() : null);
const findBy = (rows, key, value) =>
  value ? (rows.find((r) => r[key] === value) ?? null) : null;

/** Test Certificate Directory: Brickland's "Certification List", and its "Dyeing Lab Certification" form. */
const certificates = async ({ c, te, tab, can, searchParams = {} }) => {
  const [rows, look] = await Promise.all([
    apiFetch("/erp/lab-certificates"),
    options(),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: rows.length,
      },
      ...(can.manage || searchParams.ref
        ? [
            {
              key: "entry",
              label: searchParams.ref ? c("cert.edit_tab") : c("btn.add_new"),
              icon: searchParams.ref ? "Pencil" : "Plus",
            },
          ]
        : []),
    ],
    body: () =>
      tab === "entry" ? (
        <CertificateForm
          key={searchParams.ref ?? "new"}
          options={look}
          initial={findBy(rows, "document_ref", text(searchParams.ref))}
          canManage={can.manage}
          save={actions.saveCertificate}
          saveMaster={actions.saveMaster}
        />
      ) : (
        <div className="flex flex-col gap-4">
          <section className="flex items-center justify-between gap-3 rounded-xl border border-border bg-surface p-5">
            <h2 className="text-base font-semibold text-foreground">
              {c("cert.list_title")}
            </h2>
            {can.manage ? (
              <Link
                href="/colour-lab/certificate-list?tab=entry"
                className={buttonVariants()}
              >
                <Plus /> {c("btn.add_new")}
              </Link>
            ) : null}
          </section>
          <RecordsGrid
            exportName="lab-certificates"
            rows={rows}
            columns={[
              { key: "cert_type", label: c("col.cert_type") },
              { key: "document_ref", label: c("col.document_ref") },
              { key: "apply_date", label: c("col.apply_date"), type: "date" },
              { key: "issue_date", label: c("col.issue_date"), type: "date" },
              { key: "valid_date", label: c("col.expiry_date"), type: "date" },
              { key: "mail_to", label: c("col.mail_to") },
              { key: "mail_cc", label: c("col.mail_cc") },
              {
                key: "notify_days",
                label: c("col.notify_days"),
                type: "number",
              },
              { key: "applied_by_name", label: c("col.applied_by") },
              { key: "active", label: c("col.active"), type: "yesno" },
            ]}
            edit={{
              href: "/colour-lab/certificate-list?tab=entry&ref=",
              refKey: "document_ref",
            }}
            remove={
              can.manage
                ? actions.deleteResource.bind(null, "lab-certificates")
                : undefined
            }
            deleteLabels={{
              title: c("ask.cert_delete"),
              confirm: c("btn.delete"),
              aria: c("btn.delete"),
              nameKey: "document_ref",
            }}
            emptyState={{ title: c("cert.empty"), text: c("cert.empty_hint") }}
          />
        </div>
      ),
  };
};

/** Certificate Issue Register: Records (Action, Entry Date from–to) and New register entry. */
const register = async ({ c, te, tab, can, searchParams = {} }) => {
  const [all, look] = await Promise.all([
    apiFetch("/erp/lab-cert-register"),
    options(),
  ]);
  const actionOptions = look.actions.map((a) => ({
    value: a,
    label: c(`action.${a}`),
  }));
  const rows = all
    .filter((r) => !searchParams.action || r.action === searchParams.action)
    .filter((r) => !searchParams.from || r.entry_date >= searchParams.from)
    .filter((r) => !searchParams.to || r.entry_date <= searchParams.to)
    .map((r) => ({
      ...r,
      action_label: c(`action.${r.action}`),
      buyer_id: r.buyer_id ?? "",
      handed_by: r.handed_by ?? "",
      copies: r.copies === null ? null : String(r.copies),
    }));
  const none = look.certificates.length === 0;
  const fields = [
    {
      name: "certificate_id",
      label: c("f.certificate"),
      type: "select",
      required: true,
      options: look.certificates,
      help: none ? c("no_choices") : undefined,
      readOnly: none,
    },
    {
      name: "entry_date",
      label: c("f.entry_date"),
      type: "date",
      required: true,
    },
    {
      name: "action",
      label: c("f.action"),
      type: "select",
      default: "ISSUED",
      options: actionOptions,
    },
    {
      name: "issued_to",
      label: c("f.issued_to"),
      required: true,
      placeholder: "SGS Bangladesh — audit team",
    },
    {
      name: "buyer_id",
      label: c("f.buyer"),
      type: "select",
      options: [{ value: "", label: "—" }, ...look.buyers],
    },
    { name: "copies", label: c("f.copies"), type: "number", default: "1" },
    {
      name: "due_date",
      label: c("f.due_date"),
      type: "date",
      help: c("reg.due_hint"),
    },
    { name: "return_date", label: c("f.return_date"), type: "date" },
    {
      name: "handed_by",
      label: c("f.handed_by"),
      type: "select",
      options: [{ value: "", label: "—" }, ...look.users],
    },
    { name: "remarks", label: c("f.remarks"), type: "textarea" },
  ];
  return {
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: all.length,
      },
      ...(can.manage
        ? [{ key: "new", label: c("reg.new"), icon: "Plus" }]
        : []),
    ],
    body: () =>
      tab === "new" && can.manage ? (
        <EntryForm
          fields={fields}
          action={actions.createResource.bind(null, "lab-cert-register", [])}
          title={c("reg.new")}
          description={c("reg.hint")}
          submitLabel={c("reg.save")}
          columns={3}
          successHref="/colour-lab/certificate-register?tab=records"
        />
      ) : (
        <div className="flex flex-col">
          <FilterBar
            basePath="/colour-lab/certificate-register"
            keep={{ tab: "records" }}
            values={searchParams}
            fields={[
              {
                name: "action",
                label: c("f.action"),
                type: "select",
                options: actionOptions,
                placeholder: c("ph.action"),
              },
              { name: "from", label: c("f.entry_from"), type: "date" },
              { name: "to", label: c("f.entry_to"), type: "date" },
            ]}
            labels={{ show: c("btn.search"), reset: c("btn.clear") }}
            instant
          />
          <RecordsGrid
            exportName="certificate-register"
            rows={rows}
            columns={[
              { key: "register_no", label: c("col.register_no") },
              { key: "certificate", label: c("col.certificate") },
              { key: "entry_date", label: c("col.entry_date"), type: "date" },
              { key: "action_label", label: c("col.action") },
              { key: "issued_to", label: c("col.issued_to") },
              { key: "buyer", label: c("col.buyer") },
              { key: "copies", label: c("col.copies"), type: "number" },
              { key: "due_date", label: c("col.due_date"), type: "date" },
              { key: "return_date", label: c("col.return_date"), type: "date" },
              { key: "handed_by_name", label: c("col.handed_by") },
            ]}
            edit={
              can.manage
                ? {
                    fields,
                    action: actions.updateResource.bind(
                      null,
                      "lab-cert-register",
                      [],
                    ),
                    title: c("reg.edit"),
                    submitLabel: c("reg.save"),
                    columns: 3,
                  }
                : undefined
            }
            remove={
              can.manage
                ? actions.deleteResource.bind(null, "lab-cert-register")
                : undefined
            }
            emptyState={{ title: c("reg.empty"), text: c("reg.empty_hint") }}
          />
        </div>
      ),
  };
};

/** Chemical Trial Workbench: Brickland's "Trial Operation List" and "Trial Operation". */
const trials = async ({ c, tab, can, searchParams = {} }) => {
  const [look, rows] = await Promise.all([
    options(),
    apiFetch("/colour-lab/trials"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: c("trial.list_tab"),
        icon: "Table2",
        count: rows.length,
      },
      ...(can.manage || searchParams.ref
        ? [
            {
              key: "entry",
              label: searchParams.ref
                ? c("trial.edit_tab")
                : c("btn.new_trial"),
              icon: searchParams.ref ? "Pencil" : "Plus",
            },
          ]
        : []),
    ],
    body: async () => {
      if (tab === "entry") {
        const ref = text(searchParams.ref);
        const initial = ref
          ? await apiFetch(
              `/colour-lab/trials/${encodeURIComponent(ref)}`,
            ).catch(() => null)
          : null;
        return (
          <TrialEntry
            key={initial?.trial_no ?? "new"}
            options={look}
            initial={initial}
            canManage={can.manage}
            act={actions.labAction}
          />
        );
      }
      return (
        <TrialList
          options={look}
          initialRows={rows}
          canManage={can.manage}
          act={actions.labAction}
        />
      );
    },
  };
};

/** In-House Fabric Testing: Brickland's "Dyeing Lab Fabric Test List", and the test sheet. */
const fabricTests = async ({ c, tab, can, searchParams = {} }) => {
  const [look, rows] = await Promise.all([
    options(),
    apiFetch("/colour-lab/fabric-tests"),
  ]);
  return {
    tabs: [
      {
        key: "records",
        label: c("test.list_tab"),
        icon: "Table2",
        count: rows.length,
      },
      ...(can.manage || searchParams.ref
        ? [
            {
              key: "entry",
              label: searchParams.ref ? c("test.edit_tab") : c("btn.add_new"),
              icon: searchParams.ref ? "Pencil" : "Plus",
            },
          ]
        : []),
    ],
    body: async () => {
      if (tab === "entry") {
        const ref = text(searchParams.ref);
        const initial = ref
          ? await apiFetch(
              `/colour-lab/fabric-tests/${encodeURIComponent(ref)}`,
            ).catch(() => null)
          : null;
        return (
          <FabricTestEntry
            key={initial?.test_no ?? "new"}
            options={look}
            initial={initial}
            canManage={can.manage}
            act={actions.labAction}
          />
        );
      }
      return (
        <FabricTestList
          initialRows={rows}
          canManage={can.manage}
          canApprove={can.approve}
          act={actions.labAction}
        />
      );
    },
  };
};

/** Lab Test Reports: Brickland's "Dyeing Lab Reports" — one of the lab's reports picked and previewed. */
const REPORTS = [
  ["fabric-test-register", "fabric_test"],
  ["dia-gsm-variance", "fabric_test"],
  ["colour-fastness", "fabric_test"],
  ["failed-conditional", "fabric_test"],
  ["buyer-pass-rate", "fabric_test"],
  ["trial-register", "chemical_trial"],
  ["trial-recipe", "chemical_trial"],
  ["chemical-usage", "chemical_trial"],
  ["trials-awaiting", "chemical_trial"],
  ["certification-register", "certification"],
  ["renewal-due", "certification"],
  ["certificate-movement", "certification"],
  ["lab-master", "master_data"],
].map(([key, group]) => ({ key, group }));

const reports = async ({ searchParams = {} }) => {
  const key = REPORTS.some((r) => r.key === searchParams.report)
    ? searchParams.report
    : null;
  const initial = key
    ? await apiFetch(`/colour-lab/reports/${key}`).catch(() => null)
    : null;
  return {
    tabs: [],
    body: () => (
      <LabReports reports={REPORTS} initial={initial} act={actions.labAction} />
    ),
  };
};

const SCREENS = {
  "certificate-list": certificates,
  "chemical-trial-lab": trials,
  "certificate-register": register,
  "inh-fab-test": fabricTests,
  "dyeing-lab-reports": reports,
};

export { SCREENS };
