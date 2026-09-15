"use client";

import { useState, useTransition } from "react";
import { Languages } from "lucide-react";
import { useToastManager } from "@/components/ui/toast";
import { setLocale } from "@/app/(app)/locale-actions";

const LABEL = { en: "EN", bn: "বাং" };
const NEXT = { en: "bn", bn: "en" };

/**
 * The topbar had no language control at all — not broken, never built (the
 * companion to the web's own factory-scope switcher, `PreferenceController
 * ::locale`, is the other half of "the two global scope controls in the
 * header" that never got ported). This saves the account-level preference
 * for real; it doesn't retranslate the screen, since no page here has
 * Bengali strings wired up yet (`next-intl` is set up as infrastructure —
 * see docs/12-Stack-Migration-Implementation-Plan.md Phase C §6 — but no
 * component calls `useTranslations()` yet).
 */
function LocaleToggle({ locale: initialLocale }) {
  const [locale, setLocaleState] = useState(initialLocale ?? "en");
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function toggle() {
    const next = NEXT[locale] ?? "en";
    startTransition(async () => {
      const result = await setLocale(next);
      if (result?.status === "success") {
        setLocaleState(next);
        toastManager.add({
          title: next === "bn" ? "ভাষা পছন্দ সংরক্ষিত হয়েছে" : "Language preference saved",
          description: "Saved to your account. Screens still read in English — Bengali text isn't wired up yet.",
          type: "success",
        });
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message ?? "Could not save", type: "danger" });
      }
    });
  }

  return (
    <button
      type="button"
      onClick={toggle}
      disabled={pending}
      className="flex items-center gap-1 rounded-sm px-2 py-1.5 text-xs font-medium text-foreground-muted hover:bg-surface-muted hover:text-foreground disabled:opacity-50"
      aria-label={`Language: ${LABEL[locale] ?? locale}. Click to change.`}
      title={`Language: ${LABEL[locale] ?? locale}`}
    >
      <Languages className="size-[18px]" />
      {LABEL[locale] ?? locale}
    </button>
  );
}

export { LocaleToggle };
