<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('consignments',fn(Blueprint $table)=>$table->json('delivery_commitment_resolution')->nullable()); }
 public function down(): void { Schema::table('consignments',fn(Blueprint $table)=>$table->dropColumn('delivery_commitment_resolution')); }
};
