<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hcm_payroll_type_mappings')) {
            Schema::create('hcm_payroll_type_mappings', function (Blueprint $t) {
                $t->id();
                $t->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $t->foreignId('payroll_type_id')->constrained('hcm_payroll_types')->cascadeOnDelete();
                $t->foreignId('provider_id')->constrained('hcm_payroll_providers')->cascadeOnDelete();
                $t->string('external_code');
                $t->string('external_label')->nullable();
                $t->date('valid_from')->nullable();
                $t->date('valid_to')->nullable();
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->index(['team_id','provider_id','external_code']);
                $t->index(['team_id','provider_id','valid_from','valid_to']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_payroll_type_mappings');
    }
};


