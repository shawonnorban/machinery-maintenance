<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Assembly Floor → Ready For Shipment (AnnotechERP 20/06): the finished
 * goods stock ledger Brickland keeps by PO, colour and size — in from Packing
 * Out (the sealed cartons' size break-up), out by Shipment, back in on a
 * Shipment Reversal (both from 20/07). Batches already packed out before this
 * ledger existed are booked in once, here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asm_fg_ledger', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('po_no', 60)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style', 80)->nullable();
            $table->string('colour', 60);
            $table->string('size', 20);
            $table->foreignUlid('batch_id')->nullable()->constrained('asm_packing_batches')->nullOnDelete();
            $table->string('doc_type', 20); // PACKING_OUT | SHIPMENT | SHIPMENT_REVERSAL
            $table->string('doc_no', 30);
            $table->ulid('doc_id')->nullable();
            $table->unsignedInteger('qty_in')->default(0);
            $table->unsignedInteger('qty_out')->default(0);
            $table->timestamp('posted_at');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'order_id', 'colour', 'size']);
            $table->index(['company_id', 'posted_at']);
            $table->index(['company_id', 'doc_type', 'doc_id']);
        });

        // Book in every batch packed out before the ledger existed.
        $rank = fn (string $s) => ($i = array_search(strtoupper($s), ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', '4XL', '5XL'], true)) === false ? 99 : $i;
        foreach (DB::table('asm_packing_batches')->where('status', 'PACKED_OUT')->orderBy('packed_out_at')->get() as $b) {
            $sizes = DB::table('asm_packing_carton_lines as l')->join('asm_packing_cartons as c', 'c.id', '=', 'l.carton_id')
                ->where('c.batch_id', $b->id)->where('c.status', 'SEALED')->selectRaw('l.size, sum(l.qty) as qty')->groupBy('l.size')->pluck('qty', 'size')->all();
            uksort($sizes, fn ($x, $y) => [$rank((string) $x), (string) $x] <=> [$rank((string) $y), (string) $y]);
            $short = array_sum($sizes) - (int) $b->final_packed_qty;
            foreach (array_reverse(array_keys($sizes)) as $size) {
                $take = max(0, min($short, (int) $sizes[$size]));
                $sizes[$size] -= $take;
                $short -= $take;
            }
            foreach ($sizes as $size => $qty) {
                if ((int) $qty > 0) {
                    DB::table('asm_fg_ledger')->insert([
                        'id' => (string) Str::ulid(), 'company_id' => $b->company_id, 'order_id' => $b->order_id, 'po_no' => $b->po_no, 'buyer_id' => $b->buyer_id,
                        'style' => $b->style, 'colour' => $b->colour, 'size' => (string) $size, 'batch_id' => $b->id, 'doc_type' => 'PACKING_OUT', 'doc_no' => $b->packing_no,
                        'doc_id' => $b->id, 'qty_in' => (int) $qty, 'qty_out' => 0, 'posted_at' => $b->packed_out_at ?? now(), 'created_by' => $b->packed_out_by,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_fg_ledger');
    }
};
