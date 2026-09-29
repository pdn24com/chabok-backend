<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_positions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('department_id');
            $table->string('title', 200);
            $table->string('decision_level', 80)->nullable();
            // Descriptive only; it triggers no approval and authorises no payment.
            $table->bigInteger('delegation_limit')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_positions_hq_internal_id_unique');
            $table->index(['hq_id', 'department_id'], 'crm_positions_department_fk');
            $table->index(['created_by'], 'crm_positions_created_by_fk');
            $table->foreign(['created_by'], 'crm_positions_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'department_id'], 'crm_positions_department_fk')->references(['hq_id', 'id'])->on('crm_customer_departments')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_positions_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_positions');
    }
};
