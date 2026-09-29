<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Junction of a catalog item and its sales channels. It is not the channel list itself: the generic lookup
// table was dropped and no replacement reference has been chosen, so channel_id carries no foreign key and
// stays unusable until that decision. No channel table or enum is fabricated here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_catalog_channels', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('catalog_item_id');
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            // Already declared so it holds as soon as the channel reference exists; while channel_id is null
            // MySQL treats the rows as distinct, which matches the column being reserved and unusable.
            $table->unique(['hq_id', 'catalog_item_id', 'channel_id'], 'crm_catalog_channels_item_channel_unique');
            $table->index(['created_by'], 'crm_catalog_channels_created_by_fk');
            $table->foreign(['created_by'], 'crm_catalog_channels_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'catalog_item_id'], 'crm_catalog_channels_item_fk')->references(['hq_id', 'id'])->on('crm_catalog_items')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_catalog_channels_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_catalog_channels');
    }
};
