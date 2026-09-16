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
            $table->unsignedBigInteger('event_sequence')->nullable()->after('status_event_id');
            $table->index(['hq_id', 'consignment_id', 'event_sequence'], 'status_events_sequence_index');
        });
        Schema::table('parcel_custody_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_sequence')->nullable()->after('custody_event_id');
            $table->index(['hq_id', 'consignment_id', 'event_sequence'], 'custody_events_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_custody_events', function (Blueprint $table): void { $table->dropIndex('custody_events_sequence_index'); $table->dropColumn('event_sequence'); });
        Schema::table('consignment_status_events', function (Blueprint $table): void { $table->dropIndex('status_events_sequence_index'); $table->dropColumn('event_sequence'); });
    }
};
