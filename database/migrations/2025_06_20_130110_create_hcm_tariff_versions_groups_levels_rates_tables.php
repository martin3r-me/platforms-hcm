<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_tariff_agreement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_agreement_id')->constrained('hcm_tariff_agreements')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->date('effective_from');
            $table->string('status', 20)->default('draft'); // draft|approved|active|archived
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tariff_agreement_id','effective_from'], 'tariff_versions_agreement_effective_unique');
        });

        Schema::create('hcm_tariff_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_agreement_id')->constrained('hcm_tariff_agreements')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->timestamps();
            $table->unique(['tariff_agreement_id','code'], 'tariff_groups_agreement_code_unique');
        });

        Schema::create('hcm_tariff_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_group_id')->constrained('hcm_tariff_groups')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->unsignedInteger('progression_months')->nullable();
            $table->timestamps();
            $table->unique(['tariff_group_id','code'], 'tariff_levels_group_code_unique');
        });

        Schema::create('hcm_tariff_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_group_id')->constrained('hcm_tariff_groups')->cascadeOnDelete();
            $table->foreignId('tariff_level_id')->constrained('hcm_tariff_levels')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['tariff_group_id','tariff_level_id','valid_from'], 'tariff_rates_grp_lvl_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_tariff_rates');
        Schema::dropIfExists('hcm_tariff_levels');
        Schema::dropIfExists('hcm_tariff_groups');
        Schema::dropIfExists('hcm_tariff_agreement_versions');
    }
};


