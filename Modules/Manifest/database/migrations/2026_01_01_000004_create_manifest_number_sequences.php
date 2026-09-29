<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifest_number_sequences', function (Blueprint $table): void {
            $table->increments('id');
            $table->char('sequence_key', 4);
            $table->unsignedBigInteger('next_value');
            $table->timestamp('updated_at', 6)->useCurrent();
            $table->unique(['sequence_key'], 'manifest_number_sequences_sequence_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_number_sequences');
    }
};
