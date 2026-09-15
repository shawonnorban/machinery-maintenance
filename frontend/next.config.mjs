/** @type {import('next').NextConfig} */
const nextConfig = {
  // Next.js 15+ blocks cross-origin requests to dev-only resources
  // (`/_next/hmr`, and apparently enough of the rest of the dev bundle to
  // break hydration entirely) unless the request's Host header is on this
  // list — "localhost" is allowed by default, but nothing reached through
  // Docker is: the frontend container's own hostname, and the
  // `host.docker.internal` alias the Playwright container (docker-
  // compose.yml's `playwright` service, profile `test`) uses to reach the
  // host-published port. Found live: every Playwright test failed because
  // the login form's onSubmit handler never attached — the browser fell
  // back to a native GET submission with the password in the URL — and it
  // traced back to this exact warning in the dev server's own logs, not a
  // bug in the form itself.
  allowedDevOrigins: ["frontend", "host.docker.internal"],

  // The Laravel project root also carries a package-lock.json (its Vite
  // asset pipeline), which Turbopack otherwise mistakes for a second
  // workspace root sharing this one's lockfile.
  turbopack: {
    root: import.meta.dirname,

    // Hand-rolled equivalent of next-intl/plugin's createNextIntlPlugin()
    // for the one thing it does that this project actually uses: aliasing
    // the virtual `next-intl/config` module to src/i18n/request.js (see
    // node_modules/next-intl/dist/esm/production/plugin/getNextConfig.js).
    // The plugin wrapper itself is skipped because it unconditionally boots
    // an SWC-based extraction compiler even when the `experimental.extract`
    // feature it's for is never configured, and on this machine Windows'
    // Application Control policy blocks that native @swc/core binary from
    // loading at all. Not something to work around at the OS level — this
    // sidesteps needing that code path in the first place. No [locale] URL
    // segment either: the Laravel app doesn't prefix routes by locale
    // (SetLocale middleware reads it from the session), so the frontend
    // matches that rather than introducing /en/* and /bn/* routes the rest
    // of the product doesn't have.
    resolveAlias: {
      "next-intl/config": "./src/i18n/request.js",
    },
  },
};

export default nextConfig;
