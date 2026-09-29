<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->increments('id');
            $table->char('country_code', 2)->unique();
            $table->char('alpha3_code', 3)->unique();
            $table->char('numeric_code', 3)->unique();
            $table->string('name_fa', 160);
            $table->string('name_en', 160);
            $table->string('normalized_name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->index(['is_active', 'name_en'], 'countries_active_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
