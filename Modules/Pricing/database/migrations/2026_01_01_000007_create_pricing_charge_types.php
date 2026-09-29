<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_charge_types', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 80);
            $table->enum('category', ['BASE', 'SURCHARGE', 'DISCOUNT', 'TAX', 'COMMISSION']);
            $table->string('accounting_mapping_key', 120);
            $table->boolean('taxable')->default('0');
            $table->boolean('active')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['code'], 'pricing_charge_types_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_charge_types');
    }
};
