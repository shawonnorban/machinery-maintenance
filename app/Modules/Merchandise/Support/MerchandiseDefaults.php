<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Fiber;
use App\Modules\Merchandise\Models\ItemType;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Models\RateLine;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Merchandise\Models\WorkNature;

/**
 * The fabric types and fibers a company starts with (Merchandise Desk → Master
 * Data, AnnotechERP 06/01), as Brickland ships them — and the inquiry rate
 * lines and T&A tasks. Created the first time a
 * company opens either list, and only into an empty list; after that they are
 * the company's to change.
 */
final class MerchandiseDefaults
{
    /** code => [name, short name, rib?, base?, order] */
    public const FABRIC_TYPES = [
        'SJ' => ['Single Jersey', 'S/J', false, true, 10],
        'LSJ' => ['Lycra Single Jersey', 'L S/J', false, false, 20],
        'PIQ' => ['Pique / Lacoste', 'PIQ', false, false, 30],
        'RIB1X1' => ['1x1 Rib', '1x1 RIB', true, false, 40],
        'RIB2X2' => ['2x2 Rib', '2x2 RIB', true, false, 50],
        'INTLK' => ['Interlock', 'INTLK', false, false, 60],
        'FLEECE' => ['Fleece', 'FLC', false, false, 70],
        'TERRY' => ['French Terry', 'F/TERRY', false, false, 80],
        'MESH' => ['Mesh', 'MESH', false, false, 90],
    ];

    /** code => [name, short name, order] */
    public const FIBERS = [
        'COT' => ['Cotton', 'Cot', 10],
        'POLY' => ['Polyester', 'Poly', 20],
        'ELA' => ['Elastane', 'Spandex', 30],
        'VIS' => ['Viscose', 'Vis', 40],
        'MOD' => ['Modal', 'Mod', 50],
        'NYL' => ['Nylon', 'Nyl', 60],
        'LIN' => ['Linen', 'Lin', 70],
        'WOOL' => ['Wool', 'Wool', 80],
    ];

    public static function ensure(string $companyId): void
    {
        if (! FabricType::query()->exists()) {
            foreach (self::FABRIC_TYPES as $code => [$name, $short, $rib, $base, $order]) {
                FabricType::query()->create([
                    'company_id' => $companyId, 'code' => $code, 'name' => $name, 'short_name' => $short,
                    'fabric_group' => 'BODY', 'category' => 'KNIT', 'construction' => 'CIRCULAR',
                    'is_rib' => $rib, 'is_base' => $base, 'sort_order' => $order, 'active' => true,
                ]);
            }
        }
        if (! Fiber::query()->exists()) {
            foreach (self::FIBERS as $code => [$name, $short, $order]) {
                Fiber::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'short_name' => $short, 'sort_order' => $order, 'active' => true]);
            }
        }
    }

    /** group => list of [description, uom] — Brickland's inquiry rate chart. */
    public const RATE_LINES = [
        'YARN' => [['100% Cotton Any Count (Combed)', 'Kg'], ['100% Cotton Any Count (Carded)', 'Kg'], ['100% or Blended Any Fiber for Any Count', 'Kg'], ['Lycra Yarn', 'Kg']],
        'KNITTING' => [['S/Jersey', 'Kg'], ['Slub S/Jersey', 'Kg'], ['Heavy Jersey (Double ply)', 'Kg'], ['Lycra S/Jersey Full Feeder', 'Kg'], ['Pique/Lacoste', 'Kg'], ['1x1 Rib', 'Kg'], ['2x2 Rib', 'Kg'], ['Interlock', 'Kg'], ['French Terry', 'Kg'], ['Mesh', 'Kg']],
        'YARN_DYEING' => [['Avg Color', 'Kg']],
        'AOP' => [['Burn Out', 'Kg'], ['Pigment', 'Kg'], ['Reactive', 'Kg'], ['Discharge', 'Kg'], ['Digital', 'Kg']],
        'DYEING' => [['1 Part', 'Kg'], ['2 Part', 'Kg'], ['Y/D Wash', 'Kg'], ['1 Part w/Lycra', 'Kg']],
        'DYEING_FINISHING' => [['Without Lycra', 'Kg'], ['With Lycra', 'Kg'], ['w/HeatSet', 'Kg'], ['w/Brush', 'Kg']],
    ];

    /** code => [name, Bangla name] */
    public const TNA_GROUPS = [
        'GENPROC' => ['General Process', 'সাধারণ প্রক্রিয়া'],
        'SAMPLE' => ['Sample', 'স্যাম্পল'],
        'BULKPROD' => ['Bulk Production', 'বাল্ক প্রোডাকশন'],
        'SHIPMENT' => ['Shipment', 'শিপমেন্ট'],
    ];

    /** code => [group, order, name, sample?, start?, end?, production?] */
    public const TNA_TASKS = [
        'TSK-001' => ['GENPROC', 10, 'Rcv Order Confirm', false, true, false, false],
        'TSK-002' => ['GENPROC', 20, 'Rcv GMT Sketch, Artwork & Techpack', false, false, false, false],
        'TSK-003' => ['GENPROC', 30, 'Issue PI/TT/Sign Sale Contract', false, false, false, false],
        'TSK-004' => ['GENPROC', 40, 'Rcv Export LC', false, false, false, false],
        'TSK-005' => ['SAMPLE', 50, 'Lab Dip Approval', true, false, false, false],
        'TSK-006' => ['SAMPLE', 60, '1st/Fit Sample Sending', true, false, false, false],
        'TSK-007' => ['SAMPLE', 70, '1st/Fit Sample Approval', true, false, false, false],
        'TSK-008' => ['SAMPLE', 80, 'PP/Counter/IC Sample Sending', true, false, false, false],
        'TSK-009' => ['SAMPLE', 90, 'PP Sample Approval', true, false, false, false],
        'TSK-010' => ['BULKPROD', 100, 'Yarn Booking (Bulk)', false, false, false, true],
        'TSK-011' => ['BULKPROD', 110, 'Yarn In-house Complete', false, false, false, true],
        'TSK-012' => ['BULKPROD', 120, 'Knitting Start', false, false, false, true],
        'TSK-013' => ['BULKPROD', 130, 'Dyeing Start', false, false, false, true],
        'TSK-014' => ['BULKPROD', 140, 'Dyeing Finishing (RIB/CLCF)', false, false, false, true],
        'TSK-015' => ['BULKPROD', 150, 'Bulk Fabric Booking', false, false, false, true],
        'TSK-016' => ['BULKPROD', 160, 'Fabric In-House', false, false, false, true],
        'TSK-017' => ['BULKPROD', 170, 'Bulk Shade Approval', false, false, false, true],
        'TSK-018' => ['BULKPROD', 180, 'Trims In-house', false, false, false, true],
        'TSK-019' => ['BULKPROD', 190, 'Cutting Start', false, false, false, true],
        'TSK-020' => ['BULKPROD', 200, 'Sewing Start', false, false, false, true],
        'TSK-021' => ['BULKPROD', 210, 'Sewing Complete', false, false, false, true],
        'TSK-022' => ['BULKPROD', 220, 'Finishing Start', false, false, false, true],
        'TSK-023' => ['SHIPMENT', 230, 'Packing Complete', false, false, false, false],
        'TSK-024' => ['SHIPMENT', 240, 'Final Inspection', false, false, false, false],
        'TSK-025' => ['SHIPMENT', 250, 'Ex-Factory', false, false, true, false],
    ];

    /** code => [name, landed into stock value?, inside the VAT base?, order] — Brickland's procurement cost heads. */
    public const COST_HEADS = [
        'CARRIAGE' => ['Carriage Inward', true, false, 10],
        'CNF' => ['C&F Charges', true, true, 20],
        'LC-CHARGE' => ['LC Charges', true, false, 30],
        'INSURANCE' => ['Insurance', true, false, 40],
        'LOADING' => ['Loading / Unloading', true, false, 50],
    ];

    /** code => [name, order] — the natures of work a style item carried before the master. */
    public const WORK_NATURES = ['NW-SEW' => ['Sewing', 10], 'NW-SPR' => ['Sewing + Print', 20], 'NW-SEM' => ['Sewing + Embroidery', 30], 'NW-CM' => ['Cut & Make', 40]];

    /** The item types the Garment Items step offered under each group before the master, as its starting list. */
    public static function ensureItemTypes(string $companyId): void
    {
        if (ItemType::query()->exists()) {
            return;
        }
        $prefix = ['KNIT_TOP' => 'KT', 'KNIT_BOTTOM' => 'KB', 'WOVEN_TOP' => 'WT', 'WOVEN_BOTTOM' => 'WB'];
        foreach (ItemType::GROUPS as $group) {
            foreach (StyleItemOptions::TYPES[$group] as $i => $name) {
                $code = substr($prefix[$group].'-'.preg_replace('/[^A-Z0-9]/', '', strtoupper($name)), 0, 32);
                ItemType::query()->create(['company_id' => $companyId, 'item_group' => $group, 'code' => $code, 'name' => $name, 'sort_order' => ($i + 1) * 10, 'active' => true]);
            }
        }
    }

    public static function ensureWorkNatures(string $companyId): void
    {
        if (WorkNature::query()->exists()) {
            return;
        }
        foreach (self::WORK_NATURES as $code => [$name, $order]) {
            WorkNature::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'sort_order' => $order, 'active' => true]);
        }
    }

    public static function ensureCostHeads(string $companyId): void
    {
        if (ProcCostHead::query()->exists()) {
            return;
        }
        foreach (self::COST_HEADS as $code => [$name, $landed, $vat, $order]) {
            ProcCostHead::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'landed' => $landed, 'vat_applicable' => $vat, 'sort_order' => $order, 'active' => true]);
        }
    }

    public static function ensureRateLines(string $companyId): void
    {
        if (RateLine::query()->exists()) {
            return;
        }
        $order = 0;
        foreach (self::RATE_LINES as $group => $lines) {
            foreach ($lines as [$description, $uom]) {
                $order += 10;
                RateLine::query()->create(['company_id' => $companyId, 'rate_group' => $group, 'description' => $description, 'uom' => $uom, 'sort_order' => $order, 'active' => true]);
            }
        }
    }

    public static function ensureTna(string $companyId): void
    {
        if (TnaTaskGroup::query()->exists() || TnaTask::query()->exists()) {
            return;
        }
        $groups = [];
        foreach (self::TNA_GROUPS as $code => [$name, $nameBn]) {
            $groups[$code] = TnaTaskGroup::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'name_bn' => $nameBn, 'active' => true])->id;
        }
        foreach (self::TNA_TASKS as $code => [$group, $order, $name, $sample, $start, $end, $production]) {
            TnaTask::query()->create([
                'company_id' => $companyId, 'tna_task_group_id' => $groups[$group], 'code' => $code, 'sort_order' => $order, 'name' => $name,
                'is_sample' => $sample, 'is_start' => $start, 'is_end' => $end, 'is_production' => $production, 'active' => true,
            ]);
        }
    }
}
