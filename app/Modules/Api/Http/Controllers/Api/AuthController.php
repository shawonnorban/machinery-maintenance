<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Support\ApiCaller;
use App\Modules\Api\Support\SanctumTokenHandle;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Actions\AttemptLogin;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\PasswordChange;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\QualityTally\Support\QtPhones;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Support\UserAgent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * How a caller gets in (API 3).
 *
 * Two doors, because there are two kinds of caller. A person exchanges their
 * password for a token; a machine exchanges client credentials for one. Both
 * come out holding the same kind of bearer token, which is what lets every
 * other endpoint stop caring which door was used.
 */
class AuthController extends ApiController
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * A person's token.
     *
     * The credential check, the rate limiting and the audit row all come from
     * the same action the login screen uses (ADR-066), so a rule tightened for
     * one entry point is tightened for both. What this does not do is start a
     * session: a bearer token is not a login.
     */
    public function login(Request $request, AttemptLogin $login, IssueApiToken $tokens): JsonResponse
    {
        $data = $request->validate([
            // The email, or Brickland's User ID (AnnotechERP 02/01) — the key
            // stays `email` so every existing client keeps working.
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            // Optional. A person in one company never has to say which.
            'company_id' => ['nullable', 'string', 'size:26'],
            // The app's way of saying it (AnnotechERP 37): the code given when the company was set up.
            'company_code' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.ApiToken::MAX_LIFETIME_DAYS],
        ] + QtPhones::rules('sometimes'));

        $user = $login->verify($data['email'], $data['password'], (string) $request->ip());

        $companyId = $data['company_id'] ?? $this->defaultCompanyId($user->id);

        if (! empty($data['company_code'])) {
            $companyId = Company::query()->whereRaw('upper(code) = ?', [Str::upper(trim($data['company_code']))])->value('id');

            if ($companyId === null || ! $user->belongsToCompany($companyId)) {
                throw ValidationException::withMessages(['company_code' => __('auth.company_code')]);
            }
        }

        if ($companyId === null) {
            throw ApiException::of(ErrorCode::TENANT_CONTEXT_REQUIRED, __('auth.no_company_membership'));
        }

        // A blocked phone signs nobody in (it was lost, or somebody else's).
        $phone = isset($data['phone']) ? QtPhones::arrive($companyId, $data['phone'], $user->id, $request->ip()) : null;

        ['token' => $token, 'plain' => $plain] = $tokens->forUser(
            $user,
            $companyId,
            $data['device_name'] ?? __('api.default_token_name'),
            days: $data['expires_in_days'] ?? null,
        );

        if ($phone !== null) {
            $token->forceFill(['qt_phone_id' => $phone->id])->save();
        }

        // Set here rather than inside the credential check: a token in hand is
        // an arrival, and that is what this column is asked about afterwards.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'company_id' => $companyId,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
            ],
        ]);
    }

    /**
     * A machine's token (API 4.2).
     *
     * Client credentials rather than a password, because there is nobody to
     * type one. The failure is deliberately indistinguishable between an
     * unknown client id and a wrong secret: telling them apart lets somebody
     * enumerate which credentials exist.
     */
    public function token(Request $request, IssueApiToken $tokens): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'client_secret' => ['required', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.ApiToken::MAX_LIFETIME_DAYS],
        ]);

        $client = ApiClient::withoutGlobalScope(TenantScope::class)
            ->where('client_id', $data['client_id'])
            ->first();

        if ($client === null || ! $client->verifySecret($data['client_secret']) || ! $client->isUsable()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        $client->touchUsage();

        ['token' => $token, 'plain' => $plain] = $tokens->forClient($client, $data['expires_in_days'] ?? null);

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'company_id' => $client->company_id,
            'scopes' => $client->scopes(),
        ]);
    }

    /**
     * Who this token belongs to.
     */
    public function me(ApiCaller $caller): JsonResponse
    {
        $company = Company::find($caller->companyId);

        return ApiResponse::ok([
            'company_id' => $caller->companyId,
            // The sidebar's own logo mark reads this — a machine caller has
            // no sidebar to draw, but the field costs nothing to include.
            'company' => $company === null ? null : [
                'id' => $company->id,
                'name' => $company->name,
                'logo_url' => $company->logoUrl(),
            ],
            'kind' => $caller->isMachine() ? 'CLIENT' : 'USER',
            'name' => $caller->label(),
            'user' => $caller->user === null ? null : [
                'id' => $caller->user->id,
                'name' => $caller->user->name,
                'email' => $caller->user->email,
                'locale' => $caller->user->locale,
                // The next screen must be the password change (AnnotechERP 02/01).
                'must_change_password' => $caller->user->mustChangePassword(),
                'read_only' => $caller->user->isReadOnly(),
                // Only a portal here (a supplier's login, AnnotechERP 08/07): the
                // frontend keeps it to the Supplier Portal's own screens.
                'portal_only' => $caller->companyId !== null && Role::onlyPortal(
                    UserRole::query()->withoutGlobalScopes()->where('company_id', $caller->companyId)->where('user_id', $caller->user->id)->pluck('role_id')->all(),
                ),
                // The app's header (AnnotechERP 37): the person's designation — their HR
                // record's, else their first role's name.
                'designation' => $caller->companyId === null ? null : $this->designation($caller->user, $caller->companyId),
                // The app's Account page (2026-11-27): the person's HR record, when their employee code has one — work details only, never pay or ID numbers.
                'employee' => $caller->companyId === null ? null : $this->employee($caller->user, $caller->companyId),
            ],
            // The factories this caller's work reaches — the app's factory selector (AnnotechERP 37).
            'factories' => $caller->companyId === null ? [] : DB::table('factories')->whereIn('id', app(\App\Shared\Tenancy\TenantContext::class)->accessibleFactoryIds())
                ->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'code' => $f->code])->all(),
            'client' => $caller->client === null ? null : [
                'id' => $caller->client->id,
                'client_id' => $caller->client->client_id,
            ],
            'token' => [
                'id' => $caller->token->id(),
                'name' => $caller->token->name(),
                'expires_at' => $caller->token->expiresAt()?->toIso8601String(),
            ],
            // Set only on a support-session token (`PlatformSupportGrantApi
            // Controller::enter`) — lets the frontend show "you are acting
            // as this person on behalf of support" instead of leaving that
            // silent (SRS 5.4). `null` is the ordinary case and costs one
            // extra lookup only when it isn't.
            'impersonated_by' => $this->impersonator($caller->token->impersonatedBy()),
        ]);
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function impersonator(?string $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        $staff = User::find($userId);

        return $staff === null ? null : ['id' => $staff->id, 'name' => $staff->name];
    }

    /**
     * What this token may do (API 3).
     *
     * Served so a client can hide what it would be refused rather than
     * discovering it one 403 at a time. The list is what the *token* can do,
     * not what the account behind it could: a read-only token minted for a
     * dashboard reports read-only here.
     */
    public function permissions(ApiCaller $caller): JsonResponse
    {
        // Spatie's `name` is the machine code (asset.asset.view_any, ...).
        $all = Permission::query()->orderBy('name')->pluck('name')->all();

        return ApiResponse::ok([
            'permissions' => $caller->permissionCodes($all),
        ]);
    }

    /**
     * Give up this token.
     *
     * Revoked rather than deleted: a token that stops working leaves a
     * question behind, and the row is the answer to it.
     */
    public function logout(ApiCaller $caller): JsonResponse
    {
        $caller->token->revoke();

        return ApiResponse::noContent();
    }

    /**
     * Everywhere this account is signed in — a browser session included,
     * this bearer token's own request has none of its own to name.
     *
     * Same source and shape as the account screen's device list (ADR-003):
     * a person switching between the app and an integration should not see
     * two different answers to "where am I signed in".
     */
    public function sessions(ApiCaller $caller): JsonResponse
    {
        if ($caller->user === null) {
            return ApiResponse::ok([]);
        }

        return ApiResponse::ok($this->sessionsFor($caller->user->id));
    }

    public function revokeSession(ApiCaller $caller, string $session): JsonResponse
    {
        if ($caller->user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        // Scoped to the asker: without this, one bearer token could sign
        // out any session by guessing its id.
        DB::table('sessions')
            ->where('id', $session)
            ->where('user_id', $caller->user->id)
            ->delete();

        return ApiResponse::noContent();
    }

    /**
     * Every browser session this account holds, gone in one call.
     *
     * A bearer token carries no session of its own to spare, unlike the
     * account screen's equivalent — a person sitting in that screen keeps
     * the tab they are looking at. An integration calling this has nothing
     * to exempt.
     */
    public function revokeAllSessions(ApiCaller $caller): JsonResponse
    {
        if ($caller->user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        DB::table('sessions')->where('user_id', $caller->user->id)->delete();

        return ApiResponse::noContent();
    }

    /**
     * Every live bearer token this person holds, from both schemes live
     * during the Sanctum migration — mirrors `AccountController::tokens()`,
     * plus one thing the web list has no equivalent for: `is_current` on
     * whichever row is the very token this request authenticated with.
     * The web page never lists the session it was requested from as one of
     * its own rows (a Blade session and an API token are different
     * things), but here that token genuinely is one of these rows, and
     * revoking it would end this Next.js session mid-click — the frontend
     * uses this flag to disable that one row's own revoke button rather
     * than let it be revoked by accident.
     */
    public function tokens(ApiCaller $caller): JsonResponse
    {
        if ($caller->user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $isSanctumCaller = $caller->token instanceof SanctumTokenHandle;
        $currentId = (string) $caller->token->id();
        $currentSource = $isSanctumCaller ? 'sanctum' : 'legacy';

        $tokens = $this->tokensFor($caller->user)->map(fn (array $t): array => $t + [
            'is_current' => $t['source'] === $currentSource && $t['id'] === $currentId,
        ]);

        return ApiResponse::ok($tokens->all());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tokensFor(User $user): Collection
    {
        $sanctum = $user->tokens->map(fn ($t): array => [
            'source' => 'sanctum',
            'id' => (string) $t->id,
            'name' => $t->name,
            'last_four' => $t->last_four,
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'expires_at' => $t->expires_at?->toIso8601String(),
        ]);

        $legacy = ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get()
            ->map(fn (ApiToken $t): array => [
                'source' => 'legacy',
                'id' => $t->id,
                'name' => $t->name,
                'last_four' => $t->last_four,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
            ]);

        return $sanctum->merge($legacy)->sortByDesc('last_used_at')->values();
    }

    /**
     * Give up one token that isn't necessarily this request's own — mirrors
     * `AccountController::revokeToken()`'s "try Sanctum, fall back to
     * legacy" lookup, both scoped to the asker rather than a company: a
     * person in three companies holds a token for each, and being able to
     * see one but not stop it would be worse than showing neither.
     */
    public function revokeToken(ApiCaller $caller, string $token): JsonResponse
    {
        if ($caller->user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $sanctum = $caller->user->tokens()->find($token);

        if ($sanctum !== null) {
            $sanctum->delete();

            return ApiResponse::noContent();
        }

        $row = ApiToken::withoutGlobalScope(TenantScope::class)
            ->whereKey($token)
            ->where('user_id', $caller->user->id)
            ->first();

        if ($row === null) {
            throw ApiException::of(ErrorCode::RESOURCE_NOT_FOUND);
        }

        $row->revoke();

        return ApiResponse::noContent();
    }

    /**
     * Mirrors `AccountController::changePassword()`, adapted for a
     * bearer-token-only client: the web keeps the *session* the request
     * arrived on and revokes every API token unconditionally, because a
     * session cookie and a bearer token are different things there. Here,
     * the bearer token *is* what authenticated this very request, so the
     * equivalent of "don't sign yourself out doing this" is excluding the
     * current token from revocation instead — revoking it too would end
     * this Next.js session mid-flow, immediately after the password it
     * just changed. Every *other* token (both schemes) and every web
     * session are still cleared: a password changed because it may have
     * leaked is a password whose other sign-ins may have leaked with it.
     */
    public function changePassword(Request $request, ApiCaller $caller): JsonResponse
    {
        if ($caller->user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $user = $caller->user;

        if ($user->cannot_change_password) {
            // Brickland's "User can not change password" (AnnotechERP 02/01):
            // shared or kiosk accounts whose password an administrator holds.
            throw ApiException::of(ErrorCode::FORBIDDEN, __('account.cannot_change_password'));
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            // SRS 50.1: at least 10 characters and checked against a
            // known-breached list. No forced periodic rotation.
            'password' => ['required', 'confirmed', PasswordRule::min(10)->uncompromised()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            // Asked for even though the caller already holds a valid
            // token: this is the check that stops a leaked/borrowed token
            // from becoming a permanent password change.
            throw ValidationException::withMessages([
                'current_password' => __('account.current_password_wrong'),
            ]);
        }

        // Changing it answers a forced change or an expiring password.
        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'status' => $user->status === 'PASSWORD_EXPIRING' ? 'ACTIVE' : $user->status,
        ])->save();
        PasswordChange::record($user, 'SELF', $user->id);

        $isSanctumCaller = $caller->token instanceof SanctumTokenHandle;
        $currentTokenId = (string) $caller->token->id();

        $user->tokens()
            ->when($isSanctumCaller, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->when(! $isSanctumCaller, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->get()
            ->each(fn (ApiToken $t) => $t->revoke());

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit->event(
            'SECURITY_EVENT',
            ['reason' => 'PASSWORD_CHANGED'],
            userId: $user->id,
            label: 'PASSWORD_CHANGED',
        );

        return ApiResponse::ok(['status' => 'changed']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sessionsFor(string $userId): array
    {
        if (config('session.driver') !== 'database') {
            // Nothing to list. A file or cookie driver keeps no index of who
            // is signed in where, and inventing an empty list that looks
            // authoritative would be worse than saying so.
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'ip_address' => $row->ip_address,
                'agent' => UserAgent::describe((string) ($row->user_agent ?? '')),
                // Every other timestamp this API returns is ISO 8601; the
                // `sessions` table's own column is a bare Unix integer
                // (Laravel's session driver, not this app's convention), so
                // it's converted here rather than left for the frontend to
                // special-case one field.
                'last_activity' => CarbonImmutable::createFromTimestamp($row->last_activity)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Which language this person reads the product in — mirrors the web's
     * own `PreferenceController::locale`, one of "the two global scope
     * controls in the header" that never got an API of its own before now.
     */
    public function setLocale(Request $request, ApiCaller $caller): JsonResponse
    {
        if ($caller->user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $data = $request->validate([
            'locale' => ['required', 'string', 'in:en,bn'],
        ]);

        $caller->user->forceFill(['locale' => $data['locale']])->save();

        return ApiResponse::ok(['locale' => $data['locale']]);
    }

    /**
     * A token for another of this person's companies (API 3).
     *
     * A new token rather than a mutation of this one. A token that can change
     * which company it reads is a token whose reach nobody can state, and the
     * old one keeps working for the company it was minted for — a client
     * syncing two factories legitimately holds both.
     */
    public function switchCompany(Request $request, ApiCaller $caller, IssueApiToken $tokens): JsonResponse
    {
        if ($caller->isMachine()) {
            // A machine's credentials belong to one company by construction.
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.client_cannot_switch_company'));
        }

        $data = $request->validate([
            'company_id' => ['required', 'string', 'size:26'],
        ]);

        ['token' => $token, 'plain' => $plain] = $tokens->forUser(
            $caller->user,
            $data['company_id'],
            $caller->token->name(),
        );

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'company_id' => $data['company_id'],
        ]);
    }

    /**
     * The companies this person may mint a token for.
     */
    public function companies(ApiCaller $caller): JsonResponse
    {
        if ($caller->isMachine()) {
            return ApiResponse::ok([]);
        }

        return ApiResponse::ok(
            $caller->user->accessibleCompanies()
                ->map(fn ($company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'code' => $company->code,
                ])
                ->values()
                ->all(),
        );
    }

    private function defaultCompanyId(string $userId): ?string
    {
        return CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->value('company_id');
    }

    /**
     * The person's HR record by their employee code (the app's Account page):
     * code, card, department, section, line, factory, joining date, mobile,
     * blood group. Null when they have none.
     *
     * @return array<string, string|null>|null
     */
    private function employee(User $user, string $companyId): ?array
    {
        if (! $user->employee_code) {
            return null;
        }
        $e = DB::table('hr_employees as e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')->leftJoin('sections as s', 's.id', '=', 'e.section_id')
            ->leftJoin('production_lines as l', 'l.id', '=', 'e.line_id')->leftJoin('factories as f', 'f.id', '=', 'e.factory_id')
            ->leftJoin('designations as g', 'g.id', '=', 'e.designation_id')
            ->where('e.company_id', $companyId)->where('e.employee_code', $user->employee_code)
            ->first(['e.employee_code', 'e.card_no', 'e.joining_date', 'e.mobile', 'e.blood_group', 'd.name as department', 's.name as section', 'l.name as line', 'f.name as factory', 'g.name as designation']);

        return $e === null ? null : [
            'code' => $e->employee_code, 'card_no' => $e->card_no, 'designation' => $e->designation, 'department' => $e->department, 'section' => $e->section,
            'line' => $e->line, 'factory' => $e->factory, 'joining_date' => $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null,
            'mobile' => $e->mobile, 'blood_group' => $e->blood_group,
        ];
    }

    /** "Merchandiser": the HR record's designation (by employee code), else the first role's label. */
    private function designation(User $user, string $companyId): ?string
    {
        if ($user->employee_code) {
            $name = DB::table('hr_employees')->join('designations', 'designations.id', '=', 'hr_employees.designation_id')
                ->where('hr_employees.company_id', $companyId)->where('hr_employees.employee_code', $user->employee_code)->value('designations.name');
            if ($name) {
                return $name;
            }
        }
        $role = UserRole::query()->withoutGlobalScopes()->where('company_id', $companyId)->where('user_id', $user->id)->orderBy('created_at')->value('role_id');

        return $role === null ? null : (Role::query()->whereKey($role)->value('description') ?: Str::headline(strtolower((string) Role::query()->whereKey($role)->value('name'))));
    }
}
