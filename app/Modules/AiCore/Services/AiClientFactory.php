<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

use App\Modules\AiCore\Models\AiService;

/**
 * Builds the client for a configured AI service. Bound in the container so a
 * test can hand back a client that never leaves the building.
 */
class AiClientFactory
{
    public function make(AiService $service): AiClient
    {
        $key = (string) $service->api_key;
        $model = (string) $service->model;

        return match ($service->provider) {
            'ANTHROPIC' => new ClaudeClient($key, $model),
            'OPENAI' => new OpenAiClient($key, $model),
            'GEMINI' => new GeminiClient($key, $model),
            default => throw new AiException(__('aicore.e.unknown_provider')),
        };
    }
}
