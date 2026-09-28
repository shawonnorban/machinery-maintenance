<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\CompanyDomain;
use App\Modules\Tenancy\Services\DomainVerifier;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The addresses a customer reaches their system on (Platform API §4;
 * mirrors `TenantDomainController`, which this delegates to unchanged per
 * ADR-003).
 */
class PlatformDomainApiController extends ApiController
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly DomainVerifier $verifier,
    ) {}

    public function index(Company $company): JsonResponse
    {
        $domains = CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('is_primary')
            ->orderBy('host')
            ->get();

        return ApiResponse::ok($domains->map(fn (CompanyDomain $d): array => $this->summary($d))->all());
    }

    public function store(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', CompanyDomain::KINDS)],
            'host' => ['required', 'string', 'max:255'],
        ]);

        $host = $data['kind'] === 'SUBDOMAIN'
            ? $this->subdomainHost($data['host'])
            : CompanyDomain::normaliseHost($data['host']);

        if (! $this->looksLikeHost($host)) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('platform.domain_invalid'), [
                'host' => [__('platform.domain_invalid')],
            ]);
        }

        $taken = CompanyDomain::withoutGlobalScope(TenantScope::class)->where('host', $host)->exists();

        if ($taken) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('platform.domain_taken'), [
                'host' => [__('platform.domain_taken')],
            ]);
        }

        $domain = CompanyDomain::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $company->id,
            'host' => $host,
            'kind' => $data['kind'],
            'verification_token' => Str::lower(Str::random(32)),
            'verified_at' => $data['kind'] === 'SUBDOMAIN' ? now() : null,
            'is_primary' => ! CompanyDomain::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->where('is_primary', true)
                ->exists(),
        ]);

        $this->audit->event(
            'UPDATED',
            ['reason' => 'DOMAIN_ADDED', 'company_id' => $company->id, 'host' => $domain->host],
            userId: $request->user()->id,
            label: $company->name,
        );

        return ApiResponse::created($this->summary($domain));
    }

    public function verify(Request $request, string $domain): JsonResponse
    {
        $target = $this->domain($domain);

        if ($target->isVerified()) {
            return ApiResponse::ok($this->summary($target));
        }

        if (! $this->verifier->matches($target)) {
            // Not an error, in the sense that nothing is wrong with the
            // request — DNS simply has not propagated yet. 409 says "come
            // back in a moment", the same reading File API 19.1 gives a
            // scan still pending.
            throw ApiException::of(ErrorCode::CONFLICT, __('platform.domain_not_found_yet'));
        }

        $target->forceFill(['verified_at' => now()])->save();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'DOMAIN_VERIFIED', 'company_id' => $target->company_id, 'host' => $target->host],
            userId: $request->user()->id,
            label: $target->host,
        );

        return ApiResponse::ok($this->summary($target->fresh()));
    }

    public function primary(Request $request, string $domain): JsonResponse
    {
        $target = $this->domain($domain);

        if (! $target->isVerified()) {
            throw ApiException::of(ErrorCode::CONFLICT, __('platform.domain_verify_first'));
        }

        // Excludes $target: a bulk update after loading it would leave its
        // in-memory `original` stale, and the forceFill below would then see
        // is_primary unchanged (true to true) and skip writing it — quietly
        // leaving every domain, including this one, false in the database.
        CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $target->company_id)
            ->where('id', '!=', $target->id)
            ->update(['is_primary' => false]);

        $target->forceFill(['is_primary' => true])->save();

        return ApiResponse::ok($this->summary($target->fresh()));
    }

    public function destroy(Request $request, string $domain): JsonResponse
    {
        $target = $this->domain($domain);

        $target->delete();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'DOMAIN_REMOVED', 'company_id' => $target->company_id, 'host' => $target->host],
            userId: $request->user()->id,
            label: $target->host,
        );

        return ApiResponse::noContent();
    }

    private function domain(string $id): CompanyDomain
    {
        return CompanyDomain::withoutGlobalScope(TenantScope::class)->findOrFail($id);
    }

    private function subdomainHost(string $label): string
    {
        $base = config('tenancy.subdomain_host') ?: config('tenancy.platform_host');

        return CompanyDomain::normaliseHost(trim($label, '. ').'.'.$base);
    }

    private function looksLikeHost(string $host): bool
    {
        return (bool) preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CompanyDomain $domain): array
    {
        return [
            'id' => $domain->id,
            'company_id' => $domain->company_id,
            'host' => $domain->host,
            'kind' => $domain->kind,
            'is_verified' => $domain->isVerified(),
            'is_primary' => $domain->is_primary,
            'verification_record_name' => $domain->kind === 'CUSTOM' && ! $domain->isVerified()
                ? $domain->verificationRecordName()
                : null,
            'verification_token' => $domain->kind === 'CUSTOM' && ! $domain->isVerified()
                ? $domain->verification_token
                : null,
        ];
    }
}
