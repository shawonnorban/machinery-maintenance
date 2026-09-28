<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A customer's own details, corrected from the platform (SRS 3.1).
 *
 * Two different things live here and they carry very different weight.
 *
 * Editing a company's name, timezone or currency is housekeeping: a typo at
 * onboarding, a legal name that changed. The company *code* is not on the list
 * — it is inside every work order and breakdown number the customer has ever
 * issued, and changing it would leave documents referring to a code that no
 * longer exists.
 *
 * Resetting a sign-in is not housekeeping. It hands somebody outside the
 * company the keys to an account inside it, permanently and invisibly, which
 * is more than a support grant does — a grant expires and is announced. So it
 * carries the same discipline as one: a written reason, an audit row, and a
 * notification the customer reads. It exists because the alternative is worse:
 * an owner locked out of their own company with no way back in, since password
 * reset needs an inbox they may no longer have.
 */
class TenantAccountController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TenantContext $context,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'base_currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64'],
            'default_locale' => ['required', 'in:en,bn'],
        ]);

        $company->fill($data)->save();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'TENANT_DETAILS_EDITED', 'company_id' => $company->id] + $data,
            userId: $request->user()->id,
            label: 'TENANT_DETAILS_EDITED',
        );

        return back()->with('status', __('platform.details_saved'));
    }

    /**
     * Replace the customer's logo.
     *
     * Stored on the public disk directly rather than through
     * StoreFileAttachment: that action writes a tenant-scoped row for a photo
     * or a document somebody may need to produce as evidence later (SRS 37,
     * SRS 13.4), and a logo is neither — it is shown in an <img> tag on every
     * page load and never downloaded, so the machinery built for the first
     * kind of file (a scan queue, a company-scoped attachment row) would sit
     * here unused.
     */
    public function updateLogo(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:512'],
        ]);

        if ($company->logo_path !== null) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $path = $data['logo']->store('company-logos', 'public');

        $company->forceFill(['logo_path' => $path])->save();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'TENANT_LOGO_CHANGED', 'company_id' => $company->id],
            userId: $request->user()->id,
            label: 'TENANT_LOGO_CHANGED',
        );

        return back()->with('status', __('platform.logo_saved'));
    }

    /**
     * Change the address an account signs in with.
     *
     * Separate from the password reset below, because they are different
     * accidents: a mistyped address at onboarding leaves somebody unable to
     * receive a reset link at all, which is the one thing that makes the reset
     * route useless.
     */
    public function updateEmail(Request $request, Company $company, User $member): RedirectResponse
    {
        $this->assertMember($company, $member);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $was = $member->email;

        $member->forceFill(['email' => $data['email']])->save();

        $this->audit->event(
            'UPDATED',
            [
                'reason' => 'TENANT_LOGIN_EMAIL_CHANGED',
                'company_id' => $company->id,
                'user_id' => $member->id,
                'from' => $was,
                'to' => $data['email'],
                'stated_reason' => $data['reason'],
            ],
            userId: $request->user()->id,
            label: 'TENANT_LOGIN_EMAIL_CHANGED',
        );

        $this->tellTheCustomer($company, $member, 'email', $data['reason'], $request->user()->name);

        return back()->with('status', __('platform.email_changed', ['email' => $data['email']]));
    }

    /**
     * Issue a new password for one of the customer's accounts.
     *
     * Shown once on the next screen, for whoever is helping to read out. Not
     * emailed: the address is often exactly what is broken, and a password
     * sent to an address nobody can open is the same as no password at all.
     */
    public function resetPassword(
        Request $request,
        Company $company,
        User $member,
        IssueApiToken $tokens,
    ): RedirectResponse {
        $this->assertMember($company, $member);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $password = Str::password(16, symbols: false);

        $member->forceFill(['password' => $password])->save();

        // Everything the old password could still reach goes with it. A reset
        // that leaves a live session or a valid token behind has not reset
        // anything for whoever was misusing it.
        $tokens->revokeAllFor($member);

        DB::table('sessions')->where('user_id', $member->id)->delete();

        $this->audit->event(
            'PASSWORD_CHANGED',
            [
                'reason' => 'TENANT_PASSWORD_RESET_BY_PLATFORM',
                'company_id' => $company->id,
                'user_id' => $member->id,
                'email' => $member->email,
                'stated_reason' => $data['reason'],
            ],
            userId: $request->user()->id,
            label: 'TENANT_PASSWORD_RESET_BY_PLATFORM',
        );

        $this->tellTheCustomer($company, $member, 'password', $data['reason'], $request->user()->name);

        return back()
            ->with('status', __('platform.password_reset_done', ['name' => $member->name]))
            ->with('reset_email', $member->email)
            ->with('reset_password', $password);
    }

    private function assertMember(Company $company, User $member): void
    {
        $isMember = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('user_id', $member->id)
            ->exists();

        if (! $isMember) {
            // A user id from another company must not be reachable by changing
            // a number in the URL — the one place where this screen could
            // otherwise reach across the tenancy.
            abort(404);
        }
    }

    /**
     * Tell the company's administrators what was done to one of their accounts.
     *
     * The whole justification for platform staff being able to do this at all
     * is that the customer finds out. An unannounced credential change is
     * indistinguishable from a compromise, and would be the one action here
     * that the audit trail records and nobody reads.
     */
    private function tellTheCustomer(
        Company $company,
        User $member,
        string $what,
        string $reason,
        string $staffName,
    ): void {
        $restore = $this->context->companyIdOrNull();
        $this->context->set($company->id);

        try {
            $userIds = CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->where('status', 'ACTIVE')
                ->pluck('user_id');

            $recipients = User::whereIn('id', $userIds)->get();

            if ($recipients->isEmpty()) {
                return;
            }

            $this->notifications->sendToMany($recipients, 'SUPPORT_ACCESS', [
                'name' => $staffName,
                'company' => $company->name,
                'reason' => __('platform.credential_change_notice', [
                    'what' => __('platform.credential_'.$what),
                    'account' => $member->name,
                    'reason' => $reason,
                ]),
                'until' => now()->toDayDateTimeString(),
            ], 'WARNING');
        } finally {
            $this->context->forget();

            if ($restore !== null) {
                $this->context->set($restore);
            }
        }
    }
}
