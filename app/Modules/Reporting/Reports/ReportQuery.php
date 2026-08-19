<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports;

use Carbon\CarbonImmutable;

/**
 * What was asked for.
 *
 * Frozen as a value object and stored on the report job, so a file downloaded
 * next week can say which period and scope produced it. A report whose
 * parameters are not recorded is a number without provenance, and a number
 * without provenance is worth nothing in an audit (SRS 44).
 */
final class ReportQuery
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?string $factoryId = null,
        public readonly ?string $assetId = null,
        public readonly array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>  $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            CarbonImmutable::parse($stored['from']),
            CarbonImmutable::parse($stored['to']),
            $stored['factory_id'] ?? null,
            $stored['asset_id'] ?? null,
            $stored['extra'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'factory_id' => $this->factoryId,
            'asset_id' => $this->assetId,
            'extra' => $this->extra,
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->extra[$key] ?? $default;
    }
}
