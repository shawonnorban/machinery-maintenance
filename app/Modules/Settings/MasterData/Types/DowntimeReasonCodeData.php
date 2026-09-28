<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\DowntimeRecord;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class DowntimeReasonCodeData extends MasterDataType
{
    public function key(): string
    {
        return 'downtime-reason-codes';
    }

    public function model(): string
    {
        return DowntimeReasonCode::class;
    }

    public function group(): string
    {
        return 'breakdown';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::code(),
            // These two decide what availability means. A machine idle over a
            // holiday and a machine dead mid-shift are both "not running", and
            // averaging them produces a number nobody in the factory
            // recognises.
            Field::enum('downtime_class', DowntimeReasonCode::CLASSES),
            Field::boolean('counts_against_availability'),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            Breakdown::class => 'downtime_reason_code_id',
            DowntimeRecord::class => 'downtime_reason_code_id',
        ];
    }
}
