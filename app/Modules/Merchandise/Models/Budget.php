<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An order's budget sheet, per piece (Costing & Budget → Order Budget Sheet, AnnotechERP 06/09). */
class Budget extends BaseModel
{
    use BelongsToTenant;

    public const SECTIONS = ['YARN', 'PURCHASED_FABRIC', 'SEWING_ACC', 'FINISHING_ACC', 'GENERAL_ACC', 'SPECIAL_ACC'];

    public const PROCESS_HEADS = ['knitting', 'fabric', 'yarn_dyeing', 'aop', 'dyeing', 'dyeing_finishing', 'import_fabric', 'c2f'];

    /** Typed on Overall Cost; yarn and trims/accessories are the bill of material's own sums. */
    /** Budget vs Actual Costing's four groups and the heads each records. */
    public const ACTUAL_GROUPS = [
        'yarn_trims' => ['yarn', 'sewing_acc', 'finishing_acc', 'general_acc', 'special_acc'],
        'fabric_process' => ['purchased_fabric', 'knitting', 'fabric', 'yarn_dyeing', 'aop', 'dyeing', 'dyeing_finishing', 'import_fabric', 'c2f'],
        'cmt' => ['cm', 'print', 'embroidery', 'wash'],
        'export' => ['commercial', 'service', 'expense'],
    ];

    public const OVERALL_HEADS = ['print', 'embroidery', 'wash', 'cm', 'commercial', 'service', 'expense', 'fob'];

    protected $table = 'merch_budgets';

    protected $fillable = ['company_id', 'budget_no', 'order_id', 'style_id', 'buyer_office_id', 'currency', 'exchange_rate', 'process_costs', 'overall_costs', 'remarks', 'created_by', 'actual_costs', 'approved_at', 'approved_by'];

    protected function casts(): array
    {
        return ['exchange_rate' => 'float', 'process_costs' => 'array', 'overall_costs' => 'array', 'actual_costs' => 'array', 'approved_at' => 'datetime'];
    }
}
