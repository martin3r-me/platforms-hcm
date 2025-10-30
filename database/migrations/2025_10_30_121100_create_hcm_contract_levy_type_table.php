<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_contract_levy_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('hcm_employee_contracts')->cascadeOnDelete();
            $table->foreignId('levy_type_id')->constrained('hcm_levy_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'levy_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_contract_levy_type');
    }
};


