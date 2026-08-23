<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\Web;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiToken;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A token for the page the technician is already looking at (SRS 38).
 *
 * The offline queue posts to the API, and the API takes bearer tokens rather
 * than session cookies. Rather than teach the API to accept sessions — which
 * means dragging the whole session and CSRF stack onto endpoints that exist
 * for machines — the browser asks for a token the ordinary way and uses it
 * like any other client.
 *
 * Two deliberate narrowings, both because this token lives in a browser tab
 * on a factory floor rather than in a server's environment file:
 *
 *   - It carries only the two abilities the queue actually posts with. A
 *     technician's account can do a great deal more; a token lying around in a
 *     phone should not.
 *   - Minting one revokes the previous one for the same person and company, so
 *     a shared tablet used by four people across a shift leaves one live token
 *     rather than four.
 */
class SessionTokenController extends Controller
{
    /**
     * What a queued draft can be. Everything else the queue might one day post
     * has to be added here on purpose.
     */
    private const ABILITIES = [
        'breakdown.breakdown.create',
        'meter.reading.create',
    ];

    /** Short, because the tab it lives in is open for a shift, not a month. */
    private const LIFETIME_DAYS = 1;

    /**
     * Not translated. The name is what the revoke-the-previous-one query
     * matches on, and a name that changes when somebody switches to Bengali
     * would leave the old token alive for ever.
     */
    private const NAME = 'Browser session';

    public function __construct(private readonly TenantContext $context) {}

    public function store(Request $request, IssueApiToken $tokens): JsonResponse
    {
        $user = $request->user();
        $companyId = $this->context->companyId();

        ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('name', self::NAME)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);

        ['token' => $token, 'plain' => $plain] = $tokens->forUser(
            $user,
            $companyId,
            self::NAME,
            self::ABILITIES,
            self::LIFETIME_DAYS,
        );

        return response()->json([
            'token' => $plain,
            'expires_at' => $token->expires_at?->toIso8601String(),
        ]);
    }
}
