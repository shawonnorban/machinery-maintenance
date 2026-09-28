"use client";

import { createContext, useContext, useEffect, useMemo, useState } from "react";

/**
 * docs/UI-DESIGN-SYSTEM.md §7: three states — explicit light, explicit
 * dark, and system default. "System" is the absence of `data-theme`
 * (globals.css's `@media (prefers-color-scheme: dark)` block handles that
 * case entirely in CSS); this provider only needs to manage the two
 * explicit overrides and remember the choice.
 */
const STORAGE_KEY = "annotech-theme";

const ThemeContext = createContext({ theme: "system", setTheme: () => {} });

function ThemeProvider({ children }) {
  const [theme, setThemeState] = useState("system");

  useEffect(() => {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored !== "light" && stored !== "dark" && stored !== "system") {
      return;
    }

    // Deferred rather than called synchronously in the effect body: this
    // is the one render where the stored choice (possibly different from
    // the "system" the server rendered) gets applied, and doing that as a
    // microtask avoids the cascading-render pattern React's lint flags,
    // without changing when it visibly takes effect.
    queueMicrotask(() => setThemeState(stored));
  }, []);

  useEffect(() => {
    const root = document.documentElement;
    if (theme === "system") {
      root.removeAttribute("data-theme");
    } else {
      root.setAttribute("data-theme", theme);
    }
  }, [theme]);

  const value = useMemo(
    () => ({
      theme,
      setTheme: (next) => {
        setThemeState(next);
        try {
          window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
          // Private browsing / storage disabled: the choice just doesn't
          // survive a reload, which is a smaller loss than the toggle
          // throwing.
        }
      },
    }),
    [theme],
  );

  return <ThemeContext value={value}>{children}</ThemeContext>;
}

function useTheme() {
  return useContext(ThemeContext);
}

export { ThemeProvider, useTheme };
