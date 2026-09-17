"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";
import { useT } from "@/lib/i18n";

/** Switches between the company-wide view and one factory's overrides — mirrors the web index's own factory `<select>`. */
function FactoryPicker({ factories, value }) {
  const t = useT("settings");
  const router = useRouter();

  const options = [{ value: "", label: t("company_wide") }, ...factories.map((f) => ({ value: f.id, label: f.name }))];

  return (
    <Select
      options={options}
      value={value ?? ""}
      onValueChange={(factoryId) => router.push(factoryId ? `/settings/company?factory_id=${factoryId}` : "/settings/company")}
    />
  );
}

export { FactoryPicker };
