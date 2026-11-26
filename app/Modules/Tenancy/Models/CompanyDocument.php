<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A licence or certificate the company holds (Company Management → Company
 * Documents). The scanned copy is a FileAttachment with attachable_type
 * 'company_document'.
 */
class CompanyDocument extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['TRADE_LICENSE', 'TIN', 'BIN', 'BOND_LICENSE', 'FIRE_LICENSE', 'ENVIRONMENT', 'OTHER'];

    protected $table = 'company_documents';

    protected $fillable = [
        'company_id', 'document_type', 'title', 'document_no', 'issue_date', 'expiry_date', 'issued_by', 'status',
    ];

    protected function casts(): array
    {
        return ['issue_date' => 'date:Y-m-d', 'expiry_date' => 'date:Y-m-d'];
    }
}
