<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_status_revisions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('status_id');
            $table->unsignedInteger('version');
            $table->unsignedInteger('actor_id');
            $table->json('snapshot');
            $table->timestamp('created_at', 6);
            $table->unique(['status_id', 'version']);
            $table->foreign('status_id')->references('id')->on('operational_statuses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_status_revisions');
    }
};
