<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Resources;

use App\Modules\AiCore\Models\AiService;
use App\Modules\AiCore\Support\AiBook;
use App\Modules\Erp\Support\ResourceDefinition;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Settings → AI Services: the AI services the company has a key for — one
 * per provider (Claude, OpenAI, Gemini) — with the model it uses and the
 * result of the last connection test. The key is write-only: it is stored
 * encrypted, never returned (only its last four characters), and left as it
 * is when an edit leaves it blank. A new key or model clears the last test.
 */
class AiServiceResource extends ResourceDefinition
{
    private const MODEL = '/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/';

    public function key(): string
    {
        return 'ai-services';
    }

    public function model(): string
    {
        return AiService::class;
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
        return ['provider', 'model', 'enabled', 'last_tested_at', 'last_test_ok', 'last_test_message'];
    }

    public function booleans(): array
    {
        return ['enabled'];
    }

    public function defaults(): array
    {
        return ['enabled' => true];
    }

    public function query(): Builder
    {
        return AiService::query()->orderBy('created_at')->orderBy('id');
    }

    public function rules(?Model $row): array
    {
        return [
            'provider' => [$row ? 'sometimes' : 'required', Rule::in(AiBook::PROVIDERS), function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                if (AiService::query()->where('provider', $v)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('aicore.v.provider_taken'));
                }
            }],
            'model' => [$row ? 'sometimes' : 'required', 'string', 'max:80', 'regex:'.self::MODEL],
            'api_key' => [$row ? 'nullable' : 'required', 'string', 'min:20', 'max:400'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['model.required' => __('aicore.v.model'), 'model.regex' => __('aicore.v.model'), 'api_key.required' => __('aicore.v.api_key'), 'api_key.min' => __('aicore.v.api_key')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('api_key', $data)) {
            $key = trim((string) $data['api_key']);
            if ($key === '') {
                unset($data['api_key']);
            } else {
                $data['api_key'] = $key;
            }
        }
        if (isset($data['model'])) {
            $data['model'] = trim($data['model']);
        }
        // A new key or model has not been tested.
        if ($row && (isset($data['api_key']) || (isset($data['model']) && $data['model'] !== $row->model))) {
            $data += ['last_tested_at' => null, 'last_test_ok' => null, 'last_test_message' => null];
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['last_tested_at'] = $row->getAttribute('last_tested_at')?->toIso8601String();
        $out['key_hint'] = AiBook::mask($row->getAttribute('api_key'));

        return $out;
    }
}
