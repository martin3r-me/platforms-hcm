<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hcm_payroll_providers')) {
            Schema::create('hcm_payroll_providers', function (Blueprint $t) {
                $t->id();
                $t->string('key')->unique(); // z.B. 'bg', 'ipr365'
                $t->string('name');
                $t->json('meta')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_payroll_providers');
    }
};


