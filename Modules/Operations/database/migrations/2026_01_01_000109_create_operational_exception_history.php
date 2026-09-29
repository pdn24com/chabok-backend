<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_exception_history', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('exception_case_id');
            $table->string('action', 80);
            $table->unsignedInteger('actor_id');
            $table->string('safe_note', 500)->nullable();
            $table->unsignedInteger('manifest_version')->nullable();
            $table->unsignedInteger('exception_version')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['exception_case_id'], 'operational_exception_history_exception_case_id_foreign');
            $table->index(['actor_id'], 'operational_exception_history_actor_id_foreign');
            $table->index(['hq_id', 'exception_case_id', 'created_at'], 'exception_history_timeline_index');
            $table->foreign(['actor_id'], 'operational_exception_history_actor_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['exception_case_id'], 'operational_exception_history_exception_case_id_foreign')->references(['id'])->on('operational_exception_cases')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_exception_history');
    }
};
