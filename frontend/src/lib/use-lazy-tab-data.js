"use client";

import { useCallback, useEffect, useRef, useState } from "react";

/**
 * Fetches once when a lazily-mounted tab panel first appears (see
 * `AssetDetailTabs`'s own note — `TabsPanel` doesn't render an inactive
 * tab's children at all until it's selected, so this hook's effect never
 * runs for a tab nobody opened) rather than the page fetching everything
 * up front. `refetch` exists for the handful of tabs that mutate their own
 * data (a transfer approved, a cost posted, a document uploaded): calling
 * it after a successful mutation is this hook's replacement for the
 * `revalidatePath` + fresh-props-from-the-server refresh those actions
 * used to get for free when their data arrived as page props instead of
 * client-fetched state.
 *
 * @param {() => Promise<any>} fetcher
 */
function useLazyTabData(fetcher) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);
  const fetcherRef = useRef(fetcher);

  // Kept current in an effect rather than assigned during render: reading
  // or writing a ref's `.current` while rendering is exactly what refs
  // aren't for (React's own lint rule against it), even though the ref's
  // value is never read until the async work below runs, well after this
  // render has committed.
  useEffect(() => {
    fetcherRef.current = fetcher;
  });

  const load = useCallback(() => {
    let cancelled = false;

    // Deferred as a microtask rather than called synchronously in the
    // effect body below (the same reasoning as `ThemeProvider`'s own
    // applied-choice effect): calling setState synchronously inside an
    // effect is what React's set-state-in-effect check warns against,
    // and a microtask is not a visible delay to whoever is looking at a
    // loading skeleton anyway.
    queueMicrotask(() => {
      if (cancelled) {
        return;
      }

      setLoading(true);
      setError(null);

      fetcherRef.current().then(
        (result) => {
          if (!cancelled) {
            setData(result);
            setLoading(false);
          }
        },
        (loadError) => {
          if (!cancelled) {
            setError(loadError);
            setLoading(false);
          }
        },
      );
    });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => load(), [load]);

  return { data, error, loading, refetch: load };
}

export { useLazyTabData };
