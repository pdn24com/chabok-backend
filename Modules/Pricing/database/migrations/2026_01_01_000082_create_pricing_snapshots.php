<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_snapshots', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('quote_id');
            $table->string('object_type', 80);
            $table->unsignedInteger('object_id');
            $table->enum('purpose', ['SALES', 'PURCHASE', 'COMMISSION', 'INTERNAL_TRANSFER']);
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total_amount');
            $table->char('input_fingerprint', 64);
            $table->char('result_fingerprint', 64);
            $table->string('acceptance_idempotency_key', 120);
            $table->unsignedInteger('accepted_by');
            $table->timestamp('accepted_at', 6);
            $table->unique(['hq_id', 'quote_id'], 'pricing_snapshot_quote_unique');
            $table->unique(['hq_id', 'accepted_by', 'acceptance_idempotency_key'], 'pricing_snapshot_idempotency_unique');
            $table->index(['quote_id'], 'pricing_snapshots_quote_id_foreign');
            $table->index(['accepted_by'], 'pricing_snapshots_accepted_by_foreign');
            $table->index(['hq_id', 'object_type', 'object_id', 'accepted_at'], 'pricing_snapshot_object_index');
            $table->foreign(['accepted_by'], 'pricing_snapshots_accepted_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'pricing_snapshots_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['quote_id'], 'pricing_snapshots_quote_id_foreign')->references(['id'])->on('pricing_quotes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_snapshots');
    }
};
