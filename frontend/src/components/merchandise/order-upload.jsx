"use client";

import { useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CheckCheck, FileSpreadsheet, RotateCcw, Upload } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (json) => {
  const fields = [...new Set(Object.values(json?.errors ?? {}).flat())];
  return fields.length ? fields : [json?.message ?? ""];
};
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");
const qtyFmt = new Intl.NumberFormat("en-US");
const money = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 });
const card = "rounded-xl border border-border bg-surface";

/**
 * Style & Order Upload → Upload (AnnotechERP 06/01), as Brickland lays it
 * out: the file type, Attach File, Verify File Data (every row checked
 * against the register and staged below with its status and, if rejected,
 * why) and Upload — enabled once the staged file has no rejected row, since
 * one rejected row blocks the whole batch. The file goes to the server
 * through route handlers (a spreadsheet can pass a server action's limit).
 *
 * @param {{ canManage: boolean }} props
 */
function OrderUpload({ canManage }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.oup.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const fileRef = useRef(null);
  const [file, setFile] = useState(null);
  const [staged, setStaged] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();

  async function send(path) {
    const body = new FormData();
    body.append("file", file);
    body.append("file_type", "STYLE_AND_ORDER_DETAIL");
    const response = await fetch(path, { method: "POST", body });
    return { ok: response.ok, json: await response.json().catch(() => null) };
  }

  function verify() {
    if (!file) return setErrors([m("choose_file")]);
    startTransition(async () => {
      const { ok, json } = await send("/api/merchandise/order-upload/verify");
      if (!ok) return setErrors(messagesOf(json)), setStaged(null);
      setStaged(json.data);
      setErrors(null);
      toastManager.add({ title: m("verified", { ok: json.data.accepted, total: json.data.rows.length }), type: json.data.rejected ? "warning" : "success" });
    });
  }

  function upload() {
    startTransition(async () => {
      const { ok, json } = await send("/api/merchandise/order-upload");
      if (!ok) return setErrors(messagesOf(json));
      toastManager.add({ title: m("uploaded", { no: json.data.batch_no, orders: json.data.orders, styles: json.data.styles, rows: json.data.rows }), type: "success" });
      reset();
      router.refresh();
    });
  }

  function reset() {
    setFile(null);
    setStaged(null);
    setErrors(null);
    if (fileRef.current) fileRef.current.value = "";
  }

  const cols = ["row", "buyerac", "buyer", "style", "job", "order", "confirmdt", "shipdt", "shipcountry", "item", "gmtcolor", "size", "qty", "rate", "total", "status", "reason"];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <FileSpreadsheet className="size-4" /> {m("upload_tab")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{m("upload_hint")}</p>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold text-foreground">{m("file_type")}</legend>
          <label className="flex items-center gap-2 text-sm text-foreground">
            <input type="radio" name="style-order-upload-file-type" value="STYLE_AND_ORDER_DETAIL" checked readOnly className="accent-brand" />
            {m("type_detail")}
          </label>
        </fieldset>
        <div className="mt-4 flex flex-wrap items-end gap-3">
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">{m("attach")}</span>
            <input
              ref={fileRef}
              type="file"
              accept=".csv,.tsv,.txt,text/csv,text/tab-separated-values,text/plain"
              onChange={(e) => (setFile(e.target.files?.[0] ?? null), setStaged(null), setErrors(null))}
              disabled={!canManage}
              className="block text-sm text-foreground file:mr-3 file:rounded-md file:border file:border-border file:bg-surface-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
              aria-label={m("attach")}
            />
          </label>
          {canManage ? (
            <>
              <Button variant="outline" onClick={verify} loading={pending && !staged} disabled={!file}>
                <CheckCheck /> {m("verify")}
              </Button>
              <Button onClick={upload} loading={pending && Boolean(staged)} disabled={!staged || staged.rejected > 0 || staged.rows.length === 0}>
                <Upload /> {m("upload")}
              </Button>
              <Button variant="outline" onClick={reset} disabled={pending}>
                <RotateCcw /> {m("reset")}
              </Button>
            </>
          ) : null}
        </div>
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}

      <section className={cn(card, "overflow-hidden")}>
        {!staged ? (
          <div className="px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("no_rows")}</p>
            <p className="mx-auto mt-1 max-w-3xl text-xs text-foreground-muted">{m("format")}</p>
            <p className="mx-auto mt-2 max-w-3xl text-xs text-foreground-muted">{m("rule")}</p>
          </div>
        ) : (
          <>
            <div className="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
              <span className={cn("text-sm font-semibold", staged.rejected ? "text-danger" : "text-success")}>{m("summary", { total: staged.rows.length, ok: staged.accepted, bad: staged.rejected })}</span>
              <span className="text-xs text-foreground-muted">{m("rule")}</span>
            </div>
            <div className="max-h-[32rem] overflow-auto">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-brand text-brand-foreground">
                  <tr>
                    {cols.map((k) => (
                      <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", ["qty", "rate", "total", "row"].includes(k) && "text-right")}>
                        {m(`col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {staged.rows.map((r) => (
                    <tr key={r.row_no} className={cn("border-t border-border align-top", r.status === "REJECTED" && "bg-danger-subtle/50")}>
                      <td className="px-3 py-2 text-right tabular-nums">{r.row_no}</td>
                      <td className="px-3 py-2">{r.buyer_ac ?? "—"}</td>
                      <td className="px-3 py-2">{r.buyer}</td>
                      <td className="px-3 py-2 whitespace-nowrap">
                        {r.style}
                        {r.new_style ? <span className="ml-1 rounded bg-info-subtle px-1 text-[10px] font-semibold text-info">{m("new_style")}</span> : null}
                      </td>
                      <td className="px-3 py-2">{r.job_no}</td>
                      <td className="px-3 py-2">{r.po_no}</td>
                      <td className="px-3 py-2 whitespace-nowrap">{day(r.confirm_date)}</td>
                      <td className="px-3 py-2 whitespace-nowrap">{day(r.ship_date)}</td>
                      <td className="px-3 py-2">{r.ship_country ?? "—"}</td>
                      <td className="px-3 py-2">{r.item ?? "—"}</td>
                      <td className="px-3 py-2">{r.colour}</td>
                      <td className="px-3 py-2">{r.size}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{qtyFmt.format(r.qty)}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{r.rate === null ? "—" : money.format(r.rate)}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{r.total === null ? "—" : money.format(r.total)}</td>
                      <td className="px-3 py-2">
                        <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-semibold", r.status === "OK" ? "bg-success-subtle text-success" : "bg-danger-subtle text-danger")}>{m(r.status === "OK" ? "ok" : "rejected")}</span>
                      </td>
                      <td className="min-w-64 px-3 py-2 text-xs text-danger">{r.reasons.join(" · ")}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </section>
      <p className="text-[13px] text-foreground-muted">{m("footer")}</p>
    </div>
  );
}

export { OrderUpload };
