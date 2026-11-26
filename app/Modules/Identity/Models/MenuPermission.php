<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One permission string behind one flag of one menu (AnnotechERP 02/01). */
class MenuPermission extends BaseModel
{
    public $timestamps = false;

    protected $table = 'menu_permissions';

    protected $fillable = ['menu_id', 'flag', 'permission_name'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
