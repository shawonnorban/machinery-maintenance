/**
 * The Budget List's data, apart from how it looks (user, 2026-11-27): the
 * filters (together, never one at a time), the search after 300ms of quiet,
 * a page at a time from the server (`/pre-budget/budgets/cards`) — added on
 * below for the cards, one page in place for the table — the counts per
 * state, and what went wrong (no network, refused, or another error). And
 * the budget's own actions, on its own endpoints, which check everything
 * again: finalize, withdraw, revise, delete a draft.
 */
import { useCallback, useEffect, useRef, useState } from "react";
import { useSession } from "./session";

export const NO_FILTERS = { status: "", factory: "", fy: "", dept: "", buyer: "", brand: "" };
export const PAGE_SIZES = [10, 20, 50];

/** `value`, once it has stopped changing for `ms`. */
export function useDebounced(value, ms = 300) {
  const [settled, setSettled] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setSettled(value), ms);
    return () => clearTimeout(timer);
  }, [value, ms]);

  return settled;
}

/** What kind of failure: "network" (no answer), "denied" (403) or "error" — and its message. */
export function failureOf(e) {
  const status = e?.status;

  return { kind: status === 0 ? "network" : status === 403 ? "denied" : "error", message: e?.message ?? "" };
}

/**
 * The list: `filters` (state and the five dropdowns), `search` (typed —
 * debounced here), `pageSize`, and `append` (cards: the next page goes
 * under the last; table: it replaces it).
 */
export function useBudgetList({ filters, search, pageSize, append }) {
  const { call } = useSession();
  const q = useDebounced(search.trim(), 300);
  const [state, setState] = useState({ rows: null, counts: null, options: null, page: null });
  const [failure, setFailure] = useState(null);
  const [loading, setLoading] = useState(false);
  const [more, setMore] = useState(false);
  const asked = useRef(0);

  const query = new URLSearchParams(Object.entries({ ...filters, q, per_page: String(pageSize) }).filter(([, v]) => v !== "" && v !== null && v !== undefined)).toString();

  const read = useCallback(
    async (page, keep) => {
      const turn = ++asked.current;
      keep ? setMore(true) : setLoading(true);
      try {
        const data = await call(`/pre-budget/budgets/cards?${query}&page=${page}`);
        if (turn !== asked.current) return;
        setState((now) => ({ rows: keep ? [...(now.rows ?? []), ...data.rows] : data.rows, counts: data.counts, options: data.options, page: data.page }));
        setFailure(null);
      } catch (e) {
        if (turn === asked.current) setFailure(failureOf(e));
      } finally {
        if (turn === asked.current) {
          setLoading(false);
          setMore(false);
        }
      }
    },
    [call, query],
  );

  // Any change of filter, search or page size starts again at page 1.
  useEffect(() => {
    read(1, false);
  }, [read]);

  const page = state.page;

  return {
    ...state,
    q,
    failure,
    loading,
    loadingMore: more,
    reload: () => read(1, false),
    goTo: (n) => read(n, false),
    loadMore: append && page && page.current < page.last && !more && !loading ? () => read(page.current + 1, true) : null,
  };
}

/** A budget's actions, on the budget's own endpoints (the server refuses whatever its rules do not allow). */
export function useBudgetActions() {
  const { call } = useSession();
  const at = (no) => `/pre-budget/budgets/${encodeURIComponent(no)}`;

  return {
    finalize: (no) => call(`${at(no)}/finalize`, { method: "POST" }),
    withdraw: (no) => call(`${at(no)}/withdraw`, { method: "POST", body: {} }),
    revise: (no) => call(`${at(no)}/revise`, { method: "POST" }),
    remove: (no) => call(at(no), { method: "DELETE" }),
  };
}

/** Whether a finalize would pass the checklist (Costing, Stock, every BOM); unknown when the server did not say. */
export const checklistDone = (check) => (check ? Boolean(check.costing && check.stock && check.bom) : null);
