<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menu_preferences', function (Blueprint $table): void {
            $table->char('role_id', 36)->primary();
            $table->timestamps(6);
            $table->foreign('role_id')->references('role_id')->on('roles')->restrictOnDelete();
        });
        Schema::create('role_menu_items', function (Blueprint $table): void {
            $table->char('role_id', 36);
            $table->string('menu_key', 80);
            $table->primary(['role_id', 'menu_key']);
            $table->foreign('role_id')->references('role_id')->on('role_menu_preferences')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_items');
        Schema::dropIfExists('role_menu_preferences');
    }
};
