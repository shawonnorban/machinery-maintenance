<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\CompanyDomain;
use App\Modules\Tenancy\Services\DomainVerifier;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The addresses a customer reaches their system on (SRS §3.1).
 *
 * Two kinds, and they are not the same amount of work for anybody.
 *
 * A subdomain — delta.example.com — is on a host we already control. One
 * wildcard DNS record and one wildcard certificate cover every customer who
 * will ever exist, so it is issued here and works immediately.
 *
 * A custom domain — maintenance.deltaapparels.com — is the customer's own, and
 * three things have to be true before it can work: they point it at us, they
 * prove they own it, and the server has a certificate for it. Only the middle
 * one is ours. The screen says so rather than letting somebody add a row and
 * wonder why the address is dead.
 */
class TenantDomainController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly DomainVerifier $verifier,
    ) {}

    public function store(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', CompanyDomain::KINDS)],
            'host' => ['required', 'string', 'max:255'],
        ]);

        $host = $data['kind'] === 'SUBDOMAIN'
            ? $this->subdomainHost($data['host'])
            : CompanyDomain::normaliseHost($data['host']);

        if (! $this->looksLikeHost($host)) {
            return back()->withInput()->withErrors(['host' => __('platform.domain_invalid')]);
        }

        // Unique across every customer, checked here rather than left to the
        // unique index so the answer is a sentence instead of a 500.
        $taken = CompanyDomain::withoutGlobalScope(TenantScope::class)->where('host', $host)->exists();

        if ($taken) {
            return back()->withInput()->withErrors(['host' => __('platform.domain_taken')]);
        }

        $domain = CompanyDomain::withoutGlobalScope(TenantScope::class)->create([
            'company_id' => $company->id,
            'host' => $host,
            'kind' => $data['kind'],
            'verification_token' => Str::lower(Str::random(32)),
            // A subdomain is on a host we control, so there is nothing for the
            // customer to prove. A custom domain has everything to prove.
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

        return back()->with('status', $domain->isVerified()
            ? __('platform.domain_live', ['host' => $domain->host])
            : __('platform.domain_added', ['host' => $domain->host]));
    }

    public function verify(Request $request, string $domain): RedirectResponse
    {
        $target = $this->domain($domain);

        if ($target->isVerified()) {
            return back();
        }

        if (! $this->verifier->matches($target)) {
            // Not an error. DNS takes time to propagate, and the honest answer
            // is "not yet" rather than "wrong".
            return back()->withErrors(['host' => __('platform.domain_not_found_yet')]);
        }

        $target->forceFill(['verified_at' => now()])->save();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'DOMAIN_VERIFIED', 'company_id' => $target->company_id, 'host' => $target->host],
            userId: $request->user()->id,
            label: $target->host,
        );

        return back()->with('status', __('platform.domain_live', ['host' => $target->host]));
    }

    public function primary(Request $request, string $domain): RedirectResponse
    {
        $target = $this->domain($domain);

        // Only a working address can be the one the customer is told to use.
        if (! $target->isVerified()) {
            return back()->withErrors(['host' => __('platform.domain_verify_first')]);
        }

        CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $target->company_id)
            ->update(['is_primary' => false]);

        $target->forceFill(['is_primary' => true])->save();

        return back()->with('status', __('platform.domain_primary_set', ['host' => $target->host]));
    }

    public function destroy(Request $request, string $domain): RedirectResponse
    {
        $target = $this->domain($domain);

        $target->delete();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'DOMAIN_REMOVED', 'company_id' => $target->company_id, 'host' => $target->host],
            userId: $request->user()->id,
            label: $target->host,
        );

        return back()->with('status', __('platform.domain_removed', ['host' => $target->host]));
    }

    private function domain(string $id): CompanyDomain
    {
        // Resolved by hand: CompanyDomain is tenant-scoped, and model binding
        // would find nothing for platform staff, who belong to no company.
        return CompanyDomain::withoutGlobalScope(TenantScope::class)->findOrFail($id);
    }

    /**
     * A label on our own host, so the customer supplies only the first part.
     */
    private function subdomainHost(string $label): string
    {
        $base = config('tenancy.subdomain_host') ?: config('tenancy.platform_host');

        return CompanyDomain::normaliseHost(trim($label, '. ').'.'.$base);
    }

    private function looksLikeHost(string $host): bool
    {
        // Deliberately plain: at least two labels, letters, digits and hyphens.
        // Anything cleverer rejects a valid address somebody actually owns.
        return (bool) preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host);
    }
}
