<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitment_schedule_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('commitment_schedule_id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('version_number');
            $table->unsignedInteger('previous_version_id')->nullable();
            $table->enum('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED', 'CANCELLED'])->default('DRAFT');
            $table->string('timezone', 80)->default('Asia/Tehran');
            $table->string('calendar_code', 80)->default('IR_STANDARD');
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_to', 6)->nullable();
            $table->unsignedInteger('lock_version')->default('1');
            $table->char('content_digest', 64)->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->json('commitment_policy')->nullable();
            $table->unique(['commitment_schedule_id', 'version_number'], 'commitment_schedule_version_number_unique');
            $table->index(['hq_id', 'commitment_schedule_id'], 'commitment_schedule_versions_identity_fk');
            $table->index(['previous_version_id'], 'commitment_schedule_versions_previous_version_id_foreign');
            $table->index(['created_by'], 'commitment_schedule_versions_created_by_foreign');
            $table->index(['approved_by'], 'commitment_schedule_versions_approved_by_foreign');
            $table->index(['published_by'], 'commitment_schedule_versions_published_by_foreign');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'commitment_schedule_effective_index');
            $table->foreign(['approved_by'], 'commitment_schedule_versions_approved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'commitment_schedule_versions_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'commitment_schedule_id'], 'commitment_schedule_versions_identity_fk')->references(['hq_id', 'id'])->on('commitment_schedules')->onDelete('restrict');
            $table->foreign(['previous_version_id'], 'commitment_schedule_versions_previous_version_id_foreign')->references(['id'])->on('commitment_schedule_versions')->onDelete('restrict');
            $table->foreign(['published_by'], 'commitment_schedule_versions_published_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_schedule_versions');
    }
};
