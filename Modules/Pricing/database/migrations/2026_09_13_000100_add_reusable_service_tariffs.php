<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('tariff_families', function (Blueprint $table): void {
            $table->enum('tariff_kind', ['FREIGHT','SERVICE'])->default('FREIGHT');
            $table->char('service_charge_type_id',36)->nullable();
            $table->foreign('service_charge_type_id')->references('charge_type_id')->on('pricing_charge_types')->restrictOnDelete();
        });
        Schema::table('tariff_versions', function (Blueprint $table): void {
            $table->char('zone_set_version_id',36)->nullable()->change();
            $table->string('matrix_basis',40)->default('BILLABLE_WEIGHT');
            $table->boolean('is_default')->default(false);
        });
        Schema::table('tariff_rate_rules', function (Blueprint $table): void {
            $table->char('service_offering_version_id',36)->nullable()->change();
            $table->decimal('incremental_step',16,4)->nullable();
        });
        Schema::create('tariff_service_attachments', function (Blueprint $table): void {
            $table->char('tariff_version_id',36);
            $table->char('service_tariff_family_id',36);
            $table->primary(['tariff_version_id','service_tariff_family_id'],'tariff_service_attachment_pk');
            $table->foreign('tariff_version_id')->references('tariff_version_id')->on('tariff_versions')->restrictOnDelete();
            $table->foreign('service_tariff_family_id')->references('tariff_family_id')->on('tariff_families')->restrictOnDelete();
        });
        DB::unprepared("CREATE TRIGGER tariff_service_content_update BEFORE UPDATE ON tariff_versions FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (NEW.matrix_basis <=> OLD.matrix_basis AND NEW.is_default <=> OLD.is_default) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing service content'; END IF; END");
        foreach (['INSERT'=>'NEW','UPDATE'=>'OLD','DELETE'=>'OLD'] as $operation=>$row) {
            $suffix=strtolower($operation);
            DB::unprepared("CREATE TRIGGER tariff_attachment_{$suffix} BEFORE {$operation} ON tariff_service_attachments FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM tariff_versions WHERE tariff_version_id = {$row}.tariff_version_id AND status <> 'DRAFT') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing service attachment'; END IF; END");
        }
    }
    public function down(): void {
        foreach (['tariff_service_content_update','tariff_attachment_insert','tariff_attachment_update','tariff_attachment_delete'] as $trigger) DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        Schema::dropIfExists('tariff_service_attachments');
        Schema::table('tariff_rate_rules', fn (Blueprint $table) => $table->dropColumn('incremental_step'));
        Schema::table('tariff_versions', fn (Blueprint $table) => $table->dropColumn(['matrix_basis','is_default']));
        Schema::table('tariff_families', function (Blueprint $table): void {
            $table->dropForeign(['service_charge_type_id']);
            $table->dropColumn(['tariff_kind','service_charge_type_id']);
        });
    }
};
