<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\AccessMapping;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\LookupValue;
use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Shared\Tenancy\TenantContext;
use InvalidArgumentException;

/**
 * Brickland's mapping screens (AnnotechERP 02/02), as data: for each kind, what
 * its subject is (a user, a menu, a buyer or a store), what it maps to (a
 * store, a buyer, an item category, a lookup table's values, a pair of them,
 * a report), which yes/no rights a row carries, and which extra columns it
 * uses. The API and the screens are generic over this.
 *
 * The rule a screen enforcing a mapping applies (`targetsFor`): a subject with
 * no rows of that kind is not restricted by it; once it has any, only the
 * mapped targets count.
 */
class MappingRegistry
{
    /**
     * kind => [subject, target, flags (with defaults on a new row), extras, permission].
     * extras: amount (a limit or points), sort_order, is_default, text, date_from / date_to (an effective window).
     * Optional: `select` — a row is there because it is ticked, its flags are attributes of it;
     * `required` — extras a row must have; `amount_range` — the points allowed;
     * `default_scope` — `target` when the one default is per target (a buyer's default store), not per subject.
     */
    public const KINDS = [
        'user-store' => ['subject' => 'user', 'target' => 'store', 'flags' => ['issue' => false, 'receive' => true, 'adjust' => false], 'extras' => [], 'permission' => 'admin.user.manage'],
        'user-requisition' => ['subject' => 'user', 'target' => 'lookup:REQUISITION_TYPE', 'flags' => ['raise' => true, 'approve' => false], 'extras' => ['amount'], 'permission' => 'admin.user.manage'],
        'user-production-category' => ['subject' => 'user', 'target' => 'lookup:PRODUCTION_CATEGORY', 'flags' => ['plan' => false, 'report' => true], 'extras' => [], 'permission' => 'admin.user.manage'],
        'user-rule' => ['subject' => 'user', 'target' => 'lookup:BUSINESS_RULE', 'flags' => ['override' => false], 'extras' => [], 'permission' => 'admin.user.manage'],
        'menu-report' => ['subject' => 'menu', 'target' => 'report', 'flags' => [], 'extras' => ['sort_order', 'is_default'], 'permission' => 'admin.role.manage'],
        // The buyer mappings: fabric rights per user and buyer, in a window; grey fabric transfer rules per buyer;
        // a buyer's defect points; the stores holding a buyer's material; the item categories a buyer nominates.
        'user-buyer-fabric' => ['subject' => 'user', 'target' => 'buyer', 'flags' => ['issue' => false, 'receive' => false, 'transfer' => false, 'return' => false], 'extras' => ['date_from', 'date_to'], 'required' => ['date_from'], 'permission' => 'admin.user.manage'],
        'buyer-grey-transfer' => ['subject' => 'buyer', 'target' => 'pair:FABRIC_BOOKING_CATEGORY', 'flags' => ['active' => true], 'extras' => [], 'select' => true, 'permission' => 'admin.user.manage'],
        'buyer-defect' => ['subject' => 'buyer', 'target' => 'lookup:FABRIC_DEFECT', 'flags' => ['critical' => false, 'active' => true], 'extras' => ['amount', 'sort_order'], 'select' => true, 'required' => ['amount'], 'amount_range' => [1, 4], 'permission' => 'admin.role.manage'],
        'store-buyer' => ['subject' => 'store', 'target' => 'buyer', 'flags' => [], 'extras' => ['is_default'], 'default_scope' => 'target', 'permission' => 'admin.role.manage'],
        'buyer-item-category' => ['subject' => 'buyer', 'target' => 'item_category', 'flags' => ['sub_style' => false, 'active' => true], 'extras' => ['text'], 'select' => true, 'permission' => 'admin.role.manage'],
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly MenuOverlay $overlay,
    ) {}

    /**
     * @return array{subject: string, target: string, flags: array<string, bool>, extras: list<string>, permission: string}
     */
    public function kind(string $kind): array
    {
        return self::KINDS[$kind] ?? throw new InvalidArgumentException("Unknown mapping kind [{$kind}].");
    }

    /**
     * Who a kind's rows are about, as `id => label`.
     *
     * @return array<string, string>
     */
    public function subjects(string $kind): array
    {
        return match ($this->kind($kind)['subject']) {
            'user' => $this->users(),
            'menu' => $this->menus(),
            'buyer' => $this->buyers(),
            'store' => $this->stores(),
        };
    }

    /**
     * The readable key a subject is addressed by in a screen's URL (a buyer's or
     * a store's code), as `id => key`; users and menus have none.
     *
     * @return array<string, string>
     */
    public function subjectKeys(string $kind): array
    {
        return match ($this->kind($kind)['subject']) {
            'buyer' => Buyer::query()->where('active', true)->pluck('code', 'id')->map(fn ($c) => (string) $c)->all(),
            'store' => Store::query()->pluck('code', 'id')->map(fn ($c) => (string) $c)->all(),
            default => [],
        };
    }

    /**
     * What a kind maps to, as `id => label`, in display order.
     *
     * @return array<string, string>
     */
    public function targets(string $kind): array
    {
        $target = $this->kind($kind)['target'];

        if (str_starts_with($target, 'lookup:')) {
            return $this->lookupValues(substr($target, 7));
        }
        // Every ordered pair of one table's values: "FP01>FP02" => "Sample Booking (FP01) → Bulk Booking (FP02)".
        if (str_starts_with($target, 'pair:')) {
            $values = $this->lookupValues(substr($target, 5), plain: true);
            $pairs = [];
            foreach ($values as $from => $fromLabel) {
                foreach ($values as $to => $toLabel) {
                    if ($from !== $to) {
                        $pairs["{$from}>{$to}"] = "{$fromLabel} ({$from}) → {$toLabel} ({$to})";
                    }
                }
            }

            return $pairs;
        }

        return match ($target) {
            'store' => $this->stores(),
            'buyer' => $this->buyers(),
            'item_category' => ItemCategory::query()->whereNull('parent_id')->where('active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'report' => collect(app(ReportRegistry::class)->all())->map(fn ($report): string => $report->title())->sort()->all(),
        };
    }

    /**
     * The targets a subject may use for one right; null when the subject has
     * no rows of this kind at all (not restricted by it).
     *
     * @return list<string>|null
     */
    public function targetsFor(string $kind, string $subjectId, ?string $flag = null): ?array
    {
        $rows = AccessMapping::query()->where('kind', $kind)->where('subject_id', $subjectId)->get();

        if ($rows->isEmpty()) {
            return null;
        }
        // A windowed right counts only on the days it is in force.
        $today = now()->toDateString();
        $inForce = fn (AccessMapping $row): bool => ($row->date_from === null || $row->date_from->toDateString() <= $today)
            && ($row->date_to === null || $row->date_to->toDateString() >= $today);

        return $rows->filter(fn (AccessMapping $row): bool => ($flag === null || $row->holds($flag)) && $inForce($row))->pluck('target_id')->values()->all();
    }

    public function allows(string $kind, string $subjectId, string $targetId, ?string $flag = null): bool
    {
        $targets = $this->targetsFor($kind, $subjectId, $flag);

        return $targets === null || in_array($targetId, $targets, true);
    }

    /** @return array<string, string> */
    private function users(): array
    {
        $ids = CompanyUser::query()->where('company_id', $this->context->companyId())->pluck('user_id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'username', 'email'])
            ->mapWithKeys(fn (User $u): array => [$u->id => $u->name.' ('.($u->username ?? $u->email).')'])->all();
    }

    /** @return array<string, string> */
    private function menus(): array
    {
        $menus = $this->overlay->menus(Menu::query()->where('is_leaf', true)->where('active', true)->with('module')->get())->where('active', true);

        return $menus->mapWithKeys(fn (Menu $m): array => [$m->id => ($m->module?->name ? $m->module->name.' › ' : '').$m->name])->all();
    }

    /** @return array<string, string> "LPP (LPP)", as Brickland lists a buyer account */
    private function buyers(): array
    {
        return Buyer::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
            ->mapWithKeys(fn (Buyer $b): array => [$b->id => $b->code ? "{$b->name} ({$b->code})" : $b->name])->all();
    }

    /** @return array<string, string> */
    private function stores(): array
    {
        return Store::query()->orderBy('name')->get()->mapWithKeys(fn (Store $s): array => [$s->id => $s->code ? "{$s->name} ({$s->code})" : $s->name])->all();
    }

    /** @return array<string, string> */
    private function lookupValues(string $code, bool $plain = false): array
    {
        // The company's own table of that code wins over the shared one.
        $table = LookupTable::query()->visible()->where('code', $code)->orderByRaw('company_id is null')->first();
        if ($table === null) {
            return [];
        }

        return $table->values()->visible()->where('active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->mapWithKeys(fn (LookupValue $v): array => [$v->code => $plain ? $v->name : "{$v->code} — {$v->name}"])->all();
    }
}
