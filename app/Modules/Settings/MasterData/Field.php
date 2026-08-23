<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One editable column of a master data row.
 *
 * A value object rather than a loose array so the form, the validator and the
 * list all read the same declaration. Master data screens are the place where
 * a form and its validation rules drift apart quietly, because nobody looks at
 * them again for months.
 */
class Field
{
    /**
     * @param  list<string>  $options  for ENUM, the permitted values
     * @param  list<string|ValidationRule>  $rules
     */
    private function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $rules,
        public readonly array $options = [],
        public readonly ?string $reference = null,
        public readonly bool $inList = true,
        /** @var class-string|null for BELONGS_TO, the model behind it */
        public readonly ?string $model = null,
    ) {}

    public const TEXT = 'TEXT';

    public const CODE = 'CODE';

    public const ENUM = 'ENUM';

    public const BOOLEAN = 'BOOLEAN';

    public const REFERENCE = 'REFERENCE';

    /** A pointer at a model that has its own screen rather than a list here. */
    public const BELONGS_TO = 'BELONGS_TO';

    public static function text(string $name, bool $required = true, int $max = 255, bool $inList = true): self
    {
        return new self(
            $name,
            self::TEXT,
            [$required ? 'required' : 'nullable', 'string', 'max:'.$max],
            inList: $inList,
        );
    }

    /**
     * The code is the stable handle: imports resolve by it, and so does every
     * integration. Uppercase and punctuation-free so the same part cannot
     * arrive as "sew-01" one day and "SEW_01" the next.
     */
    public static function code(int $max = 48): self
    {
        return new self('code', self::CODE, ['required', 'string', 'max:'.$max, 'regex:/^[A-Z0-9][A-Z0-9._-]*$/']);
    }

    /**
     * @param  list<string>  $options
     */
    public static function enum(string $name, array $options, bool $required = true): self
    {
        return new self(
            $name,
            self::ENUM,
            [$required ? 'required' : 'nullable', 'string'],
            options: $options,
        );
    }

    public static function boolean(string $name): self
    {
        return new self($name, self::BOOLEAN, ['boolean']);
    }

    /**
     * A pointer at another master data type, which is how the dependent lists
     * work: a failure code belongs to a failure category, a category to an
     * asset type.
     */
    public static function reference(string $name, string $typeKey, bool $required = true): self
    {
        return new self(
            $name,
            self::REFERENCE,
            [$required ? 'required' : 'nullable', 'string', 'size:26'],
            reference: $typeKey,
        );
    }

    /**
     * A pointer at something managed elsewhere — a factory, for instance,
     * which has its own screen because it carries a timezone and an address
     * rather than just a name and a code.
     *
     * @param  class-string  $model
     */
    public static function belongsTo(string $name, string $model, bool $required = true): self
    {
        return new self(
            $name,
            self::BELONGS_TO,
            [$required ? 'required' : 'nullable', 'string', 'size:26'],
            model: $model,
        );
    }

    public function label(): string
    {
        return __('masterdata.fields.'.$this->name);
    }

    public function isRequired(): bool
    {
        return in_array('required', $this->rules, true);
    }
}
