<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Support;

/**
 * The AI services the ERP can use and the AI features that use them.
 */
final class AiBook
{
    public const PROVIDERS = ['ANTHROPIC', 'OPENAI', 'GEMINI'];

    /** The model a new service starts on; any model the service offers can be typed in instead. */
    public const DEFAULT_MODELS = ['ANTHROPIC' => 'claude-opus-5-5', 'OPENAI' => '', 'GEMINI' => ''];

    /**
     * The ERP's AI features: reading files dropped into the AI File & PO
     * Import intake (26), and the written analysis on the Merchandise order
     * book (06/02–06/04).
     */
    public const FEATURES = ['FILE_READING', 'ORDER_ANALYSIS'];

    /** A key as a screen may show it: the last four characters only. */
    public static function mask(?string $key): ?string
    {
        $key = (string) $key;

        return $key === '' ? null : '••••'.mb_substr($key, -4);
    }
}
