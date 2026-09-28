<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generic custom-fields engine: a company admin defines a field once per
 * entity type (`custom_field_definitions`), and every record of that type
 * carries its own value row (`custom_field_values`) — no schema migration
 * per field, ever.
 *
 * `fieldable_type`/`fieldable_id` is a loose pair, not an Eloquent morph
 * relation (this codebase uses none anywhere — `file_attachments`,
 * `approval_requests` and `audit_logs` all follow the same convention): no
 * FK on `fieldable_id`, because the value a company entered survives
 * independently of whether the record it was entered against still exists
 * in exactly this shape. `definition_id`, by contrast, is a real FK with
 * cascade delete — the schema, unlike a business record, has nothing else
 * worth keeping once it is gone.
 *
 * The discriminator strings reuse `file_attachments`' own convention
 * (lowercase snake, singular — `'asset'`, `'work_order'`) deliberately, so
 * a company's custom fields and its attachments agree on what an entity
 * type is called.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('fieldable_type', 64);
            // Stable once created — a code renders inside a submitted
            // `custom_fields[{code}]` key, and existing values must keep
            // resolving to the same definition after a label is edited.
            $table->string('code', 64);
            $table->string('label', 255);
            $table->string('field_type', 16); // TEXT|NUMBER|DATE|BOOLEAN|SELECT
            // SELECT's own choice list; unused, and left null, for every
            // other field_type.
            $table->json('options_json')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'fieldable_type', 'code']);
            $table->index(['company_id', 'fieldable_type', 'active']);
        });

        Schema::create('custom_field_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();

            $table->string('fieldable_type', 64);
            $table->ulid('fieldable_id');
            $table->json('value_json')->nullable();

            $table->timestamps();

            $table->unique(['definition_id', 'fieldable_id'], 'custom_field_values_definition_record_unique');
            $table->index(['company_id', 'fieldable_type', 'fieldable_id'], 'custom_field_values_record_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
