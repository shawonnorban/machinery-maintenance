<?php

declare(strict_types=1);

namespace App\Modules\CustomField\Actions;

use App\Modules\CustomField\Models\CustomFieldDefinition;

/**
 * Removes a field definition outright — its values cascade with it (the
 * migration's own FK), unlike a master-data row, which stays referenced by
 * historical records that must keep resolving.
 */
class DeleteCustomFieldDefinition
{
    public function handle(CustomFieldDefinition $definition): void
    {
        $definition->delete();
    }
}
