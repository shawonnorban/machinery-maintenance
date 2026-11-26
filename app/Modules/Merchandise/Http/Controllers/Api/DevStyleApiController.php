<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleApproval;
use App\Modules\Merchandise\Models\StyleAttachment;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleFabric;
use App\Modules\Merchandise\Models\StyleFabricMap;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Models\StylePacking;
use App\Modules\Merchandise\Models\StylePackingDetail;
use App\Modules\Merchandise\Models\StyleProcess;
use App\Modules\Merchandise\Models\StyleSizeRange;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Style & Order → Development Style Catalogue (AnnotechERP 06/07): the
 * development styles with their approval, the approvers' Inbox and the
 * sender's Sent list, and the wizard's own parts — the review of a style
 * (what is done, its history), its approval moves, its attachments and its
 * packing details.
 *
 * Where a style stands: sent, returned for revision or rejected while its
 * approval is in hand; otherwise approved when its status is Style Approved
 * or Running, and draft before. Approving sets its status to Style Approved.
 * A style that is with its approver, or approved, is closed to changes in
 * the wizard.
 */
class DevStyleApiController extends ApiController
{
    public const STEPS = ['profile', 'items', 'fabric', 'colour', 'services', 'bom', 'mapping', 'packing', 'attachments'];

    public function index(): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $styles = Style::query()->orderByDesc('updated_at')->get();
        $rows = $this->rows($styles);
        $statuses = collect($rows)->countBy('dev_status');
        $mine = (string) $this->caller()->auditUserId();

        return ApiResponse::ok([
            'rows' => $rows,
            'stats' => [
                'total' => count($rows),
                'DRAFT' => (int) ($statuses['DRAFT'] ?? 0), 'SENT' => (int) ($statuses['SENT'] ?? 0), 'APPROVED' => (int) ($statuses['APPROVED'] ?? 0),
                'REJECTED' => (int) ($statuses['REJECTED'] ?? 0), 'REVISION' => (int) ($statuses['REVISION'] ?? 0),
            ],
            'inbox' => (int) ($statuses['SENT'] ?? 0),
            'sent' => StyleApproval::query()->where('action', 'SENT')->where('user_id', $mine)->distinct()->count('style_id'),
            'sent_pending' => Style::query()->where('approval_status', 'SENT')->where('sent_by', $mine)->count(),
            'can_approve' => $this->caller()->can('merchandise.style.approve'),
        ]);
    }

    /** Styles waiting for an approver — every approver sees the same list. */
    public function inbox(): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $styles = Style::query()->where('approval_status', 'SENT')->orderBy('sent_at')->get();
        $users = User::query()->whereIn('id', $styles->pluck('sent_by')->filter())->pluck('name', 'id');

        return ApiResponse::ok(collect($this->rows($styles))->map(fn (array $r): array => $r + [
            'sent_by' => $users[$styles->firstWhere('id', $r['id'])?->sent_by] ?? null,
            'sent_at' => $styles->firstWhere('id', $r['id'])?->sent_at?->toIso8601String(),
        ])->values()->all());
    }

    /** The styles the caller has sent, each where it stands now and the approver's last word. */
    public function sent(): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $mine = (string) $this->caller()->auditUserId();
        $ids = StyleApproval::query()->where('action', 'SENT')->where('user_id', $mine)->pluck('style_id')->unique();
        $styles = Style::query()->whereIn('id', $ids)->get();
        $history = StyleApproval::query()->whereIn('style_id', $ids)->orderBy('created_at')->get()->groupBy('style_id');

        return ApiResponse::ok(collect($this->rows($styles))->map(function (array $r) use ($history, $mine): array {
            $steps = $history[$r['id']] ?? collect();
            $sent = $steps->where('action', 'SENT')->where('user_id', $mine)->last();
            $decision = $steps->whereIn('action', ['APPROVED', 'REVISION', 'REJECTED'])->last();

            return $r + ['sent_on' => $sent?->created_at?->toIso8601String(), 'approver_note' => $decision?->note];
        })->sortByDesc('sent_on')->values()->all());
    }

    /** The wizard's Review: the style's summary, what each step holds, where its approval stands and its history. */
    public function review(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $counts = $this->counts($row);
        $done = collect($counts)->filter(fn (int $n): bool => $n > 0)->count();
        $history = StyleApproval::query()->where('style_id', $row->id)->orderBy('created_at')->get();
        $users = User::query()->whereIn('id', $history->pluck('user_id')->filter())->pluck('name', 'id');
        $sizeIds = StyleSizeRange::query()->where('style_id', $row->id)->orderBy('sl_no')->get()->flatMap(fn ($r) => $r->size_ids ?? [])->unique()->values();
        $sizes = Size::query()->whereIn('id', $sizeIds)->get(['id', 'name', 'display_order'])->sortBy('display_order')->pluck('name')->values();
        $image = StyleAttachment::query()->where('style_id', $row->id)->where('kind', 'STYLE_IMAGE')->value('file_id');
        $status = $this->statusOf($row);

        return ApiResponse::ok([
            'style' => $this->rows(collect([$row]))[0] + [
                'size_set' => $sizes->isEmpty() ? null : ($sizes->count() === 1 ? $sizes[0] : $sizes->first().' – '.$sizes->last()),
                'image' => $image ? "/api/files/{$image}/download" : null,
            ],
            'counts' => $counts,
            'completion' => (int) round($done * 100 / count(self::STEPS)),
            'status' => $status,
            'locked' => in_array($status, ['SENT', 'APPROVED'], true),
            'last_note' => $history->last()?->note,
            'history' => $history->map(fn (StyleApproval $a): array => [
                'action' => $a->action, 'note' => $a->note, 'by' => $users[$a->user_id] ?? null, 'at' => $a->created_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    /**
     * An approval move. The merchandiser sends a draft, returned or rejected
     * style; an approver approves it, returns it for revision or rejects it
     * (a note says why), or approves a style directly without sending it.
     */
    public function act(Request $request, string $style, string $move): JsonResponse
    {
        $row = Style::query()->whereKey($style)->firstOrFail();
        $this->allow($move === 'send' ? 'merchandise.style.manage' : 'merchandise.style.approve');
        $needsNote = in_array($move, ['revision', 'reject'], true);
        $data = $request->validate(['note' => [$needsNote ? 'required' : 'nullable', 'string', 'max:2000']], [
            'note.required' => __($move === 'reject' ? 'merchandise.dev_style.reject_note' : 'merchandise.dev_style.revision_note'),
        ]);
        $note = isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null;
        $status = $this->statusOf($row);
        $allowed = match ($move) {
            'send' => in_array($status, ['DRAFT', 'REVISION', 'REJECTED'], true),
            'approve', 'revision', 'reject' => $status === 'SENT',
            'direct' => $status !== 'APPROVED',
        };
        if (! $allowed) {
            throw ValidationException::withMessages(['status' => __('merchandise.dev_style.not_now', ['status' => __('merchandise.dev_style.status.'.$status)])]);
        }

        DB::transaction(function () use ($row, $move, $note): void {
            $user = $this->caller()->auditUserId();
            match ($move) {
                'send' => $row->update(['approval_status' => 'SENT', 'sent_at' => now(), 'sent_by' => $user]),
                'approve', 'direct' => $row->update(['approval_status' => null, 'status' => 'STYLE_APPROVED']),
                'revision' => $row->update(['approval_status' => 'REVISION']),
                'reject' => $row->update(['approval_status' => 'REJECTED']),
            };
            StyleApproval::query()->create([
                'style_id' => $row->id,
                'action' => match ($move) {
                    'send' => 'SENT', 'approve', 'direct' => 'APPROVED', 'revision' => 'REVISION', 'reject' => 'REJECTED'
                },
                'note' => $note,
                'user_id' => $user,
            ]);
        });

        return $this->review($row->id);
    }

    public function attachments(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::ok($this->attachmentsOf($row));
    }

    /** A file for one box: stored privately, it takes that box's place. */
    public function attach(Request $request, StoreFileAttachment $files, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $data = $request->validate([
            'kind' => ['required', Rule::in(StyleAttachment::KINDS)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $file = $files->handle($request->file('file'), 'merch_style_attachment', $this->caller()->companyId, $this->caller()->auditUserId());
        StyleAttachment::query()->updateOrCreate(['style_id' => $row->id, 'kind' => $data['kind']], ['file_id' => $file->id, 'file_name' => $request->file('file')->getClientOriginalName()]);

        return ApiResponse::ok($this->attachmentsOf($row));
    }

    public function detach(string $style, string $kind): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        StyleAttachment::query()->where('style_id', $row->id)->where('kind', $kind)->delete();

        return ApiResponse::ok($this->attachmentsOf($row));
    }

    public function packingDetails(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::ok($this->packingOf($row) + ['methods' => StylePackingDetail::METHODS, 'cartons' => StylePackingDetail::CARTONS]);
    }

    public function savePackingDetails(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $data = $request->validate([
            'packing_method' => ['nullable', Rule::in(StylePackingDetail::METHODS)],
            'carton_type' => ['nullable', Rule::in(StylePackingDetail::CARTONS)],
            'pcs_per_poly' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'pcs_per_carton' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'carton_size' => ['nullable', 'string', 'max:64'],
            'carton_marking' => ['nullable', 'string', 'max:128'],
            'barcode' => ['sometimes', 'boolean'],
            'hangtag' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'photo_id' => ['nullable', 'ulid'],
        ]);
        StylePackingDetail::query()->updateOrCreate(['style_id' => $row->id], [
            'barcode' => (bool) ($data['barcode'] ?? false),
            'hangtag' => (bool) ($data['hangtag'] ?? false),
        ] + collect($data)->except(['barcode', 'hangtag'])->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all());

        return ApiResponse::ok($this->packingOf($row));
    }

    /** Where a style stands in its approval. */
    public static function statusOf(Style $style): string
    {
        if (in_array($style->approval_status, ['SENT', 'REVISION', 'REJECTED'], true)) {
            return $style->approval_status;
        }

        return in_array($style->status, ['STYLE_APPROVED', 'RUNNING'], true) ? 'APPROVED' : 'DRAFT';
    }

    /** @return array<string, int> what each wizard step holds */
    private function counts(Style $style): array
    {
        $own = StyleItemStyle::query()->where('style_id', $style->id)->pluck('style_item_id');
        $bom = StyleBom::query()->where('style_id', $style->id)->value('id');
        $version = $bom ? StyleBomVersion::query()->where('bom_id', $bom)->orderByDesc('version')->value('id') : null;

        return [
            'profile' => StyleSizeRange::query()->where('style_id', $style->id)->count(),
            'items' => $own->count(),
            'fabric' => StyleFabric::query()->where('style_id', $style->id)->count(),
            'colour' => StyleColour::query()->where('style_id', $style->id)->count(),
            'services' => StyleProcess::query()->where('style_id', $style->id)->count(),
            'bom' => $version ? StyleBomLine::query()->where('version_id', $version)->count() : 0,
            'mapping' => StyleFabricMap::query()->where('style_id', $style->id)->count(),
            'packing' => StylePacking::query()->where('style_id', $style->id)->count() + (StylePackingDetail::query()->where('style_id', $style->id)->exists() ? 1 : 0),
            'attachments' => StyleAttachment::query()->where('style_id', $style->id)->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rows(Collection $styles): array
    {
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id'))->get(['id', 'short_name', 'name'])->keyBy('id');
        $links = StyleItemStyle::query()->whereIn('style_id', $styles->pluck('id'))->get(['style_id', 'style_item_id']);
        $items = StyleItem::query()->whereIn('id', $links->pluck('style_item_id'))->get(['id', 'full_name', 'short_name'])->keyBy('id');

        return $styles->map(function (Style $s) use ($buyers, $links, $items): array {
            $item = $items[$links->firstWhere('style_id', $s->id)?->style_item_id] ?? null;

            return [
                'id' => $s->id,
                'dev_no' => $s->dev_no ? 'DEV-'.str_pad((string) $s->dev_no, 6, '0', STR_PAD_LEFT) : null,
                'style_no' => $s->styleNo(),
                'buyer_id' => $s->buyer_id,
                'buyer' => $buyers[$s->buyer_id]->short_name ?? $buyers[$s->buyer_id]->name ?? null,
                'description' => $s->description,
                'garment_type' => $item ? ($item->full_name ?: $item->short_name) : null,
                'season' => $s->season,
                'status' => $s->status,
                'dev_status' => self::statusOf($s),
                'updated_at' => $s->updated_at?->format('Y-m-d'),
            ];
        })->values()->all();
    }

    /** @return array<string, array{file_id: string, name: ?string, path: string}> */
    private function attachmentsOf(Style $style): array
    {
        return StyleAttachment::query()->where('style_id', $style->id)->get()->mapWithKeys(fn (StyleAttachment $a): array => [
            $a->kind => ['file_id' => $a->file_id, 'name' => $a->file_name, 'path' => "/api/files/{$a->file_id}/download"],
        ])->all();
    }

    /** @return array{details: ?array<string, mixed>} */
    private function packingOf(Style $style): array
    {
        $d = StylePackingDetail::query()->where('style_id', $style->id)->first();

        return ['details' => $d ? $d->only(['packing_method', 'carton_type', 'pcs_per_poly', 'pcs_per_carton', 'carton_size', 'carton_marking', 'barcode', 'hangtag', 'remarks', 'photo_id'])
            + ['photo' => $d->photo_id ? "/api/files/{$d->photo_id}/download" : null] : null];
    }
}
