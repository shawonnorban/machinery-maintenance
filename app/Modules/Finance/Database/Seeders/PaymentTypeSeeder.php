<?php

declare(strict_types=1);

namespace App\Modules\Finance\Database\Seeders;

use App\Modules\Finance\Models\PaymentType;
use Illuminate\Database\Seeder;

/**
 * How a Bangladeshi factory pays its suppliers (Master Data spec §11).
 *
 * Platform rows: null company_id, visible to every tenant. Banks are not
 * seeded — every company banks somewhere different.
 */
class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['CASH', 'Cash'],
            ['BANK_TRANSFER', 'Bank transfer'],
            ['CHEQUE', 'Cheque'],
            ['LC', 'Letter of credit'],
            ['MOBILE_BANKING', 'Mobile banking'],
            ['CREDIT', 'Credit'],
        ] as [$code, $name]) {
            PaymentType::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'active' => true],
            );
        }
    }
}
