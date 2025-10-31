<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hcm_employees')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('hcm_employee_contracts')->nullOnDelete();
            $table->foreignId('training_type_id')->nullable()->constrained('hcm_employee_training_types')->nullOnDelete();
            $table->string('title'); // Name der Schulung (z.B. "Hygieneschulung 2025")
            $table->string('provider')->nullable(); // Durchführende Stelle
            $table->date('completed_date')->nullable(); // Abschlussdatum
            $table->date('valid_from')->nullable(); // Gültig ab
            $table->date('valid_until')->nullable(); // Gültig bis (berechnet aus validity_months oder manuell)
            $table->enum('status', ['planned', 'in_progress', 'completed', 'expired', 'failed'])->default('planned');
            $table->text('notes')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['contract_id']);
            $table->index(['training_type_id']);
            $table->index(['valid_until']); // Für Abfrage abgelaufener Schulungen
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_trainings');
    }
};

