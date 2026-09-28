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

      const date = new Date(value);
      setText(
        mode === "date"
          ? date.toLocaleDateString(undefined, options)
          : mode === "time"
            ? date.toLocaleTimeString(undefined, options)
            : date.toLocaleString(undefined, options),
      );
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, mode, fallback]);

  return text ?? fallback;
}

export { FormattedDateTime };
