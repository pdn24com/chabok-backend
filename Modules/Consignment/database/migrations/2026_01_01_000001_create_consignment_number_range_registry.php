<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_number_range_registry', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('registry_key', 32);
            $table->timestamp('updated_at', 6)->useCurrent();
            $table->unique(['registry_key'], 'consignment_number_range_registry_registry_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_number_range_registry');
    }
};
