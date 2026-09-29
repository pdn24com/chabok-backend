<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_exception_cases', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->enum('exception_type', ['NPU', 'NOK']);
            $table->unsignedInteger('manifest_id')->nullable();
            $table->unsignedInteger('submission_sequence')->default('1');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('parcel_id')->nullable();
            $table->unsignedInteger('pickup_task_id')->nullable();
            $table->unsignedInteger('delivery_task_id')->nullable();
            $table->unsignedInteger('driver_id');
            $table->unsignedInteger('submitted_by');
            $table->string('reason_code', 80);
            $table->string('description', 500);
            $table->enum('case_status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'EXPIRED']);
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->enum('resolution_action', ['RETRY', 'RETURN', 'CANCEL', 'ESCALATE', 'NO_CHANGE'])->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->char('active_pending_slot', 64)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['manifest_id', 'submission_sequence'], 'exception_cases_manifest_attempt_unique');
            $table->unique(['active_pending_slot'], 'operational_exception_cases_active_pending_slot_unique');
            $table->index(['hq_id', 'consignment_id'], 'exception_cases_consignment_fk');
            $table->index(['hq_id', 'driver_id'], 'exception_cases_driver_fk');
            $table->index(['submitted_by'], 'operational_exception_cases_submitted_by_foreign');
            $table->index(['reviewed_by'], 'operational_exception_cases_reviewed_by_foreign');
            $table->index(['hq_id', 'exception_type', 'case_status', 'created_at'], 'exception_cases_queue_index');
            $table->index(['hq_id', 'manifest_id', 'case_status'], 'exception_cases_manifest_status_index');
            $table->foreign(['hq_id', 'consignment_id'], 'exception_cases_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'driver_id'], 'exception_cases_driver_fk')->references(['hq_id', 'id'])->on('drivers')->onDelete('restrict');
            $table->foreign(['hq_id', 'manifest_id'], 'exception_cases_manifest_fk')->references(['hq_id', 'id'])->on('manifests')->onDelete('restrict');
            $table->foreign(['reviewed_by'], 'operational_exception_cases_reviewed_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['submitted_by'], 'operational_exception_cases_submitted_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_exception_cases');
    }
};
