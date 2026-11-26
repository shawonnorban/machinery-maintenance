<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

/**
 * One AI service, asked for one structured answer.
 *
 * A part is ['kind' => 'text', 'text' => …], ['kind' => 'pdf', 'data' => bytes]
 * or ['kind' => 'image', 'mime' => 'image/png', 'data' => bytes]. The answer
 * is the decoded JSON object that matches `$schema` (a JSON Schema object with
 * every property required and no extra properties, which all three services
 * accept). Any failure — the service unreachable, the key refused, the request
 * declined, an answer that is not the JSON asked for — is an AiException.
 */
interface AiClient
{
    /**
     * @param  list<array{kind: string, text?: string, data?: string, mime?: string}>  $parts
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function structured(string $system, array $parts, array $schema, int $maxTokens = 16000): array;

    /** Which service and model answered, for the record: "Claude · claude-opus-5-5". */
    public function label(): string;
}
