<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_policies', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('policy_code', 80);
            $table->string('policy_title', 200);
            $table->unsignedInteger('published_version_id')->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'policy_code'], 'coverage_policies_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'coverage_policies_hq_id_unique');
            $table->index(['hq_id', 'published_version_id'], 'coverage_policies_published_index');
            $table->foreign(['hq_id'], 'coverage_policies_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_policies');
    }
};
