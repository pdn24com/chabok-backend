<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table): void {
            $table->char('current_node_id', 36)->nullable()->after('current_status');
            $table->enum('current_custody_type', ['NODE', 'PICKUP_DRIVER', 'TRANSPORT_RUN', 'DELIVERY_DRIVER', 'RECIPIENT'])->default('NODE')->after('current_node_id');
            $table->char('current_custodian_id', 36)->nullable()->after('current_custody_type');
            $table->unsignedInteger('version')->default(1)->after('current_custodian_id');
            $table->foreign(['hq_id', 'current_node_id'], 'parcels_current_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->index(['hq_id', 'current_node_id', 'current_status'], 'parcels_node_status_index');
        });
        DB::table('parcels as p')->join('consignments as c', function ($join): void {
            $join->on('c.hq_id', '=', 'p.hq_id')->on('c.consignment_id', '=', 'p.consignment_id');
        })->update(['p.current_node_id' => DB::raw('c.pickup_node_id'), 'p.current_custody_type' => 'NODE']);

        Schema::create('pickup_tasks', function (Blueprint $table): void {
            $table->char('pickup_task_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->char('node_id', 36);
            $table->char('assigned_driver_id', 36)->nullable();
            $table->enum('status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED', 'FAILED']);
            $table->string('failure_reason_code', 80)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'consignment_id'], 'pickup_tasks_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'node_id'], 'pickup_tasks_node_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'assigned_driver_id'], 'pickup_tasks_driver_fk')->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->unique(['hq_id', 'consignment_id'], 'pickup_tasks_consignment_unique');
            $table->unique(['hq_id', 'pickup_task_id'], 'pickup_tasks_hq_id_unique');
            $table->index(['hq_id', 'node_id', 'status', 'created_at'], 'pickup_tasks_queue_index');
        });

        Schema::create('delivery_tasks', function (Blueprint $table): void {
            $table->char('delivery_task_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->char('node_id', 36);
            $table->char('assigned_driver_id', 36)->nullable();
            $table->char('manifest_id', 36)->nullable();
            $table->enum('status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED', 'FAILED']);
            $table->string('recipient_name', 200)->nullable();
            $table->enum('proof_type', ['MANUAL_CONFIRMATION'])->nullable();
            $table->string('proof_note', 500)->nullable();
            $table->string('failure_reason_code', 80)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('delivered_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'consignment_id'], 'delivery_tasks_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'node_id'], 'delivery_tasks_node_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'assigned_driver_id'], 'delivery_tasks_driver_fk')->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['hq_id', 'manifest_id'], 'delivery_tasks_manifest_fk')->references(['hq_id', 'manifest_id'])->on('manifests')->restrictOnDelete();
            $table->unique(['hq_id', 'consignment_id'], 'delivery_tasks_consignment_unique');
            $table->unique(['hq_id', 'delivery_task_id'], 'delivery_tasks_hq_id_unique');
            $table->index(['hq_id', 'node_id', 'status', 'created_at'], 'delivery_tasks_queue_index');
        });

        Schema::create('parcel_custody_events', function (Blueprint $table): void {
            $table->char('custody_event_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->char('parcel_id', 36);
            $table->char('from_node_id', 36)->nullable();
            $table->char('to_node_id', 36)->nullable();
            $table->string('from_custody_type', 40)->nullable();
            $table->string('to_custody_type', 40);
            $table->char('from_custodian_id', 36)->nullable();
            $table->char('to_custodian_id', 36)->nullable();
            $table->string('command_name', 100);
            $table->char('initiator_id', 36);
            $table->char('manifest_id', 36)->nullable();
            $table->char('route_plan_id', 36)->nullable();
            $table->char('route_plan_leg_id', 36)->nullable();
            $table->char('transport_run_id', 36)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign(['hq_id', 'consignment_id'], 'custody_events_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'parcel_id'], 'custody_events_parcel_fk')->references(['hq_id', 'parcel_id'])->on('parcels')->restrictOnDelete();
            $table->foreign('initiator_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(['hq_id', 'consignment_id', 'created_at'], 'custody_events_timeline_index');
        });

        Schema::create('operational_exception_cases', function (Blueprint $table): void {
            $table->char('exception_case_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->enum('exception_type', ['NPU', 'NOK']);
            $table->char('consignment_id', 36);
            $table->char('parcel_id', 36)->nullable();
            $table->char('pickup_task_id', 36)->nullable();
            $table->char('delivery_task_id', 36)->nullable();
            $table->char('driver_id', 36);
            $table->char('submitted_by', 36);
            $table->string('reason_code', 80);
            $table->string('description', 500);
            $table->enum('case_status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'EXPIRED']);
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->enum('resolution_action', ['RETRY', 'RETURN', 'CANCEL', 'ESCALATE', 'NO_CHANGE'])->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign(['hq_id', 'consignment_id'], 'exception_cases_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'driver_id'], 'exception_cases_driver_fk')->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->foreign('submitted_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(['hq_id', 'exception_type', 'case_status', 'created_at'], 'exception_cases_queue_index');
        });

        Schema::create('operational_exception_history', function (Blueprint $table): void {
            $table->char('exception_history_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('exception_case_id', 36);
            $table->string('action', 80);
            $table->char('actor_id', 36);
            $table->string('safe_note', 500)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign('exception_case_id')->references('exception_case_id')->on('operational_exception_cases')->restrictOnDelete();
            $table->foreign('actor_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(['hq_id', 'exception_case_id', 'created_at'], 'exception_history_timeline_index');
        });

        foreach (['parcel_custody_events', 'operational_exception_history'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable operational history'");
            DB::unprepared("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable operational history'");
        }
    }

    public function down(): void
    {
        foreach (['parcel_custody_events', 'operational_exception_history'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
        }
        Schema::dropIfExists('operational_exception_history');
        Schema::dropIfExists('operational_exception_cases');
        Schema::dropIfExists('parcel_custody_events');
        Schema::dropIfExists('delivery_tasks');
        Schema::dropIfExists('pickup_tasks');
        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropForeign('parcels_current_node_fk');
            $table->dropIndex('parcels_node_status_index');
            $table->dropColumn(['current_node_id', 'current_custody_type', 'current_custodian_id', 'version']);
        });
    }
};
