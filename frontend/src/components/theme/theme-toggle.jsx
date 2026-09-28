"use client";

import { Sun, Moon, Monitor } from "lucide-react";
import { cn } from "@/lib/utils";
import { useTheme } from "@/components/theme/theme-provider";

const THEME_ICONS = { light: Sun, dark: Moon, system: Monitor };
const ORDER = ["light", "dark", "system"];

/** Shared by `AppShell`'s topbar and the signed-out login pages. */
function ThemeToggle({ className }) {
  const { theme, setTheme } = useTheme();
  const Icon = THEME_ICONS[theme];

  return (
    <button
      type="button"
      onClick={() => setTheme(ORDER[(ORDER.indexOf(theme) + 1) % ORDER.length])}
      className={cn(
        "rounded-sm p-2 text-foreground-muted hover:bg-surface-muted hover:text-foreground",
        className,
      )}
      aria-label={`Theme: ${theme}. Click to change.`}
      title={`Theme: ${theme}`}
    >
      <Icon className="size-[18px]" />
    </button>
  );
}

export { ThemeToggle };
