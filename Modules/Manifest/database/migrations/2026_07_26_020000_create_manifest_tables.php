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
        Schema::create('manifest_number_sequences', function (Blueprint $table): void {
            $table->char('sequence_key', 4)->primary();
            $table->unsignedBigInteger('next_value');
            $table->timestamp('updated_at', 6)->useCurrent();
        });

        Schema::create('manifests', function (Blueprint $table): void {
            $table->char('manifest_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('manifest_number', 32)->unique();
            $table->char('node_id', 36);
            $table->enum('manifest_status', ['IR', 'OF', 'OD']);
            $table->char('assigned_driver_id', 36)->nullable();
            $table->enum('state', ['DRAFT', 'OPEN', 'CLOSED']);
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 36);
            $table->char('approved_by', 36)->nullable();
            $table->timestamp('closed_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign(['hq_id', 'node_id'], 'manifests_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id', 'manifest_id'], 'manifests_hq_id_unique');
            $table->index(['hq_id', 'node_id', 'state', 'created_at', 'manifest_id'], 'manifests_node_list_index');
            $table->index(['hq_id', 'manifest_status', 'state'], 'manifests_target_state_index');
        });

        Schema::create('manifest_parcels', function (Blueprint $table): void {
            $table->char('manifest_parcel_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('manifest_id', 36);
            $table->char('parcel_id', 36);
            $table->enum('manifest_parcel_status', ['PENDING', 'VALIDATED', 'SUCCEEDED', 'FAILED', 'SKIPPED']);
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->enum('input_source', ['SCAN', 'MANUAL', 'BATCH', 'AWAITING']);
            $table->string('input_value', 64);
            $table->char('active_slot', 64)->nullable()->unique();
            $table->char('created_by', 36);
            $table->timestamp('processed_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'manifest_id'], 'manifest_parcels_manifest_fk')
                ->references(['hq_id', 'manifest_id'])->on('manifests')->restrictOnDelete();
            $table->foreign(['hq_id', 'parcel_id'], 'manifest_parcels_parcel_fk')
                ->references(['hq_id', 'parcel_id'])->on('parcels')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['manifest_id', 'parcel_id'], 'manifest_parcels_pair_unique');
            $table->index(['hq_id', 'manifest_id', 'manifest_parcel_status'], 'manifest_parcels_bucket_index');
        });

        Schema::table('consignment_status_events', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'manifest_id'], 'status_events_manifest_fk')
                ->references(['hq_id', 'manifest_id'])->on('manifests')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE manifests ADD CONSTRAINT manifests_close_consistency CHECK ((state = 'CLOSED' AND approved_by IS NOT NULL AND closed_at IS NOT NULL) OR (state <> 'CLOSED' AND approved_by IS NULL AND closed_at IS NULL))");
        DB::statement("ALTER TABLE manifest_parcels ADD CONSTRAINT manifest_parcels_failure_consistency CHECK ((manifest_parcel_status = 'FAILED' AND failure_code IS NOT NULL) OR (manifest_parcel_status <> 'FAILED' AND failure_code IS NULL AND failure_reason IS NULL))");
        DB::statement("ALTER TABLE manifest_parcels ADD CONSTRAINT manifest_parcels_processed_consistency CHECK ((manifest_parcel_status IN ('SUCCEEDED','FAILED','SKIPPED') AND processed_at IS NOT NULL) OR (manifest_parcel_status IN ('PENDING','VALIDATED') AND processed_at IS NULL))");
    }

    public function down(): void
    {
        Schema::table('consignment_status_events', function (Blueprint $table): void {
            $table->dropForeign('status_events_manifest_fk');
        });
        Schema::dropIfExists('manifest_parcels');
        Schema::dropIfExists('manifests');
        Schema::dropIfExists('manifest_number_sequences');
    }
};
