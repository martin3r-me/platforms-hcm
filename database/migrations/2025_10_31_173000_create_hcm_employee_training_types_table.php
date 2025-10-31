<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_employee_training_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // z.B. "Hygiene", "Sicherheit", "Führerschein"
            $table->boolean('requires_certification')->default(false); // Benötigt Bescheinigung/Zertifikat
            $table->unsignedSmallInteger('validity_months')->nullable(); // Gültigkeitsdauer in Monaten (null = unbegrenzt)
            $table->boolean('is_mandatory')->default(false); // Pflichtschulung
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
            $table->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_training_types');
    }
};

