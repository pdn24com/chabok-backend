<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('consignments', fn (Blueprint $table) => $table->json('catalog_snapshot')->nullable()); }
    public function down(): void { Schema::table('consignments', fn (Blueprint $table) => $table->dropColumn('catalog_snapshot')); }
};
