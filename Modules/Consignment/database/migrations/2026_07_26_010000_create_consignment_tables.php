<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = [
        'D00', 'CFM', 'PD', 'PU', 'IR', 'ROU', 'OF', 'OS', 'OD', 'OK',
        'NPU', 'NOK', 'RH', 'RCH', 'RO', 'AA',
    ];

    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->unique(['hq_id', 'node_id'], 'nodes_hq_node_unique');
        });

        Schema::create('consignment_number_sequences', function (Blueprint $table): void {
            $table->char('sequence_key', 4)->primary();
            $table->unsignedBigInteger('next_value');
            $table->timestamp('updated_at', 6)->useCurrent();
        });

        Schema::create('consignments', function (Blueprint $table): void {
            $table->char('consignment_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('consignment_number', 32)->unique();
            $table->char('initiator_id', 36);
            $table->char('pickup_node_id', 36);
            $table->char('delivery_node_id', 36)->nullable();
            $table->char('pickup_man_id', 36)->nullable();
            $table->char('delivery_man_id', 36)->nullable();
            $table->char('sender_id', 36)->nullable();
            $table->char('receiver_id', 36)->nullable();

            foreach (['sender', 'receiver'] as $party) {
                $table->string("{$party}_contact_name", 200);
                $table->string("{$party}_mobile", 32);
                $table->string("{$party}_phone", 32)->nullable();
                $table->string("{$party}_address_text", 1000);
                $table->string("{$party}_country", 120)->nullable();
                $table->string("{$party}_state", 160);
                $table->string("{$party}_city", 160);
                $table->string("{$party}_postal_code", 32)->nullable();
                $table->decimal("{$party}_latitude", 10, 7)->nullable();
                $table->decimal("{$party}_longitude", 10, 7)->nullable();
            }

            $table->char('service_type_id', 36);
            $table->char('shipping_method_id', 36);
            $table->timestamp('pickup_commitment_at', 6)->nullable();
            $table->timestamp('delivery_commitment_at', 6)->nullable();
            $table->decimal('weight_kg', 12, 3);
            $table->decimal('width_cm', 12, 3)->nullable();
            $table->decimal('length_cm', 12, 3)->nullable();
            $table->decimal('height_cm', 12, 3)->nullable();
            $table->unsignedBigInteger('declared_value_amount');
            $table->boolean('insurance_enabled');
            $table->unsignedBigInteger('insurance_value_amount')->nullable();
            $table->boolean('cod_enabled');
            $table->unsignedBigInteger('cod_amount')->nullable();
            $table->enum('payer', ['SENDER', 'RECEIVER', 'VENDOR']);
            $table->enum('payment_method', ['CASH', 'CREDIT', 'COD']);
            $table->enum('current_status', self::STATUSES);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);

            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('initiator_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign(['hq_id', 'pickup_node_id'], 'consignments_pickup_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'delivery_node_id'], 'consignments_delivery_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->unique(['hq_id', 'consignment_id'], 'consignments_hq_id_unique');
            $table->index(
                ['hq_id', 'pickup_node_id', 'created_at', 'consignment_id'],
                'consignments_pickup_list_index',
            );
            $table->index(
                ['hq_id', 'current_status', 'created_at', 'consignment_id'],
                'consignments_status_list_index',
            );
            $table->index(['hq_id', 'delivery_node_id'], 'consignments_delivery_node_index');
            $table->index(['hq_id', 'service_type_id'], 'consignments_service_index');
            $table->index(['hq_id', 'shipping_method_id'], 'consignments_shipping_index');
            $table->index(['hq_id', 'receiver_mobile'], 'consignments_receiver_mobile_index');
        });

        Schema::create('parcels', function (Blueprint $table): void {
            $table->char('parcel_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->string('parcel_number', 40)->unique();
            $table->enum('current_status', self::STATUSES);
            $table->decimal('weight_kg', 12, 3)->nullable();
            $table->decimal('width_cm', 12, 3)->nullable();
            $table->decimal('length_cm', 12, 3)->nullable();
            $table->decimal('height_cm', 12, 3)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign(['hq_id', 'consignment_id'], 'parcels_consignment_fk')
                ->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->unique(['hq_id', 'parcel_id'], 'parcels_hq_id_unique');
            $table->index(['hq_id', 'consignment_id'], 'parcels_consignment_index');
            $table->index(['hq_id', 'current_status'], 'parcels_status_index');
        });

        Schema::create('consignment_pricing_versions', function (Blueprint $table): void {
            $table->char('pricing_version_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->unsignedInteger('version_number');
            $table->string('provider_code', 40);
            $table->char('quote_id', 36);
            $table->unsignedInteger('quote_version');
            $table->char('option_id', 36);
            $table->string('external_method_code', 120);
            $table->string('method_name', 240);
            $table->string('external_price_list_code', 120)->nullable();
            $table->string('zone', 120)->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('min_ins')->nullable();
            $table->json('delivery_windows');
            $table->char('input_fingerprint', 64);
            $table->timestamp('provider_calculated_at', 6);
            $table->timestamp('accepted_at', 6);
            $table->char('accepted_by', 36);
            $table->foreign(['hq_id', 'consignment_id'], 'pricing_consignment_fk')
                ->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign('accepted_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(
                ['hq_id', 'consignment_id', 'version_number'],
                'pricing_consignment_version_unique',
            );
            $table->unique(
                ['hq_id', 'quote_id', 'quote_version', 'option_id'],
                'pricing_quote_option_unique',
            );
            $table->unique(['hq_id', 'pricing_version_id'], 'pricing_hq_id_unique');
            $table->index(
                ['hq_id', 'consignment_id', 'accepted_at'],
                'pricing_timeline_index',
            );
        });

        Schema::create('consignment_pricing_charge_lines', function (Blueprint $table): void {
            $table->char('pricing_charge_line_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('pricing_version_id', 36);
            $table->unsignedTinyInteger('line_number');
            $table->string('charge_code', 80);
            $table->string('title', 200);
            $table->unsignedBigInteger('amount');
            $table->foreign(['hq_id', 'pricing_version_id'], 'pricing_lines_version_fk')
                ->references(['hq_id', 'pricing_version_id'])
                ->on('consignment_pricing_versions')->restrictOnDelete();
            $table->unique(
                ['pricing_version_id', 'charge_code'],
                'pricing_lines_code_unique',
            );
            $table->unique(
                ['pricing_version_id', 'line_number'],
                'pricing_lines_order_unique',
            );
        });

        Schema::create('consignment_status_events', function (Blueprint $table): void {
            $table->char('status_event_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->char('parcel_id', 36)->nullable();
            $table->enum('previous_status', self::STATUSES)->nullable();
            $table->enum('new_status', self::STATUSES);
            $table->char('initiator_id', 36);
            $table->char('node_id', 36)->nullable();
            $table->char('driver_id', 36)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->char('manifest_id', 36)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign(['hq_id', 'consignment_id'], 'status_events_consignment_fk')
                ->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'parcel_id'], 'status_events_parcel_fk')
                ->references(['hq_id', 'parcel_id'])->on('parcels')->restrictOnDelete();
            $table->foreign('initiator_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign(['hq_id', 'node_id'], 'status_events_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->index(
                ['hq_id', 'consignment_id', 'created_at'],
                'status_events_timeline_index',
            );
        });

        DB::statement(
            'ALTER TABLE consignments ADD CONSTRAINT consignments_dimensions_all_or_none CHECK ((width_cm IS NULL AND length_cm IS NULL AND height_cm IS NULL) OR (width_cm > 0 AND length_cm > 0 AND height_cm > 0))',
        );
        DB::statement(
            'ALTER TABLE consignments ADD CONSTRAINT consignments_commercial_amounts CHECK ((insurance_enabled = 0 AND insurance_value_amount IS NULL) OR (insurance_enabled = 1 AND insurance_value_amount IS NOT NULL))',
        );
        DB::statement(
            'ALTER TABLE consignments ADD CONSTRAINT consignments_cod_amounts CHECK ((cod_enabled = 0 AND cod_amount IS NULL) OR (cod_enabled = 1 AND cod_amount IS NOT NULL))',
        );
        DB::statement(
            'ALTER TABLE parcels ADD CONSTRAINT parcels_dimensions_all_or_none CHECK ((width_cm IS NULL AND length_cm IS NULL AND height_cm IS NULL) OR (width_cm > 0 AND length_cm > 0 AND height_cm > 0))',
        );
        foreach ([
            'consignment_pricing_versions',
            'consignment_pricing_charge_lines',
            'consignment_status_events',
        ] as $table) {
            DB::unprepared(
                "CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'",
            );
            DB::unprepared(
                "CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'",
            );
        }
    }

    public function down(): void
    {
        foreach ([
            'consignment_pricing_versions',
            'consignment_pricing_charge_lines',
            'consignment_status_events',
        ] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
        }
        Schema::dropIfExists('consignment_status_events');
        Schema::dropIfExists('consignment_pricing_charge_lines');
        Schema::dropIfExists('consignment_pricing_versions');
        Schema::dropIfExists('parcels');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('consignment_number_sequences');
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropUnique('nodes_hq_node_unique');
        });
    }
};
