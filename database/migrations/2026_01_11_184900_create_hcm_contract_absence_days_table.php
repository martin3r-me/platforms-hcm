<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hcm_contract_absence_days', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Zugehörigkeit
            $table->foreignId('contract_id')->constrained('hcm_employee_contracts')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hcm_employees')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            
            // Abwesenheitstag
            $table->date('absence_date'); // Tag der Abwesenheit
            $table->enum('type', ['full_day', 'half_day_morning', 'half_day_afternoon'])->default('full_day');
            
            // Abwesenheitsgrund (Lookup statt ENUM)
            $table->foreignId('absence_reason_id')->constrained('hcm_absence_reasons')->cascadeOnDelete();
            $table->string('reason_custom')->nullable(); // Freitext-Ergänzung (optional)
            
            // Krankmeldung (bei reason mit requires_sick_note=true)
            $table->boolean('has_sick_note')->default(false); // Attest vorhanden
            $table->date('sick_note_from')->nullable(); // Attest von
            $table->date('sick_note_until')->nullable(); // Attest bis
            $table->string('sick_note_number')->nullable(); // Attest-Nummer
            
            // Status & Validierung
            $table->enum('status', ['reported', 'confirmed', 'rejected', 'cancelled'])->default('reported');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Quelle (für späteren Push-Import)
            $table->string('source', 50)->default('manual'); // manual, import, api, push
            $table->string('source_reference')->nullable(); // Referenz zur Quelle
            $table->timestamp('source_synced_at')->nullable(); // Wann vom externen System synchronisiert
            
            // Metadaten
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indizes
            $table->unique(['contract_id', 'absence_date']); // Ein Abwesenheitstag pro Tag/Contract
            $table->index(['employee_id', 'absence_date']);
            $table->index(['team_id', 'absence_date']);
            $table->index(['absence_reason_id']);
            $table->index(['status']);
            $table->index(['source', 'source_synced_at']); // Für Push-Sync
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_contract_absence_days');
    }
};
