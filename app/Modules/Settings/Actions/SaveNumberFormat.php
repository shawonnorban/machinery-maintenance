<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\NumberSequenceFormat;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Changing what a document number looks like (SRS 52).
 *
 * Almost everything here is a refusal, and each refusal prevents a collision
 * that could not be undone afterwards. A document number is printed on a work
 * order, quoted in an email and typed into somebody else's ERP; two documents
 * sharing one is not a display problem.
 *
 * A change takes effect from the next period. The generator writes the format
 * on to the counter row when a period's first number is allocated, so a
 * company that switches on the 14th keeps one shape of number for the month
 * and gets the new one in the next.
 */
class SaveNumberFormat
{
    /** Everything a format may contain besides the placeholders. */
    private const LITERAL_PATTERN = '/^[A-Za-z0-9\-\/.\s{}A-Z]*$/';

    private const PLACEHOLDERS = ['{FACTORY}', '{YYYY}', '{MM}', '{SEQ}'];

    public function __construct(private readonly TenantContext $context) {}

    public function handle(string $documentType, string $format, int $padding, ?string $userId = null): NumberSequenceFormat
    {
        $default = NumberSequenceGenerator::FORMATS[$documentType] ?? null;

        if ($default === null) {
            throw ValidationException::withMessages([
                'document_type' => __('numbering.unknown_document_type'),
            ]);
        }

        $format = trim($format);

        $this->assertShape($format);
        $this->assertHasSequence($format);
        $this->assertKeepsFactory($format, $default['format']);
        $this->assertPeriodMatchesReset($format, $default['reset']);

        if ($padding < 1 || $padding > 10) {
            throw ValidationException::withMessages([
                'padding' => __('numbering.padding_range'),
            ]);
        }

        return NumberSequenceFormat::updateOrCreate(
            [
                'company_id' => $this->context->companyId(),
                'document_type' => $documentType,
            ],
            [
                'format' => $format,
                'padding' => $padding,
                'updated_by' => $userId,
            ],
        );
    }

    /**
     * Back to the platform default.
     *
     * Deleting the row rather than writing the default into it, so "we never
     * changed this" and "we changed it back" stay distinguishable, and a
     * company that reset inherits any later change to the default.
     */
    public function reset(string $documentType): void
    {
        NumberSequenceFormat::where('document_type', $documentType)->delete();
    }

    private function assertShape(string $format): void
    {
        if ($format === '' || mb_strlen($format) > 128) {
            throw ValidationException::withMessages([
                'format' => __('numbering.format_length'),
            ]);
        }

        // The placeholders are removed first, so a stray brace is caught
        // rather than being read as part of a valid one.
        $literals = str_replace(self::PLACEHOLDERS, '', $format);

        if (str_contains($literals, '{') || str_contains($literals, '}')) {
            throw ValidationException::withMessages([
                'format' => __('numbering.unknown_placeholder', [
                    'placeholders' => implode(' ', self::PLACEHOLDERS),
                ]),
            ]);
        }

        if (preg_match(self::LITERAL_PATTERN, $literals) !== 1) {
            throw ValidationException::withMessages([
                'format' => __('numbering.format_characters'),
            ]);
        }
    }

    private function assertHasSequence(string $format): void
    {
        if (! str_contains($format, '{SEQ}')) {
            // Without the counter every document in the period gets the same
            // number. Nothing downstream would notice until two work orders
            // could not be told apart.
            throw ValidationException::withMessages([
                'format' => __('numbering.sequence_required'),
            ]);
        }
    }

    private function assertKeepsFactory(string $format, string $default): void
    {
        if (str_contains($default, '{FACTORY}') && ! str_contains($format, '{FACTORY}')) {
            // These counters are allocated per factory. Drop the factory from
            // the format and two factories issue the same number on the same
            // day, each believing it is unique.
            throw ValidationException::withMessages([
                'format' => __('numbering.factory_required'),
            ]);
        }
    }

    /**
     * The date parts have to match how often the counter restarts.
     *
     * A monthly counter without {MM} restarts at 1 every month inside a format
     * that only names the year, so January's 00001 and February's 00001 are
     * the same string. A yearly counter carrying {MM} is the opposite mistake:
     * harmless to uniqueness, but the month in the number is then not the
     * month the counter belongs to, which is worse than not showing it.
     */
    private function assertPeriodMatchesReset(string $format, string $reset): void
    {
        if ($reset === 'MONTHLY' && ! str_contains($format, '{MM}')) {
            throw ValidationException::withMessages([
                'format' => __('numbering.month_required'),
            ]);
        }

        if ($reset === 'MONTHLY' && ! str_contains($format, '{YYYY}')) {
            throw ValidationException::withMessages([
                'format' => __('numbering.year_required'),
            ]);
        }

        if ($reset === 'YEARLY') {
            if (! str_contains($format, '{YYYY}')) {
                throw ValidationException::withMessages([
                    'format' => __('numbering.year_required'),
                ]);
            }

            if (str_contains($format, '{MM}')) {
                throw ValidationException::withMessages([
                    'format' => __('numbering.month_not_allowed'),
                ]);
            }
        }
    }
}
