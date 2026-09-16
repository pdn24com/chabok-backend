<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const TABLES = ['service_types', 'shipping_methods', 'service_offerings', 'service_options', 'commitment_schedules'];
    public function up(): void
    {
        foreach (self::TABLES as $name) Schema::table($name, function (Blueprint $table): void {
            $table->unsignedInteger('edit_lock')->default(1);
            $table->char('saved_input_fingerprint', 64)->nullable();
        });
    }
    public function down(): void
    {
        foreach (self::TABLES as $name) Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['edit_lock', 'saved_input_fingerprint']));
    }
};
