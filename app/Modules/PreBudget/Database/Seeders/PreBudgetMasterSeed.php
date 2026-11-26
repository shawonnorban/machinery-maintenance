<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;
use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\LookupValue;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\OriginCountry;
use Illuminate\Support\Str;

/**
 * Pre-Budget → Master Data (AnnotechERP 33/34): the factory's own lists as
 * Norban held them (user, 2026-11-19: "নরবান থেকে তুলে আনবে") — Yarn Type,
 * Wash Type, Additional Process, GSM, Sustainability, Order Type and the
 * Accessories Items, from `Data/norban_masters.json` (exported from Norban's
 * database; duplicate names dropped, Norban units turned into UOM codes).
 * Rows go in through the screens' own rules; a name already there is left.
 *
 * Added 2026-11-20 (user: Norban's requisition lists into Annotech): Item,
 * Fabric Type, Fabric / Yarn Composition, Parts and Yarn Count as Pre-Budget
 * lists; Norban's buyers into the Buyer master (a buyer already there under
 * another spelling — CnA / C&A — is left; the country from the address, else
 * Bangladesh; the company's first buyer office); Brand, Season and Dept into
 * the company's lookup values.
 */
final class PreBudgetMasterSeed extends MasterSeed
{
    private const LIST_KEYS = [
        'YARN_TYPE' => 'pb-yarn-types', 'WASH_TYPE' => 'pb-wash-types', 'ADDITIONAL_PROCESS' => 'pb-additional-processes',
        'GSM' => 'pb-gsms', 'SUSTAINABILITY' => 'pb-sustainabilities', 'ORDER_TYPE' => 'pb-order-types',
        'ITEM' => 'pb-items', 'FABRIC_TYPE' => 'pb-fabric-types', 'FABRIC_COMPOSITION' => 'pb-fabric-compositions',
        'YARN_COMPOSITION' => 'pb-yarn-compositions', 'PARTS' => 'pb-parts', 'YARN_COUNT' => 'pb-yarn-counts',
    ];

    /** Norban's address country => this ERP's origin country name. */
    private const COUNTRY_ALIASES = ['USA' => 'United States', 'UK' => 'United Kingdom', 'Korea' => 'South Korea', 'UAE' => 'United Arab Emirates'];

    public function seed(ResourceSeeder $s): void
    {
        $data = json_decode((string) file_get_contents(dirname(__DIR__).'/Data/norban_masters.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach (self::LIST_KEYS as $list => $key) {
            $rows = [];
            foreach ($data['lists'][$list] ?? [] as $i => $v) {
                $rows[] = ['name' => $v['name'], 'description' => $v['description'], 'sort_order' => ($i + 1) * 10, 'active' => $v['active']];
            }
            $s->seed($key, $rows, ['name']);
        }

        $rows = [];
        foreach ($data['accessory_items'] ?? [] as $i => $a) {
            $rows[] = ['code' => sprintf('ACC-%04d', $i + 1), 'name' => $a['name'], 'uom' => $a['unit'], 'description' => $a['description'], 'active' => $a['active']];
        }
        $s->seed('pb-accessory-items', $rows, ['name']);

        $this->buyers($s, $data['buyers'] ?? []);
        foreach ($data['lookups'] ?? [] as $code => $values) {
            $this->lookups($code, $values);
        }
    }

    /** "C&A", "CnA", "c & a" → "cna": one buyer under Norban's spelling or ours. */
    private static function same(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', str_replace('&', 'n', mb_strtolower($name)));
    }

    /** @param list<array{name: string, address: ?string, active: bool}> $buyers */
    private function buyers(ResourceSeeder $s, array $buyers): void
    {
        $office = BuyerOffice::query()->orderBy('name')->value('id');
        if (! $office || $buyers === []) {
            return;
        }
        $have = [];
        foreach (Buyer::query()->get(['name', 'short_name', 'code']) as $b) {
            $have[self::same((string) $b->name)] = true;
            $have[self::same((string) $b->short_name)] = true;
        }
        $codes = Buyer::query()->pluck('code')->map(fn ($c) => strtoupper((string) $c))->flip()->all();
        $countries = OriginCountry::query()->pluck('id', 'name')->all();
        $home = $countries['Bangladesh'] ?? (reset($countries) ?: null);
        if (! $home) {
            return;
        }

        $rows = [];
        foreach ($buyers as $i => $b) {
            if (isset($have[self::same($b['name'])])) {
                continue;
            }
            $have[self::same($b['name'])] = true;
            $base = Str::limit(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($b['name']))), 12, '') ?: 'BUYER';
            $code = $base;
            for ($n = 2; isset($codes[$code]); $n++) {
                $code = $base.$n;
            }
            $codes[$code] = true;
            $place = trim((string) Str::afterLast((string) $b['address'], ','));
            $place = self::COUNTRY_ALIASES[$place] ?? $place;
            $rows[] = [
                'code' => $code, 'name' => $b['name'], 'short_name' => Str::limit($b['name'], 64, ''),
                'pi_address' => trim((string) $b['address']) ?: 'N/A', 'country_id' => $countries[$place] ?? $home,
                'registered_from' => now()->toDateString(), 'office_id' => $office, 'display_order' => ($i + 1) * 10, 'active' => $b['active'],
            ];
        }
        $s->seed('buyers', $rows, ['name']);
    }

    /** Values of a lookup table (Brand, Season, Garment Department) the company does not have yet, as its own values. */
    private function lookups(string $code, array $values): void
    {
        $table = LookupTable::query()->visible()->where('code', $code)->orderByRaw('company_id is null')->first();
        if (! $table) {
            return;
        }
        $names = $table->values()->visible()->pluck('name')->map(fn ($n) => mb_strtolower(trim((string) $n)))->flip()->all();
        $codes = LookupValue::query()->withoutGlobalScopes()->where('lookup_table_id', $table->id)->pluck('code')->flip()->all();
        $sort = (int) $table->values()->visible()->max('sort_order');
        foreach ($values as $v) {
            $key = mb_strtolower($v['name']);
            if (isset($names[$key])) {
                continue;
            }
            $names[$key] = true;
            $base = Str::limit(strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii($v['name'])), '_')), 44, '') ?: 'VALUE';
            $c = $base;
            for ($n = 2; isset($codes[$c]); $n++) {
                $c = "{$base}_{$n}";
            }
            $codes[$c] = true;
            $table->values()->create(['company_id' => $this->companyId(), 'code' => $c, 'name' => Str::limit($v['name'], 255, ''), 'sort_order' => $sort += 10, 'active' => $v['active']]);
        }
    }
}
