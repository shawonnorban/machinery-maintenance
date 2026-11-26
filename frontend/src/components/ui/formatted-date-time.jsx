"use client";

import { useEffect, useState } from "react";

/**
 * `toLocaleString()`/`toLocaleDateString()` read the runtime's own default
 * timezone — a Docker backend renders in UTC, a Dhaka browser renders in
 * +6 — so calling either one directly during a server-rendered component's
 * render produces a real mismatch on hydration (confirmed: `MetersTable`
 * threw exactly this for `last_reading_at`).
 *
 * Rendered as `fallback` on both the server and the client's first pass
 * (deterministic either way), then filled in by `useEffect` once mounted —
 * hydration only ever compares that first pass, so the later update is
 * never flagged.
 */
function FormattedDateTime({ value, mode = "datetime", options, fallback = "—" }) {
  const [text, setText] = useState(null);

  useEffect(() => {
    // Deferred rather than called synchronously in the effect body, same
    // reasoning as ThemeProvider's own stored-theme effect: this is the one
    // render where the real formatted value (possibly different from the
    // `fallback` the server rendered) gets applied, and a microtask avoids
    // the cascading-render pattern React's lint flags without changing when
    // it visibly takes effect.
    queueMicrotask(() => {
      if (!value) {
        setText(fallback);
        return;
      }

      // A bare "2026-10-06" is a calendar day, not UTC midnight: read it as local so no timezone shows the day before.
      const day = typeof value === "string" ? value.match(/^(\d{4})-(\d{2})-(\d{2})$/) : null;
      const date = day ? new Date(Number(day[1]), Number(day[2]) - 1, Number(day[3])) : new Date(value);
      setText(
        mode === "date"
          ? date.toLocaleDateString(undefined, options)
          : mode === "time"
            ? date.toLocaleTimeString(undefined, { hour12: true, ...options })
            : date.toLocaleString(undefined, { hour12: true, ...options }),
      );
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, mode, fallback]);

  return text ?? fallback;
}

export { FormattedDateTime };
