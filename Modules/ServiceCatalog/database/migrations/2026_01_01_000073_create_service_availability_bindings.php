<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_availability_bindings', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('service_offering_version_id');
            $table->enum('scope_type', ['PLATFORM', 'TENANT', 'CUSTOMER_SEGMENT', 'CUSTOMER', 'CONTRACT', 'CHANNEL']);
            $table->string('scope_value', 120)->nullable();
            $table->boolean('enabled')->default('1');
            $table->index(['service_offering_version_id', 'scope_type', 'scope_value'], 'service_availability_lookup_index');
            $table->foreign(['service_offering_version_id'], 'svc_availability_offering_version_fk')->references(['id'])->on('service_offering_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availability_bindings');
    }
};
