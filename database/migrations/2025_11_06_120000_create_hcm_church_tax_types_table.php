<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_church_tax_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // AK, EV, RK, etc.
            $table->string('name', 100); // z.B. "Römisch-Katholische Kirchensteuer"
            $table->text('description')->nullable(); // Optional: Beschreibung
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_church_tax_types');
    }
};

