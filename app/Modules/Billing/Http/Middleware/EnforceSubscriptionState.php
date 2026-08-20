<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Middleware;

use App\Modules\Billing\Models\SubscriptionContract;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only enforcement for a lapsed subscription (ERD Section 19, ADR-029).
 *
 * Writes are refused; reads and exports never are. That asymmetry is the whole
 * point: the data belongs to the customer (ADR-030), so a company in arrears
 * can still open every screen, run every report and export everything they have
 * — they simply cannot add to it until the account is settled.
 *
 * Refusal is explicit and named, `SUBSCRIPTION_READ_ONLY`, rather than a
 * generic 403. Somebody hitting this needs to know it is a billing state and
 * not a permission problem, because the two are fixed by different people.
 */
class EnforceSubscriptionState
{
    /**
     * Methods that change something. GET and HEAD always pass, which is what
     * keeps reading and exporting available.
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Paths that stay open even in read-only.
     *
     * Two groups. Housekeeping — signing out, switching company, changing
     * language — and the two things a customer in arrears must still be able to
     * do: pay the bill, and take their data with them.
     *
     * Exports are on this list because they are POSTs. "Reads and exports
     * remain available so a customer can always retrieve their own data" is not
     * satisfied by allowing GET alone (ERD Section 19, SRS 49.3, ADR-030), and
     * a data-ownership promise that quietly excludes the export button is not a
     * promise.
     */
    private const ALWAYS_ALLOWED = [
        'logout',
        'app/locale',
        'app/switch-company',
        'app/factory-scope',
        'app/billing*',
        'app/reports/*/export',
        'app/imports/*/export',
        'broadcasting/auth',
    ];

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        if ($request->is(self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        $companyId = $this->context->companyIdOrNull();

        if ($companyId === null) {
            return $next($request);
        }

        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->first();

        // No contract at all is not a lapsed contract. A company being
        // onboarded, or a self-hosted deployment with no billing, must not be
        // locked out by the absence of a row.
        if ($contract === null || ! $contract->isReadOnly()) {
            return $next($request);
        }

        return $this->refuse($request, $contract);
    }

    private function refuse(Request $request, SubscriptionContract $contract): Response
    {
        $message = __('billing.read_only_message', [
            'since' => $contract->read_only_at?->toDateString() ?? '',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'code' => 'SUBSCRIPTION_READ_ONLY',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        // Back to where they were, with the reason. A blank 402 page on a shop
        // floor is a support call.
        return back()->with('error', $message);
    }
}
