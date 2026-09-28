<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\ManageSupportAccess;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Support access to a customer's data (Platform API §6; opening, entering
 * and closing a grant all delegate to `ManageSupportAccess` unchanged per
 * ADR-003 — the same reason, expiry, and audit-at-each-end rules SRS 5.4
 * requires apply exactly as they do on the web).
 *
 * `enter()` is the one genuinely new piece: the web flow swaps the platform
 * admin's session for the target user's (`Auth::login`); an API request has
 * no session to swap, so this mints an ordinary bearer token for the target
 * user instead, marked with `impersonated_by` so every row written under it
 * is attributed exactly as an impersonated web session's rows are — see
 * `IssueApiToken::forUser()` and `AuditRecorder`.
 */
class PlatformSupportGrantApiController extends Controller
{
    public function __construct(private readonly ManageSupportAccess $support) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupportGrant::query()->with(['holder:id,name', 'company:id,name,code']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->string('company_id'));
        }

        $grants = $query->orderByDesc('starts_at')->limit(100)->get();

        if ($request->query('active') === 'true') {
            $grants = $grants->filter(fn (SupportGrant $g): bool => $g->isActive())->values();
        } elseif ($request->query('active') === 'false') {
            $grants = $grants->reject(fn (SupportGrant $g): bool => $g->isActive())->values();
        }

        return ApiResponse::ok($grants->map(fn (SupportGrant $g): array => $this->summary($g))->all());
    }

    public function store(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'hours' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        $grant = $this->support->open($company, $request->user(), $data['reason'], (int) $data['hours']);

        return ApiResponse::created($this->summary($grant));
    }

    /**
     * Step inside, as a named user of the company. Returns a bearer token
     * scoped to that user and that company, expiring with the grant.
     */
    public function enter(Request $request, SupportGrant $grant, IssueApiToken $tokens): JsonResponse
    {
        if ($grant->granted_to !== $request->user()->id) {
            abort(404);
        }

        $data = $request->validate(['user_id' => ['required', 'string', 'size:26']]);

        $asUser = User::findOrFail($data['user_id']);
        $staff = $request->user();

        try {
            $this->support->enter($grant, $staff, $asUser);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        ['token' => $token, 'plain' => $plain] = $tokens->forUser(
            $asUser,
            $grant->company_id,
            __('platform.support_session_token_name', ['name' => $staff->name]),
            // The day-granularity lifetime an ordinary login token gets is
            // wrong here: the token must die exactly when the grant does,
            // not up to a day later, so its expiry is overwritten below to
            // the grant's own instant rather than relying on this figure.
            days: 1,
            impersonatedBy: $staff->id,
        );

        $token->forceFill(['expires_at' => $grant->expires_at])->save();

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'company_id' => $grant->company_id,
            'acting_as' => ['id' => $asUser->id, 'name' => $asUser->name, 'email' => $asUser->email],
        ]);
    }

    /**
     * Revoke the impersonation token early — the normal case, since the
     * call usually ends before the grant's own clock does — without
     * closing the grant, so entering again inside the same window still
     * works.
     */
    public function leave(Request $request, SupportGrant $grant): JsonResponse
    {
        if ($grant->granted_to !== $request->user()->id) {
            abort(404);
        }

        PersonalAccessToken::where('impersonated_by', $request->user()->id)
            ->where('company_id', $grant->company_id)
            ->delete();

        $this->support->leave($grant, $request->user());

        return ApiResponse::noContent();
    }

    public function close(Request $request, SupportGrant $grant): JsonResponse
    {
        if ($grant->granted_to !== $request->user()->id) {
            abort(404);
        }

        PersonalAccessToken::where('impersonated_by', $request->user()->id)
            ->where('company_id', $grant->company_id)
            ->delete();

        $this->support->close($grant, $request->user());

        return ApiResponse::ok($this->summary($grant->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SupportGrant $grant): array
    {
        return [
            'id' => $grant->id,
            'company' => $grant->relationLoaded('company') && $grant->company !== null
                ? ['id' => $grant->company->id, 'name' => $grant->company->name, 'code' => $grant->company->code]
                : ['id' => $grant->company_id],
            'holder' => $grant->relationLoaded('holder') && $grant->holder !== null
                ? ['id' => $grant->holder->id, 'name' => $grant->holder->name]
                : ['id' => $grant->granted_to],
            'reason' => $grant->reason,
            'is_active' => $grant->isActive(),
            'starts_at' => $grant->starts_at?->toIso8601String(),
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'ended_at' => $grant->ended_at?->toIso8601String(),
        ];
    }
}
