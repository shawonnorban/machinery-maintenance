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
        /** Used when the form sends nothing — a new row starts ACTIVE, say. */
        public readonly mixed $default = null,
        /** CODE only: issued by the system when left blank (BLD-001, BLD-002 …). */
        public readonly bool $auto = false,
    ) {}

    public const TEXT = 'TEXT';

    public const CODE = 'CODE';

    public const ENUM = 'ENUM';

    public const BOOLEAN = 'BOOLEAN';

    public const NUMBER = 'NUMBER';

    public const REFERENCE = 'REFERENCE';

    /** A pointer at a model that has its own screen rather than a list here. */
    public const BELONGS_TO = 'BELONGS_TO';

    public const DATE = 'DATE';

    /** Free text longer than a line — remarks, notes. */
    public const TEXTAREA = 'TEXTAREA';

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
    public static function code(int $max = 48, bool $auto = false): self
    {
        // An auto code may be left blank; MasterDataType::autoCodePrefix()
        // supplies it before validation, so the stored value is never empty.
        return new self(
            'code',
            self::CODE,
            [$auto ? 'nullable' : 'required', 'string', 'max:'.$max, 'regex:/^[A-Z0-9][A-Z0-9._-]*$/'],
            auto: $auto,
        );
    }

    /**
     * @param  list<string>  $options
     */
    public static function enum(string $name, array $options, bool $required = true, ?string $default = null, bool $inList = true): self
    {
        return new self(
            $name,
            self::ENUM,
            [$required ? 'required' : 'nullable', 'string'],
            options: $options,
            inList: $inList,
            default: $default,
        );
    }

    /** Active/Inactive, defaulting to Active — the organisation screens' Status column. */
    public static function status(): self
    {
        return self::enum('status', ['ACTIVE', 'INACTIVE'], required: false, default: 'ACTIVE');
    }

    public static function date(string $name, bool $required = false, bool $inList = true): self
    {
        return new self($name, self::DATE, [$required ? 'required' : 'nullable', 'date'], inList: $inList);
    }

    public static function textarea(string $name, bool $required = false, int $max = 2000, bool $inList = true): self
    {
        return new self($name, self::TEXTAREA, [$required ? 'required' : 'nullable', 'string', 'max:'.$max], inList: $inList);
    }

    public static function boolean(string $name): self
    {
        return new self($name, self::BOOLEAN, ['boolean']);
    }

    /** A non-negative decimal: a conversion factor, a duty rate. */
    public static function number(string $name, bool $required = true, bool $inList = true): self
    {
        return new self(
            $name,
            self::NUMBER,
            [$required ? 'required' : 'nullable', 'numeric', 'min:0'],
            inList: $inList,
        );
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

    /**
     * How an ENUM option reads on screen — `masterdata.values.active` when a
     * translation exists (the convention the existing lists already use),
     * otherwise the value itself made readable.
     *
     * @return array<string, string>
     */
    public function optionLabels(): array
    {
        $labels = [];

        foreach ($this->options as $option) {
            $key = 'masterdata.values.'.strtolower($option);
            $translated = __($key);
            $labels[$option] = $translated === $key ? ucwords(strtolower(str_replace('_', ' ', $option))) : $translated;
        }

        return $labels;
    }

    public function isRequired(): bool
    {
        return in_array('required', $this->rules, true);
    }
}
