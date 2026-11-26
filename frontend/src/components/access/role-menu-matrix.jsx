"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { RotateCcw, Save } from "lucide-react";
import { Card, CardBody, CardDescription, CardFooter, CardHeader, CardTitle, CardTitleGroup } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const FLAGS = ["V", "C", "E", "D", "A", "P", "X"];

/** The role's current matrix as menu code => set of fully-granted flags. */
function initialTicks(menus) {
  const ticks = {};
  for (const menu of menus) {
    ticks[menu.code] = FLAGS.filter((flag) => menu.flags[flag] === "all");
  }
  return ticks;
}

/**
 * Role Menu Assignment (AnnotechERP 02/01): pick a role, tick V/C/E/D/A/P/X
 * per screen. A flag the role holds only part of shows as partly granted;
 * ticking it grants the rest, clearing it removes it. Saved as one matrix.
 */
function RoleMenuMatrix({ roles, grants, modules, saveGrants, locale }) {
  const t = useT("access");
  const router = useRouter();
  const searchParams = useSearchParams();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [ticks, setTicks] = useState(() => (grants ? initialTicks(grants.menus) : {}));
  const [touched, setTouched] = useState(false);

  const role = grants?.role;
  const readOnly = !role || role.is_system || role.is_admin;
  const name = (row) => (locale === "bn" && row.name_bn) || row.name;
  const moduleNames = useMemo(() => Object.fromEntries(modules.map((m) => [m.code, name(m)])), [modules, locale]); // eslint-disable-line react-hooks/exhaustive-deps

  const byModule = useMemo(() => {
    const groups = new Map();
    for (const menu of grants?.menus ?? []) {
      if (!groups.has(menu.module)) groups.set(menu.module, []);
      groups.get(menu.module).push(menu);
    }
    return [...groups.entries()];
  }, [grants]);

  function chooseRole(roleId) {
    const params = new URLSearchParams(searchParams);
    params.set("role", roleId);
    router.push(`/access-control/role-menu?${params.toString()}`);
  }

  function toggle(menu, flag, on) {
    setTouched(true);
    setTicks((current) => {
      const flags = new Set(current[menu.code] ?? []);
      if (on) flags.add(flag);
      else flags.delete(flag);
      // Viewing is the precondition of every other action on a screen.
      if (on && flag !== "V" && menu.flags.V) flags.add("V");
      if (!on && flag === "V") flags.clear();
      return { ...current, [menu.code]: [...flags] };
    });
  }

  function save() {
    startTransition(async () => {
      const result = await saveGrants(role.id, ticks);
      if (result.status === "success") {
        toastManager.add({ title: t("grants_saved"), type: "success" });
        setTouched(false);
        router.refresh();
      } else {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitleGroup>
          <CardTitle>{t("tabs.role-menu")}</CardTitle>
          <CardDescription>{t("descriptions.role-menu")}</CardDescription>
        </CardTitleGroup>
        <div className="w-72">
          <Select
            value={role ? String(role.id) : ""}
            onValueChange={chooseRole}
            placeholder={t("choose_role")}
            options={roles.map((r) => ({ value: String(r.id), label: `${r.name} (${r.code})${r.is_system ? ` — ${t("system_role")}` : ""}` }))}
            aria-label={t("role")}
          />
        </div>
      </CardHeader>

      {role?.is_system ? <Alert variant="info" className="mx-4 mb-2">{t("system_role_note")}</Alert> : null}
      {role?.is_admin ? <Alert variant="warning" className="mx-4 mb-2">{t("admin_role_note")}</Alert> : null}

      {grants ? (
        <CardBody className="overflow-x-auto p-0">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-primary text-left text-primary-foreground">
                <th className="px-3 py-2.5 font-semibold">{t("menu")}</th>
                {FLAGS.map((flag) => (
                  <th key={flag} className="px-2 py-2.5 text-center font-semibold" title={t(`flags.${flag}`)}>
                    {flag}
                    <span className="block text-[10px] font-normal opacity-80">{t(`flags.${flag}`)}</span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {byModule.map(([moduleCode, menus]) => (
                <ModuleRows
                  key={moduleCode}
                  title={moduleNames[moduleCode] ?? moduleCode}
                  menus={menus}
                  ticks={ticks}
                  readOnly={readOnly}
                  isAdmin={role.is_admin}
                  name={name}
                  onToggle={toggle}
                  t={t}
                />
              ))}
            </tbody>
          </table>
        </CardBody>
      ) : null}

      {grants && !readOnly ? (
        <CardFooter className="flex justify-end gap-2">
          <Button variant="outline" size="sm" onClick={() => { setTicks(initialTicks(grants.menus)); setTouched(false); }} disabled={!touched || pending}>
            <RotateCcw /> {t("revert")}
          </Button>
          <Button size="sm" onClick={save} loading={pending} disabled={!touched}>
            <Save /> {t("save")}
          </Button>
        </CardFooter>
      ) : null}
    </Card>
  );
}

function ModuleRows({ title, menus, ticks, readOnly, isAdmin, name, onToggle, t }) {
  return (
    <>
      <tr className="bg-surface-muted">
        <td colSpan={FLAGS.length + 1} className="px-3 py-2 text-xs font-semibold tracking-wider text-foreground-muted uppercase">
          {title}
        </td>
      </tr>
      {menus.map((menu) => (
        <tr key={menu.code} className="border-b border-border last:border-0">
          <td className="px-3 py-2">{name(menu)}</td>
          {FLAGS.map((flag) => {
            const state = menu.flags[flag];
            if (!state) {
              return (
                <td key={flag} className="px-2 py-2 text-center text-foreground-muted" title={t("flag_unavailable")}>
                  —
                </td>
              );
            }
            const ticked = isAdmin || (ticks[menu.code] ?? []).includes(flag);
            const partial = !ticked && state === "some" && !(ticks[menu.code] ?? []).length;
            return (
              <td key={flag} className="px-2 py-2 text-center">
                <Checkbox
                  checked={ticked}
                  indeterminate={partial}
                  disabled={readOnly}
                  onCheckedChange={(on) => onToggle(menu, flag, on)}
                  aria-label={`${name(menu)} — ${t(`flags.${flag}`)}${partial ? ` (${t("partial")})` : ""}`}
                />
              </td>
            );
          })}
        </tr>
      ))}
    </>
  );
}

export { RoleMenuMatrix };
