<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') DB::statement("ALTER TABLE nodes DROP CHECK nodes_type_check, ADD CONSTRAINT nodes_type_check CHECK (node_type IN ('BRANCH','HUB','GATEWAY','AGENT'))");
    }
    public function down(): void
    {
        if (DB::table('nodes')->where('node_type', 'AGENT')->exists()) throw new RuntimeException('Resolve operational Agent nodes before rolling back this migration. No records were changed.');
        if (DB::getDriverName() === 'mysql') DB::statement("ALTER TABLE nodes DROP CHECK nodes_type_check, ADD CONSTRAINT nodes_type_check CHECK (node_type IN ('BRANCH','HUB','GATEWAY'))");
    }
};
