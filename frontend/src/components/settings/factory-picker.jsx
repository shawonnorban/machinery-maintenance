"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";

/** Switches between the company-wide view and one factory's overrides — mirrors the web index's own factory `<select>`. */
function FactoryPicker({ factories, value }) {
  const router = useRouter();

  const options = [{ value: "", label: "Company-wide" }, ...factories.map((f) => ({ value: f.id, label: f.name }))];

  return (
    <Select
      options={options}
      value={value ?? ""}
      onValueChange={(factoryId) => router.push(factoryId ? `/settings/company?factory_id=${factoryId}` : "/settings/company")}
    />
  );
}

export { FactoryPicker };
