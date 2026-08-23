<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_policies', function (Blueprint $table): void {
            $table->char('coverage_policy_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('policy_code', 80);
            $table->string('policy_title', 200);
            $table->char('published_version_id', 36)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->unique(['hq_id', 'policy_code'], 'coverage_policies_hq_code_unique');
            $table->unique(['hq_id', 'coverage_policy_id'], 'coverage_policies_hq_id_unique');
            $table->index(['hq_id', 'published_version_id'], 'coverage_policies_published_index');
        });

        Schema::create('coverage_policy_versions', function (Blueprint $table): void {
            $table->char('coverage_policy_version_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('coverage_policy_id', 36);
            $table->unsignedInteger('version_number');
            $table->enum('status', ['DRAFT', 'VALIDATED', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED']);
            $table->timestamp('effective_from', 6)->nullable();
            $table->timestamp('effective_to', 6)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 36);
            $table->char('validated_by', 36)->nullable();
            $table->char('approved_by', 36)->nullable();
            $table->char('published_by', 36)->nullable();
            $table->timestamp('validated_at', 6)->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->string('content_digest', 64)->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'coverage_policy_id'], 'coverage_versions_policy_fk')->references(['hq_id', 'coverage_policy_id'])->on('coverage_policies')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('validated_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['coverage_policy_id', 'version_number'], 'coverage_versions_number_unique');
            $table->unique(['hq_id', 'coverage_policy_version_id'], 'coverage_versions_hq_id_unique');
            $table->index(['hq_id', 'status', 'effective_from', 'effective_to'], 'coverage_versions_runtime_index');
        });

        Schema::create('coverage_rules', function (Blueprint $table): void {
            $table->char('coverage_rule_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('coverage_policy_version_id', 36);
            $table->enum('target', ['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE']);
            $table->char('target_node_id', 36);
            $table->integer('priority')->default(0);
            $table->char('offering_version_id', 36)->nullable();
            $table->enum('criterion_type', ['PROVINCE', 'CITY', 'POSTAL_RANGE', 'POLYGON', 'POINT_RADIUS']);
            $table->char('province_id', 36)->nullable();
            $table->char('city_id', 36)->nullable();
            $table->char('postal_code_from', 10)->nullable();
            $table->char('postal_code_to', 10)->nullable();
            $table->json('geometry_geojson')->nullable();
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'coverage_policy_version_id'], 'coverage_rules_version_fk')->references(['hq_id', 'coverage_policy_version_id'])->on('coverage_policy_versions')->restrictOnDelete();
            $table->foreign(['hq_id', 'target_node_id'], 'coverage_rules_target_node_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign('province_id')->references('province_id')->on('provinces')->restrictOnDelete();
            $table->foreign('city_id')->references('city_id')->on('cities')->restrictOnDelete();
            $table->foreign('offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->restrictOnDelete();
            $table->unique(['hq_id', 'coverage_rule_id'], 'coverage_rules_hq_id_unique');
            $table->index(['hq_id', 'target', 'priority', 'criterion_type'], 'coverage_rules_resolution_index');
            $table->index(['province_id', 'city_id'], 'coverage_rules_geography_index');
        });

        Schema::create('route_definition_versions', function (Blueprint $table): void {
            $table->char('route_definition_version_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('route_definition_id', 36);
            $table->unsignedInteger('version_number');
            $table->enum('status', ['DRAFT', 'VALIDATED', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED']);
            $table->enum('purpose', ['TRUNK', 'LAST_MILE']);
            $table->char('origin_node_id', 36);
            $table->char('destination_node_id', 36);
            $table->integer('priority')->default(0);
            $table->char('offering_version_id', 36)->nullable();
            $table->timestamp('effective_from', 6)->nullable();
            $table->timestamp('effective_to', 6)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 36)->nullable();
            $table->char('validated_by', 36)->nullable();
            $table->char('approved_by', 36)->nullable();
            $table->char('published_by', 36)->nullable();
            $table->timestamp('validated_at', 6)->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->string('content_digest', 64)->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'route_definition_id'], 'route_versions_definition_fk')->references(['hq_id', 'route_definition_id'])->on('route_definitions')->restrictOnDelete();
            $table->foreign(['hq_id', 'origin_node_id'], 'route_versions_origin_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_node_id'], 'route_versions_destination_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign('offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('validated_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['route_definition_id', 'version_number'], 'route_versions_number_unique');
            $table->unique(['hq_id', 'route_definition_version_id'], 'route_versions_hq_id_unique');
            $table->index(['hq_id', 'status', 'purpose', 'origin_node_id', 'destination_node_id', 'priority'], 'route_versions_resolution_index');
        });

        Schema::create('route_definition_version_legs', function (Blueprint $table): void {
            $table->char('route_definition_version_leg_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('route_definition_version_id', 36);
            $table->unsignedSmallInteger('leg_order');
            $table->char('origin_node_id', 36);
            $table->char('destination_node_id', 36);
            $table->timestamps(6);
            $table->foreign(['hq_id', 'route_definition_version_id'], 'route_version_legs_version_fk')->references(['hq_id', 'route_definition_version_id'])->on('route_definition_versions')->restrictOnDelete();
            $table->foreign(['hq_id', 'origin_node_id'], 'route_version_legs_origin_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_node_id'], 'route_version_legs_destination_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->unique(['route_definition_version_id', 'leg_order'], 'route_version_legs_order_unique');
            $table->unique(['hq_id', 'route_definition_version_leg_id'], 'route_version_legs_hq_id_unique');
        });
        DB::statement('ALTER TABLE route_definition_version_legs ADD CONSTRAINT route_version_legs_not_self CHECK (origin_node_id <> destination_node_id)');

        Schema::table('route_definitions', function (Blueprint $table): void {
            $table->char('published_version_id', 36)->nullable()->after('route_title');
        });
        Schema::table('route_plans', function (Blueprint $table): void {
            $table->char('route_definition_version_id', 36)->nullable()->after('route_definition_id');
        });
        Schema::table('route_plan_legs', function (Blueprint $table): void {
            $table->char('source_route_definition_version_leg_id', 36)->nullable()->after('source_route_definition_leg_id');
        });

        $now = now();
        foreach (DB::table('route_definitions')->orderBy('route_definition_id')->get() as $definition) {
            $legs = DB::table('route_definition_legs')->where('route_definition_id', $definition->route_definition_id)->where('status', 'ACTIVE')->orderBy('leg_order')->get();
            if ($legs->isEmpty()) continue;
            $versionId = (string) Str::uuid();
            DB::table('route_definition_versions')->insert([
                'route_definition_version_id' => $versionId, 'hq_id' => $definition->hq_id,
                'route_definition_id' => $definition->route_definition_id, 'version_number' => 1,
                'status' => $definition->status === 'ACTIVE' ? 'PUBLISHED' : 'ARCHIVED', 'purpose' => 'TRUNK',
                'origin_node_id' => $legs->first()->origin_node_id, 'destination_node_id' => $legs->last()->destination_node_id,
                'priority' => 0, 'version' => 1, 'published_at' => $definition->status === 'ACTIVE' ? $now : null,
                'content_digest' => hash('sha256', json_encode($legs, JSON_THROW_ON_ERROR)), 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($legs as $leg) {
                DB::table('route_definition_version_legs')->insert([
                    'route_definition_version_leg_id' => (string) Str::uuid(), 'hq_id' => $definition->hq_id,
                    'route_definition_version_id' => $versionId, 'leg_order' => $leg->leg_order,
                    'origin_node_id' => $leg->origin_node_id, 'destination_node_id' => $leg->destination_node_id,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            if ($definition->status === 'ACTIVE') DB::table('route_definitions')->where('route_definition_id', $definition->route_definition_id)->update(['published_version_id' => $versionId]);
            DB::table('route_plans')->where('route_definition_id', $definition->route_definition_id)->whereNull('route_definition_version_id')->update(['route_definition_version_id' => $versionId]);
        }

        Schema::table('coverage_policies', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'published_version_id'], 'coverage_policies_published_fk')->references(['hq_id', 'coverage_policy_version_id'])->on('coverage_policy_versions')->restrictOnDelete();
        });
        Schema::table('route_definitions', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'published_version_id'], 'route_definitions_published_fk')->references(['hq_id', 'route_definition_version_id'])->on('route_definition_versions')->restrictOnDelete();
        });
        Schema::table('route_plans', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'route_definition_version_id'], 'route_plans_definition_version_fk')->references(['hq_id', 'route_definition_version_id'])->on('route_definition_versions')->restrictOnDelete();
        });
        Schema::table('route_plan_legs', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'source_route_definition_version_leg_id'], 'route_plan_legs_source_version_fk')->references(['hq_id', 'route_definition_version_leg_id'])->on('route_definition_version_legs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('route_plan_legs', function (Blueprint $table): void { $table->dropForeign('route_plan_legs_source_version_fk'); $table->dropColumn('source_route_definition_version_leg_id'); });
        Schema::table('route_plans', function (Blueprint $table): void { $table->dropForeign('route_plans_definition_version_fk'); $table->dropColumn('route_definition_version_id'); });
        Schema::table('route_definitions', function (Blueprint $table): void { $table->dropForeign('route_definitions_published_fk'); $table->dropColumn('published_version_id'); });
        Schema::table('coverage_policies', function (Blueprint $table): void { $table->dropForeign('coverage_policies_published_fk'); });
        Schema::dropIfExists('route_definition_version_legs');
        Schema::dropIfExists('route_definition_versions');
        Schema::dropIfExists('coverage_rules');
        Schema::dropIfExists('coverage_policy_versions');
        Schema::dropIfExists('coverage_policies');
    }
};
