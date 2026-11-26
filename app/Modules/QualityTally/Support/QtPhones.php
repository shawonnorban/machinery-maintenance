<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Support;

use App\Modules\Api\Support\TokenHandle;
use App\Modules\QualityTally\Models\QtInspector;
use App\Modules\QualityTally\Models\QtPhone;
use App\Modules\QualityTally\Models\QtSession;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Phones and the QC sessions on them (AnnotechERP 37, phase 1). Used before a
 * tenant is known (sign-in), so the company is always named.
 */
final class QtPhones
{
    /** A PIN session never lasts longer than a shift and its overtime. */
    public const SESSION_HOURS = 14;

    /** A QC seen on a phone in this many days may sign in on it without a network. */
    public const OFFLINE_DAYS = 7;

    /** All a PIN session may do: tap. Whatever else the user's roles allow, it does not reach. */
    public const ABILITIES = ['qtally.tally.create', 'qtally.cutting.tally', 'qtally.sewing.tally', 'qtally.quality.tally'];

    /** The routes a PIN session's token opens; every other one refuses it, permission or not. */
    private const ROUTES = ['api.v1.auth.me', 'api.v1.auth.permissions', 'api.v1.quality-tally.tally.*', 'api.v1.quality-tally.cut-bundles.scan', 'api.v1.quality-tally.mobile.*'];

    public static function find(string $companyId, string $installId): ?QtPhone
    {
        return QtPhone::query()->withoutGlobalScope(TenantScope::class)->where('company_id', $companyId)->where('install_id', $installId)->first();
    }

    /**
     * The phone a sign-in comes from, noted (model, version, last seen). One that
     * names an id without its secret is somebody else's; a blocked one gets no further.
     *
     * @param  array{install_id: string, secret: string, model?: ?string, os?: ?string, app_version?: ?string}  $d
     */
    public static function arrive(string $companyId, array $d, string $userId, ?string $ip): QtPhone
    {
        $phone = self::find($companyId, $d['install_id']);
        if ($phone !== null && ! $phone->knows($d['secret'])) {
            throw self::refuse('PHONE_UNKNOWN', __('qtally.phone.unknown'));
        }
        if ($phone?->blocked_at !== null) {
            throw self::refuse('PHONE_BLOCKED', __('qtally.phone.blocked'));
        }
        $phone ??= new QtPhone(['company_id' => $companyId, 'install_id' => $d['install_id'], 'secret_hash' => hash('sha256', $d['secret']), 'first_user_id' => $userId]);
        $phone->fill(['model' => $d['model'] ?? $phone->model, 'os' => $d['os'] ?? $phone->os, 'app_version' => $d['app_version'] ?? $phone->app_version, 'last_ip' => $ip, 'last_seen_at' => now()])->save();

        return $phone;
    }

    /** @return array<string, list<string>> the rules of a sign-in's `phone` */
    public static function rules(string $presence): array
    {
        return [
            'phone' => [$presence, 'array'],
            'phone.install_id' => ['required_with:phone', 'uuid'],
            'phone.secret' => ['required_with:phone', 'string', 'min:32', 'max:128'],
            'phone.model' => ['nullable', 'string', 'max:80'],
            'phone.os' => ['nullable', 'string', 'max:40'],
            'phone.app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Ends a phone's open sessions and the tokens they hold. */
    public static function endOn(QtPhone $phone, string $reason): void
    {
        QtSession::query()->withoutGlobalScope(TenantScope::class)->where('phone_id', $phone->id)->whereNull('ended_at')->get()
            ->each(fn (QtSession $s) => self::endSession($s, $reason));
    }

    /** Ends a QC's open sessions on every phone, but for one. */
    public static function endFor(QtInspector $inspector, string $reason, ?string $except = null): void
    {
        QtSession::query()->withoutGlobalScope(TenantScope::class)->where('inspector_id', $inspector->id)->whereNull('ended_at')
            ->when($except, fn ($q) => $q->whereKeyNot($except))->get()->each(fn (QtSession $s) => self::endSession($s, $reason));
    }

    public static function endSession(QtSession $session, string $reason): void
    {
        $session->update(['ended_at' => now(), 'end_reason' => $reason]);
        PersonalAccessToken::query()->where('qt_session_id', $session->id)->delete();
    }

    /** Blocks a phone: its QC sessions end, and every token signed in on it, email ones too. */
    public static function block(QtPhone $phone, ?string $by): void
    {
        $phone->update(['blocked_at' => now(), 'blocked_by' => $by]);
        self::endOn($phone, 'BLOCKED');
        PersonalAccessToken::query()->where('qt_phone_id', $phone->id)->delete();
    }

    /**
     * A PIN session's token, on every request: only its own routes, and only
     * while the session, the phone, the QC and their HR employee all stand.
     */
    public static function assertPinSession(TokenHandle $token, ?string $route): void
    {
        $session = QtSession::query()->withoutGlobalScope(TenantScope::class)->find($token->pinSessionId());
        $phone = $session === null ? null : QtPhone::query()->withoutGlobalScope(TenantScope::class)->find($session->phone_id);
        $inspector = $session?->inspector_id === null ? null : QtInspector::query()->withoutGlobalScope(TenantScope::class)->find($session->inspector_id);

        if ($session === null || $session->ended_at !== null || $phone?->status() !== 'APPROVED' || ! $inspector?->active || ! self::employed($inspector)) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }
        if ($route === null || ! Str::is(self::ROUTES, $route)) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }
    }

    /**
     * The sections this user may tap in for this company today — none for a
     * user switched off, read-only, or no longer in it.
     *
     * @return list<string>
     */
    public static function tapSections(\App\Modules\Identity\Models\User $user, string $companyId): array
    {
        if (! $user->isActive() || $user->isReadOnly() || ! $user->belongsToCompany($companyId)) {
            return [];
        }
        $gate = \Illuminate\Support\Facades\Gate::forUser($user);

        return array_values(array_filter(QtBook::SECTIONS, fn (string $s): bool => $gate->any(['qtally.tally.create', 'qtally.'.strtolower($s).'.tally'])));
    }

    /** The QC's HR employee is still working here. */
    public static function employed(QtInspector $inspector): bool
    {
        return $inspector->hr_employee_id !== null && DB::table('hr_employees')
            ->where('id', $inspector->hr_employee_id)->where('company_id', $inspector->company_id)->where('status', 'ACTIVE')->where('active', true)->exists();
    }

    /** What the app branches on is `meta.reason`; the message is for the person. */
    public static function refuse(string $reason, string $message, ErrorCode $code = ErrorCode::FORBIDDEN, array $meta = []): ApiException
    {
        return new ApiException($code, $message, meta: ['reason' => $reason] + $meta);
    }
}
