<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The platform's own host
    |--------------------------------------------------------------------------
    |
    | The address the platform area and the default sign-in live on. A request
    | arriving here is resolved from membership as it always was; a request
    | arriving on any other known host is pinned to the customer that owns it.
    |
    | Derived from APP_URL so a deployment that sets APP_URL correctly — which
    | it must anyway, for links in email — needs no second setting.
    |
    */

    'platform_host' => env(
        'TENANCY_PLATFORM_HOST',
        parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost',
    ),

    /*
    |--------------------------------------------------------------------------
    | Customer subdomains
    |--------------------------------------------------------------------------
    |
    | Where <customer>.example.com addresses are issued from. Usually the same
    | as the platform host, and separate here because a deployment serving the
    | platform on admin.example.com still wants customers on example.com.
    |
    | Serving these needs one wildcard DNS record and one wildcard certificate.
    | See docs/11-Deployment.md.
    |
    */

    'subdomain_host' => env('TENANCY_SUBDOMAIN_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Custom domain verification
    |--------------------------------------------------------------------------
    |
    | A customer proves they own an address by publishing a TXT record at
    | _<label>.<host> containing the token we generated. Until that check
    | passes the address does not resolve to anybody, because honouring an
    | unverified claim would let one customer collect another's sign-ins.
    |
    */

    'verification_label' => env('TENANCY_VERIFICATION_LABEL', 'mm-verify'),

    /*
    |--------------------------------------------------------------------------
    | The tenant-facing Next.js app
    |--------------------------------------------------------------------------
    |
    | Where a tenant user's own screens actually live now (docs/12-Stack-
    | Migration-Implementation-Plan.md Phase D/F). A handful of Blade-side
    | call sites still have to send someone to "the app" even though their
    | own `/app/*` routes are gone: a login redirect, a company-switch
    | redirect, a notification's action link, and Platform's own "enter as
    | this customer" support-session redirect. All of them build off this
    | one value rather than each guessing at a URL.
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3010'),

];
