# 03-Platform-API-Specification.md
# Platform (Superadmin) API Specification

**Status:** Approved and implemented.

## 0. Why this is a separate document

`docs/03-API-Specification.md` is the tenant-facing contract every other endpoint built this session implements. It has no Platform section at all — Platform is not a gap in that spec, it is outside it: every endpoint below acts *above* a tenant (onboarding one, suspending one, stepping inside one as support), the way `EnsurePlatformAdmin` and the `Platform` module's web controllers already do. Building a Platform API is therefore designing a new contract, not filling in an existing one, which is why this is a separate document proposed for review rather than code landed directly.

Everything below mirrors an existing web controller 1:1 (ADR-003): the Actions are already written and tested (`OnboardTenant`, `ManageSupportAccess`, `ManageSupportTicket`), and this document's job is only to give them a REST shape. Two genuinely new pieces are called out explicitly in §5 and §9 because they have no web equivalent to mirror.

## 1. Namespace, authentication, authorization

- Prefix: `/api/v1/platform/*`, alongside the existing `/api/v1/*` tenant surface — one API, one token scheme, a different area.
- Authentication: the same bearer token as everywhere else (Sanctum for a person; the legacy scheme during the migration window). No new token type.
- Authorization: a `platform.admin` API middleware, parallel to `api.auth`, checks `$caller->user?->is_platform_admin === true`. Everyone else — including a perfectly valid tenant token — gets **404**, never 403, matching `EnsurePlatformAdmin`'s existing stance that whether the platform area exists is itself not information to hand out. Every refusal is recorded as a `PLATFORM_ACCESS_DENIED` audit event, exactly as today.
- A machine/client-credential caller can never reach this namespace. Every action here is either destructive, financial, or an entry into a customer's account, and all three require a named, accountable person — the same reason `work_order.work_order.start` refuses a machine caller today.
- Tenant scope: none. Every query below reads `withoutGlobalScope(TenantScope::class)`, exactly as the web controllers do, because the platform is not inside any tenant.

## 2. Tenants

### GET `/platform/tenants`
List, with factory/asset/active-user counts and the current contract per row (mirrors `TenantController::index`). Query: `status` (`active`/`suspended`/`closed`).

### POST `/platform/tenants`
Onboard a new customer (`OnboardTenant`): company, first factory, owner account. Response includes the owner's one-time password — shown once, never retrievable again, exactly as the web flow flashes it once.

### GET `/platform/tenants/{company}`
Detail: contract, recent invoices, domains, recent support grants, factory/asset/active-user counts, open ticket count, members, and the owner account (mirrors `TenantController::show`, all tabs' data merged since an API response has no notion of an unopened tab).

### PATCH `/platform/tenants/{company}`
Company details — name, legal name, contact, currency, timezone, locale. Never `code` (SRS: it is embedded in every work order and breakdown number ever issued).

### POST `/platform/tenants/{company}/logo`
Multipart. Replaces the public-disk logo (`TenantAccountController::updateLogo`).

### POST `/platform/tenants/{company}/suspend`
Body: `reason` (required, ≥10 chars). Stops sign-in without touching data; ends the company's live sessions.

### POST `/platform/tenants/{company}/reactivate`
Reverses a suspension.

### DELETE `/platform/tenants/{company}`
Close the account (soft delete). Body: `confirm_code` (must equal the company's own `code`, typed not clicked — SRS's deliberate friction on this action) and `reason`.

### POST `/platform/tenants/{company}/restore`
Reopen a closed (not yet purged) account.

### DELETE `/platform/tenants/{company}/purge`
Permanently erase a **closed** account and every row under it. 404 on a company that is not already closed — two separate decisions on two separate days, never one call. Body: `confirm_code`, `reason`.

## 3. Contracts and entitlements

### POST `/platform/tenants/{company}/contracts`
A new contract, superseding (archiving) whichever is currently active/trial/read-only. Full terms: price, cycle, dates, limits, overage policy.

### PATCH `/platform/tenants/{company}/entitlements`
Adjust `included_factories`, `included_assets`, `included_users`, `overage_policy` on the **current** contract without superseding it (`TenantController::updateLimits` — a limit change is not a new commercial term).

## 4. Domains

### GET `/platform/tenants/{company}/domains`
### POST `/platform/tenants/{company}/domains`
Body: `kind` (`SUBDOMAIN`/`CUSTOM`), `host`. A subdomain is verified immediately (it's on a host the platform controls); a custom domain starts unverified.
### POST `/platform/domains/{domain}/verify`
Re-checks DNS; `422` (not an error state, just "not yet") if it doesn't match.
### POST `/platform/domains/{domain}/primary`
Requires the domain to already be verified.
### DELETE `/platform/domains/{domain}`

## 5. Tenant account recovery

*(New capability wrapped in existing rigor — the web version is already this careful; nothing here is invented.)*

### PATCH `/platform/tenants/{company}/members/{user}/email`
Body: `email`, `reason` (≥10 chars). Audited (`TENANT_LOGIN_EMAIL_CHANGED`) and the company's own admins are notified — same `SUPPORT_ACCESS` channel the web flow uses, since this is exactly the kind of account event SRS 5.4 wants a tenant to be able to see, not just the platform.

### POST `/platform/tenants/{company}/members/{user}/reset-password`
Body: `reason` (≥10 chars). Revokes every token and session the account holds, issues a new password, returns it **once** in the response body. Audited and the customer notified, identically to the email change.

Both endpoints 404 if `{user}` is not actually a member of `{company}` — a user id from another company must not become reachable by editing a path segment.

## 6. Support access (impersonation)

This is the one area with a real design decision, because the web flow (`Auth::login($asUser)` plus three session keys) has no literal API equivalent — there is no session to swap.

**Proposed shape:** entering a grant mints an ordinary person-scoped bearer token *for the target user*, through the same `IssueApiToken::forUser()` every other login uses, but carrying a new `impersonated_by` field on the token record (Sanctum's `PersonalAccessToken` already has room for custom columns, as `company_id`/`last_four` were added the same way during the Sanctum migration). `AuthenticateApiToken` reads it back onto `ApiCaller`, and `AuditRecorder` writes it onto every row exactly as the session-based flow's `impersonated_by` column does today — so nothing downstream needs to know API impersonation is even a different mechanism, which is the same design property that makes the web version safe (`ManageSupportAccess`'s own docblock: "there is no parallel path with its own bugs"). The token expires with the grant, not on a fixed lifetime, and closing the grant early revokes it immediately.

### GET `/platform/support-grants`
Query: `company_id`, `active` (open only vs. history). Mirrors `PlatformDeskController::support`.

### POST `/platform/tenants/{company}/support-grants`
Body: `reason` (≥10 chars), `hours` (1–8). Opens a grant; notifies the company's admins. Grants nobody entry by itself.

### POST `/platform/support-grants/{grant}/enter`
Body: `user_id` (must be an active member of the grant's company). Returns an impersonation bearer token as described above. 404 unless the caller is the grant's own holder.

### POST `/platform/support-grants/{grant}/leave`
Revokes the impersonation token early (the normal case — the call ends before the clock runs out) without closing the grant itself, so a second entry is still possible inside the same window.

### POST `/platform/support-grants/{grant}/close`
Ends the grant outright.

## 7. Support tickets (platform side)

### GET `/platform/tickets`
Every ticket, every tenant. Query: `status`, `company_id`, `assigned_to`.
### GET `/platform/tickets/{ticket}`
### POST `/platform/tickets/{ticket}/reply`
### PATCH `/platform/tickets/{ticket}/status`
### PATCH `/platform/tickets/{ticket}/assign`

A tenant's own side of the same ticket (`GET/POST /support/tickets`, opened from inside a company) is **tenant-facing, not platform**, and belongs as a new section in the regular `docs/03-API-Specification.md` instead — it needs no `is_platform_admin` gate, the same way `SupportTicketController`'s web route sits under `/app`, not `/platform`. Proposed there as a new §26.1, immediately after Audit.

## 8. Finance

Every figure here is re-derived from existing rows on each call (SRS/ADR-063: no stored running total that could drift from the invoices and payments it claims to summarise), grouped by currency rather than summed across them.

### GET `/platform/finance/summary`
Invoiced/received/refunded/spent/net per currency, plus per-customer breakdown and twelve-month received-vs-spent trend (`PlatformFinanceController::index`, minus the two lists below which get their own paginated endpoints).

### GET `/platform/finance/payments`
Every received payment, most recent first.

### GET `/platform/finance/invoices/overdue`
Unpaid, past due date, across every tenant.

### GET `/platform/finance/expenses`
### POST `/platform/finance/expenses`
### DELETE `/platform/finance/expenses/{expense}`
Running costs of operating the platform itself (hosting, support tooling) — not billing, the platform's own books.

## 9. Platform notifications

*(New capability: nothing today exposes a platform admin's own `company_id IS NULL` notification inbox outside the web bell icon.)*

### GET `/platform/notifications`
### POST `/platform/notifications/read-all`

Same shape as the tenant `NotificationApiController` already built, scoped by `whereNull('company_id')` and `user_id = caller` instead of a company — a materially different query, so its own small controller rather than a parameter on the tenant one.

## 10. What is deliberately not proposed

- **Impersonating without a grant, or a grant with no expiry** — SRS 5.4 is unambiguous that access is time-boxed and reasoned; no endpoint here can create standing access.
- **A `PATCH` that changes a company's `code`** — never offered, for the reason §2 states.
- **Any endpoint returning a customer's actual operational data** (assets, work orders, breakdowns) through the platform namespace. §2's detail endpoint returns *counts*, matching the web screen's own explicit refusal to show more (`TenantController`'s own docblock: "a 'helpful' asset list here would be exactly the silent access SRS 5.4 prohibits"). Reaching real data requires an actual support-access token from §6, same as today.
