<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_status_events', function (Blueprint $table): void {
            $table->char('correlation_id', 36)->nullable()->after('manifest_id');
            $table->index(
                ['hq_id', 'consignment_id', 'correlation_id'],
                'status_events_correlation_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('consignment_status_events', function (Blueprint $table): void {
            $table->dropIndex('status_events_correlation_index');
            $table->dropColumn('correlation_id');
        });
    }
};
