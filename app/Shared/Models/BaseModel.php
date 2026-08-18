<?php

declare(strict_types=1);

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for every model in the system.
 *
 * ULID primary keys per ERD rule 3: public identifiers must be
 * non-sequential so a resource id never leaks record counts or
 * allows enumeration.
 */
abstract class BaseModel extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';
}
