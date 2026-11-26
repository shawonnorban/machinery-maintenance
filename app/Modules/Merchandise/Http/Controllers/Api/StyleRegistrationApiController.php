<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Inquiry;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleApproval;
use App\Modules\Merchandise\Models\StyleAttachment;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleFabric;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Models\StyleProcess;
use App\Modules\Merchandise\Models\StyleRegistration;
use App\Modules\Merchandise\Models\StyleRegistrationEvent;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Style & Order → Style Registration Entry (AnnotechERP 06/07). Only a
 * development style whose approval is complete is registered: a request
 * (SRQ-2026-000001) is raised from it, gives it a style code (ST-2026-00001)
 * and its approved data, and asks for the profile it lacks. A complete
 * profile is submitted; an approver approves it (the registration number is
 * issued and the version becomes the active style), returns it or rejects
 * it, with a note. An active style changes through a change request — the
 * next version, approved the same way.
 */
class StyleRegistrationApiController extends ApiController
{
    /** The wizard's steps after the profile, each read from the development style. */
    public const CARRIED = ['items', 'fabric', 'colour', 'services', 'bom', 'documents'];

    public function index(): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $requests = StyleRegistration::query()->orderByDesc('updated_at')->get();
        $by = $requests->countBy('status');

        return ApiResponse::ok([
            'requests' => $this->rows($requests),
            'counts' => [
                'total' => $requests->count(),
                'DRAFT' => (int) ($by['DRAFT'] ?? 0), 'PENDING' => (int) ($by['PENDING'] ?? 0), 'RETURNED' => (int) ($by['RETURNED'] ?? 0),
                'APPROVED' => (int) ($by['APPROVED'] ?? 0), 'REJECTED' => (int) ($by['REJECTED'] ?? 0),
                'registered' => $requests->where('status', 'APPROVED')->pluck('style_code')->unique()->count(),
                'active' => $requests->where('active', true)->count(),
            ],
            'registered' => $this->registered($requests->where('active', true)->values()),
            'can_approve' => $this->caller()->can('merchandise.style.approve'),
        ]);
    }

    /** The approved development styles a request may be raised from, with how far each is registered. */
    public function candidates(): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $styles = Style::query()->whereNull('approval_status')->whereIn('status', ['STYLE_APPROVED', 'RUNNING'])->orderByDesc('dev_no')->get();
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id'))->pluck('short_name', 'id');
        $requests = StyleRegistration::query()->whereIn('style_id', $styles->pluck('id'))->orderBy('version')->get()->groupBy('style_id');
        $approvedOn = StyleApproval::query()->whereIn('style_id', $styles->pluck('id'))->where('action', 'APPROVED')->get()->groupBy('style_id')->map(fn ($g) => $g->last()->created_at);

        return ApiResponse::ok($styles->map(function (Style $s) use ($buyers, $requests, $approvedOn): array {
            $last = ($requests[$s->id] ?? collect())->last();

            return [
                'id' => $s->id, 'dev_no' => $this->devNo($s), 'style_no' => $s->styleNo(), 'description' => $s->description, 'buyer' => $buyers[$s->buyer_id] ?? null,
                'brand' => $last?->brand, 'product' => $last?->product, 'season' => $s->season,
                'approved_on' => ($approvedOn[$s->id] ?? $s->updated_at)?->format('Y-m-d'),
                'registration' => $last ? ['status' => $last->status, 'request_no' => $last->request_no, 'registration_no' => $last->registration_no] : null,
                // A style already registered changes through its active version's change request, not a new request.
                'selectable' => $last === null || in_array($last->status, ['REJECTED'], true),
            ];
        })->values()->all());
    }

    /** A request raised from an approved development style: numbered, given its style code, opened as a draft. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate(['style_id' => ['required', Rule::exists('merch_styles', 'id')->where('company_id', $company)]]);
        $style = Style::query()->whereKey($data['style_id'])->firstOrFail();
        if (DevStyleApiController::statusOf($style) !== 'APPROVED') {
            throw ValidationException::withMessages(['style_id' => __('merchandise.style_registration.not_approved')]);
        }
        $last = StyleRegistration::query()->where('style_id', $style->id)->orderByDesc('version')->first();
        if ($last && $last->status !== 'REJECTED') {
            throw ValidationException::withMessages(['style_id' => __('merchandise.style_registration.already', ['no' => $last->request_no])]);
        }

        $row = DB::transaction(fn (): StyleRegistration => StyleRegistration::query()->create([
            'request_no' => $this->next('SRQ', 6, 'request_no'),
            'type' => 'REGISTRATION',
            'version' => 1,
            'style_id' => $style->id,
            'style_code' => $last?->style_code ?? $this->next('ST', 5, 'style_code'),
            'status' => 'DRAFT',
            'season_year' => $style->season && preg_match('/(\d{4})/', $style->season, $m) ? (int) $m[1] : null,
            'created_by' => $this->caller()->auditUserId(),
        ]));

        return ApiResponse::created($this->present($row));
    }

    /** An active style's change request: its next version, with its profile carried over, as a draft. */
    public function change(string $registration): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $active = StyleRegistration::query()->whereKey($registration)->where('active', true)->firstOrFail();
        $open = StyleRegistration::query()->where('style_code', $active->style_code)->whereIn('status', ['DRAFT', 'PENDING', 'RETURNED'])->first();
        if ($open) {
            throw ValidationException::withMessages(['registration' => __('merchandise.style_registration.already', ['no' => $open->request_no])]);
        }

        $row = DB::transaction(fn (): StyleRegistration => StyleRegistration::query()->create($active->only(array_merge(['style_id', 'style_code', 'registration_no'], StyleRegistration::PROFILE)) + [
            'request_no' => $this->next('SRQ', 6, 'request_no'),
            'type' => 'CHANGE',
            'version' => 1 + (int) StyleRegistration::query()->where('style_code', $active->style_code)->max('version'),
            'status' => 'DRAFT',
            'created_by' => $this->caller()->auditUserId(),
        ]));

        return ApiResponse::created($this->present($row));
    }

    public function show(string $registration): JsonResponse
    {
        $this->allow('merchandise.style.view');

        return ApiResponse::ok($this->present(StyleRegistration::query()->whereKey($registration)->firstOrFail()));
    }

    /** The profile the registration adds, while the request is a draft or returned. */
    public function update(Request $request, string $registration): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = StyleRegistration::query()->whereKey($registration)->firstOrFail();
        $this->editable($row);
        $data = $request->validate([
            'brand' => ['nullable', 'string', 'max:96'],
            'product' => ['nullable', 'string', 'max:96'],
            'product_category' => ['nullable', 'string', 'max:96'],
            'product_type' => ['nullable', 'string', 'max:96'],
            'gender' => ['nullable', Rule::in(StyleRegistration::GENDERS)],
            'season_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'collection' => ['nullable', 'string', 'max:96'],
            'department' => ['nullable', 'string', 'max:96'],
            'division' => ['nullable', 'string', 'max:96'],
        ]);
        $row->update(collect($data)->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all());

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** A complete profile goes to the approver. */
    public function submit(Request $request, string $registration): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = StyleRegistration::query()->whereKey($registration)->firstOrFail();
        $this->editable($row);
        $missing = $this->missing($row);
        if ($missing !== []) {
            throw ValidationException::withMessages(['profile' => $missing]);
        }
        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        DB::transaction(function () use ($row, $note): void {
            $row->update(['status' => 'PENDING']);
            $this->event($row, 'SUBMITTED', $note);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** The approver's word on a pending request: approve, return (with a note) or reject (with a note). */
    public function decide(Request $request, string $registration, string $move): JsonResponse
    {
        $this->allow('merchandise.style.approve');
        $row = StyleRegistration::query()->whereKey($registration)->firstOrFail();
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => __('merchandise.style_registration.not_pending')]);
        }
        $data = $request->validate(['note' => [$move === 'approve' ? 'nullable' : 'required', 'string', 'max:2000']], [
            'note.required' => __($move === 'reject' ? 'merchandise.style_registration.reject_note' : 'merchandise.style_registration.return_note'),
        ]);
        $note = isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null;

        DB::transaction(function () use ($row, $move, $note): void {
            if ($move === 'approve') {
                // The style keeps its registration number from version to version; the approved version is the active one.
                $number = $row->registration_no ?? StyleRegistration::query()->where('style_code', $row->style_code)->whereNotNull('registration_no')->value('registration_no') ?? $this->next('REG', 6, 'registration_no');
                StyleRegistration::query()->where('style_code', $row->style_code)->where('active', true)->update(['active' => false]);
                $row->update(['status' => 'APPROVED', 'registration_no' => $number, 'active' => true]);
            } else {
                $row->update(['status' => $move === 'return' ? 'RETURNED' : 'REJECTED']);
            }
            $this->event($row, match ($move) {
                'approve' => 'APPROVED', 'return' => 'RETURNED', 'reject' => 'REJECTED'
            }, $note);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** A draft that is not wanted after all. */
    public function discard(string $registration): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = StyleRegistration::query()->whereKey($registration)->firstOrFail();
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('merchandise.style_registration.not_draft')]);
        }
        $row->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> the request, what it carries from the development style, how complete each step is, and its history */
    private function present(StyleRegistration $row): array
    {
        $style = Style::query()->whereKey($row->style_id)->firstOrFail();
        $buyer = Buyer::query()->whereKey($style->buyer_id)->first(['short_name', 'name', 'code']);
        $carried = $this->carried($style);
        $missing = $this->missing($row);
        $required = count(StyleRegistration::REQUIRED) + 7;
        $profile = (int) round(($required - count($missing)) * 100 / $required);
        $steps = ['profile' => $profile] + collect(self::CARRIED)->mapWithKeys(fn (string $k): array => [$k => $carried[$k]['count'] > 0 ? 100 : 0])->all()
            + ['review' => in_array($row->status, ['PENDING', 'APPROVED'], true) ? 100 : 0];
        $events = StyleRegistrationEvent::query()->where('registration_id', $row->id)->orderBy('created_at')->get();
        $users = User::query()->whereIn('id', $events->pluck('user_id')->filter())->pluck('name', 'id');

        return [
            'request' => $this->rows(collect([$row]))[0] + $row->only(StyleRegistration::PROFILE),
            'profile' => [
                'buyer' => $buyer?->short_name ?? $buyer?->name, 'buyer_code' => $buyer?->code, 'dev_no' => $this->devNo($style), 'dev_style_no' => $style->styleNo(),
                'style_code' => $row->style_code, 'style_name' => $style->description, 'season' => $style->season,
            ],
            'carried' => $carried,
            'steps' => $steps,
            'progress' => (int) round(array_sum($steps) / count($steps)),
            'missing' => $missing,
            'editable' => in_array($row->status, ['DRAFT', 'RETURNED'], true),
            'history' => $events->map(fn (StyleRegistrationEvent $e): array => ['action' => $e->action, 'note' => $e->note, 'by' => $users[$e->user_id] ?? null, 'at' => $e->created_at?->toIso8601String()])->values()->all(),
            'genders' => StyleRegistration::GENDERS,
        ];
    }

    /** @return list<string> what the profile still needs before it can be submitted */
    private function missing(StyleRegistration $row): array
    {
        return collect(StyleRegistration::REQUIRED)->filter(fn (string $f): bool => $row->{$f} === null || $row->{$f} === '')
            ->map(fn (string $f): string => __('merchandise.style_registration.required', ['field' => __('merchandise.master.srq.f.'.$f)]))->values()->all();
    }

    /** @return array<string, array{count: int, lines: list<string>}> the development style's approved data, step by step */
    private function carried(Style $style): array
    {
        $items = StyleItem::query()->whereIn('id', StyleItemStyle::query()->where('style_id', $style->id)->pluck('style_item_id'))->get(['full_name', 'short_name'])
            ->map(fn ($i) => $i->full_name ?: $i->short_name)->all();
        $fabrics = StyleFabric::query()->where('style_id', $style->id)->orderBy('sort_order')->pluck('description')->filter()->values()->all();
        $names = Colour::query()->pluck('name', 'id');
        $colours = StyleColour::query()->where('style_id', $style->id)->orderBy('sort_order')->pluck('colour_id')->map(fn ($id) => $names[$id] ?? '—')->all();
        $processes = StyleProcess::query()->where('style_id', $style->id)->orderBy('sort_order')->get(['process', 'type'])
            ->map(fn ($p) => trim(__('merchandise.master.svc.process.'.$p->process).' '.($p->type ?? '')))->all();
        $bom = StyleBom::query()->where('style_id', $style->id)->value('id');
        $version = $bom ? StyleBomVersion::query()->where('bom_id', $bom)->where('status', 'APPROVED')->orderByDesc('version')->value('id')
            ?? StyleBomVersion::query()->where('bom_id', $bom)->orderByDesc('version')->value('id') : null;
        $lines = $version ? StyleBomLine::query()->where('version_id', $version)->orderBy('sort_order')->pluck('item_name')->all() : [];
        $documents = StyleAttachment::query()->where('style_id', $style->id)->get(['kind', 'file_id', 'file_name'])
            ->map(fn ($a) => __('merchandise.master.wiz.kind.'.$a->kind).($a->file_name ? " — {$a->file_name}" : ''))->all();

        return collect(['items' => $items, 'fabric' => $fabrics, 'colour' => $colours, 'services' => $processes, 'bom' => $lines, 'documents' => $documents])
            ->map(fn (array $l): array => ['count' => count($l), 'lines' => array_values($l)])->all();
    }

    /** @return list<array<string, mixed>> */
    private function rows(Collection $requests): array
    {
        $styles = Style::query()->whereIn('id', $requests->pluck('style_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id'))->pluck('short_name', 'id');

        return $requests->map(function (StyleRegistration $r) use ($styles, $buyers): array {
            $s = $styles[$r->style_id] ?? null;

            return [
                'id' => $r->id, 'request_no' => $r->request_no, 'type' => $r->type, 'version' => $r->version, 'style_code' => $r->style_code, 'registration_no' => $r->registration_no,
                'style_id' => $r->style_id, 'style_no' => $s?->styleNo(), 'style_name' => $s?->description, 'buyer' => $s ? ($buyers[$s->buyer_id] ?? null) : null, 'season' => $s?->season,
                'status' => $r->status, 'active' => $r->active, 'updated_at' => $r->updated_at?->toIso8601String(),
                'completion' => $this->completionOf($r, $s),
            ];
        })->values()->all();
    }

    private function completionOf(StyleRegistration $r, ?Style $style): int
    {
        if (! $style) {
            return 0;
        }
        $required = count(StyleRegistration::REQUIRED) + 7;
        $profile = ($required - count($this->missing($r))) * 100 / $required;
        $carried = collect($this->carried($style))->filter(fn (array $c): bool => $c['count'] > 0)->count() * 100;
        $review = in_array($r->status, ['PENDING', 'APPROVED'], true) ? 100 : 0;

        return (int) round(($profile + $carried + $review) / 8);
    }

    /** @return list<array<string, mixed>> the active registered styles, as the Records list shows them */
    private function registered(Collection $active): array
    {
        $styles = Style::query()->whereIn('id', $active->pluck('style_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id'))->pluck('short_name', 'id');
        $offices = BuyerOffice::query()->whereIn('id', $styles->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $inquiries = Inquiry::query()->whereIn('id', $styles->pluck('inquiry_id')->filter())->pluck('inquiry_no', 'id');
        $countries = OriginCountry::query()->whereIn('id', $styles->pluck('origin_country_id')->filter())->pluck('name', 'id');
        $orders = Order::query()->whereIn('style_id', $active->pluck('style_id'))->where('status', '!=', 'CANCELLED')->selectRaw('style_id, count(*) as n')->groupBy('style_id')->pluck('n', 'style_id');

        return $active->map(function (StyleRegistration $r) use ($styles, $buyers, $offices, $inquiries, $countries, $orders): array {
            $s = $styles[$r->style_id];

            return [
                'id' => $r->id, 'request_no' => $r->request_no, 'style_code' => $r->style_code, 'registration_no' => $r->registration_no, 'version' => $r->version, 'style_ref' => $s->style_ref, 'style_no' => $s->styleNo(),
                'style_desc' => $s->description, 'buyer' => $buyers[$s->buyer_id] ?? null, 'buyer_ac' => $s->buyer_office_id ? ($offices[$s->buyer_office_id] ?? null) : null,
                'inquiry_no' => $s->inquiry_id ? ($inquiries[$s->inquiry_id] ?? null) : null, 'season' => $s->season, 'qty_unit' => $s->qty_unit,
                'origin' => $s->origin_country_id ? ($countries[$s->origin_country_id] ?? null) : null, 'orders' => (int) ($orders[$r->style_id] ?? 0),
                'status' => $s->status, 'brand' => $r->brand,
            ];
        })->values()->all();
    }

    private function editable(StyleRegistration $row): void
    {
        if (! in_array($row->status, ['DRAFT', 'RETURNED'], true)) {
            throw ValidationException::withMessages(['status' => __('merchandise.style_registration.closed')]);
        }
    }

    private function event(StyleRegistration $row, string $action, ?string $note): void
    {
        StyleRegistrationEvent::query()->create(['registration_id' => $row->id, 'action' => $action, 'note' => $note === null || trim($note) === '' ? null : trim($note), 'user_id' => $this->caller()->auditUserId()]);
    }

    private function devNo(Style $style): ?string
    {
        return $style->dev_no ? 'DEV-'.str_pad((string) $style->dev_no, 6, '0', STR_PAD_LEFT) : null;
    }

    /** SRQ-2026-000001, ST-2026-00001, REG-2026-000001: the year and the company's next number in that series; held to the end of the save. */
    private function next(string $prefix, int $digits, string $column): string
    {
        $head = $prefix.'-'.now()->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["merch_style_registrations:{$column}:".app(TenantContext::class)->companyId()]);
        $n = 1 + (int) StyleRegistration::query()->where($column, 'like', $head.'%')->pluck($column)
            ->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, $digits, '0', STR_PAD_LEFT);
    }
}
