<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support\MerchAi;

use App\Modules\AiCore\Services\AiException;
use App\Modules\AiCore\Services\AiGateway;
use App\Modules\Merchandise\Models\AiOrderScore;
use App\Modules\Merchandise\Models\AiRun;
use App\Modules\Merchandise\Models\Order;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * The written analysis on top of the rule engine (AnnotechERP 06/02–06/04),
 * as Brickland offers it: with an AI service set up for order analysis in
 * Settings → AI Services (Claude, OpenAI or Gemini), the service reads the
 * run's most exposed orders — the scores and the reasons the engine found —
 * and writes the next move for each and a short read of the book. Without a
 * service, or if the call fails or is declined, the rule engine's sentences
 * stand. (The `CLAUDE` source code on a written action now means "written by
 * the AI service", whichever it is.)
 */
final class ClaudeWriter
{
    /** The orders Claude is asked about: the most exposed first. */
    private const ORDERS = 15;

    public function __construct(private readonly AiGateway $gateway) {}

    public function enabled(): bool
    {
        return $this->gateway->serviceFor('ORDER_ANALYSIS') !== null;
    }

    public function write(AiRun $run): void
    {
        if (! $this->enabled()) {
            return;
        }
        $scores = AiOrderScore::query()->where('run_id', $run->id)->orderByDesc('risk')->limit(self::ORDERS)->get();
        if ($scores->isEmpty()) {
            return;
        }
        $orders = Order::query()->whereIn('id', $scores->pluck('order_id'))->pluck('order_no', 'id');
        $facts = $scores->map(fn (AiOrderScore $s): array => [
            'order_no' => $orders[$s->order_id] ?? '', 'band' => $s->band, 'risk' => $s->risk, 'health' => $s->health, 'delay_probability' => $s->delay_probability,
            'expected_delay_days' => $s->expected_delay, 'material_risk' => $s->material_risk, 'production_risk' => $s->production_risk, 'approval_risk' => $s->approval_risk, 'tna_risk' => $s->tna_risk,
            'shipment_risk' => $s->shipment_risk, 'summary' => $s->summary, 'reasons' => array_values((array) $s->factors), 'rule_engine_action' => $s->action,
        ])->values()->all();

        $client = $this->gateway->clientFor('ORDER_ANALYSIS');
        if ($client === null) {
            return;
        }
        try {
            $out = $client->structured(
                'You are a senior garment merchandiser reviewing the live order book of a knit and woven garment factory. For each order you are given the scores and the reasons a rule engine found. Write the single next move for each order: one or two plain sentences naming the order, what is wrong and what to do first. Then write a short read of the book as a whole (three to five sentences): where the exposure sits and what to tackle this week. Use only the facts given; do not invent quantities, dates or names.',
                [['kind' => 'text', 'text' => json_encode(['orders' => $facts], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]],
                [
                    'type' => 'object',
                    'properties' => [
                        'narrative' => ['type' => 'string'],
                        'actions' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'properties' => ['order_no' => ['type' => 'string'], 'action' => ['type' => 'string']],
                            'required' => ['order_no', 'action'],
                            'additionalProperties' => false,
                        ]],
                    ],
                    'required' => ['narrative', 'actions'],
                    'additionalProperties' => false,
                ],
            );
        } catch (AiException|JsonException $e) {
            Log::warning('Merchandise AI: the AI analysis was skipped, the rule engine stands.', ['error' => $e->getMessage()]);

            return;
        }
        $byNo = array_flip(array_map('strval', $orders->all()));
        foreach ($out['actions'] ?? [] as $a) {
            $orderId = $byNo[$a['order_no'] ?? ''] ?? null;
            if ($orderId !== null && trim((string) ($a['action'] ?? '')) !== '') {
                AiOrderScore::query()->where('run_id', $run->id)->where('order_id', $orderId)->update(['action' => trim($a['action']), 'action_source' => 'CLAUDE']);
            }
        }
        $run->update(['engine' => 'CLAUDE', 'narrative' => trim((string) ($out['narrative'] ?? '')) ?: null]);
    }
}
