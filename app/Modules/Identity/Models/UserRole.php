<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A role assignment. factory_id null means company-wide; set means the role
 * applies only within that factory (ERD Section 3).
 */
class UserRole extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'user_roles';

    protected $fillable = ['company_id', 'user_id', 'role_id', 'factory_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
