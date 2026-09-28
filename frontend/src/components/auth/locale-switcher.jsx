"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Globe, ChevronDown } from "lucide-react";
import { Dropdown } from "@/components/ui/dropdown";
import { cn } from "@/lib/utils";
import { setGuestLocale } from "@/app/login/actions";

// Each language's own name, in its own script, regardless of the current
// UI locale — the same convention `UserForm`'s own locale field already
// uses, not something a translation key would help with.
const LOCALES = [
  { value: "en", code: "EN", label: "English" },
  { value: "bn", code: "বাং", label: "বাংলা" },
];

/** The signed-out counterpart to `LocaleToggle` (the in-app topbar's own switcher) — a visitor here has no account yet to save a preference to, so `setGuestLocale` only sets the cookie next-intl reads (see that action's own docblock). */
function LocaleSwitcher({ locale: initialLocale, className }) {
  const [locale, setLocale] = useState(initialLocale ?? "en");
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const current = LOCALES.find((option) => option.value === locale) ?? LOCALES[0];

  function choose(next) {
    if (next === locale) return;
    startTransition(async () => {
      await setGuestLocale(next);
      setLocale(next);
      router.refresh();
    });
  }

  return (
    <Dropdown
      trigger={
        <span
          className={cn(
            "flex items-center gap-1.5 rounded-full border border-border-strong bg-surface px-3 py-1.5 text-xs font-medium text-foreground-muted",
            "hover:bg-surface-muted hover:text-foreground disabled:opacity-50",
            className,
          )}
        >
          <Globe className="size-[15px]" />
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

export { LocaleSwitcher };
