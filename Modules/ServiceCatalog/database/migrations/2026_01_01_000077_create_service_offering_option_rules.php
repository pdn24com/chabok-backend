<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_offering_option_rules', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('service_offering_version_id');
            $table->unsignedInteger('service_option_version_id');
            $table->enum('compatibility', ['ALLOWED', 'REQUIRED', 'FORBIDDEN', 'CONDITIONAL']);
            $table->json('condition')->nullable();
            $table->unique(['service_offering_version_id', 'service_option_version_id'], 'service_offering_option_unique');
            $table->index(['service_option_version_id'], 'service_offering_option_rules_service_option_version_id_foreign');
            $table->foreign(['service_offering_version_id'], 'svc_option_rule_offering_version_fk')->references(['id'])->on('service_offering_versions')->onDelete('cascade');
            $table->foreign(['service_option_version_id'], 'service_offering_option_rules_service_option_version_id_foreign')->references(['id'])->on('service_option_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_offering_option_rules');
    }
};
