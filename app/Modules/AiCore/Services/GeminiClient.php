<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Google Gemini, through its generateContent API over HTTP: the document as
 * inline data or text parts, and a JSON response held to a JSON Schema.
 */
final class GeminiClient implements AiClient
{
    public const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(private readonly string $apiKey, private readonly string $model) {}

    public function label(): string
    {
        return "Gemini · {$this->model}";
    }

    public function structured(string $system, array $parts, array $schema, int $maxTokens = 16000): array
    {
        $content = [];
        foreach ($parts as $part) {
            $content[] = match ($part['kind']) {
                'pdf' => ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode($part['data'])]],
                'image' => ['inlineData' => ['mimeType' => $part['mime'], 'data' => base64_encode($part['data'])]],
                default => ['text' => (string) $part['text']],
            };
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->acceptJson()->timeout(300)->post(sprintf(self::URL, rawurlencode($this->model)), [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => $content]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => $schema, 'maxOutputTokens' => $maxTokens],
            ]);
        } catch (ConnectionException $e) {
            throw AiException::failed('Gemini', $e->getMessage());
        }
        if (! $response->successful()) {
            throw AiException::status('Gemini', $response->status(), $response->json('error.message'));
        }
        if ($response->json('promptFeedback.blockReason') || in_array($response->json('candidates.0.finishReason'), ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'RECITATION'], true)) {
            throw AiException::declined('Gemini');
        }
        $text = implode('', array_map(fn ($p) => (string) ($p['text'] ?? ''), (array) $response->json('candidates.0.content.parts', [])));
        try {
            $out = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw AiException::notJson('Gemini');
        }

        return is_array($out) ? $out : throw AiException::notJson('Gemini');
    }
}
