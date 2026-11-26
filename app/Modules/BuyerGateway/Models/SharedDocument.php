<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A document released to a buyer. Buyer Gateway → Shared Document Vault (AnnotechERP 25/02).
 */
class SharedDocument extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_shared_documents';

    protected $fillable = ['company_id', 'title', 'buyer_id', 'kind', 'shared_on', 'shared_by', 'size_mb', 'downloads', 'state', 'file_path'];

    protected function casts(): array
    {
        return ['shared_on' => 'date', 'size_mb' => 'float', 'downloads' => 'integer'];
    }
}
