"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Search } from "lucide-react";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/** Mirrors `audit::audit.index.blade.php`'s filter form — search, action, entity type, who, and a date range. */
function AuditFilters({ actions, entityTypes, users, filters }) {
  const t = useT("audit");
  const router = useRouter();
  const [searchValue, setSearchValue] = useState(filters.search ?? "");

  function navigate(next) {
    const merged = { ...filters, ...next };
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(merged)) {
      if (value) params.set(key, value);
    }
    router.push(`/audit-logs?${params.toString()}`);
  }

  function searchOnEnter(event) {
    if (event.key === "Enter") navigate({ search: searchValue, page: undefined });
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="relative w-full max-w-[240px]">
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
        <Input
          value={searchValue}
          onChange={(e) => setSearchValue(e.target.value)}
          onKeyDown={searchOnEnter}
          placeholder={t("search_placeholder")}
          className="pl-9"
        />
      </div>

      <Button
        variant="outline"
        size="sm"
        onClick={() => navigate({ search: searchValue, page: undefined })}
      >
        {t("filter")}
      </Button>

      <div className="w-full max-w-[180px]">
        <Select
          options={[{ value: "", label: t("all_actions") }, ...actions.map((a) => ({ value: a, label: t(`actions.${a}`) }))]}
          value={filters.action ?? ""}
          onValueChange={(value) => navigate({ action: value, page: undefined })}
          placeholder={t("all_actions")}
        />
      </div>

      <div className="w-full max-w-[180px]">
        <Select
          options={[{ value: "", label: t("all_types") }, ...entityTypes.map((type) => ({ value: type, label: type }))]}
          value={filters.entity_type ?? ""}
          onValueChange={(value) => navigate({ entity_type: value, page: undefined })}
          placeholder={t("all_types")}
        />
      </div>

      <div className="w-full max-w-[200px]">
        <Select
          options={[{ value: "", label: t("all_users") }, ...users.map((u) => ({ value: u.id, label: u.name }))]}
          value={filters.user_id ?? ""}
          onValueChange={(value) => navigate({ user_id: value, page: undefined })}
          placeholder={t("all_users")}
        />
      </div>

      <div className="w-full max-w-[160px]">
        <DatePicker value={filters.from ?? null} onChange={(value) => navigate({ from: value, page: undefined })} placeholder={t("from")} />
      </div>

      <div className="w-full max-w-[160px]">
        <DatePicker value={filters.to ?? null} onChange={(value) => navigate({ to: value, page: undefined })} placeholder={t("to")} />
      </div>

      <Button variant="outline" size="sm" onClick={() => router.push("/audit-logs")}>
        {t("clear")}
      </Button>
    </div>
  );
}

export { AuditFilters };
