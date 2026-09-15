"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";

/** Mirrors the web index's auto-submitting factory `<select>` — only shown when there's more than one to choose from. */
function FactoryPicker({ factories, value }) {
  const router = useRouter();

  return (
    <div className="w-full max-w-xs">
      <Select
        options={factories.map((f) => ({ value: f.id, label: f.name }))}
        value={value}
        onValueChange={(factoryId) => router.push(`/settings/calendar?factory_id=${factoryId}`)}
      />
    </div>
  );
}

export { FactoryPicker };
