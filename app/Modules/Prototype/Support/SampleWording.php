<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Support;

use App\Modules\Prototype\Models\SampleAlert;
use App\Modules\Prototype\Models\SampleEvent;

/**
 * The words of a timeline event or an alert, in the reader's language:
 * each is stored as an action (or kind) and its parameters, and a parameter
 * that is a code — an approval level, a QC result, a process — is put into
 * words first.
 */
final class SampleWording
{
    /** Which lang group words each coded parameter. */
    private const CODES = [
        'level' => 'level', 'result' => 'result', 'step' => 'step', 'process' => 'process', 'type' => 'fb_type',
    ];

    /** @return array{title: string, summary: string} */
    public static function event(SampleEvent $e): array
    {
        $params = self::words($e->params ?? [], $e->action);

        return ['title' => __('prototype.event.'.$e->action), 'summary' => __('prototype.event_text.'.$e->action, $params)];
    }

    /** @return array{title: string, message: string} */
    public static function alert(SampleAlert $a): array
    {
        $params = self::words($a->params ?? [], $a->kind);

        return ['title' => __('prototype.alert_title.'.$a->kind, $params), 'message' => __('prototype.alert_text.'.$a->kind, $params)];
    }

    /** @param array<string, mixed> $params @return array<string, string> */
    private static function words(array $params, string $context): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            $value = (string) ($value ?? '—');
            $group = self::CODES[$key] ?? null;
            // A decision or status is worded by the document it belongs to.
            if ($key === 'decision') {
                $group = str_starts_with($context, 'approval') || $context === 'REVISION_REQUIRED' ? 'decision_sa' : 'decision';
            }
            if ($key === 'status') {
                $group = str_starts_with($context, 'handover') ? 'ho_status' : (str_starts_with($context, 'feedback') ? 'fb_status' : null);
            }
            $out[$key] = $group && $value !== '—' ? __("prototype.{$group}.{$value}") : $value;
        }

        return $out;
    }
}
