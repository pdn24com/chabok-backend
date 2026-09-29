<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('legacy_province_code', 4);
            $table->string('name_fa', 160);
            $table->string('normalized_name', 160);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['legacy_province_code'], 'provinces_legacy_province_code_unique');
            $table->index(['is_active', 'normalized_name'], 'provinces_active_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
