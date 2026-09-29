<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_status_catalog_lock', function (Blueprint $table): void {
            $table->increments('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_status_catalog_lock');
    }
};
