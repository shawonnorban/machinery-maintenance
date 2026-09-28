<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Actions;

use App\Modules\Compliance\Models\BondEntitlement;
use App\Modules\Compliance\Models\BondLicence;
use App\Modules\Compliance\Models\UtilisationDeclaration;
use App\Modules\Compliance\Models\UtilisationDeclarationLine;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bond licences and utilisation declarations (Shared Systems §3.1, §3.2).
 *
 * The rule worth naming: a declaration can only be issued against a
 * licence that is **live on the day it is issued**. Customs accepts a UD
 * on the strength of the bond behind it, and raising one against a lapsed
 * or suspended licence produces paperwork that looks valid in the system
 * and is worthless at the port.
 */
class ManageBondCompliance
{
    private const SCALE = 4;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data  licence_no, issuing_office, issued_date?, valid_from, valid_to, notes?
     * @param  list<array<string, mixed>>  $entitlements  item_id, entitled_quantity, basis?, period_start?, period_end?
     */
    public function createLicence(Factory $factory, array $data, array $entitlements = [], ?string $userId = null): BondLicence
    {
        [$from, $to] = $this->period($data);

        return DB::transaction(function () use ($factory, $data, $from, $to, $entitlements, $userId): BondLicence {
            $licence = BondLicence::create([
                'factory_id' => $factory->id,
                'licence_no' => trim((string) $data['licence_no']),
                'issuing_office' => trim((string) $data['issuing_office']),
                'issued_date' => $data['issued_date'] ?? $from,
                'valid_from' => $from,
                'valid_to' => $to,
                'status' => 'ACTIVE',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            if ($entitlements !== []) {
                $this->writeEntitlements($licence, $this->resolveItems($entitlements));
            }

            return $licence->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $entitlements
     */
    public function updateLicence(BondLicence $licence, array $data, ?array $entitlements = null): BondLicence
    {
        [$from, $to] = $this->period($data + [
            'valid_from' => $licence->valid_from,
            'valid_to' => $licence->valid_to,
        ]);

        $resolved = $entitlements === null ? null : $this->resolveItems($entitlements);

        return DB::transaction(function () use ($licence, $data, $from, $to, $resolved): BondLicence {
            $licence->forceFill([
                'issuing_office' => $data['issuing_office'] ?? $licence->issuing_office,
                'issued_date' => $data['issued_date'] ?? $licence->issued_date,
                'valid_from' => $from,
                'valid_to' => $to,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $licence->notes,
            ])->save();

            if ($resolved !== null) {
                $licence->entitlements()->delete();
                $this->writeEntitlements($licence, $resolved);
            }

            return $licence->fresh();
        });
    }

    public function setLicenceStatus(BondLicence $licence, string $status, ?string $reason = null): BondLicence
    {
        if (! $licence->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $licence->status, 'to' => $status]),
            ])->status(409);
        }

        $licence->forceFill([
            'status' => $status,
            'notes' => $reason === null ? $licence->notes : trim(($licence->notes ?? '')."\n".$reason),
        ])->save();

        return $licence->fresh();
    }

    /**
     * @param  array<string, mixed>  $data  ud_no, ud_type?, issue_date?, valid_to?, purchase_order_id?, buyer_reference?, notes?
     * @param  list<array<string, mixed>>  $lines  item_id, declared_quantity, wastage_percent?
     */
    public function createDeclaration(
        BondLicence $licence,
        array $data,
        array $lines,
        ?string $userId = null,
    ): UtilisationDeclaration {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('compliance.ud_needs_lines')]);
        }

        $type = strtoupper((string) ($data['ud_type'] ?? 'UD'));

        if (! in_array($type, UtilisationDeclaration::TYPES, true)) {
            throw ValidationException::withMessages([
                'ud_type' => __('validation.in', ['attribute' => 'ud_type']),
            ]);
        }

        $resolved = $this->resolveItems($lines);

        return DB::transaction(function () use ($licence, $data, $type, $resolved, $userId): UtilisationDeclaration {
            $declaration = UtilisationDeclaration::create([
                'factory_id' => $licence->factory_id,
                'bond_licence_id' => $licence->id,
                'ud_no' => trim((string) $data['ud_no']),
                'ud_type' => $type,
                'issue_date' => $data['issue_date'] ?? CarbonImmutable::today(),
                'valid_to' => $data['valid_to'] ?? null,
                'production_order_id' => $data['production_order_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'buyer_reference' => $data['buyer_reference'] ?? null,
                'status' => 'DRAFT',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->writeDeclarationLines($declaration, $resolved);

            return $declaration->fresh();
        });
    }

    public function issueDeclaration(UtilisationDeclaration $declaration): UtilisationDeclaration
    {
        $this->assertDeclarationTransition($declaration, 'ISSUED');

        $licence = BondLicence::findOrFail($declaration->bond_licence_id);
        $on = $declaration->issue_date ?? CarbonImmutable::today();

        if (! $licence->isLive(CarbonImmutable::parse((string) $on))) {
            // Paperwork that looks valid here and is worthless at the port.
            throw ValidationException::withMessages([
                'bond_licence_id' => __('compliance.licence_not_live', [
                    'number' => $licence->licence_no,
                    'status' => $licence->status,
                ]),
            ])->status(409);
        }

        $declaration->forceFill([
            'status' => 'ISSUED',
            'issued_at' => CarbonImmutable::now(),
        ])->save();

        return $declaration->fresh();
    }

    public function closeDeclaration(UtilisationDeclaration $declaration): UtilisationDeclaration
    {
        $this->assertDeclarationTransition($declaration, 'CLOSED');

        $declaration->forceFill([
            'status' => 'CLOSED',
            'closed_at' => CarbonImmutable::now(),
        ])->save();

        return $declaration->fresh();
    }

    public function cancelDeclaration(UtilisationDeclaration $declaration, ?string $reason = null): UtilisationDeclaration
    {
        $this->assertDeclarationTransition($declaration, 'CANCELLED');

        $declaration->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $declaration->notes : trim(($declaration->notes ?? '')."\n".$reason),
        ])->save();

        return $declaration->fresh();
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, input: array<string, mixed>}>  $resolved
     */
    private function writeEntitlements(BondLicence $licence, Collection $resolved): void
    {
        foreach ($resolved as $index => $row) {
            $quantity = $this->money($row['input']['entitled_quantity'] ?? 0);

            if (bccomp($quantity, '0', self::SCALE) !== 1) {
                throw ValidationException::withMessages([
                    "entitlements.$index.entitled_quantity" => __('inventory.quantity_must_be_positive'),
                ]);
            }

            $basis = strtoupper((string) ($row['input']['basis'] ?? 'ANNUAL'));

            if (! in_array($basis, BondEntitlement::BASES, true)) {
                throw ValidationException::withMessages([
                    "entitlements.$index.basis" => __('validation.in', ['attribute' => 'basis']),
                ]);
            }

            BondEntitlement::create([
                'bond_licence_id' => $licence->id,
                'item_id' => $row['item']->id,
                'item_code' => $row['item']->item_code,
                'item_name' => $row['item']->name,
                'uom_id' => $row['uom_id'],
                'basis' => $basis,
                'entitled_quantity' => $quantity,
                // An annual ceiling defaults to the licence's own period
                // when nobody states a different year.
                'period_start' => $row['input']['period_start'] ?? ($basis === 'ANNUAL' ? $licence->valid_from : null),
                'period_end' => $row['input']['period_end'] ?? ($basis === 'ANNUAL' ? $licence->valid_to : null),
                'notes' => $row['input']['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, input: array<string, mixed>}>  $resolved
     */
    private function writeDeclarationLines(UtilisationDeclaration $declaration, Collection $resolved): void
    {
        foreach ($resolved as $index => $row) {
            $quantity = $this->money($row['input']['declared_quantity'] ?? 0);

            if (bccomp($quantity, '0', self::SCALE) !== 1) {
                throw ValidationException::withMessages([
                    "lines.$index.declared_quantity" => __('inventory.quantity_must_be_positive'),
                ]);
            }

            UtilisationDeclarationLine::create([
                'utilisation_declaration_id' => $declaration->id,
                'item_id' => $row['item']->id,
                'item_code' => $row['item']->item_code,
                'item_name' => $row['item']->name,
                'uom_id' => $row['uom_id'],
                'declared_quantity' => $quantity,
                'wastage_percent' => $row['input']['wastage_percent'] ?? 0,
                'notes' => $row['input']['notes'] ?? null,
            ]);
        }
    }

    /**
     * Items for entitlement and declaration lines.
     *
     * Not `ItemLineResolver::resolve()`: that requires a `quantity` key,
     * and these lines carry `entitled_quantity` or `declared_quantity`
     * instead. Its other rules — the item exists, is not retired, and its
     * unit is one this company uses — are kept.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return Collection<int, array{item: Item, uom_id: string, input: array<string, mixed>}>
     */
    private function resolveItems(array $lines): Collection
    {
        $items = Item::whereIn('id', collect($lines)->pluck('item_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        $uomIds = UnitOfMeasure::availableTo($this->context->companyId())
            ->where('active', true)->pluck('id')->flip();

        return collect($lines)->values()->map(function (array $line, int $index) use ($items, $uomIds): array {
            $item = $items->get((string) ($line['item_id'] ?? ''));

            if ($item === null) {
                throw ValidationException::withMessages(["lines.$index.item_id" => __('procurement.unknown_item')]);
            }

            if (! $item->active) {
                throw ValidationException::withMessages([
                    "lines.$index.item_id" => __('procurement.item_inactive', ['item' => $item->item_code.' — '.$item->name]),
                ]);
            }

            $uomId = (string) ($line['uom_id'] ?? '') ?: (string) $item->uom_id;

            if (! $uomIds->has($uomId)) {
                throw ValidationException::withMessages(["lines.$index.uom_id" => __('itemmaster.unknown_uom')]);
            }

            return ['item' => $item, 'uom_id' => $uomId, 'input' => $line];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(array $data): array
    {
        $from = CarbonImmutable::parse((string) ($data['valid_from'] ?? CarbonImmutable::today()));
        $to = CarbonImmutable::parse((string) ($data['valid_to'] ?? $from->addYear()));

        if ($to->startOfDay()->lessThan($from->startOfDay())) {
            throw ValidationException::withMessages([
                'valid_to' => __('compliance.licence_period_backwards'),
            ]);
        }

        return [$from, $to];
    }

    private function assertDeclarationTransition(UtilisationDeclaration $declaration, string $to): void
    {
        if (! $declaration->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $declaration->status, 'to' => $to]),
            ])->status(409);
        }
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }
}
