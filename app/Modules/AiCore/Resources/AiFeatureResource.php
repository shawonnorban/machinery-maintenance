<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Resources;

use App\Modules\AiCore\Models\AiFeature;
use App\Modules\AiCore\Support\AiBook;
use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Settings → AI Services → Features: which AI service each AI feature of the
 * ERP uses, whether it runs by itself, and whether it is on at all. The rows
 * are the ERP's features (one each); they are listed through
 * `/ai/features`, which creates any that are missing, and changed here.
 */
class AiFeatureResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ai-features';
    }

    public function model(): string
    {
        return AiFeature::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function managePermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['feature', 'service_id', 'auto_run', 'enabled'];
    }

    public function booleans(): array
    {
        return ['auto_run', 'enabled'];
    }

    public function query(): Builder
    {
        return AiFeature::query()->orderByRaw("CASE feature WHEN 'FILE_READING' THEN 1 ELSE 2 END");
    }

    public function rules(?Model $row): array
    {
        return [
            'feature' => [$row ? 'prohibited' : 'required', Rule::in(AiBook::FEATURES), Rule::unique('ai_features', 'feature')->where('company_id', app(TenantContext::class)->companyId())],
            'service_id' => ['nullable', 'string', Rule::exists('ai_services', 'id')->where('company_id', app(TenantContext::class)->companyId())],
            'auto_run' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function deletable(): bool
    {
        return false;
    }
}
