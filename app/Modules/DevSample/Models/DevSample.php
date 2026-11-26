<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One development sample of Dev Sample Status (AnnotechERP 31, from Norban):
 * the buyer's request, the sample's dates from booking to submission, the
 * quantities, the quoted price levels and where sample and order stand.
 */
class DevSample extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'ds_samples';

    /** Production & sample tracking dates, in the form's order. */
    public const TRACKING_DATES = [
        'booking_date', 'knitting_plan_date', 'actual_grige_rcvd_date', 'finish_fabric_delivery_plan_date', 'finish_fabric_rcvd_date',
        'sample_sewing_plan_date', 'file_handover_date', 'pattern_ready_date', 'cutting_date', 'print_emb_date',
        'sewing_start_date', 'sewing_output_date', 'sample_submitted_date',
    ];

    /** Every date of a sample. */
    public const DATES = ['tp_rcv_date', 'buyer_requested_date', 'submission_deadline', 'sub_plan_date', ...self::TRACKING_DATES, 'last_price_quote_date'];

    protected $fillable = [
        'company_id', 'serial_no', 'season', 'buyer_id', 'customer_name', 'brand', 'department', 'division', 'styling', 'image_file_id', 'job_no',
        'items', 'required_category', 'sample_type', 'fabrication', 'colours',
        'tp_rcv_date', 'buyer_requested_date', 'submission_deadline', 'sub_plan_date',
        'booking_date', 'knitting_plan_date', 'actual_grige_rcvd_date', 'finish_fabric_delivery_plan_date', 'finish_fabric_rcvd_date',
        'sample_sewing_plan_date', 'file_handover_date', 'pattern_ready_date', 'cutting_date', 'print_emb_date',
        'sewing_start_date', 'sewing_output_date', 'sample_submitted_date', 'awb_tracking',
        'sample_qty', 'possible_qty', 'actual_order_qty', 'target_prices', 'fob_prices', 'target_price', 'fob_price',
        'last_price_quote_date', 'last_price_quote_by',
        'price_status', 'sample_status', 'order_confirmation', 'current_order_situation', 'remarks',
        'created_by', 'updated_by', 'values', 'version',
    ];

    protected function casts(): array
    {
        return array_fill_keys(self::DATES, 'date') + [
            'serial_no' => 'integer',
            'items' => 'array',
            'colours' => 'array',
            'target_prices' => 'array',
            'fob_prices' => 'array',
            'target_price' => 'decimal:4',
            'fob_price' => 'decimal:4',
            'sample_qty' => 'integer',
            'possible_qty' => 'integer',
            'actual_order_qty' => 'integer',
            'values' => 'array',
            'version' => 'integer',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function quoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_price_quote_by');
    }

    /** The summary (first) level of a price list, else the stored price. */
    public static function firstLevel(?array $levels, mixed $fallback): float
    {
        return ! empty($levels) ? (float) ($levels[0]['price'] ?? 0) : (float) ($fallback ?? 0);
    }

    /** The stored price: the second level when there is one, else the first; null without levels. */
    public static function storedLevel(array $levels): ?float
    {
        if ($levels === []) {
            return null;
        }

        return (float) ($levels[count($levels) >= 2 ? 1 : 0]['price'] ?? 0);
    }
}
