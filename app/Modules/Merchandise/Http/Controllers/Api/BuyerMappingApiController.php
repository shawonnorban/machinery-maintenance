<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\BuyerOfficeLink;
use App\Modules\Merchandise\Models\BuyerSampleType;
use App\Modules\Merchandise\Models\BuyerTestInstitute;
use App\Modules\Merchandise\Models\BuyerUserPermission;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\TestInstitute;
use App\Modules\Merchandise\Support\SampleTypeDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Merchandise Desk → Master Data, the "pick one, tick the rest" screens
 * (AnnotechERP 06/01 step B): an office's buyers, a buyer's offices, the
 * buyers a user may work (merchandising and marketing), a buyer's test labs —
 * and Buyer Sample Mapping, where each ticked sample type may carry its own
 * source, size and quantity. Each list is saved as a whole.
 */
class BuyerMappingApiController extends ApiController
{
    /** kind => [subject, item, pivot, subject column, item column, pivot scope] */
    private const KINDS = [
        'office-buyers' => [BuyerOffice::class, Buyer::class, BuyerOfficeLink::class, 'buyer_office_id', 'buyer_id', []],
        'buyer-offices' => [Buyer::class, BuyerOffice::class, BuyerOfficeLink::class, 'buyer_id', 'buyer_office_id', []],
        'user-buyers' => [User::class, Buyer::class, BuyerUserPermission::class, 'user_id', 'buyer_id', ['kind' => 'MERCHANDISING']],
        'user-buyers-marketing' => [User::class, Buyer::class, BuyerUserPermission::class, 'user_id', 'buyer_id', ['kind' => 'MARKETING']],
        'buyer-institutes' => [Buyer::class, TestInstitute::class, BuyerTestInstitute::class, 'buyer_id', 'test_institute_id', []],
    ];

    /** Records tab: every subject that has something ticked, with what. */
    public function index(string $kind): JsonResponse
    {
        $this->allow('merchandise.master.view');
        [$subjectClass, $itemClass, $pivot, $subjectCol, $itemCol, $scope] = $this->kind($kind);

        $links = $pivot::query()->where($scope)->get([$subjectCol, $itemCol])->groupBy($subjectCol);
        $subjects = $this->subjects($subjectClass)->whereIn('id', $links->keys())->get()->keyBy('id');
        $labels = $this->items($itemClass)->get()->mapWithKeys(fn (Model $m): array => [$m->getKey() => $this->label($m)]);

        return ApiResponse::ok($links->map(fn (Collection $rows, string $id): array => [
            'id' => $id,
            'subject' => $subjects->has($id) ? $this->label($subjects[$id]) : null,
            'count' => $rows->count(),
            'items' => $rows->pluck($itemCol)->map(fn (string $i): ?string => $labels[$i] ?? null)->filter()->sort()->values()->all(),
        ])->filter(fn (array $r): bool => $r['subject'] !== null)->values()->all());
    }

    /** What the picker offers: the offices, buyers or users of this company. */
    public function subjectOptions(string $kind): JsonResponse
    {
        $this->allow('merchandise.master.view');
        [$subjectClass] = $this->kind($kind);

        return ApiResponse::ok($this->subjects($subjectClass)->get()->map(fn (Model $m): array => ['value' => $m->getKey(), 'label' => $this->label($m)])->all());
    }

    public function show(string $kind, string $subject): JsonResponse
    {
        $this->allow('merchandise.master.view');

        return ApiResponse::ok($this->rows($kind, $this->subject($kind, $subject)));
    }

    public function save(Request $request, string $kind, string $subject): JsonResponse
    {
        $this->allow('merchandise.master.manage');
        [, $itemClass, $pivot, $subjectCol, $itemCol, $scope] = $this->kind($kind);
        $row = $this->subject($kind, $subject);
        $data = $request->validate(['ids' => ['present', 'array'], 'ids.*' => ['string', 'distinct']]);

        $valid = $this->items($itemClass)->whereIn('id', $data['ids'])->pluck('id')->all();
        if (count($valid) !== count($data['ids'])) {
            throw ValidationException::withMessages(['ids' => __('merchandise.master.unknown_item')]);
        }

        DB::transaction(function () use ($pivot, $scope, $subjectCol, $itemCol, $row, $valid): void {
            $pivot::query()->where($scope)->where($subjectCol, $row->getKey())->whereNotIn($itemCol, $valid)->delete();
            foreach ($valid as $itemId) {
                $pivot::query()->firstOrCreate($scope + [$subjectCol => $row->getKey(), $itemCol => $itemId], ['company_id' => $this->caller()->companyId]);
            }
        });

        return ApiResponse::ok($this->rows($kind, $row));
    }

    /** Buyer Sample Mapping: every active sample type, the ones this buyer requires ticked with their overrides. */
    public function samples(string $buyer): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $row = Buyer::query()->whereKey($buyer)->firstOrFail();

        return ApiResponse::ok($this->sampleRows($row));
    }

    public function saveSamples(Request $request, string $buyer): JsonResponse
    {
        $this->allow('merchandise.master.manage');
        $row = Buyer::query()->whereKey($buyer)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'samples' => ['present', 'array'],
            'samples.*.sample_type_id' => ['required', 'distinct', Rule::exists('sample_types', 'id')->where('company_id', $company)],
            'samples.*.source' => ['nullable', Rule::in(BuyerSampleType::SOURCES)],
            'samples.*.size' => ['nullable', 'string', 'max:32'],
            'samples.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        DB::transaction(function () use ($row, $data, $company): void {
            $keep = array_column($data['samples'], 'sample_type_id');
            BuyerSampleType::query()->where('buyer_id', $row->id)->whereNotIn('sample_type_id', $keep)->delete();
            foreach ($data['samples'] as $sample) {
                BuyerSampleType::query()->updateOrCreate(
                    ['buyer_id' => $row->id, 'sample_type_id' => $sample['sample_type_id']],
                    ['company_id' => $company, 'source' => $sample['source'] ?? null, 'size' => $sample['size'] ?? null, 'quantity' => $sample['quantity'] ?? null],
                );
            }
        });

        return ApiResponse::ok($this->sampleRows($row));
    }

    /**
     * @return array{0: class-string<Model>, 1: class-string<Model>, 2: class-string<Model>, 3: string, 4: string, 5: array<string, string>}
     */
    private function kind(string $kind): array
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind];
    }

    private function subject(string $kind, string $id): Model
    {
        [$subjectClass] = $this->kind($kind);

        return $this->subjects($subjectClass)->whereKey($id)->firstOrFail();
    }

    /** Users are this company's active members; everything else is tenant-scoped already. */
    private function subjects(string $class): Builder
    {
        if ($class === User::class) {
            $members = CompanyUser::query()->where('company_id', $this->caller()->companyId)->where('status', 'ACTIVE')->pluck('user_id');

            return User::query()->whereIn('id', $members)->orderBy('name');
        }

        return $this->items($class);
    }

    private function items(string $class): Builder
    {
        return match ($class) {
            Buyer::class => Buyer::query()->orderBy('display_order')->orderBy('short_name')->orderBy('code'),
            default => $class::query()->orderBy('name'),
        };
    }

    private function label(Model $m): string
    {
        return match (true) {
            $m instanceof Buyer => "{$m->short_name} ({$m->code})",
            default => (string) $m->getAttribute('name'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $kind, Model $subject): array
    {
        [, $itemClass, $pivot, $subjectCol, $itemCol, $scope] = $this->kind($kind);
        $ticked = $pivot::query()->where($scope)->where($subjectCol, $subject->getKey())->pluck($itemCol)->flip();

        return $this->items($itemClass)->get()->map(fn (Model $m): array => [
            'id' => $m->getKey(),
            'label' => $this->label($m),
            'active' => (bool) ($m->getAttribute('active') ?? true),
            'assigned' => $ticked->has($m->getKey()),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleRows(Buyer $buyer): array
    {
        SampleTypeDefaults::ensure($this->caller()->companyId);
        $mapped = BuyerSampleType::query()->where('buyer_id', $buyer->id)->get()->keyBy('sample_type_id');

        return SampleType::query()->where('active', true)->orderBy('stage')->orderBy('code')->get()->map(function (SampleType $t) use ($mapped): array {
            $m = $mapped->get($t->id);

            return ['sample_type_id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'stage' => $t->stage, 'lead_days' => $t->lead_days, 'assigned' => $m !== null, 'source' => $m?->source, 'size' => $m?->size, 'quantity' => $m?->quantity];
        })->all();
    }
}
