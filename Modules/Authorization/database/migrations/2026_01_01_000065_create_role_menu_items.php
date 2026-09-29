<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menu_items', function (Blueprint $table): void {
            $table->unsignedInteger('role_id');
            $table->string('menu_key', 80);
            $table->increments('id');
            $table->unique(['role_id', 'menu_key'], 'role_menu_items_role_id_menu_key_unique');
            $table->foreign(['role_id'], 'role_menu_items_role_id_foreign')->references(['role_id'])->on('role_menu_preferences')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_items');
    }
};
