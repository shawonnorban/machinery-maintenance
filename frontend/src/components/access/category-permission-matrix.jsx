"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, ChevronLeft, ChevronRight, ChevronsDownUp, ChevronsUpDown, RotateCcw, Save, Search } from "lucide-react";
import { Card, CardBody, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const ACTIONS = ["view", "create", "edit", "approve"];
const PAGE_SIZE = 50;

function ticksOf(categories) {
  return Object.fromEntries(categories.map((c) => [c.id, Object.fromEntries(ACTIONS.map((a) => [a, c[`can_${a}`]]))]));
}

/**
 * Category Access Permission (AnnotechERP 02/01): choose a role, then tick the
 * item categories it may view, create, edit and approve in. Saved as a whole.
 */
function CategoryPermissionMatrix({ roles, data, savePermissions, locale }) {
  const t = useT("access");
  const router = useRouter();
  const toastManager = useToastManager();
  const [ticks, setTicks] = useState(() => (data ? ticksOf(data.categories) : {}));
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(() => new Set());
  const [query, setQuery] = useState("");
  const [tickedOnly, setTickedOnly] = useState(false);
  const [page, setPage] = useState(0);
  const bangla = locale === "bn";
  const categories = useMemo(() => data?.categories ?? [], [data]);

  // The legacy library runs to hundreds of categories, so the tree starts
  // folded to its top level; a search shows every match with its ancestors.
  const { parentOf, childCount } = useMemo(() => {
    const parents = new Map(categories.map((c) => [c.id, c.parent_id]));
    const counts = new Map();
    for (const c of categories) if (c.parent_id) counts.set(c.parent_id, (counts.get(c.parent_id) ?? 0) + 1);
    return { parentOf: parents, childCount: counts };
  }, [categories]);

  const ancestorsOf = (id) => {
    const out = [];
    for (let p = parentOf.get(id); p; p = parentOf.get(p)) out.push(p);
    return out;
  };

  const filtering = query.trim() !== "" || tickedOnly;

  const visible = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (needle || tickedOnly) {
      const shown = new Set();
      for (const c of categories) {
        const matches =
          !needle ||
          [c.name, c.name_bn, c.code].some((text) =>
            String(text ?? "")
              .toLowerCase()
              .includes(needle),
          );
        if (matches && (!tickedOnly || ACTIONS.some((a) => ticks[c.id]?.[a]))) {
          shown.add(c.id);
          for (const a of ancestorsOf(c.id)) shown.add(a);
        }
      }
      return categories.filter((c) => shown.has(c.id));
    }
    return categories.filter((c) => ancestorsOf(c.id).every((a) => open.has(a)));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [categories, query, tickedOnly, ticks, open, parentOf]);

  // Fifty rows a page: a top level of several hundred is still one screen at a time.
  const pageCount = Math.max(1, Math.ceil(visible.length / PAGE_SIZE));
  const currentPage = Math.min(page, pageCount - 1);
  const rows = visible.slice(currentPage * PAGE_SIZE, (currentPage + 1) * PAGE_SIZE);

  // How many categories below a folded parent carry a tick, so nothing granted is hidden silently.
  const tickedBelow = (id) => categories.filter((c) => c.id !== id && ancestorsOf(c.id).includes(id) && ACTIONS.some((a) => ticks[c.id]?.[a])).length;

  function toggleOpen(id) {
    setOpen((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggle(categoryId, action, checked) {
    setTicks((current) => {
      const row = { ...current[categoryId], [action]: checked };
      // Doing anything in a category means seeing it; no View, nothing else.
      if (action !== "view" && checked) row.view = true;
      if (action === "view" && !checked) for (const a of ACTIONS) row[a] = false;
      return { ...current, [categoryId]: row };
    });
  }

  function save() {
    startTransition(async () => {
      const permissions = Object.entries(ticks)
        .filter(([, row]) => ACTIONS.some((a) => row[a]))
        .map(([id, row]) => ({ item_category_id: id, ...Object.fromEntries(ACTIONS.map((a) => [`can_${a}`, row[a]])) }));
      const result = await savePermissions(data.role.id, permissions);
      if (result?.status === "success") {
        toastManager.add({ title: t("category_saved"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-center gap-3">
        <CardTitle>{t("role")}</CardTitle>
        <div className="w-full max-w-sm">
          <Select
            value={data?.role.id ? String(data.role.id) : ""}
            onValueChange={(value) => router.push(`/access-control/category-permission?role=${value}`)}
            placeholder={t("choose_role")}
            options={roles.map((role) => ({ value: String(role.id), label: `${role.name} (${role.code})` }))}
          />
        </div>
      </CardHeader>

      {data ? (
        <>
          <CardBody className="flex flex-col gap-3">
            <Alert variant={data.restricted ? "warning" : "info"}>{data.restricted ? t("category_restricted") : t("category_unrestricted")}</Alert>
            {categories.length > 0 ? (
              <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-[200px] flex-1">
                  <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
                  <Input
                    value={query}
                    onChange={(event) => {
                      setQuery(event.target.value);
                      setPage(0);
                    }}
                    placeholder={t("search_categories")}
                    className="pl-9"
                  />
                </div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                  <Checkbox
                    checked={tickedOnly}
                    onCheckedChange={(checked) => {
                      setTickedOnly(checked === true);
                      setPage(0);
                    }}
                  />
                  {t("ticked_only")}
                </label>
                <Button variant="outline" size="sm" onClick={() => setOpen(new Set(childCount.keys()))}>
                  <ChevronsUpDown /> {t("expand_all")}
                </Button>
                <Button variant="outline" size="sm" onClick={() => setOpen(new Set())}>
                  <ChevronsDownUp /> {t("collapse_all")}
                </Button>
              </div>
            ) : null}
            {categories.length === 0 ? (
              <p className="py-6 text-center text-sm text-foreground-muted">{t("no_categories")}</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-primary text-left text-primary-foreground">
                      <th className="px-3 py-2.5 font-semibold">{t("category")}</th>
                      {ACTIONS.map((action) => (
                        <th key={action} className="px-3 py-2.5 text-center font-semibold">
                          {t(`category_flags.${action}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((category) => {
                      const children = childCount.get(category.id) ?? 0;
                      const expanded = open.has(category.id) || filtering;
                      const hidden = children > 0 && !expanded ? tickedBelow(category.id) : 0;
                      return (
                        <tr key={category.id} className="border-b border-border last:border-0">
                          <td className="px-3 py-2" style={{ paddingLeft: `${0.75 + (category.depth - 1) * 1.5}rem` }}>
                            <span className="inline-flex items-center gap-1">
                              {children > 0 ? (
                                <button
                                  type="button"
                                  onClick={() => toggleOpen(category.id)}
                                  className="rounded-sm p-0.5 text-foreground-muted hover:bg-surface-muted"
                                  aria-label={expanded ? t("collapse_all") : t("expand_all")}
                                  aria-expanded={expanded}
                                >
                                  {expanded ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                                </button>
                              ) : (
                                <span className="inline-block w-5" />
                              )}
                              <span className={category.depth === 1 ? "font-medium" : ""}>{(bangla && category.name_bn) || category.name}</span>
                            </span>
                            {category.code ? <span className="ml-2 font-mono text-xs text-foreground-muted">{category.code}</span> : null}
                            {children > 0 ? <span className="ml-2 text-xs text-foreground-subtle">({children})</span> : null}
                            {hidden > 0 ? <span className="ml-2 rounded-full bg-brand-subtle px-2 py-0.5 text-xs text-brand">{t("ticked_below", { count: hidden })}</span> : null}
                          </td>
                          {ACTIONS.map((action) => (
                            <td key={action} className="px-3 py-2 text-center">
                              <Checkbox
                                checked={Boolean(ticks[category.id]?.[action])}
                                onCheckedChange={(checked) => toggle(category.id, action, checked === true)}
                                aria-label={`${category.name} — ${t(`category_flags.${action}`)}`}
                              />
                            </td>
                          ))}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
            {pageCount > 1 ? (
              <div className="flex items-center justify-end gap-2 text-sm text-foreground-muted">
                <span>{t("page_of", { current: currentPage + 1, last: pageCount, total: visible.length })}</span>
                <Button variant="outline" size="sm" disabled={currentPage === 0} onClick={() => setPage(currentPage - 1)}>
                  <ChevronLeft />
                </Button>
                <Button variant="outline" size="sm" disabled={currentPage >= pageCount - 1} onClick={() => setPage(currentPage + 1)}>
                  <ChevronRight />
                </Button>
              </div>
            ) : null}
          </CardBody>
          <CardFooter className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-foreground-muted">{t("category_note")}</p>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => setTicks(ticksOf(data.categories))} disabled={pending}>
                <RotateCcw /> {t("revert")}
              </Button>
              <Button size="sm" onClick={save} loading={pending}>
                <Save /> {t("save")}
              </Button>
            </div>
          </CardFooter>
        </>
      ) : null}
    </Card>
  );
}

export { CategoryPermissionMatrix };
