<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_service_attachments', function (Blueprint $table): void {
            $table->unsignedInteger('tariff_version_id');
            $table->unsignedInteger('service_tariff_family_id');
            $table->increments('id');
            $table->unique(['tariff_version_id', 'service_tariff_family_id'], 'tariff_service_attachment_pk');
            $table->index(['service_tariff_family_id'], 'tariff_service_attachments_service_tariff_family_id_foreign');
            $table->foreign(['service_tariff_family_id'], 'tariff_service_attachments_service_tariff_family_id_foreign')->references(['id'])->on('tariff_families')->onDelete('restrict');
            $table->foreign(['tariff_version_id'], 'tariff_service_attachments_tariff_version_id_foreign')->references(['id'])->on('tariff_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_service_attachments');
    }
};
