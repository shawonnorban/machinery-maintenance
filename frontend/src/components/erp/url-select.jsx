"use client";

import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";

/**
 * A one-field filter that lives in the url (Item Master's "All categories"):
 * choosing a value navigates, so the server filters and the view can be
 * bookmarked. An empty value clears the parameter.
 *
 * @param {{ basePath: string, param: string, value?: string, options: {value: string, label: string}[], allLabel: string }} props
 */
function UrlSelect({ basePath, param, value = "", options, allLabel }) {
  const router = useRouter();
  const known = new Set(options.map((o) => o.value));

  return (
    <div className="w-60">
      <Select
        value={known.has(value) ? value : ""}
        onValueChange={(next) => {
          // The select emits values it never offered when it resets; only known ones navigate.
          if (next !== "" && !known.has(next)) return;
          router.push(next ? `${basePath}?${param}=${encodeURIComponent(next)}` : basePath, { scroll: false });
        }}
        options={[{ value: "", label: allLabel }, ...options]}
        placeholder={allLabel}
      />
    </div>
  );
}

export { UrlSelect };
