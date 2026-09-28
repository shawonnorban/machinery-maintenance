"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";
import { FIELDABLE_TYPES } from "@/lib/custom-field-types";

function CustomFieldTypePicker({ value, labels }) {
  const router = useRouter();

  const options = FIELDABLE_TYPES.map((type) => ({ value: type, label: labels[type] }));

  return <Select options={options} value={value} onValueChange={(type) => router.push(`/settings/custom-fields?type=${type}`)} />;
}

export { CustomFieldTypePicker };
