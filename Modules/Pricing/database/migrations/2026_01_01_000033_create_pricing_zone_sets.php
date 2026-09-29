<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_zone_sets', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('owner_key', 36);
            $table->string('code', 80);
            $table->enum('purpose', ['SALES', 'PURCHASE', 'COMMISSION', 'INTERNAL_TRANSFER']);
            $table->string('title', 200);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['owner_key', 'purpose', 'code'], 'pricing_zone_set_owner_code_unique');
            $table->index(['hq_id'], 'pricing_zone_sets_hq_id_foreign');
            $table->index(['created_by'], 'pricing_zone_sets_created_by_foreign');
            $table->foreign(['created_by'], 'pricing_zone_sets_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'pricing_zone_sets_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_zone_sets');
    }
};
