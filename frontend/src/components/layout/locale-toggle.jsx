"use client";

import { useState, useTransition } from "react";
import { Globe, ChevronDown } from "lucide-react";
import { Dropdown } from "@/components/ui/dropdown";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { setLocale } from "@/app/(app)/locale-actions";

// Each language's own name, in its own script, regardless of the current
// UI locale — same convention as `LocaleSwitcher` (the signed-out login
// page's own counterpart) and `UserForm`'s locale field.
const LOCALES = [
  { value: "en", code: "EN", label: "English" },
  { value: "bn", code: "বাং", label: "বাংলা" },
];

/**
 * The topbar's language control — the companion to the web's own
 * factory-scope switcher, `PreferenceController::locale`, and now a
 * dropdown rather than a click-to-flip toggle so a third language could be
 * added later without changing the interaction. Saves the choice to the
 * account (`PATCH /auth/locale` via `setLocale`) and sets the `locale`
 * cookie next-intl reads, so the whole app re-renders in the chosen
 * language on the next request.
 */
function LocaleToggle({ locale: initialLocale }) {
  const t = useT("common");
  const [locale, setLocaleState] = useState(initialLocale ?? "en");
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();
  const current = LOCALES.find((option) => option.value === locale) ?? LOCALES[0];

  function choose(next) {
    if (next === locale) return;
    startTransition(async () => {
      const result = await setLocale(next);
      if (result?.status === "success") {
        setLocaleState(next);
        toastManager.add({ title: t("language_saved_toast"), description: t("language_saved_hint"), type: "success" });
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message ?? t("could_not_save"), type: "danger" });
      }
    });
  }

  return (
    <Dropdown
      trigger={
        <span
          className="flex items-center gap-1 rounded-sm px-2 py-1.5 text-xs font-medium text-foreground-muted hover:bg-surface-muted hover:text-foreground disabled:opacity-50"
          aria-label={`${t("language")}: ${current.code}`}
          title={`${t("language")}: ${current.code}`}
        >
          <Globe className="size-[18px]" />
          {current.code}
          <ChevronDown className="size-3.5" />
        </span>
      }
      items={LOCALES.map((option) => ({
        label: option.label,
        onSelect: () => choose(option.value),
        disabled: pending,
      }))}
    />
  );
}

export { LocaleToggle };
