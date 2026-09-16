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
        DB::unprepared('DROP TRIGGER IF EXISTS transport_run_history_immutable_update');
        DB::unprepared('DROP TRIGGER IF EXISTS transport_run_history_immutable_delete');

        if (DB::connection()->getDriverName() === 'mysql') {
            // Preserve authoritative movement evidence before removing the
            // obsolete execution aggregate and its identifiers.
            DB::statement("ALTER TABLE parcels MODIFY current_custody_type ENUM('NODE','PICKUP_DRIVER','TRANSPORT_RUN','LINEHAUL_DRIVER','DELIVERY_DRIVER','RECIPIENT') NOT NULL DEFAULT 'NODE'");
            DB::statement("UPDATE manifests m JOIN transport_runs r ON r.hq_id=m.hq_id AND r.transport_run_id=m.transport_run_id SET m.origin_node_id=COALESCE(m.origin_node_id,r.origin_node_id), m.destination_node_id=COALESCE(m.destination_node_id,r.destination_node_id), m.route_plan_leg_id=COALESCE(m.route_plan_leg_id,r.route_plan_leg_id), m.assigned_driver_id=COALESCE(m.assigned_driver_id,r.driver_id), m.assigned_vehicle_id=COALESCE(m.assigned_vehicle_id,r.vehicle_id)");
            DB::statement("UPDATE parcels p JOIN transport_runs r ON r.hq_id=p.hq_id AND r.transport_run_id=p.active_transport_run_id SET p.current_custody_type='LINEHAUL_DRIVER', p.current_custodian_id=r.driver_id WHERE p.current_custody_type='TRANSPORT_RUN'");
            DB::statement("UPDATE parcel_custody_events e JOIN transport_runs r ON r.hq_id=e.hq_id AND r.transport_run_id=e.transport_run_id SET e.from_custody_type=CASE WHEN e.from_custody_type='TRANSPORT_RUN' THEN 'LINEHAUL_DRIVER' ELSE e.from_custody_type END, e.to_custody_type=CASE WHEN e.to_custody_type='TRANSPORT_RUN' THEN 'LINEHAUL_DRIVER' ELSE e.to_custody_type END, e.from_custodian_id=CASE WHEN e.from_custody_type='TRANSPORT_RUN' THEN r.driver_id ELSE e.from_custodian_id END, e.to_custodian_id=CASE WHEN e.to_custody_type='TRANSPORT_RUN' THEN r.driver_id ELSE e.to_custodian_id END");
            DB::table('parcels')->where('current_custody_type', 'TRANSPORT_RUN')->update([
                'current_custody_type' => 'NODE',
                'current_custodian_id' => null,
            ]);
        }

        Schema::table('manifests', function (Blueprint $table): void {
            $table->dropForeign('manifests_transport_run_fk');
            $table->dropColumn('transport_run_id');
        });
        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropForeign('parcels_active_transport_run_fk');
            $table->dropColumn('active_transport_run_id');
        });
        Schema::table('parcel_custody_events', function (Blueprint $table): void {
            $table->dropColumn('transport_run_id');
        });

        Schema::dropIfExists('transport_run_history');
        Schema::dropIfExists('transport_run_parcels');
        Schema::dropIfExists('transport_runs');

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE parcels MODIFY current_custody_type ENUM('NODE','PICKUP_DRIVER','LINEHAUL_DRIVER','DELIVERY_DRIVER','RECIPIENT') NOT NULL DEFAULT 'NODE'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE parcels MODIFY current_custody_type ENUM('NODE','PICKUP_DRIVER','TRANSPORT_RUN','LINEHAUL_DRIVER','DELIVERY_DRIVER','RECIPIENT') NOT NULL DEFAULT 'NODE'");
        }
        Schema::create('transport_runs', function (Blueprint $table): void {
            $table->char('transport_run_id', 36)->primary(); $table->char('hq_id', 36); $table->string('transport_run_number', 40);
            $table->char('route_plan_leg_id', 36); $table->char('origin_node_id', 36); $table->char('destination_node_id', 36);
            $table->char('driver_id', 36); $table->char('vehicle_id', 36); $table->enum('status', ['CREATED','LOADED','DEPARTED','ARRIVED','CLOSED']);
            $table->unsignedInteger('version')->default(1); $table->char('created_by', 36); $table->timestamp('departed_at', 6)->nullable(); $table->timestamp('arrived_at', 6)->nullable(); $table->timestamp('closed_at', 6)->nullable(); $table->timestamps(6);
            $table->foreign(['hq_id','route_plan_leg_id'], 'transport_runs_leg_fk')->references(['hq_id','route_plan_leg_id'])->on('route_plan_legs')->restrictOnDelete();
            $table->foreign(['hq_id','origin_node_id'], 'transport_runs_origin_fk')->references(['hq_id','node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id','destination_node_id'], 'transport_runs_destination_fk')->references(['hq_id','node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id','driver_id'], 'transport_runs_driver_fk')->references(['hq_id','driver_id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['hq_id','vehicle_id'], 'transport_runs_vehicle_fk')->references(['hq_id','vehicle_id'])->on('vehicles')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id','transport_run_number'], 'transport_runs_hq_number_unique'); $table->unique(['hq_id','transport_run_id'], 'transport_runs_hq_id_unique'); $table->unique(['hq_id','route_plan_leg_id'], 'transport_runs_leg_unique');
            $table->index(['hq_id','origin_node_id','status','created_at'], 'transport_runs_origin_status_index'); $table->index(['hq_id','destination_node_id','status'], 'transport_runs_destination_status_index');
        });
        Schema::create('transport_run_parcels', function (Blueprint $table): void {
            $table->char('transport_run_parcel_id', 36)->primary(); $table->char('hq_id', 36); $table->char('transport_run_id', 36); $table->char('parcel_id', 36); $table->timestamp('loaded_at', 6); $table->char('loaded_by', 36);
            $table->foreign(['hq_id','transport_run_id'], 'transport_run_parcels_run_fk')->references(['hq_id','transport_run_id'])->on('transport_runs')->restrictOnDelete(); $table->foreign(['hq_id','parcel_id'], 'transport_run_parcels_parcel_fk')->references(['hq_id','parcel_id'])->on('parcels')->restrictOnDelete(); $table->foreign('loaded_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['transport_run_id','parcel_id'], 'transport_run_parcels_pair_unique'); $table->index(['hq_id','parcel_id'], 'transport_run_parcels_parcel_index');
        });
        Schema::create('transport_run_history', function (Blueprint $table): void {
            $table->char('transport_run_history_id', 36)->primary(); $table->char('hq_id', 36); $table->char('transport_run_id', 36); $table->unsignedInteger('event_sequence'); $table->enum('event_type', ['CREATED','LOADED','DEPARTED','ARRIVED','CLOSED']); $table->string('from_status', 40)->nullable(); $table->string('to_status', 40); $table->char('node_id', 36); $table->char('actor_id', 36); $table->unsignedInteger('aggregate_version'); $table->json('metadata')->nullable(); $table->timestamp('occurred_at', 6)->useCurrent();
            $table->foreign(['hq_id','transport_run_id'], 'transport_run_history_run_fk')->references(['hq_id','transport_run_id'])->on('transport_runs')->restrictOnDelete(); $table->foreign(['hq_id','node_id'], 'transport_run_history_node_fk')->references(['hq_id','node_id'])->on('nodes')->restrictOnDelete(); $table->foreign('actor_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['transport_run_id','event_sequence'], 'transport_run_history_sequence_unique'); $table->index(['hq_id','transport_run_id','occurred_at'], 'transport_run_history_timeline_index');
        });
        Schema::table('manifests', function (Blueprint $table): void { $table->char('transport_run_id', 36)->nullable()->after('route_plan_leg_id'); $table->foreign(['hq_id','transport_run_id'], 'manifests_transport_run_fk')->references(['hq_id','transport_run_id'])->on('transport_runs')->restrictOnDelete(); });
        Schema::table('parcels', function (Blueprint $table): void { $table->char('active_transport_run_id', 36)->nullable()->after('active_route_plan_leg_id'); $table->foreign(['hq_id','active_transport_run_id'], 'parcels_active_transport_run_fk')->references(['hq_id','transport_run_id'])->on('transport_runs')->restrictOnDelete(); });
        Schema::table('parcel_custody_events', function (Blueprint $table): void { $table->char('transport_run_id', 36)->nullable(); });
        DB::unprepared("CREATE TRIGGER transport_run_history_immutable_update BEFORE UPDATE ON transport_run_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable transport delivery history'");
        DB::unprepared("CREATE TRIGGER transport_run_history_immutable_delete BEFORE DELETE ON transport_run_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable transport delivery history'");
    }
};
