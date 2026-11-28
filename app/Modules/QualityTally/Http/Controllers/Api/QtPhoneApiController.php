<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\QualityTally\Models\QtPhone;
use App\Modules\QualityTally\Models\QtSession;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\QualityTally\Support\QtPhones;
use App\Modules\QualityTally\Support\QtSettings;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Quality Tally → Phones (AnnotechERP 37): the phones the app runs on. A new
 * one waits here until it is approved — unless approval is switched off
 * (user, 2026-11-28); it can be named, tied to a line, blocked and deleted.
 */
class QtPhoneApiController extends ApiController
{
    private const MANAGE = 'qtally.phone.manage';

    public function index(): JsonResponse
    {
        $this->allow(self::MANAGE);
        $phones = QtPhone::query()->get();
        $lines = QtBook::allLines($this->caller()->companyId);
        $open = QtSession::query()->whereIn('phone_id', $phones->pluck('id'))->whereNull('ended_at')->orderByDesc('started_at')->get()->unique('phone_id')->keyBy('phone_id');
        $used = QtSession::query()->whereIn('phone_id', $phones->pluck('id'))->distinct()->pluck('phone_id')->flip();
        $names = DB::table('users')->whereIn('id', $open->pluck('user_id')->merge($phones->pluck('first_user_id'))->merge($phones->pluck('approved_by'))->filter()->unique()->values())->pluck('name', 'id');
        $order = ['PENDING' => 0, 'APPROVED' => 1, 'PERSONAL' => 2, 'BLOCKED' => 3];

        return ApiResponse::ok($phones->map(function (QtPhone $p) use ($lines, $open, $used, $names) {
            $s = $open->get($p->id);

            return [
                'id' => $p->id, 'code' => QtMobileApiController::shortCode($p), 'name' => $p->name, 'status' => $p->status(),
                'section' => $p->section, 'line_id' => $p->line_id, 'line' => $p->line_id ? QtBook::label($lines->get($p->section.':'.$p->line_id)) : null,
                'model' => $p->model, 'os' => $p->os, 'app_version' => $p->app_version, 'waiting_taps' => $p->waiting_taps,
                'last_seen_at' => $p->last_seen_at?->toIso8601String(), 'requested_at' => $p->requested_at?->toIso8601String(),
                'first_user' => $names[$p->first_user_id] ?? null, 'approved_by' => $names[$p->approved_by] ?? null,
                'signed_in' => $s ? ['name' => $names[$s->user_id] ?? null, 'since' => $s->started_at->toIso8601String()] : null,
                'used' => $used->has($p->id),
            ];
        })->sortBy(fn ($r) => $order[$r['status']].'|'.mb_strtolower((string) $r['name']).'|'.$r['code'])->values()->all(),
            ['approval' => QtSettings::phoneApproval($this->caller()->companyId)]);
    }

    /**
     * Phone approval on or off (user, 2026-11-28). Off, every phone waiting now
     * is let in too, as the next one will be on its first sign-in.
     */
    public function approval(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);
        $on = (bool) $request->validate(['on' => ['required', 'boolean']])['on'];
        $company = $this->caller()->companyId;
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($company, $on, $by): void {
            QtSettings::setPhoneApproval($company, $on, $by);
            if (! $on) {
                QtPhone::query()->whereNull('approved_at')->whereNull('blocked_at')->whereNotNull('requested_at')->update(['approved_at' => now(), 'approved_by' => $by]);
            }
        });

        return ApiResponse::ok(['approval' => $on]);
    }

    /** Names a phone and ties it to a line; with `approve`, lets QCs sign in on it. */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->allow(self::MANAGE);
        $phone = QtPhone::query()->findOrFail($id);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('qt_phones', 'name')->where('company_id', $phone->company_id)->ignore($phone->id)],
            'section' => ['nullable', Rule::in(QtBook::SECTIONS), 'required_with:line_id'],
            'line_id' => ['nullable', 'string', 'size:26'],
            'approve' => ['sometimes', 'boolean'],
        ], ['name.unique' => __('qtally.phone.name_taken')], __('qtally.attrs'));
        $line = $d['line_id'] ?? null;
        if ($line !== null && ! QtBook::lines($phone->company_id, $d['section'])->has($line)) {
            throw ValidationException::withMessages(['line_id' => __('qtally.v.line')]);
        }
        if ($phone->blocked_at !== null) {
            throw ValidationException::withMessages(['name' => __('qtally.phone.unblock_first')]);
        }
        $moved = $phone->line_id !== $line;
        DB::transaction(function () use ($phone, $d, $line, $moved): void {
            $phone->update(['name' => trim($d['name']), 'section' => $line === null ? null : $d['section'], 'line_id' => $line]
                + (($d['approve'] ?? false) && $phone->approved_at === null ? ['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()] : []));
            // A phone moved to another line: whoever is on it signs in again, on that line.
            if ($moved) {
                QtPhones::endOn($phone, 'LINE_CHANGED');
            }
        });

        return ApiResponse::ok(['id' => $phone->id, 'status' => $phone->status()]);
    }

    public function block(string $id): JsonResponse
    {
        $this->allow(self::MANAGE);
        $phone = QtPhone::query()->findOrFail($id);
        DB::transaction(fn () => QtPhones::block($phone, $this->caller()->auditUserId()));

        return ApiResponse::ok(['id' => $id, 'status' => $phone->status()]);
    }

    /** Back to waiting: an unblocked phone is approved anew before a QC signs in on it. */
    public function unblock(string $id): JsonResponse
    {
        $this->allow(self::MANAGE);
        $phone = QtPhone::query()->findOrFail($id);
        $phone->update(['blocked_at' => null, 'blocked_by' => null, 'approved_at' => null, 'approved_by' => null]);

        return ApiResponse::ok(['id' => $id, 'status' => $phone->status()]);
    }

    /**
     * Takes a phone off the list (user, 2026-11-28: any phone): whoever is on it
     * is signed out and every token on it ends. Its taps stay; if it is opened
     * again it comes back as a new phone.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->allow(self::MANAGE);
        $phone = QtPhone::query()->findOrFail($id);
        DB::transaction(function () use ($phone): void {
            QtPhones::endOn($phone, 'REMOVED');
            PersonalAccessToken::query()->where('qt_phone_id', $phone->id)->delete();
            $phone->delete();
        });

        return ApiResponse::ok(['id' => $id]);
    }
}
