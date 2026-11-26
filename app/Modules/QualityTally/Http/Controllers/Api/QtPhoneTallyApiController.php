<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\QualityTally\Models\QtCutBundle;
use App\Modules\QualityTally\Models\QtInspector;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtPanel;
use App\Modules\QualityTally\Models\QtPhone;
use App\Modules\QualityTally\Models\QtSession;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\QualityTally\Support\QtPhones;
use App\Modules\QualityTally\Support\QtTally;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The tally on the QC's phone (AnnotechERP 37, phase 2), asked as the phone
 * itself for the QC whose session it names — so a QC who signed in with no
 * network works on once it is back, with no token of their own. The session
 * must be open, on this phone, and no older than a shift; the QC, their HR
 * employee and their user must all still stand.
 */
class QtPhoneTallyApiController extends ApiController
{
    /**
     * Everything the tally needs to open with no network: the QC's lines (the
     * phone's own when tied to one), today's jobs and targets, panels, defects,
     * and each cutting table's bundle cards of the last week.
     */
    public function context(Request $request): JsonResponse
    {
        [$session, $user, $sections, $phone] = $this->session($request);
        $only = $phone->line_id === null ? null : ['section' => (string) $phone->section, 'line_id' => $phone->line_id];
        $ctx = QtTallyApiController::contextFor($phone->company_id, $user->id, $sections, $only);

        $tables = collect($ctx['lines'])->where('section', 'CUTTING')->pluck('line_id')->all();
        $ctx['bundles'] = $tables === [] ? [] : $this->bundles($phone->company_id, $tables);

        return ApiResponse::ok($ctx + ['session' => $session->uuid, 'server_time' => now()->toIso8601String()]);
    }

    /** One line and job's running total, as the web's Tally Entry shows it. */
    public function summary(Request $request): JsonResponse
    {
        [, $user, $sections, $phone] = $this->session($request);
        $d = $this->pick($request, $sections);
        $date = QtTally::currentDate($phone->company_id, $user->id);
        $bundle = null;
        if ($d['section'] === 'CUTTING') {
            $cb = $request->filled('cut_bundle_id') ? QtCutBundle::query()->find($request->input('cut_bundle_id')) : null;
            $bundle = $cb ? ['cutting_no' => $cb->cutting_no, 'bundle_no' => $cb->bundle_no, 'cut_bundle_id' => $cb->id] : ['cutting_no' => '', 'bundle_no' => ''];
        }

        return ApiResponse::ok(QtTally::summary($d['section'], $d['line_id'], $d['job_id'], $date, $user->id, $bundle) + ['server_time' => now()->toIso8601String()]);
    }

    /** A scanned bundle card: given as input (and not another table's), or not yet. */
    public function scan(Request $request): JsonResponse
    {
        [, , $sections] = $this->session($request);
        if (! in_array('CUTTING', $sections, true)) {
            throw ValidationException::withMessages(['code' => __('qtally.v.not_your_line')]);
        }
        $code = strtoupper(trim((string) $request->validate(['code' => ['required', 'string', 'max:40']])['code']));
        $plain = str_replace('-', '', $code);
        $phone = $this->phone($request);

        $mine = QtCutBundle::query()->where(fn ($w) => $w->where('barcode', $plain)->orWhere('bundle_no', $code))->first();
        if ($mine) {
            return ApiResponse::ok(['found' => 'INPUT', 'bundle' => $this->bundles($phone->company_id, null, $mine->id)[0] ?? null]);
        }
        $card = Schema::hasTable('cut_bundles') ? DB::table('cut_bundles')->where('company_id', $phone->company_id)->where('status', '<>', 'CANCELLED')
            ->where(fn ($w) => $w->whereRaw("replace(bundle_no, '-', '') = ?", [$plain])->orWhere('bundle_no', $code))->value('bundle_no') : null;

        return ApiResponse::ok($card !== null ? ['found' => 'CARD', 'card' => ['bundle_no' => $card]] : ['found' => null]);
    }

    /** Takes back the QC's own latest tap the server has (one still on the phone is taken back there). */
    public function undo(Request $request): JsonResponse
    {
        [, $user, $sections] = $this->session($request);
        $d = $this->pick($request, $sections);
        QtTally::assertAssigned($user->id, $d['section'], $d['line_id']);
        $entry = QtTally::undo($this->phone($request)->company_id, $user->id, $d['section'], $d['line_id'], $d['job_id']);
        if ($entry === null) {
            throw ValidationException::withMessages(['action' => __('qtally.v.nothing_to_undo')]);
        }

        return ApiResponse::ok(['undone' => ['id' => $entry->id, 'client_uuid' => $entry->client_uuid, 'action' => $entry->action]]);
    }

    /**
     * The bundle cards of these tables (each table's own of the last week, and
     * those no table has opened), or one card.
     *
     * @param  list<string>|null  $tables
     * @return list<array<string, mixed>>
     */
    private function bundles(string $companyId, ?array $tables, ?string $id = null): array
    {
        $rows = QtCutBundle::query()
            ->when($id, fn ($q) => $q->whereKey($id), fn ($q) => $q->whereDate('input_date', '>=', QtBook::now()->subDays(6)->toDateString())
                ->where(fn ($x) => $x->whereIn('line_id', $tables ?? [])->orWhereNull('line_id')))
            ->orderByDesc('created_at')->limit(500)->get();
        $checked = DB::table('qt_entries')->where('company_id', $companyId)->whereIn('cut_bundle_id', $rows->pluck('id'))->whereIn('action', ['RFT', 'DEFECT'])
            ->select('cut_bundle_id', DB::raw('count(*) as n'))->groupBy('cut_bundle_id')->pluck('n', 'cut_bundle_id');
        $jobs = QtJob::query()->whereIn('id', $rows->pluck('job_id'))->get()->keyBy('id');
        $panels = QtPanel::query()->whereIn('id', $rows->pluck('panel_id')->filter())->pluck('name', 'id');
        $lines = QtBook::lines($companyId, 'CUTTING');

        return $rows->map(fn (QtCutBundle $b) => [
            'id' => $b->id, 'barcode' => $b->barcode, 'bundle_no' => $b->bundle_no, 'job_id' => $b->job_id, 'line_id' => $b->line_id,
            'line' => $b->line_id ? QtBook::label($lines->get($b->line_id)) : null,
            'garment_type' => $b->garment_type, 'panel_id' => $b->panel_id, 'panel' => $b->panel_id ? ($panels[$b->panel_id] ?? null) : null,
            'size' => $b->size, 'serial' => $b->serial_from !== null ? $b->serial_from.'-'.$b->serial_to : null, 'qty' => $b->qty,
            'cutting_no' => $b->cutting_no, 'batch_no' => $b->batch_no, 'input_date' => $b->input_date->toDateString(),
            'job_no' => $jobs->get($b->job_id)?->job_no, 'style' => $jobs->get($b->job_id)?->style, 'checked' => (int) ($checked[$b->id] ?? 0),
        ])->values()->all();
    }

    /** @return array{section: string, line_id: string, job_id: string} */
    private function pick(Request $request, array $sections): array
    {
        return $request->validate([
            'section' => ['required', Rule::in($sections)],
            'line_id' => ['required', 'string', 'size:26'],
            'job_id' => ['required', 'string', 'size:26'],
        ]);
    }

    /**
     * The open session the request names, on this phone, and who it is.
     *
     * @return array{0: QtSession, 1: User, 2: list<string>, 3: QtPhone}
     */
    private function session(Request $request): array
    {
        $phone = $this->phone($request);
        $uuid = (string) $request->input('session', '');
        $session = $uuid === '' ? null : QtSession::query()->where('phone_id', $phone->id)->where('uuid', $uuid)->first();
        $inspector = $session?->inspector_id ? QtInspector::query()->find($session->inspector_id) : null;
        $user = $session ? User::find($session->user_id) : null;
        $sections = $user ? QtPhones::tapSections($user, $phone->company_id) : [];

        // Ended, out of date, or the QC is no longer one: the phone signs them out.
        if ($session === null || $session->ended_at !== null || $session->started_at->lessThan(now()->subHours(QtPhones::SESSION_HOURS))
            || ! $inspector?->active || ! QtPhones::employed($inspector) || $sections === []) {
            throw QtPhones::refuse('SESSION_ENDED', __('qtally.phone.session_ended'), ErrorCode::UNAUTHENTICATED);
        }

        return [$session, $user, $sections, $phone];
    }

    private function phone(Request $request): QtPhone
    {
        return $request->attributes->get('qt_phone');
    }
}
