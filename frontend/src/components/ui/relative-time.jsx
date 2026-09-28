"use client";

import { useEffect, useState } from "react";
import { formatDistanceToNow } from "date-fns";

/**
 * Same hydration-safety reasoning as `FormattedDateTime`: "2 hours ago" is
 * relative to the moment it's computed, which the server and the client
 * never agree on to the second — rendered as `fallback` on both the server
 * and the client's first pass, then filled in after mount.
 */
function RelativeTime({ value, fallback = "" }) {
  const [text, setText] = useState(null);

  useEffect(() => {
    queueMicrotask(() => {
      setText(value ? formatDistanceToNow(new Date(value), { addSuffix: true }) : fallback);
    });
  }, [value, fallback]);

  return text ?? fallback;
}

export { RelativeTime };
