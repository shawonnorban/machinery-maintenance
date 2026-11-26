<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeAuditCheck;
use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Floor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Dye House → Master Data (AnnotechERP 17/01): what its forms pick from, the
 * colour group grid saved as a whole, and each buyer's chemical audit checks.
 */
class DyeHouseApiController extends ApiController
{
    /** Countries, floors, people, buyers, parties and currencies — with only the dye house permission. */
    public function lookups(): JsonResponse
    {
        $this->allow('dyehouse.master.view');
        $company = $this->caller()->companyId;
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id');

        return ApiResponse::ok([
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'floors' => Floor::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($f) => ['value' => $f->id, 'label' => "{$f->code} — {$f->name}"])->all(),
            'users' => User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            'buyers' => Buyer::query()->orderBy('short_name')->get(['id', 'code', 'short_name'])->map(fn ($b) => ['value' => $b->id, 'label' => "{$b->short_name} ({$b->code})"])->all(),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            // The company's currencies (Settings); every currency the system knows until it has set any.
            'currencies' => $this->currencies(),
        ]);
    }

    /** @return list<array{value: string, label: string}> */
    private function currencies(): array
    {
        $own = Currency::query()->where('active', true)->orderBy('code')->get(['code', 'name']);

        return $own->isNotEmpty()
            ? $own->map(fn ($c) => ['value' => $c->code, 'label' => "{$c->code} — {$c->name}"])->all()
            : array_map(fn (string $code) => ['value' => $code, 'label' => $code], Currency::CODES);
    }

    /** The colour group grid, every row at once. */
    public function saveColourGroups(Request $request): JsonResponse
    {
        $this->allow('dyehouse.master.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'groups' => ['present', 'array'],
            'groups.*.id' => ['required', 'distinct', Rule::exists('dye_colour_groups', 'id')->where('company_id', $company)],
            'groups.*.code' => ['required', 'string', 'max:16', 'distinct'],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.labdip_required' => ['required', 'boolean'],
            'groups.*.water_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'groups.*.wash_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'groups.*.display_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'groups.*.active' => ['required', 'boolean'],
        ], ['groups.*.code.distinct' => __('dyehouse.master.code_taken')]);

        DB::transaction(function () use ($data): void {
            // Codes may be swapped between rows, so they are cleared first and written after.
            $ids = array_column($data['groups'], 'id');
            DyeColourGroup::query()->whereIn('id', $ids)->update(['code' => DB::raw("'~' || right(id, 15)")]);
            foreach ($data['groups'] as $g) {
                DyeColourGroup::query()->whereKey($g['id'])->update([
                    'code' => trim($g['code']), 'name' => $g['name'], 'labdip_required' => $g['labdip_required'],
                    'water_cost' => $g['water_cost'] ?? 0, 'wash_cost' => $g['wash_cost'] ?? 0, 'display_order' => $g['display_order'] ?? 0, 'active' => $g['active'],
                ]);
            }
        });

        return ApiResponse::ok(['saved' => count($data['groups'])]);
    }

    /** Buyers with how many checks each has. */
    public function auditBuyers(): JsonResponse
    {
        $this->allow('dyehouse.master.view');
        $counts = DyeAuditCheck::query()->where('dropped', false)->selectRaw('buyer_id, count(*) as n')->groupBy('buyer_id')->pluck('n', 'buyer_id');

        return ApiResponse::ok(Buyer::query()->orderBy('short_name')->get(['id', 'code', 'short_name'])
            ->map(fn ($b) => ['value' => $b->id, 'label' => "{$b->short_name} ({$b->code})", 'checks' => (int) ($counts[$b->id] ?? 0)])->all());
    }

    public function auditChecks(string $buyer): JsonResponse
    {
        $this->allow('dyehouse.master.view');

        return ApiResponse::ok($this->checksOf($this->buyer($buyer)));
    }

    /** A buyer's checks as a whole: renamed, dropped, added and removed together. */
    public function saveAuditChecks(Request $request, string $buyer): JsonResponse
    {
        $this->allow('dyehouse.master.manage');
        $row = $this->buyer($buyer);
        $data = $request->validate([
            'checks' => ['present', 'array', 'max:500'],
            'checks.*.item_name' => ['required', 'string', 'max:255', 'distinct'],
            'checks.*.alter_item_name' => ['nullable', 'string', 'max:255'],
            'checks.*.dropped' => ['sometimes', 'boolean'],
        ], ['checks.*.item_name.distinct' => __('dyehouse.master.check_repeated')]);

        DB::transaction(function () use ($row, $data): void {
            DyeAuditCheck::query()->where('buyer_id', $row->id)->whereNotIn('item_name', array_column($data['checks'], 'item_name'))->delete();
            foreach (array_values($data['checks']) as $i => $check) {
                DyeAuditCheck::query()->updateOrCreate(
                    ['buyer_id' => $row->id, 'item_name' => $check['item_name']],
                    ['company_id' => $row->company_id, 'alter_item_name' => $check['alter_item_name'] ?? null, 'dropped' => (bool) ($check['dropped'] ?? false), 'sort_order' => ($i + 1) * 10],
                );
            }
        });

        return ApiResponse::ok($this->checksOf($row));
    }

    private function buyer(string $id): Buyer
    {
        return Buyer::query()->whereKey($id)->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function checksOf(Buyer $buyer): array
    {
        return DyeAuditCheck::query()->where('buyer_id', $buyer->id)->orderBy('sort_order')->get()
            ->map(fn (DyeAuditCheck $c) => ['item_name' => $c->item_name, 'alter_item_name' => $c->alter_item_name, 'dropped' => $c->dropped])->all();
    }
}
