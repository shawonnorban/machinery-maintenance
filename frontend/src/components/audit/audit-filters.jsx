"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Button } from "@/components/ui/button";

/** Mirrors `audit::audit.index.blade.php`'s filter form — action, entity type, who, and a date range. */
function AuditFilters({ actions, entityTypes, users, filters }) {
  const router = useRouter();

  function navigate(next) {
    const merged = { ...filters, ...next };
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(merged)) {
      if (value) params.set(key, value);
    }
    router.push(`/audit-logs?${params.toString()}`);
  }

  return (
    <div className="flex flex-wrap items-end gap-3">
      <div className="w-full max-w-[180px]">
        <Select
          options={[{ value: "", label: "All actions" }, ...actions.map((a) => ({ value: a, label: a }))]}
          value={filters.action ?? ""}
          onValueChange={(value) => navigate({ action: value, page: undefined })}
          placeholder="All actions"
        />
      </div>

      <div className="w-full max-w-[180px]">
        <Select
          options={[{ value: "", label: "All types" }, ...entityTypes.map((t) => ({ value: t, label: t }))]}
          value={filters.entity_type ?? ""}
          onValueChange={(value) => navigate({ entity_type: value, page: undefined })}
          placeholder="All types"
        />
      </div>

      <div className="w-full max-w-[200px]">
        <Select
          options={[{ value: "", label: "All users" }, ...users.map((u) => ({ value: u.id, label: u.name }))]}
          value={filters.user_id ?? ""}
          onValueChange={(value) => navigate({ user_id: value, page: undefined })}
          placeholder="All users"
        />
      </div>

      <div className="w-full max-w-[160px]">
        <DatePicker value={filters.from ?? null} onChange={(value) => navigate({ from: value, page: undefined })} placeholder="From" />
      </div>

      <div className="w-full max-w-[160px]">
        <DatePicker value={filters.to ?? null} onChange={(value) => navigate({ to: value, page: undefined })} placeholder="To" />
      </div>

      <Button variant="outline" size="sm" onClick={() => router.push("/audit-logs")}>
        Clear
      </Button>
    </div>
  );
}

export { AuditFilters };
