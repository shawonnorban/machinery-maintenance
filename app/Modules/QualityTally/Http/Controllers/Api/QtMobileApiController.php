<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Support\LicensedModules;
use App\Modules\QualityTally\Models\QtInspector;
use App\Modules\QualityTally\Models\QtPhone;
use App\Modules\QualityTally\Models\QtSession;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\QualityTally\Support\QtPhones;
use App\Modules\QualityTally\Support\QtPins;
use App\Modules\QualityTally\Support\QtTally;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

/**
 * The QC's phone (AnnotechERP 37, phase 1; user 2026-11-25): Company Code +
 * Employee ID + PIN in, a tally-only session out; the QC's own PIN; sign out;
 * and, as the phone itself, the sessions it opened offline and its QCs' taps.
 */
class QtMobileApiController extends ApiController
{
    /** The clock of a phone and of the server may differ by this much. */
    private const SLACK_MINUTES = 2;

    public function signIn(Request $request, IssueApiToken $tokens, TenantContext $context): JsonResponse
    {
        $d = $request->validate([
            'company_code' => ['required', 'string', 'max:50'],
            'employee_id' => ['required', 'string', 'max:50'],
            'pin' => ['required', 'digits:6'],
            'session_uuid' => ['required', 'uuid'],
        ] + QtPhones::rules('required'), [], __('qtally.attrs'));
        $ip = (string) $request->ip();
        $code = Str::upper(trim($d['company_code']));
        $employeeId = Str::upper(trim($d['employee_id']));
        $label = $code.' / '.$employeeId;

        $company = Company::query()->whereRaw('upper(code) = ?', [$code])->first();

        // A phone this company approved keeps its own count of wrong PINs, so nobody outside can lock a QC out of it.
        $known = $company === null ? null : QtPhones::find($company->id, $d['phone']['install_id']);
        $known = $known?->status() === 'APPROVED' && $known->knows($d['phone']['secret']) ? $known : null;
        $lock = 'qt-pin:'.sha1($code.'|'.$employeeId.'|'.($known?->id ?? 'new'));
        if (RateLimiter::tooManyAttempts($lock, QtPins::TRIES)) {
            $this->attempt($label, null, $ip, 'RATE_LIMITED');
            throw $this->locked($lock);
        }

        $employee = $company === null ? null : HrEmployee::query()->withoutGlobalScope(TenantScope::class)->where('company_id', $company->id)->whereRaw('upper(employee_code) = ?', [$employeeId])->first();
        $inspector = $employee === null ? null : QtInspector::query()->withoutGlobalScope(TenantScope::class)->where('company_id', $company->id)->where('hr_employee_id', $employee->id)->first();

        // One answer for a wrong company, ID or PIN: which of them it was is nobody else's to learn.
        if (! QtPins::matches($inspector, $d['pin'])) {
            RateLimiter::hit($lock, QtPins::LOCK_SECONDS);
            $this->attempt($label, $inspector?->user_id, $ip, 'BAD_PIN');
            $left = RateLimiter::remaining($lock, QtPins::TRIES);
            throw $left > 0 ? QtPhones::refuse('WRONG', __('qtally.phone.wrong', ['left' => $left]), ErrorCode::FORBIDDEN, ['left' => $left]) : $this->locked($lock);
        }

        $user = User::find($inspector->user_id);
        if ($company->isSuspended()) {
            throw ApiException::of(ErrorCode::TENANT_SUSPENDED);
        }
        if (! LicensedModules::permits($company->id, 'qtally.tally.create')) {
            throw QtPhones::refuse('MODULE_OFF', __('qtally.phone.module_off'));
        }
        if (! $inspector->active || $user === null || ! $user->isActive() || ! $user->belongsToCompany($company->id) || ! QtPhones::employed($inspector)) {
            $this->attempt($label, $inspector->user_id, $ip, 'ACCOUNT_INACTIVE');
            throw QtPhones::refuse('INACTIVE', __('qtally.phone.inactive'));
        }
        RateLimiter::clear($lock);

        $context->set($company->id, []);
        $phone = QtPhones::arrive($company->id, $d['phone'], $user->id, $ip);
        if ($phone->approved_at === null) {
            $phone->update(['requested_at' => $phone->requested_at ?? now()]);
            $this->attempt($label, $user->id, $ip, 'PHONE_PENDING');
            throw QtPhones::refuse('PHONE_PENDING', __('qtally.phone.pending', ['code' => self::shortCode($phone)]), ErrorCode::FORBIDDEN, ['code' => self::shortCode($phone)]);
        }
        $lines = $this->lines($inspector, $phone);
        if ($phone->line_id !== null && $lines === []) {
            $this->attempt($label, $user->id, $ip, 'OTHER_LINE');
            throw QtPhones::refuse('OTHER_LINE', __('qtally.phone.other_line', ['line' => $this->phoneLine($phone) ?? '—']));
        }

        [$session, $token, $plain] = DB::transaction(function () use ($phone, $d, $company, $inspector, $user, $tokens) {
            if (QtSession::query()->where('phone_id', $phone->id)->where('uuid', $d['session_uuid'])->exists()) {
                throw ValidationException::withMessages(['session_uuid' => __('qtally.phone.session_used')]);
            }
            // Whoever was signed in here has handed the phone over.
            QtPhones::endOn($phone, 'SWITCHED');
            $session = QtSession::query()->create(['company_id' => $company->id, 'uuid' => $d['session_uuid'], 'phone_id' => $phone->id, 'inspector_id' => $inspector->id, 'user_id' => $user->id, 'started_at' => now()]);
            ['token' => $token, 'plain' => $plain] = $tokens->forUser($user, $company->id, 'Quality Tally PIN', QtPhones::ABILITIES, 1);
            $token->forceFill(['expires_at' => now()->addHours(QtPhones::SESSION_HOURS), 'qt_session_id' => $session->id, 'qt_phone_id' => $phone->id])->save();

            return [$session, $token, $plain];
        });
        $this->attempt($label, $user->id, $ip, null);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::created(['access_token' => $plain, 'expires_at' => $token->expires_at->toIso8601String()]
            + $this->profile($company, $inspector, $employee, $phone, $session, $lines));
    }

    /** Who is signed in on this phone, where and until when: asked again whenever the app comes back. */
    public function session(): JsonResponse
    {
        [$session, $inspector, $phone] = $this->pinSession();

        return ApiResponse::ok(['expires_at' => $this->caller()->token->expiresAt()?->toIso8601String()]
            + $this->profile(Company::findOrFail($session->company_id), $inspector, HrEmployee::query()->findOrFail($inspector->hr_employee_id), $phone, $session, $this->lines($inspector, $phone)));
    }

    public function signOut(): JsonResponse
    {
        [$session] = $this->pinSession();
        QtPhones::endSession($session, 'SIGNED_OUT');

        return ApiResponse::noContent();
    }

    /** The QC's own PIN. The other phones they are signed in on are signed out. */
    public function changePin(Request $request): JsonResponse
    {
        [$session, $inspector] = $this->pinSession();
        $d = $request->validate(['current_pin' => ['required', 'digits:6'], 'pin' => ['required', 'digits:6', 'confirmed']], [], __('qtally.attrs'));

        $lock = 'qt-pin-change:'.$inspector->id;
        if (RateLimiter::tooManyAttempts($lock, QtPins::TRIES)) {
            throw $this->locked($lock);
        }
        if (! QtPins::matches($inspector, $d['current_pin'])) {
            RateLimiter::hit($lock, QtPins::LOCK_SECONDS);
            throw ValidationException::withMessages(['current_pin' => __('qtally.phone.current_pin')]);
        }
        if (QtPins::weak($d['pin'])) {
            throw ValidationException::withMessages(['pin' => __('qtally.phone.weak_pin')]);
        }
        if ($d['pin'] === $d['current_pin']) {
            throw ValidationException::withMessages(['pin' => __('qtally.phone.same_pin')]);
        }
        RateLimiter::clear($lock);
        DB::transaction(function () use ($inspector, $session, $d): void {
            QtPins::set($inspector, $d['pin']);
            QtPhones::endFor($inspector, 'PIN_CHANGED', $session->id);
        });

        return ApiResponse::ok(['pin_default' => false]);
    }

    /**
     * A QC who signed in on the app with email and password (user, 2026-11-25:
     * both ways in): the phone gets a QC session for them, as a PIN sign-in
     * would, so their taps go the same way. The token stays theirs, whole;
     * somebody who is not a QC is told so and uses the app without the tally.
     */
    public function attach(Request $request): JsonResponse
    {
        $d = $request->validate(['session_uuid' => ['required', 'uuid']]);
        if ($this->caller()->token->pinSessionId() !== null || $this->caller()->user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }
        $phoneId = PersonalAccessToken::query()->whereKey($this->caller()->token->id())->value('qt_phone_id');
        $phone = $phoneId === null ? null : QtPhone::query()->find($phoneId);
        $user = $this->caller()->user;
        $inspector = QtInspector::query()->where('user_id', $user->id)->first();
        if ($phone === null || $inspector === null || ! $inspector->active || ! QtPhones::employed($inspector) || QtPhones::tapSections($user, $phone->company_id) === []) {
            throw QtPhones::refuse('NOT_QC', __('qtally.phone.not_qc'));
        }
        if ($phone->blocked_at !== null) {
            throw QtPhones::refuse('PHONE_BLOCKED', __('qtally.phone.blocked'));
        }
        if ($phone->approved_at === null) {
            $phone->update(['requested_at' => $phone->requested_at ?? now()]);
            throw QtPhones::refuse('PHONE_PENDING', __('qtally.phone.pending', ['code' => self::shortCode($phone)]), ErrorCode::FORBIDDEN, ['code' => self::shortCode($phone)]);
        }
        $lines = $this->lines($inspector, $phone);
        if ($phone->line_id !== null && $lines === []) {
            throw QtPhones::refuse('OTHER_LINE', __('qtally.phone.other_line', ['line' => $this->phoneLine($phone) ?? '—']));
        }
        $session = DB::transaction(function () use ($phone, $d, $inspector, $user) {
            if (QtSession::query()->where('phone_id', $phone->id)->where('uuid', $d['session_uuid'])->exists()) {
                throw ValidationException::withMessages(['session_uuid' => __('qtally.phone.session_used')]);
            }
            QtPhones::endOn($phone, 'SWITCHED');

            return QtSession::query()->create(['company_id' => $phone->company_id, 'uuid' => $d['session_uuid'], 'phone_id' => $phone->id, 'inspector_id' => $inspector->id, 'user_id' => $user->id, 'started_at' => now()]);
        });

        return ApiResponse::created($this->profile(Company::findOrFail($phone->company_id), $inspector, HrEmployee::query()->findOrFail($inspector->hr_employee_id), $phone, $session, $lines));
    }

    /* ── As the phone itself ────────────────────────────────────────── */

    /** The phone checks in: still approved, its line, and the server's clock. */
    public function hello(Request $request): JsonResponse
    {
        $phone = $this->phone($request);

        return ApiResponse::ok(['phone' => $this->phoneOut($phone), 'server_time' => now()->toIso8601String()]);
    }

    /**
     * Sessions the phone opened or closed while there was no network. A QC may
     * only have opened one on a phone they signed in on (with the server) within
     * the last days; the answer names those refused.
     */
    public function syncSessions(Request $request): JsonResponse
    {
        $phone = $this->phone($request);
        $d = $request->validate([
            'sessions' => ['required', 'array', 'max:100'],
            'sessions.*.uuid' => ['required', 'uuid'],
            'sessions.*.employee_id' => ['required', 'string', 'max:50'],
            'sessions.*.started_at' => ['required', 'date'],
            'sessions.*.ended_at' => ['nullable', 'date'],
        ]);
        $zone = (string) config('app.timezone');
        $out = ['saved' => [], 'rejected' => []];

        foreach (collect($d['sessions'])->sortBy(fn ($r) => CarbonImmutable::parse($r['started_at'])->getTimestamp()) as $row) {
            $started = CarbonImmutable::parse($row['started_at'])->setTimezone($zone);
            $ended = empty($row['ended_at']) ? null : CarbonImmutable::parse($row['ended_at'])->setTimezone($zone);
            $session = QtSession::query()->where('phone_id', $phone->id)->where('uuid', $row['uuid'])->first();

            if ($session === null) {
                $inspector = $this->inspectorByCode($phone->company_id, $row['employee_id']);
                $seen = $inspector !== null && QtSession::query()->where('phone_id', $phone->id)->where('inspector_id', $inspector->id)->where('offline', false)
                    ->whereBetween('started_at', [$started->subDays(QtPhones::OFFLINE_DAYS), $started])->exists();
                $sane = $started->lessThanOrEqualTo(now()->addMinutes(self::SLACK_MINUTES)) && $started->greaterThan(now()->subDays(QtBook::LATE_DAYS + 1)) && ($ended === null || $ended->greaterThanOrEqualTo($started));
                if (! $seen || ! $inspector->active || ! $sane) {
                    $out['rejected'][] = ['uuid' => $row['uuid'], 'message' => __('qtally.phone.offline_refused')];

                    continue;
                }
                $session = QtSession::query()->create(['company_id' => $phone->company_id, 'uuid' => $row['uuid'], 'phone_id' => $phone->id, 'inspector_id' => $inspector->id, 'user_id' => $inspector->user_id, 'offline' => true, 'started_at' => $started]);
            }
            // The phone knows when the QC left; the server may only have noticed later.
            if ($ended !== null && $ended->greaterThanOrEqualTo($session->started_at) && ($session->ended_at === null || $ended->lessThan($session->ended_at))) {
                QtPhones::endSession($session, $session->end_reason ?? 'SIGNED_OUT');
                $session->update(['ended_at' => $ended]);
            }
            $out['saved'][] = $row['uuid'];
        }

        return ApiResponse::ok($out + ['server_time' => now()->toIso8601String()]);
    }

    /**
     * The phone's waiting taps, its QCs' mixed: each names its session, and
     * counts for that QC if the session was theirs, on this phone, at that moment.
     */
    public function taps(Request $request): JsonResponse
    {
        $phone = $this->phone($request);
        $request->validate(['entries' => ['required', 'array', 'max:500']]);
        $named = collect($request->input('entries'))->map(fn ($e) => is_array($e) ? ($e['session'] ?? null) : null)->filter(fn ($v) => is_string($v) && Str::isUuid($v))->unique()->values();
        $sessions = QtSession::query()->where('phone_id', $phone->id)->whereIn('uuid', $named)->get()->keyBy('uuid');
        $may = [];
        $out = ['recorded' => 0, 'duplicate' => 0, 'rejected' => []];

        foreach ($request->input('entries') as $i => $raw) {
            $raw = is_array($raw) ? $raw : [];
            try {
                $session = is_string($raw['session'] ?? null) ? $sessions->get($raw['session']) : null;
                if ($session === null) {
                    throw ValidationException::withMessages(['session' => __('qtally.phone.no_session')]);
                }
                $d = QtTallyApiController::tap($raw);
                if (empty($d['occurred_at']) || empty($d['client_uuid'])) {
                    throw ValidationException::withMessages(['occurred_at' => __('qtally.phone.tap_time')]);
                }
                $at = CarbonImmutable::parse($d['occurred_at']);
                $from = CarbonImmutable::instance($session->started_at)->subMinutes(self::SLACK_MINUTES);
                $to = CarbonImmutable::instance($session->ended_at ?? now())->addMinutes(self::SLACK_MINUTES);
                if ($at->lessThan($from) || $at->greaterThan($to)) {
                    throw ValidationException::withMessages(['session' => __('qtally.phone.not_signed_in_then')]);
                }
                if ($phone->line_id !== null && ($phone->section !== $d['section'] || $phone->line_id !== $d['line_id'])) {
                    throw ValidationException::withMessages(['line_id' => __('qtally.phone.tap_other_line', ['line' => $this->phoneLine($phone) ?? '—'])]);
                }
                if (! ($may[$session->user_id.$d['section']] ??= $this->mayTap($session->user_id, $phone->company_id, $d['section']))) {
                    throw ValidationException::withMessages(['section' => __('qtally.v.not_your_line')]);
                }
                $res = QtTally::record($phone->company_id, $session->user_id, $d + ['phone_id' => $phone->id, 'session_id' => $session->id]);
                $res['duplicate'] ? $out['duplicate']++ : $out['recorded']++;
            } catch (ValidationException $e) {
                $message = (string) collect($e->errors())->flatten()->first();
                $out['rejected'][] = ['index' => $i, 'client_uuid' => $raw['client_uuid'] ?? null, 'message' => $message];
                QtTally::refused($phone->company_id, $raw, $message, $session?->user_id, $phone->id, $session?->id);
            }
        }

        return ApiResponse::ok($out + ['server_time' => now()->toIso8601String()]);
    }

    /** Six characters a person can read out: the waiting phone's, on its screen and on the Phones page. */
    public static function shortCode(QtPhone $phone): string
    {
        return Str::upper(substr(sha1($phone->install_id), 0, 6));
    }

    /**
     * @param  list<array{section: string, line_id: string, label: string}>  $lines
     * @return array<string, mixed>
     */
    private function profile(Company $company, QtInspector $inspector, HrEmployee $employee, QtPhone $phone, QtSession $session, array $lines): array
    {
        $shift = QtBook::shiftOf($inspector->hr_employee_id, QtBook::now(), QtBook::shifts($company->id));

        return [
            'company' => ['id' => $company->id, 'code' => $company->code, 'name' => $company->name],
            'qc' => ['employee_id' => $employee->employee_code, 'name' => $employee->name, 'designation' => DB::table('designations')->where('id', $employee->designation_id)->value('name')],
            'session' => ['uuid' => $session->uuid, 'started_at' => $session->started_at->toIso8601String()],
            'phone' => $this->phoneOut($phone),
            'shift' => $shift === null ? null : ['name' => $shift->name, 'start' => $shift->start, 'end' => $shift->end],
            'lines' => $lines,
            'pin_default' => QtPins::isDefault($inspector),
            'offline_days' => QtPhones::OFFLINE_DAYS,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * The QC's lines; only the phone's own when it is tied to one.
     *
     * @return list<array{section: string, line_id: string, label: string}>
     */
    private function lines(QtInspector $inspector, QtPhone $phone): array
    {
        $all = QtBook::allLines($inspector->company_id);

        return $inspector->lines()->get()
            ->filter(fn ($l) => $phone->line_id === null || ($l->section === $phone->section && $l->line_id === $phone->line_id))
            ->map(fn ($l) => ['section' => $l->section, 'line_id' => $l->line_id, 'label' => QtBook::label($all->get($l->section.':'.$l->line_id))])
            ->filter(fn ($l) => $l['label'] !== null)->sortBy(fn ($l) => $l['section'].'|'.$l['label'])->values()->all();
    }

    /** @return array{name: ?string, section: ?string, line_id: ?string, line: ?string} */
    private function phoneOut(QtPhone $phone): array
    {
        return ['name' => $phone->name, 'section' => $phone->section, 'line_id' => $phone->line_id, 'line' => $this->phoneLine($phone)];
    }

    private function phoneLine(QtPhone $phone): ?string
    {
        return $phone->line_id === null ? null : QtBook::label(QtBook::lines($phone->company_id, (string) $phone->section)->get($phone->line_id));
    }

    private function inspectorByCode(string $companyId, string $code): ?QtInspector
    {
        $employee = HrEmployee::query()->where('company_id', $companyId)->whereRaw('upper(employee_code) = ?', [Str::upper(trim($code))])->value('id');

        return $employee === null ? null : QtInspector::query()->where('company_id', $companyId)->where('hr_employee_id', $employee)->first();
    }

    private function mayTap(string $userId, string $companyId, string $section): bool
    {
        $user = User::find($userId);

        return $user !== null && in_array($section, QtPhones::tapSections($user, $companyId), true);
    }

    private function locked(string $lock): ApiException
    {
        $seconds = RateLimiter::availableIn($lock);

        return QtPhones::refuse('PIN_LOCKED', __('qtally.phone.locked', ['minutes' => max(1, (int) ceil($seconds / 60))]), ErrorCode::RATE_LIMITED, ['retry_after' => $seconds]);
    }

    private function phone(Request $request): QtPhone
    {
        return $request->attributes->get('qt_phone');
    }

    /** @return array{0: QtSession, 1: QtInspector, 2: QtPhone} */
    private function pinSession(): array
    {
        $id = $this->caller()->token->pinSessionId();
        if ($id === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }
        $session = QtSession::query()->findOrFail($id);

        return [$session, QtInspector::query()->findOrFail($session->inspector_id), QtPhone::query()->findOrFail($session->phone_id)];
    }

    private function attempt(string $label, ?string $userId, string $ip, ?string $failure): void
    {
        LoginAttempt::create(['email' => $label, 'user_id' => $userId, 'ip_address' => $ip, 'successful' => $failure === null, 'failure_reason' => $failure, 'attempted_at' => now()]);
    }
}
