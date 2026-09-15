/**
 * `action_url` is generated server-side via `route('app.breakdowns.show', ...)`
 * (`NotificationDispatcher`/`MaintenanceNotifier`) — an absolute URL to the
 * still-live Blade app's own `/app/...` route, not this one. Following it
 * as-is would leave the SPA and send the browser to the other application
 * entirely. Stripped down to a same-app path: the leading `/app` segment
 * dropped, matching every route this Next.js build has ported using the
 * same path shape as its Blade counterpart.
 */
function toAppPath(actionUrl) {
  if (!actionUrl) return null;

  try {
    // Always absolute already (Laravel's own `route()` output) — no base
    // needed, and no `window` dependency to trip up the server render pass.
    const { pathname } = new URL(actionUrl);
    return pathname.replace(/^\/app(\/|$)/, "/") || "/";
  } catch {
    return null;
  }
}

export { toAppPath };
