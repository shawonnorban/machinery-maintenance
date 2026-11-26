"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
import { Card, CardBody, CardDescription, CardFooter, CardHeader, CardTitle, CardTitleGroup } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { Folder, FileText } from "lucide-react";
import { useT } from "@/lib/i18n";

/**
 * Menu Definition (AnnotechERP 02/01): the menu tree, module by module. Names,
 * Bangla names, order and Active are edited in place and saved together; the
 * URL and the grantable flags are facts of the code and only shown.
 */
function MenuDefinition({ modules, saveMenus }) {
  const t = useT("access");
  const router = useRouter();
  const toastManager = useToastManager();
  const [drafts, setDrafts] = useState({});
  const [pending, startTransition] = useTransition();
  const dirty = Object.keys(drafts);

  function edit(menu, field, value) {
    setDrafts((current) => {
      const next = { ...(current[menu.id] ?? {}), [field]: value };
      if (String(menu[field] ?? "") === String(value ?? "")) delete next[field];
      const all = { ...current, [menu.id]: next };
      if (Object.keys(next).length === 0) delete all[menu.id];
      return all;
    });
  }

  const value = (menu, field) => (drafts[menu.id] && field in drafts[menu.id] ? drafts[menu.id][field] : menu[field]);

  function save() {
    startTransition(async () => {
      const result = await saveMenus(dirty.map((id) => ({ id, payload: drafts[id] })));
      const failed = result.results.filter((entry) => entry.status === "error");
      setDrafts((current) => Object.fromEntries(failed.map((entry) => [entry.id, current[entry.id]])));
      toastManager.add(
        failed.length > 0
          ? { title: t("save_failed", { count: failed.length, message: failed[0].message }), type: "danger" }
          : { title: t("saved"), type: "success" },
      );
      router.refresh();
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitleGroup>
          <CardTitle>{t("tabs.menu-definition")}</CardTitle>
          <CardDescription>{t("descriptions.menu-definition")}</CardDescription>
        </CardTitleGroup>
      </CardHeader>
      <CardBody className="overflow-x-auto p-0">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-primary text-left text-primary-foreground">
              <th className="px-3 py-2.5 font-semibold" />
              <th className="px-3 py-2.5 font-semibold">{t("menu_fields.name")}</th>
              <th className="px-3 py-2.5 font-semibold">{t("menu_fields.name_bn")}</th>
              <th className="px-3 py-2.5 font-semibold">{t("menu_fields.url")}</th>
              <th className="px-3 py-2.5 font-semibold">{t("menu_fields.sort_order")}</th>
              <th className="px-3 py-2.5 font-semibold">{t("menu_fields.flags")}</th>
              <th className="px-3 py-2.5 font-semibold">{t("menu_fields.active")}</th>
            </tr>
          </thead>
          <tbody>
            {modules.map((module) => (
              <ModuleBlock key={module.code} module={module} value={value} edit={edit} drafts={drafts} t={t} />
            ))}
          </tbody>
        </table>
      </CardBody>
      <CardFooter className="flex justify-end gap-2">
        <Button variant="outline" size="sm" onClick={() => setDrafts({})} disabled={dirty.length === 0 || pending}>
          <RotateCcw /> {t("revert")}
        </Button>
        <Button size="sm" onClick={save} loading={pending} disabled={dirty.length === 0}>
          <Save /> {t("save")}
        </Button>
      </CardFooter>
    </Card>
  );
}

function ModuleBlock({ module, value, edit, drafts, t }) {
  return (
    <>
      <tr className="bg-surface-muted">
        <td colSpan={7} className="px-3 py-2 text-xs font-semibold tracking-wider text-foreground-muted uppercase">
          {module.name}
          {module.name_bn ? <span className="ml-2 normal-case">{module.name_bn}</span> : null}
        </td>
      </tr>
      {module.menus.map((menu) => (
        <tr key={menu.id} className={drafts[menu.id] ? "bg-warning-subtle/40" : "border-b border-border last:border-0"}>
          <td className="px-3 py-2 text-foreground-muted">
            <span className={`inline-flex items-center gap-1.5 text-xs ${menu.parent_id ? "ml-4" : ""}`} title={menu.is_leaf ? t("screen") : t("folder")}>
              {menu.is_leaf ? <FileText className="size-4" /> : <Folder className="size-4" />}
              {menu.icon}
            </span>
          </td>
          <td className="px-3 py-2">
            <Input value={value(menu, "name") ?? ""} onChange={(e) => edit(menu, "name", e.target.value)} className="min-w-48" aria-label={t("menu_fields.name")} />
          </td>
          <td className="px-3 py-2">
            <Input value={value(menu, "name_bn") ?? ""} onChange={(e) => edit(menu, "name_bn", e.target.value)} className="min-w-48" aria-label={t("menu_fields.name_bn")} />
          </td>
          <td className="px-3 py-2 font-mono text-xs text-foreground-muted">{menu.url ?? "—"}</td>
          <td className="px-3 py-2">
            <Input type="number" value={value(menu, "sort_order") ?? 0} onChange={(e) => edit(menu, "sort_order", e.target.value)} className="w-20" aria-label={t("menu_fields.sort_order")} />
          </td>
          <td className="px-3 py-2 font-mono text-xs">{Object.keys(menu.flags ?? {}).join(" ") || "—"}</td>
          <td className="px-3 py-2">
            <Checkbox checked={Boolean(value(menu, "active"))} onCheckedChange={(on) => edit(menu, "active", on)} aria-label={t("menu_fields.active")} />
          </td>
        </tr>
      ))}
    </>
  );
}

export { MenuDefinition };
