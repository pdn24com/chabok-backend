<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Route definitions and coverage policies each point at their own version table and
// back again, so one side of the cycle cannot be declared inside Schema::create.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverage_policies', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'published_version_id'], 'coverage_policies_published_fk')->references(['hq_id', 'id'])->on('coverage_policy_versions')->onDelete('restrict');
        });
        Schema::table('route_definition_versions', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'route_definition_id'], 'route_versions_definition_fk')->references(['hq_id', 'id'])->on('route_definitions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('coverage_policies', function (Blueprint $table): void {
            $table->dropForeign('coverage_policies_published_fk');
        });
        Schema::table('route_definition_versions', function (Blueprint $table): void {
            $table->dropForeign('route_versions_definition_fk');
        });
    }
};
