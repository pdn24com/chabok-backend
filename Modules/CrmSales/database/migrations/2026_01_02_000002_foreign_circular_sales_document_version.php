<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A sales document points at its current content version and every version points back at its document,
// so one side of the cycle cannot be declared inside Schema::create.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_sales_documents', function (Blueprint $table): void {
            $table->foreign(['hq_id', 'current_version_id'], 'crm_sales_documents_current_version_fk')->references(['hq_id', 'id'])->on('crm_sales_document_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('crm_sales_documents', function (Blueprint $table): void {
            $table->dropForeign('crm_sales_documents_current_version_fk');
        });
    }
};
