<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Knitting\Models\KnitMachine;
use App\Modules\Knitting\Models\KnitOperatorPosting;
use App\Modules\Knitting\Models\KnitRefEntry;
use App\Modules\Knitting\Models\KnitUserOffice;
use App\Modules\Knitting\Support\KnittingDefaults;
use App\Modules\Knitting\Support\KnittingLabels;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Master Data (AnnotechERP 15/01): what its forms pick
 * from; the machine reference lists, each saved as a whole; users' company and
 * office mapping; and a machine's postings, closed or withdrawn as a whole.
 */
class KnittingApiController extends ApiController
{
    public function lookups(): JsonResponse
    {
        $this->allow('knitting.master.view');
        $company = $this->caller()->companyId;
        KnittingDefaults::refLists($company);
        $floors = KnittingLabels::floors();
        $refs = KnitRefEntry::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'list', 'code', 'name']);

        return ApiResponse::ok([
            'machines' => KnitMachine::query()->where('active', true)->orderBy('machine_no')->get(['id', 'machine_no', 'floor_id'])->map(fn ($m) => ['value' => $m->id, 'label' => $m->machine_no, 'floor' => $floors[$m->floor_id] ?? null])->all(),
            'floors' => collect($floors)->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'shifts' => collect(KnittingLabels::shifts())->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'users' => $this->users()->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'code' => $u->employee_code, 'login' => $u->username ?: $u->email])->all(),
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            // Sub-Contract Party Rate (Dye House's quotation): the parties and currencies it picks from.
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'currencies' => ($own = Currency::query()->where('active', true)->orderBy('code')->get(['code', 'name']))->isNotEmpty()
                ? $own->map(fn ($c) => ['value' => $c->code, 'label' => "{$c->code} — {$c->name}"])->all()
                : array_map(fn (string $code) => ['value' => $code, 'label' => $code], Currency::CODES),
            'refs' => collect(KnitRefEntry::LISTS)->mapWithKeys(fn ($list) => [$list => $refs->where('list', $list)->values()->map(fn ($r) => ['value' => $r->id, 'label' => $list === 'MC_TYPE' ? "{$r->name} ({$r->code})" : $r->name])->all()])->all(),
        ]);
    }

    /** The four reference lists with how many entries each holds. */
    public function refLists(): JsonResponse
    {
        $this->allow('knitting.master.view');
        KnittingDefaults::refLists($this->caller()->companyId);
        $counts = KnitRefEntry::query()->selectRaw('list, count(*) as n')->groupBy('list')->pluck('n', 'list');

        return ApiResponse::ok(array_map(fn ($list) => ['key' => $list, 'count' => (int) ($counts[$list] ?? 0)], KnitRefEntry::LISTS));
    }

    public function refEntries(string $list): JsonResponse
    {
        $this->allow('knitting.master.view');
        abort_unless(in_array($list, KnitRefEntry::LISTS, true), 404);
        KnittingDefaults::refLists($this->caller()->companyId);
        $uses = $this->uses($list);

        return ApiResponse::ok(KnitRefEntry::query()->where('list', $list)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($e) => ['id' => $e->id, 'code' => $e->code, 'name' => $e->name, 'sort_order' => (string) $e->sort_order, 'machines' => $uses[$e->id] ?? 0, 'active' => $e->active])->all());
    }

    /** One reference list as a whole: names, order and Active? edited, entries added, unused ones removed. */
    public function saveRefEntries(Request $request, string $list): JsonResponse
    {
        $this->allow('knitting.master.manage');
        abort_unless(in_array($list, KnitRefEntry::LISTS, true), 404);
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', Rule::exists('knit_ref_entries', 'id')->where('company_id', $company)->where('list', $list)],
            'rows.*.code' => ['required', 'string', 'max:16'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rows.*.active' => ['sometimes', 'boolean'],
        ], ['rows.*.code.required' => __('knitting.master.entry_needs_code'), 'rows.*.name.required' => __('knitting.master.entry_needs_name')]);
        $codes = array_map(fn ($r) => strtoupper(trim($r['code'])), $data['rows']);
        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['rows' => __('knitting.master.entry_code_taken')]);
        }
        $uses = $this->uses($list);
        $kept = array_filter(array_column($data['rows'], 'id'));
        $dropped = KnitRefEntry::query()->where('list', $list)->whereNotIn('id', $kept)->pluck('id');
        if ($dropped->contains(fn ($id) => ($uses[$id] ?? 0) > 0)) {
            throw ValidationException::withMessages(['rows' => __('knitting.master.entry_in_use')]);
        }

        DB::transaction(function () use ($data, $list, $company, $dropped): void {
            KnitRefEntry::query()->whereIn('id', $dropped)->delete();
            foreach ($data['rows'] as $row) {
                $values = ['code' => strtoupper(trim($row['code'])), 'name' => trim($row['name']), 'sort_order' => (int) ($row['sort_order'] ?? 0), 'active' => (bool) ($row['active'] ?? true)];
                empty($row['id'])
                    ? KnitRefEntry::query()->create($values + ['company_id' => $company, 'list' => $list])
                    : KnitRefEntry::query()->whereKey($row['id'])->firstOrFail()->update($values);
            }
        });
        KnittingLabels::forget();

        return $this->refEntries($list);
    }

    /** Every mapping — the Mappings tab. */
    public function userOffices(): JsonResponse
    {
        $this->allow('knitting.master.view');
        $users = User::query()->pluck('name', 'id');
        $factories = Factory::query()->get(['id', 'name', 'address'])->keyBy('id');
        $companyName = Company::query()->whereKey($this->caller()->companyId)->value('name');

        return ApiResponse::ok(KnitUserOffice::query()->get()->map(fn ($m) => [
            'id' => $m->id, 'user' => $users[$m->user_id] ?? null, 'company' => $companyName, 'office' => $factories[$m->factory_id]->name ?? null,
            'address' => $factories[$m->factory_id]->address ?? null, 'is_primary' => $m->is_primary, 'can_write' => $m->can_write,
        ])->sortBy('user')->values()->all());
    }

    /** The user list, with how many offices each is confined to. */
    public function officeUsers(): JsonResponse
    {
        $this->allow('knitting.master.view');
        $counts = KnitUserOffice::query()->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        return ApiResponse::ok($this->users()->map(fn ($u) => ['key' => $u->id, 'label' => $u->name.' ('.($u->username ?: $u->email).')', 'count' => (int) ($counts[$u->id] ?? 0)])->all());
    }

    /** Every office, ticked where the user is mapped to it today. */
    public function officesOf(string $user): JsonResponse
    {
        $this->allow('knitting.master.view');
        abort_unless(in_array($user, $this->members(), true), 404);
        $mapped = KnitUserOffice::query()->where('user_id', $user)->get()->keyBy('factory_id');
        $companyName = Company::query()->whereKey($this->caller()->companyId)->value('name');

        return ApiResponse::ok(Factory::query()->orderBy('name')->get(['id', 'name', 'code', 'address'])->map(fn ($f) => [
            'factory_id' => $f->id, 'company' => $companyName, 'office' => "{$f->name} ({$f->code})", 'address' => $f->address,
            'selected' => $mapped->has($f->id), 'is_primary' => (bool) $mapped->get($f->id)?->is_primary, 'can_write' => $mapped->has($f->id) ? (bool) $mapped->get($f->id)->can_write : true,
        ])->all());
    }

    /** The user's grid as it stands: ticked offices granted, unticked revoked. */
    public function saveOfficesOf(Request $request, string $user): JsonResponse
    {
        $this->allow('knitting.master.manage');
        abort_unless(in_array($user, $this->members(), true), 404);
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.factory_id' => ['required', 'distinct', Rule::exists('factories', 'id')->where('company_id', $company)],
            'rows.*.selected' => ['sometimes', 'boolean'],
            'rows.*.is_primary' => ['sometimes', 'boolean'],
            'rows.*.can_write' => ['sometimes', 'boolean'],
        ]);
        $chosen = array_values(array_filter($data['rows'], fn ($r) => $r['selected'] ?? false));
        if (count(array_filter($chosen, fn ($r) => $r['is_primary'] ?? false)) > 1) {
            throw ValidationException::withMessages(['rows' => __('knitting.master.one_primary')]);
        }

        DB::transaction(function () use ($chosen, $user, $company): void {
            KnitUserOffice::query()->where('user_id', $user)->delete();
            foreach ($chosen as $row) {
                KnitUserOffice::query()->create(['company_id' => $company, 'user_id' => $user, 'factory_id' => $row['factory_id'], 'is_primary' => (bool) ($row['is_primary'] ?? false), 'can_write' => (bool) ($row['can_write'] ?? true)]);
            }
        });

        return $this->officesOf($user);
    }

    /** The machine list, with how many postings each holds. */
    public function postingMachines(): JsonResponse
    {
        $this->allow('knitting.master.view');
        $counts = KnitOperatorPosting::query()->where('active', true)->selectRaw('machine_id, count(*) as n')->groupBy('machine_id')->pluck('n', 'machine_id');

        return ApiResponse::ok(KnitMachine::query()->orderBy('machine_no')->get(['id', 'machine_no'])->map(fn ($m) => ['key' => $m->id, 'label' => $m->machine_no, 'count' => (int) ($counts[$m->id] ?? 0)])->all());
    }

    public function postingsOf(string $machine): JsonResponse
    {
        $this->allow('knitting.master.view');
        $row = KnitMachine::query()->whereKey($machine)->firstOrFail();
        $users = User::query()->get(['id', 'name', 'employee_code'])->keyBy('id');
        $shifts = KnittingLabels::shifts();

        return ApiResponse::ok(KnitOperatorPosting::query()->where('machine_id', $row->id)->where('active', true)->orderByDesc('effective_at')->get()->map(fn ($p) => [
            'id' => $p->id, 'emp_code' => $users[$p->user_id]->employee_code ?? null, 'name' => $users[$p->user_id]->name ?? null, 'shift' => $shifts[$p->shift_id] ?? null,
            'effective_at' => $p->effective_at->format('Y-m-d\TH:i'), 'effective_label' => $p->effective_at->format('d-M-Y h:i A'), 'until_at' => $p->until_at?->format('Y-m-d\TH:i'),
        ])->all());
    }

    /** Close postings by dating them, or withdraw those raised in error (left out of the grid). */
    public function savePostingsOf(Request $request, string $machine): JsonResponse
    {
        $this->allow('knitting.master.manage');
        $row = KnitMachine::query()->whereKey($machine)->firstOrFail();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['required', 'distinct', Rule::exists('knit_operator_postings', 'id')->where('machine_id', $row->id)],
            'rows.*.until_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($data, $row): void {
            $kept = array_column($data['rows'], 'id');
            KnitOperatorPosting::query()->where('machine_id', $row->id)->where('active', true)->whereNotIn('id', $kept)->update(['active' => false]);
            foreach ($data['rows'] as $line) {
                $posting = KnitOperatorPosting::query()->whereKey($line['id'])->firstOrFail();
                $until = empty($line['until_at']) ? null : Carbon::parse($line['until_at']);
                if ($until && $until->lte($posting->effective_at)) {
                    throw ValidationException::withMessages(['rows' => __('knitting.master.until_before_effective')]);
                }
                $posting->update(['until_at' => $until]);
            }
        });

        return $this->postingsOf($row->id);
    }

    /** @return array<string, int> entry id => machines pointing at it, a supported gauge included */
    private function uses(string $list): array
    {
        $column = ['MC_TYPE' => 'machine_type_id', 'BRAND' => 'brand_id', 'DIA' => 'dia_id', 'GG' => 'gauge_id'][$list];
        $uses = KnitMachine::query()->whereNotNull($column)->selectRaw("{$column} as ref, count(*) as n")->groupBy($column)->pluck('n', 'ref')->map(fn ($n) => (int) $n)->all();
        if ($list === 'GG') {
            foreach (KnitMachine::query()->get(['gauge_id', 'supported_gauge_ids']) as $m) {
                foreach (array_diff($m->supported_gauge_ids ?? [], [$m->gauge_id]) as $id) {
                    $uses[$id] = ($uses[$id] ?? 0) + 1;
                }
            }
        }

        return $uses;
    }

    /** @return Collection<int, User> */
    private function users()
    {
        return User::query()->whereIn('id', $this->members())->orderBy('name')->get(['id', 'name', 'email', 'username', 'employee_code']);
    }

    /** @return list<string> */
    private function members(): array
    {
        return CompanyUser::query()->where('company_id', $this->caller()->companyId)->where('status', 'ACTIVE')->pluck('user_id')->all();
    }
}
