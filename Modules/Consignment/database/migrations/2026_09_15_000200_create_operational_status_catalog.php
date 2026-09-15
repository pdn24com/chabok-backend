<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    private array $columns = ['consignments'=>['current_status'], 'parcels'=>['current_status'], 'consignment_status_events'=>['previous_status','new_status'], 'manifests'=>['manifest_status']];
    public function up(): void {
        Schema::create('operational_status_catalog_lock', function (Blueprint $t) { $t->unsignedInteger('id')->primary(); });
        DB::table('operational_status_catalog_lock')->insert(['id'=>1]);
        Schema::create('operational_statuses', function (Blueprint $t) {
            $t->uuid('status_id')->primary(); $t->uuid('hq_id')->nullable(); $t->string('owner_key',36); $t->string('code',32);
            $t->string('title_fa',200); $t->string('title_en',200)->nullable();
            $t->string('partial_title_fa',200)->nullable(); $t->string('partial_title_en',200)->nullable();
            $t->string('tone',20); $t->string('status_group',30)->nullable();
            $t->boolean('is_terminal')->default(false); $t->boolean('manifest_enabled')->default(false);
            $t->boolean('is_system')->default(false); $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0); $t->unsignedInteger('version')->default(1); $t->timestamps(6);
            $t->unique(['owner_key','code']); $t->index(['hq_id','is_active']);
            $t->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
        });
        foreach (json_decode(file_get_contents(__DIR__.'/../../resources/operational-statuses.json'),true,512,JSON_THROW_ON_ERROR) as $i=>$row) {
            DB::table('operational_statuses')->insert([...$row,'status_id'=>(string)Str::uuid(),'owner_key'=>'GLOBAL','hq_id'=>null,'is_system'=>true,'is_active'=>true,'sort_order'=>$i,'version'=>1,'created_at'=>now(),'updated_at'=>now()]);
        }
        DB::statement("ALTER TABLE operational_statuses ADD CONSTRAINT status_owner_check CHECK (owner_key = COALESCE(hq_id, 'GLOBAL'))");
        DB::statement("ALTER TABLE operational_statuses ADD CONSTRAINT status_code_check CHECK (code REGEXP '^[A-Z][A-Z0-9_]{1,31}$' AND code <> 'D01')");
        DB::unprepared("CREATE TRIGGER operational_status_identity BEFORE UPDATE ON operational_statuses FOR EACH ROW BEGIN IF NOT (OLD.code <=> NEW.code) OR NOT (OLD.hq_id <=> NEW.hq_id) OR OLD.owner_key <> NEW.owner_key OR OLD.is_system <> NEW.is_system OR OLD.manifest_enabled <> NEW.manifest_enabled THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable operational status identity'; END IF; END");
        Schema::create('operational_status_revisions', function (Blueprint $t) {
            $t->uuid('revision_id')->primary(); $t->uuid('status_id'); $t->unsignedInteger('version');
            $t->uuid('actor_id'); $t->json('snapshot'); $t->timestamp('created_at',6);
            $t->unique(['status_id','version']); $t->foreign('status_id')->references('status_id')->on('operational_statuses')->restrictOnDelete();
        });
        foreach ($this->columns as $table=>$columns) foreach ($columns as $column) {
            $nullable = $column==='previous_status' ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE {$table} MODIFY {$column} VARCHAR(32) {$nullable}");
        }
        foreach ($this->columns as $table=>$columns) foreach (['INSERT','UPDATE'] as $event) {
            // Existing immutable history triggers remain authoritative for UPDATE.
            if ($table==='consignment_status_events' && $event==='UPDATE') continue;
            $checks='';
            foreach ($columns as $column) $checks.="IF NEW.{$column} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_statuses s WHERE s.code=NEW.{$column} AND (s.hq_id IS NULL OR s.hq_id=NEW.hq_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown operational status for tenant'; END IF; ";
            DB::unprepared('CREATE TRIGGER '.$table.'_catalog_'.strtolower($event)." BEFORE {$event} ON {$table} FOR EACH ROW BEGIN {$checks} END");
        }
        foreach (['UPDATE','DELETE'] as $event) DB::unprepared('CREATE TRIGGER status_revisions_'.strtolower($event)." BEFORE {$event} ON operational_status_revisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable status revision'");
        DB::unprepared("CREATE TRIGGER operational_status_no_delete BEFORE DELETE ON operational_statuses FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Deactivate operational status instead of deleting'");
    }
    public function down(): void {
        if (DB::table('operational_statuses')->where('is_system',false)->exists() || DB::table('operational_status_revisions')->exists()) {
            throw new RuntimeException('Preserve the status catalogue and revisions when rolling back application code.');
        }
        // Keep VARCHAR: reverting to an ENUM could discard valid custom history.
        foreach ($this->columns as $table=>$columns) foreach (['insert','update'] as $event) DB::unprepared("DROP TRIGGER IF EXISTS {$table}_catalog_{$event}");
        DB::unprepared('DROP TRIGGER IF EXISTS operational_status_no_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS operational_status_identity');
        foreach (['update','delete'] as $event) DB::unprepared("DROP TRIGGER IF EXISTS status_revisions_{$event}");
        Schema::dropIfExists('operational_status_revisions'); Schema::dropIfExists('operational_statuses'); Schema::dropIfExists('operational_status_catalog_lock');
    }
};
