<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers a browser needs in order to defend the page (SRS 50, API 35.3).
 *
 * Every one of these is a defence the application cannot mount on its own:
 * they tell the browser what to refuse. Without them a single reflected
 * script, a MIME sniff, or a hostile page in an iframe turns a bug into an
 * account takeover, and no amount of server-side care prevents it.
 *
 * Applied to every response, HTML and JSON alike. A JSON endpoint that can be
 * framed or sniffed is a JSON endpoint that can be read across origins.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before anything renders. A worker process that keeps its memory
        // between requests would otherwise serve one nonce to everybody, which
        // is the same as having none.
        Nonce::forget();

        $response = $next($request);

        $headers = [
            // Clickjacking. The application is never legitimately framed —
            // there is no embed, no widget, no partner iframe — so the answer
            // is a flat no rather than a same-origin allowance.
            'X-Frame-Options' => 'DENY',

            // Stops a browser deciding for itself that an uploaded file is
            // HTML. The file store is full of documents somebody else chose
            // the name of.
            'X-Content-Type-Options' => 'nosniff',

            // Referrers leak paths, and a path here contains record ids. Same
            // origin sees the full URL; anywhere else sees only the site.
            'Referrer-Policy' => 'same-origin',

            // Nothing in this product uses a camera except the QR scanner,
            // which is same-origin, and nothing uses a microphone, geolocation
            // or payment at all. Denying them by default means a compromised
            // dependency cannot start.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',

            // Cross-origin isolation of the document itself.
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',

        ];

        // The Vite dev server serves its client and its modules from another
        // origin over a websocket, which no honest policy can allow. Rather
        // than write an opening that would then ship to production, the policy
        // is simply absent while the dev server is running — and present the
        // moment it is not, which is every deployed environment.
        if (! $this->viteDevServerRunning()) {
            $headers['Content-Security-Policy'] = $this->contentSecurityPolicy();
        }

        foreach ($headers as $name => $value) {
            // Never overwritten. A controller that has deliberately set one —
            // a printable label sheet that must be framed, say — knows
            // something this middleware does not.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // HSTS only where the connection is already secure. Sent over plain
        // HTTP it is ignored by browsers and, in local development, would
        // pin a developer's machine to https://localhost for a year.
        if ($request->secure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Vite's `hot` file exists only while `npm run dev` is running. It is
     * never present in a build, which is what makes it a safe signal.
     */
    private function viteDevServerRunning(): bool
    {
        return is_file(public_path('hot'));
    }

    /**
     * The policy, kept deliberately tight and honest about why each opening
     * exists.
     *
     * `'unsafe-inline'` for styles is the one real compromise. Blade templates
     * carry a handful of inline `style` attributes for column widths, and
     * CoreUI's own components write inline styles as they animate; removing
     * the allowance would break the shell rather than harden it. Scripts get
     * no such allowance — that is where the danger actually is, and the app's
     * own JavaScript is all bundled files.
     *
     * `window.App` is the exception, and it is served through a nonce so the
     * blanket inline-script allowance never has to be granted.
     */
    private function contentSecurityPolicy(): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            // No plugins, no <object>, no Flash-era attack surface.
            "object-src 'none'",
            // Nothing may frame this page, restated for browsers that honour
            // CSP over X-Frame-Options.
            "frame-ancestors 'none'",
            "form-action 'self'",

            "script-src 'self' 'nonce-".Nonce::current()."'",
            "style-src 'self' 'unsafe-inline'",
            // data: for the inline SVG QR codes and the label sheet.
            "img-src 'self' data:",
            "font-src 'self' data:",

            // The websocket for live events. ws: as well as wss: because a
            // factory intranet deployment may not terminate TLS internally.
            "connect-src 'self' ws: wss:",
        ];

        return implode('; ', $directives);
    }
}
