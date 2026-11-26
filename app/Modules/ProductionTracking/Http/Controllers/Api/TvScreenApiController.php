<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Http\Controllers\Api;

use App\Modules\ProductionTracking\Models\PtTvScreen;
use App\Modules\ProductionTracking\Support\ProductionBoard;
use App\Modules\ProductionTracking\Support\PtBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Production Tracking → TV Dashboard Show (AnnotechERP 30/04): each
 * factory-floor TV's saved selection of sewing or finishing lines (today,
 * with the approved daily target), its layout and refresh, and its display
 * link. The link opens the TV with no ERP sign-in and cannot change anything;
 * only its hash is kept, so it is shown once — on issue or replacement — and
 * can be revoked.
 */
class TvScreenApiController extends ApiController
{
    public const LAYOUTS = ['AUTO', '1', '2', '3', '4', '6'];

    public function index(): JsonResponse
    {
        $this->allow('productiontracking.view');
        $c = $this->caller()->companyId;
        $lines = PtBook::lines($c, 'SEWING')->merge(PtBook::lines($c, 'FINISHING'));
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok(PtTvScreen::query()->orderBy('name')->get()->map(fn (PtTvScreen $s) => [
            'id' => $s->id, 'name' => $s->name, 'section' => $s->section, 'line_ids' => $s->line_ids,
            'lines' => collect($s->line_ids)->map(fn ($id) => $lines->get($id)?->code)->filter()->values()->all(),
            'layout' => $s->layout, 'refresh_seconds' => $s->refresh_seconds, 'show_defects' => $s->show_defects, 'active' => $s->active,
            'link' => $s->token_hash ? ['hint' => $s->token_hint, 'issued_at' => $s->issued_at?->toIso8601String()] : null,
            'last_seen_at' => $s->last_seen_at?->toIso8601String(), 'updated_by' => $users[$s->updated_by] ?? null, 'updated_at' => $s->updated_at?->toIso8601String(),
        ])->all());
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('productiontracking.tv.manage');
        $c = $this->caller()->companyId;
        $row = $id ? PtTvScreen::query()->findOrFail($id) : null;
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'section' => ['required', Rule::in(PtBook::SECTIONS)],
            'line_ids' => ['required', 'array', 'min:1'], 'line_ids.*' => ['string', 'size:26'],
            'layout' => ['required', Rule::in(self::LAYOUTS)],
            'refresh_seconds' => ['required', 'integer', 'min:15', 'max:600'],
            'show_defects' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'],
        ], ['name.required' => __('productiontracking.v.tv_name'), 'line_ids.required' => __('productiontracking.v.tv_lines'), 'line_ids.min' => __('productiontracking.v.tv_lines')], __('productiontracking.attrs'));
        $known = PtBook::lines($c, $d['section']);
        if (collect($d['line_ids'])->contains(fn ($l) => ! $known->has($l))) {
            throw ValidationException::withMessages(['line_ids' => __('productiontracking.v.tv_lines')]);
        }
        $values = $d + ['show_defects' => $d['show_defects'] ?? true, 'active' => $d['active'] ?? true, 'updated_by' => $this->caller()->auditUserId()];
        $saved = $row ? tap($row)->update($values) : PtTvScreen::query()->create($values);

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->allow('productiontracking.tv.manage');
        PtTvScreen::query()->findOrFail($id)->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Issues a display link, replacing any earlier one; the token is shown this once. */
    public function issue(string $id): JsonResponse
    {
        $this->allow('productiontracking.tv.manage');
        $screen = PtTvScreen::query()->findOrFail($id);
        $token = Str::random(40);
        $screen->update(['token_hash' => hash('sha256', $token), 'token_hint' => substr($token, -4), 'issued_at' => now(), 'updated_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok(['token' => $token, 'hint' => $screen->token_hint]);
    }

    public function revoke(string $id): JsonResponse
    {
        $this->allow('productiontracking.tv.manage');
        PtTvScreen::query()->findOrFail($id)->update(['token_hash' => null, 'token_hint' => null, 'issued_at' => null, 'updated_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok(['revoked' => true]);
    }

    /** What a TV shows, by its display link — no sign-in, read-only. */
    public function display(string $token): JsonResponse
    {
        $row = DB::table('pt_tv_screens')->where('token_hash', hash('sha256', $token))->where('active', true)->first();
        if ($row === null) {
            return ApiResponse::error(ErrorCode::RESOURCE_NOT_FOUND, __('productiontracking.v.tv_link'));
        }
        app(TenantContext::class)->set($row->company_id);
        DB::table('pt_tv_screens')->where('id', $row->id)->update(['last_seen_at' => now()]);

        return ApiResponse::ok($this->payload($row));
    }

    /** The same screen seen from the ERP — its display link (and the TV using it) untouched. */
    public function preview(string $id): JsonResponse
    {
        $this->allow('productiontracking.view');
        $screen = PtTvScreen::query()->findOrFail($id);

        return ApiResponse::ok($this->payload((object) DB::table('pt_tv_screens')->where('id', $screen->id)->first()));
    }

    private function payload(object $row): array
    {
        $today = PtBook::today()->toDateString();
        $board = (new ProductionBoard($row->company_id, $today, $today))->lines($row->section);
        $ids = json_decode((string) $row->line_ids, true) ?: [];
        $lines = collect($board['lines'])->filter(fn ($l) => in_array($l['id'], $ids, true))->values();
        if (! $row->show_defects) {
            $lines = $lines->map(fn ($l) => array_merge($l, ['reject' => null, 'rework' => null]));
        }
        $settings = PtBook::settings($row->company_id);

        return [
            'name' => $row->name, 'section' => $row->section, 'layout' => $row->layout, 'refresh_seconds' => (int) $row->refresh_seconds,
            'show_defects' => (bool) $row->show_defects, 'company' => DB::table('companies')->where('id', $row->company_id)->value('name'),
            'date' => $today, 'refreshed_at' => now()->toIso8601String(), 'on_track_pct' => $settings->on_track_pct, 'below_pct' => $settings->below_pct,
            'lines' => $lines->all(),
            'totals' => ['target' => (int) $lines->whereNotNull('target')->sum('target'), 'actual' => (int) $lines->sum('actual')],
        ];
    }
}
