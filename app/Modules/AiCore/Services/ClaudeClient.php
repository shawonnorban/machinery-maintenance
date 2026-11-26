<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use JsonException;

/**
 * Claude, through Anthropic's PHP SDK: one Messages call with the document
 * as content blocks (a PDF as a document block, an image as an image block,
 * everything else as text) and a JSON Schema structured output. On the
 * models that take it, a declined request falls back server-side to the
 * model Anthropic routes it to, instead of simply stopping.
 */
final class ClaudeClient implements AiClient
{
    /** Models that accept the server-side refusal fallback (`fallbacks: "default"`). */
    private const FALLBACK_MODELS = ['claude-fable-5-2', 'claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5'];

    /** Image types Claude reads. */
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(private readonly string $apiKey, private readonly string $model) {}

    public function label(): string
    {
        return "Claude · {$this->model}";
    }

    public function structured(string $system, array $parts, array $schema, int $maxTokens = 16000): array
    {
        $content = [];
        foreach ($parts as $part) {
            $content[] = match ($part['kind']) {
                'pdf' => ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($part['data'])]],
                'image' => ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $part['mime'], 'data' => base64_encode($part['data'])]],
                default => ['type' => 'text', 'text' => (string) $part['text']],
            };
        }
        $args = [
            'model' => $this->model,
            'maxTokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $content]],
            'outputConfig' => ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
        ];
        if (in_array($this->model, self::FALLBACK_MODELS, true)) {
            $args += ['fallbacks' => 'default', 'betas' => ['server-side-fallback-2026-07-01']];
        }

        try {
            $message = (new Client(apiKey: $this->apiKey))->beta->messages->create(...$args);
        } catch (APIStatusException $e) {
            $detail = is_array($e->body) ? ($e->body['error']['message'] ?? null) : null;
            throw AiException::status('Claude', $e->status, is_string($detail) ? $detail : null);
        } catch (APIConnectionException $e) {
            throw AiException::failed('Claude', $e->getMessage());
        }
        if ($message->stopReason === 'refusal') {
            throw AiException::declined('Claude');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                try {
                    $out = json_decode($block->text, true, 64, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw AiException::notJson('Claude');
                }

                return is_array($out) ? $out : throw AiException::notJson('Claude');
            }
        }
        throw AiException::notJson('Claude');
    }
}
