<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_tax_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // z. B. 1..6
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hcm_tax_factors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // z. B. 0.5, 1.0
            $table->string('name', 100)->nullable();
            $table->decimal('value', 4, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_tax_factors');
        Schema::dropIfExists('hcm_tax_classes');
    }
};


