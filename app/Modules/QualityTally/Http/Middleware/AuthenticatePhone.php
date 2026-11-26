<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Middleware;

use App\Modules\Platform\Support\LicensedModules;
use App\Modules\QualityTally\Models\QtPhone;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The phone itself as the caller (AnnotechERP 37): its id and secret, approved
 * and not blocked. What it sends are its QCs' taps, each naming the session it
 * was made in — so a QC's waiting taps still go after the next QC signs in.
 */
class AuthenticatePhone
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) $request->header('X-Phone-Id');
        $phone = ! Str::isUuid($id) ? null : QtPhone::query()->withoutGlobalScope(TenantScope::class)
            ->where('install_id', $id)->where('company_id', (string) $request->header('X-Company-Id'))->first();

        if ($phone === null || ! $phone->knows((string) $request->header('X-Phone-Secret')) || $phone->status() !== 'APPROVED') {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }
        $company = Company::find($phone->company_id);
        if ($company === null || $company->isSuspended()) {
            throw ApiException::of(ErrorCode::TENANT_SUSPENDED);
        }
        if (! LicensedModules::permits($phone->company_id, 'qtally.tally.create')) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $this->context->set($phone->company_id, Factory::withoutGlobalScope(TenantScope::class)->where('company_id', $phone->company_id)->pluck('id')->all());
        $request->attributes->set('qt_phone', $phone);
        $phone->update([
            'last_seen_at' => now(), 'last_ip' => $request->ip(),
            'app_version' => substr((string) $request->header('X-App-Version', (string) $phone->app_version), 0, 20) ?: null,
            'waiting_taps' => min(1000000, max(0, (int) $request->header('X-Waiting-Taps', (string) $phone->waiting_taps))),
        ]);

        return $next($request);
    }
}
