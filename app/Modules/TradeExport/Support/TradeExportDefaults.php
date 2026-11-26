<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Support;

use App\Modules\TradeExport\Models\TxBank;
use App\Modules\TradeExport\Models\TxReferenceEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Trade & Export lists a company starts with, as Brickland ships them
 * (AnnotechERP 22/01): the reference lists' entries and the twenty-six banks
 * of the register Finance shares (Bank Account Master). Made the first time a list is opened, and only into an empty list;
 * insertOrIgnore because a page opens several requests at once.
 */
final class TradeExportDefaults
{
    /** list => [code => name] (short name = code) */
    public const REFERENCES = [
        'INCO_TERM' => ['FOB' => 'Free On Board', 'CIF' => 'Cost, Insurance and Freight', 'CFR' => 'Cost and Freight', 'EXW' => 'Ex Works', 'DDP' => 'Delivered Duty Paid'],
        'PAYMENT_TERM' => ['LC-SIGHT' => 'LC at Sight', 'LC-30' => 'LC at 30 days', 'LC-60' => 'LC at 60 days', 'LC-120' => 'LC at 120 days', 'TT-AFTER' => 'TT AFTER SHIPMENT', 'TT-ADV' => 'TT in Advance'],
        'DELIVERY_PLACE' => ['CTG' => 'Chattogram Port', 'DAC' => 'Dhaka ICD', 'MGL' => 'Mongla Port'],
        'TRANSIT_PORT' => ['SIN' => 'Singapore', 'CMB' => 'Colombo', 'PKG' => 'Port Klang'],
        'LC_TYPE' => ['IRREV' => 'Irrevocable', 'IRREV-TR' => 'Irrevocable Transferable', 'BTB' => 'Back to Back', 'SC' => 'Sales Contract'],
        'SHIPMENT_MODE' => ['SEA' => 'By Sea', 'AIR' => 'By Air', 'ROAD' => 'By Road'],
        'BUSINESS_ASSOCIATION' => ['BKMEA' => 'Bangladesh Knitwear Manufacturers & Exporters Association', 'BGMEA' => 'Bangladesh Garment Manufacturers & Exporters Association', 'EPB' => 'Export Promotion Bureau'],
        'FORWARDER' => [],
    ];

    /** code => name */
    public const BANKS = [
        'ABN' => 'ABN AMRO BANK', 'ABBL' => 'AGRANI BANK LIMITED', 'AAIBL' => 'AL ARAFAH ISLAMI BANK LIMITED', 'BASIC' => 'BASIC BANK LIMITED', 'BNK-BRAC' => 'BRAC Bank Ltd',
        'BNK-ASIA' => 'Bank Asia Ltd', 'DBBL' => 'DUTCH-BANGLA BANK LIMITED', 'EBL' => 'EASTERN BANK LIMITED', 'EXIM' => 'EXPORT IMPORT BANK OF BANGLADESH LIMITED', 'FSIBL' => 'FIRST SECURITY ISLAMI BANK LIMITED',
        'BNK-HSBC' => 'HSBC Bangladesh', 'IBBL' => 'ISLAMI BANK BANGLADESH LIMITED', 'JBL' => 'JANATA BANK LIMITED', 'MTBL' => 'MUTUAL TRUST BANK LIMITED', 'NBL' => 'NATIONAL BANK LIMITED',
        'PBL' => 'PRIME BANK LIMITED', 'PUBALI' => 'PUBALI BANK LIMITED', 'RUPALI' => 'RUPALI BANK LIMITED', 'SIBL' => 'SOCIAL ISLAMI BANK LIMITED', 'SBL' => 'SONALI BANK LIMITED',
        'SEBL' => 'SOUTHEAST BANK LIMITED', 'BNK-SCB' => 'Standard Chartered Bank', 'TBL' => 'TRUST BANK LIMITED', 'BNK-CITY' => 'The City Bank Ltd', 'UCB' => 'UNITED COMMERCIAL BANK LIMITED',
        'UTTARA' => 'UTTARA BANK LIMITED',
    ];

    public static function references(string $companyId): void
    {
        if (TxReferenceEntry::query()->exists()) {
            return;
        }
        $now = now();
        $rows = [];
        foreach (self::REFERENCES as $list => $entries) {
            $order = 0;
            foreach ($entries as $code => $name) {
                $rows[] = ['id' => (string) Str::ulid(), 'company_id' => $companyId, 'list' => $list, 'code' => $code, 'name' => $name, 'name_bn' => null, 'short_name' => $code, 'display_order' => ++$order, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('tx_reference_entries')->insertOrIgnore($rows);
    }

    public static function banks(string $companyId): void
    {
        if (TxBank::query()->exists()) {
            return;
        }
        $now = now();
        DB::table('tx_banks')->insertOrIgnore(array_map(fn (string $code) => [
            'id' => (string) Str::ulid(), 'company_id' => $companyId, 'code' => $code, 'name' => self::BANKS[$code], 'is_local' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], array_keys(self::BANKS)));
    }
}
