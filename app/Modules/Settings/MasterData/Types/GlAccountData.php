<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class GlAccountData extends MasterDataType
{
    public function key(): string
    {
        return 'gl-accounts';
    }

    public function model(): string
    {
        return GlAccount::class;
    }

    public function group(): string
    {
        return 'finance';
    }

    /** A chart of accounts is a company's own; the platform seeds none. */
    public function sharedWithPlatform(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::code(32),
            Field::text('name'),
            Field::enum('account_category', GlAccount::CATEGORIES),
            Field::reference('parent_account_id', 'gl-accounts', required: false),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [GlAccount::class => 'parent_account_id'];
    }

    /**
     * The parent must not be the row itself, nor anything underneath it —
     * either would put a loop in the chart that a rollup could never leave.
     */
    public function validateRow(array $data, ?Model $existing): void
    {
        $parentId = $data['parent_account_id'] ?? null;

        if ($parentId === null || $existing === null) {
            return;
        }

        if ($parentId === $existing->getKey()) {
            throw ValidationException::withMessages([
                'parent_account_id' => __('masterdata.self_parent'),
            ]);
        }

        $cursor = GlAccount::query()->find($parentId);

        while ($cursor !== null) {
            if ($cursor->getKey() === $existing->getKey()) {
                throw ValidationException::withMessages([
                    'parent_account_id' => __('masterdata.hierarchy_cycle'),
                ]);
            }

            $cursor = $cursor->parent_account_id === null ? null : GlAccount::query()->find($cursor->parent_account_id);
        }
    }
}
