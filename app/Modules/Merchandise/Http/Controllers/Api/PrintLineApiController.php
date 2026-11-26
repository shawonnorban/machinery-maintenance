<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\PrintLine;
use App\Modules\Merchandise\Models\PrintProgram;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Support\SampleTypeDefaults;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Print Design Sheet → Style & Order Lines (AnnotechERP 06/10): the styles,
 * orders and instructions one sheet asks a print design and strike-off for.
 * Add line keeps a line at once, with its artwork image if one comes — a
 * line needs its material description, sample type and shipment date, as
 * Brickland's entry row does. The list is then changed in place and saved
 * together (a line left out is removed), and a line's artwork is replaced
 * on its own. Artwork is a private file: JPG, PNG or WebP, up to 10 MB.
 */
class PrintLineApiController extends ApiController
{
    public function index(string $program): JsonResponse
    {
        $this->allow('merchandise.sample.view');
        $row = PrintProgram::query()->whereKey($program)->firstOrFail();
        SampleTypeDefaults::ensure($this->caller()->companyId);

        return ApiResponse::ok($this->present($row) + [
            // Brickland's "MOOD KIDS SHIRT — T-SHIRT"; the program buyer's own.
            'styles' => Style::query()->where('buyer_id', $row->buyer_id)->orderBy('style_ref')->get()->map(fn (Style $s): array => ['value' => $s->id, 'label' => "{$s->styleNo()} — {$s->description}"])->values()->all(),
            'orders' => Order::query()->where('buyer_id', $row->buyer_id)->where('status', '!=', 'CANCELLED')->orderByDesc('order_no')->get(['id', 'order_no', 'style_id', 'style_no', 'work_style_no', 'ship_date'])
                ->map(fn (Order $o): array => ['value' => $o->id, 'label' => "{$o->order_no} · {$o->style_no} (".($o->work_style_no ?: $o->style_no).')', 'style_id' => $o->style_id, 'ship_date' => $o->ship_date?->toDateString()])->values()->all(),
            'types' => SampleType::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (SampleType $t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
            'priorities' => PrintLine::PRIORITIES,
        ]);
    }

    public function add(Request $request, StoreFileAttachment $files, string $program): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = PrintProgram::query()->whereKey($program)->firstOrFail();
        $data = $request->validate($this->lineRules('', $row) + ['artwork' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240']], $this->lineMessages(''));

        DB::transaction(function () use ($row, $data, $request, $files): void {
            $line = PrintLine::query()->create($this->values($data) + ['program_id' => $row->id, 'sort_order' => 10 + (int) PrintLine::query()->where('program_id', $row->id)->max('sort_order')]);
            if ($request->hasFile('artwork')) {
                $this->keepArtwork($line, $request->file('artwork'), $files);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    public function artwork(Request $request, StoreFileAttachment $files, string $program, string $line): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = PrintProgram::query()->whereKey($program)->firstOrFail();
        $target = PrintLine::query()->where('program_id', $row->id)->whereKey($line)->firstOrFail();
        $request->validate(['artwork' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240']], ['artwork.required' => __('merchandise.print.artwork_required')]);
        $this->keepArtwork($target, $request->file('artwork'), $files);

        return ApiResponse::ok($this->present($row));
    }

    public function save(Request $request, string $program): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = PrintProgram::query()->whereKey($program)->firstOrFail();
        $data = $request->validate(['rows' => ['present', 'array', 'max:200'], 'rows.*.id' => ['required', 'string']] + $this->lineRules('rows.*.', $row), $this->lineMessages('rows.*.'));
        $existing = PrintLine::query()->where('program_id', $row->id)->get()->keyBy('id');
        $kept = collect($data['rows'])->pluck('id')->all();
        foreach ($kept as $id) {
            if (! $existing->has($id)) {
                throw ValidationException::withMessages(['rows' => __('merchandise.print.line_gone')]);
            }
        }

        DB::transaction(function () use ($row, $data, $existing, $kept): void {
            PrintLine::query()->where('program_id', $row->id)->whereNotIn('id', $kept)->delete();
            foreach (array_values($data['rows']) as $i => $r) {
                $existing[$r['id']]->update($this->values($r) + ['sort_order' => ($i + 1) * 10]);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** The artwork is the line's own private file; a new one takes the old one's place. */
    private function keepArtwork(PrintLine $line, UploadedFile $upload, StoreFileAttachment $files): void
    {
        $file = $files->handle($upload, 'merch_print_artwork', $line->id, $this->caller()->auditUserId());
        $line->update(['artwork_file_id' => $file->id, 'artwork_name' => mb_substr($upload->getClientOriginalName(), 0, 255)]);
    }

    /** @return array<string, list<mixed>> */
    private function lineRules(string $prefix, PrintProgram $row): array
    {
        $company = $this->caller()->companyId;

        return [
            $prefix.'style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)->where('buyer_id', $row->buyer_id)],
            $prefix.'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)->where('buyer_id', $row->buyer_id)],
            $prefix.'sample_type_id' => ['required', Rule::exists('sample_types', 'id')->where('company_id', $company)],
            $prefix.'material_description' => ['required', 'string', 'max:255'],
            $prefix.'nature_of_work' => ['nullable', 'string', 'max:160'],
            $prefix.'shipment_date' => ['required', 'date'],
            $prefix.'item' => ['nullable', 'string', 'max:96'],
            $prefix.'old_style_no' => ['nullable', 'string', 'max:96'],
            $prefix.'print_type' => ['nullable', 'string', 'max:96'],
            $prefix.'print_color' => ['nullable', 'string', 'max:96'],
            $prefix.'req_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            $prefix.'unit' => ['nullable', 'string', 'max:16'],
            $prefix.'priority' => ['nullable', Rule::in(PrintLine::PRIORITIES)],
            $prefix.'instruction' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    private function lineMessages(string $prefix): array
    {
        return [
            $prefix.'material_description.required' => __('merchandise.print.material_required'),
            $prefix.'sample_type_id.required' => __('merchandise.print.type_required'),
            $prefix.'shipment_date.required' => __('merchandise.print.shipment_required'),
            $prefix.'style_id.exists' => __('merchandise.print.other_buyer'),
            $prefix.'order_id.exists' => __('merchandise.print.other_buyer'),
        ];
    }

    /** @return array<string, mixed> */
    private function values(array $r): array
    {
        $text = fn (string $k): ?string => isset($r[$k]) && trim((string) $r[$k]) !== '' ? trim((string) $r[$k]) : null;

        return [
            'style_id' => $r['style_id'] ?? null, 'order_id' => $r['order_id'] ?? null, 'sample_type_id' => $r['sample_type_id'], 'material_description' => trim($r['material_description']),
            'nature_of_work' => $text('nature_of_work'), 'shipment_date' => $r['shipment_date'], 'item' => $text('item'), 'old_style_no' => $text('old_style_no'),
            'print_type' => $text('print_type'), 'print_color' => $text('print_color'), 'req_qty' => isset($r['req_qty']) && $r['req_qty'] !== '' ? $r['req_qty'] : null,
            'unit' => $text('unit'), 'priority' => $r['priority'] ?? null, 'instruction' => $text('instruction'),
        ];
    }

    /** @return array{program: array<string, mixed>, rows: list<array<string, mixed>>} */
    private function present(PrintProgram $row): array
    {
        return [
            'program' => ['id' => $row->id, 'program_no' => $row->program_no, 'buyer_id' => $row->buyer_id],
            'rows' => PrintLine::query()->where('program_id', $row->id)->orderBy('sort_order')->get()->map(fn (PrintLine $l): array => [
                'id' => $l->id, 'style_id' => $l->style_id, 'order_id' => $l->order_id, 'sample_type_id' => $l->sample_type_id, 'material_description' => $l->material_description,
                'nature_of_work' => $l->nature_of_work, 'shipment_date' => $l->shipment_date->toDateString(), 'item' => $l->item, 'old_style_no' => $l->old_style_no,
                'print_type' => $l->print_type, 'print_color' => $l->print_color, 'req_qty' => $l->req_qty, 'unit' => $l->unit, 'priority' => $l->priority,
                'instruction' => $l->instruction, 'artwork_file_id' => $l->artwork_file_id, 'artwork_name' => $l->artwork_name,
            ])->values()->all(),
        ];
    }
}
