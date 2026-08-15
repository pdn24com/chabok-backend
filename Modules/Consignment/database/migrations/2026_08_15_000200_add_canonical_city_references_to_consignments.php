<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignments', function (Blueprint $table): void {
            $table->char('sender_city_id', 36)->nullable()->after('sender_city');
            $table->char('receiver_city_id', 36)->nullable()->after('receiver_city');
            $table->foreign('sender_city_id')->references('city_id')->on('cities')->restrictOnDelete();
            $table->foreign('receiver_city_id')->references('city_id')->on('cities')->restrictOnDelete();
            $table->index('sender_city_id', 'consignments_sender_city_index');
            $table->index('receiver_city_id', 'consignments_receiver_city_index');
        });
    }

    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table): void {
            $table->dropForeign(['sender_city_id']);
            $table->dropForeign(['receiver_city_id']);
            $table->dropIndex('consignments_sender_city_index');
            $table->dropIndex('consignments_receiver_city_index');
            $table->dropColumn(['sender_city_id', 'receiver_city_id']);
        });
    }
};
