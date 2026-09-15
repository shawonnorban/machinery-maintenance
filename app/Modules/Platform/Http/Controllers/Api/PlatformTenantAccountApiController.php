<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Credential recovery for a customer's own account, from the platform
 * (Platform API §5; mirrors `TenantAccountController`'s email/password
 * halves, which this delegates to unchanged per ADR-003).
 *
 * Not housekeeping. Resetting a sign-in hands somebody outside the company
 * the keys to an account inside it, so it carries the same discipline as a
 * support grant: a written reason, an audit row, and a notification the
 * customer reads (SRS 5.4's spirit applied to a narrower act).
 *
 * Extends the plain `Controller`, not `ApiController`: nothing here reads
 * `ApiCaller` — the caller is a platform admin resolved by
 * `AuthenticatePlatformToken`, not a tenant token.
 */
class PlatformTenantAccountApiController extends Controller
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TenantContext $context,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function updateLogo(Request $request, Company $company): JsonResponse
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

        return ApiResponse::ok(['id' => $company->id, 'logo_url' => $company->fresh()->logoUrl()]);
    }

    public function updateEmail(Request $request, Company $company, User $member): JsonResponse
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
                'reason' => 'TENANT_LOGIN_EMAIL_CHANGED', 'company_id' => $company->id, 'user_id' => $member->id,
                'from' => $was, 'to' => $data['email'], 'stated_reason' => $data['reason'],
            ],
            userId: $request->user()->id,
            label: 'TENANT_LOGIN_EMAIL_CHANGED',
        );

        $this->tellTheCustomer($company, $member, 'email', $data['reason'], $request->user()->name);

        return ApiResponse::ok(['id' => $member->id, 'email' => $member->fresh()->email]);
    }

    public function resetPassword(
        Request $request,
        Company $company,
        User $member,
        IssueApiToken $tokens,
    ): JsonResponse {
        $this->assertMember($company, $member);

        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $password = Str::password(16, symbols: false);

        $member->forceFill(['password' => $password])->save();

        $tokens->revokeAllFor($member);
        DB::table('sessions')->where('user_id', $member->id)->delete();

        $this->audit->event(
            'PASSWORD_CHANGED',
            [
                'reason' => 'TENANT_PASSWORD_RESET_BY_PLATFORM', 'company_id' => $company->id,
                'user_id' => $member->id, 'email' => $member->email, 'stated_reason' => $data['reason'],
            ],
            userId: $request->user()->id,
            label: 'TENANT_PASSWORD_RESET_BY_PLATFORM',
        );

        $this->tellTheCustomer($company, $member, 'password', $data['reason'], $request->user()->name);

        // Returned once, here, and nowhere else — the same one-time-reveal
        // rule every minted secret in this API follows.
        return ApiResponse::ok(['email' => $member->email, 'password' => $password]);
    }

    private function assertMember(Company $company, User $member): void
    {
        $isMember = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('user_id', $member->id)
            ->exists();

        if (! $isMember) {
            abort(404);
        }
    }

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
