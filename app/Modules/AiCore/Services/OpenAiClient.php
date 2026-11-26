<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * OpenAI, through its Responses API over HTTP: the document as input parts
 * (a PDF as an input file, an image as an input image, everything else as
 * text) and a strict JSON Schema text format.
 */
final class OpenAiClient implements AiClient
{
    public const URL = 'https://api.openai.com/v1/responses';

    public function __construct(private readonly string $apiKey, private readonly string $model) {}

    public function label(): string
    {
        return "OpenAI · {$this->model}";
    }

    public function structured(string $system, array $parts, array $schema, int $maxTokens = 16000): array
    {
        $content = [];
        foreach ($parts as $i => $part) {
            $content[] = match ($part['kind']) {
                'pdf' => ['type' => 'input_file', 'filename' => 'document-'.($i + 1).'.pdf', 'file_data' => 'data:application/pdf;base64,'.base64_encode($part['data'])],
                'image' => ['type' => 'input_image', 'image_url' => "data:{$part['mime']};base64,".base64_encode($part['data'])],
                default => ['type' => 'input_text', 'text' => (string) $part['text']],
            };
        }

        try {
            $response = Http::withToken($this->apiKey)->acceptJson()->timeout(300)->post(self::URL, [
                'model' => $this->model,
                'instructions' => $system,
                'input' => [['role' => 'user', 'content' => $content]],
                'text' => ['format' => ['type' => 'json_schema', 'name' => 'answer', 'schema' => $schema, 'strict' => true]],
                'max_output_tokens' => $maxTokens,
            ]);
        } catch (ConnectionException $e) {
            throw AiException::failed('OpenAI', $e->getMessage());
        }
        if (! $response->successful()) {
            throw AiException::status('OpenAI', $response->status(), $response->json('error.message'));
        }
        foreach ((array) $response->json('output', []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $c) {
                if (($c['type'] ?? null) === 'refusal') {
                    throw AiException::declined('OpenAI');
                }
                if (($c['type'] ?? null) === 'output_text') {
                    try {
                        $out = json_decode((string) $c['text'], true, 64, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        throw AiException::notJson('OpenAI');
                    }

                    return is_array($out) ? $out : throw AiException::notJson('OpenAI');
                }
            }
        }
        throw AiException::notJson('OpenAI');
    }
}
