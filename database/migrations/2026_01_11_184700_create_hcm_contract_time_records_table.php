<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hcm_contract_time_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Zugehörigkeit
            $table->foreignId('contract_id')->constrained('hcm_employee_contracts')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hcm_employees')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            
            // Datum & Zeiten
            $table->date('record_date'); // Tag der Stempelung
            $table->time('clock_in')->nullable(); // Einstempeln
            $table->time('clock_out')->nullable(); // Ausstempeln
            $table->time('break_start')->nullable(); // Pausenbeginn
            $table->time('break_end')->nullable(); // Pausenende
            $table->unsignedInteger('break_minutes')->default(0); // Pausenzeit in Minuten (kalkuliert)
            $table->unsignedInteger('work_minutes')->nullable(); // Arbeitszeit in Minuten (kalkuliert)
            
            // Status & Validierung
            $table->enum('status', ['draft', 'confirmed', 'rejected', 'corrected'])->default('draft');
            $table->boolean('is_corrected')->default(false); // Korrektur vorhanden
            $table->foreignId('corrected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            
            // Quelle
            $table->string('source', 50)->default('manual'); // manual, import, api, terminal
            $table->string('source_reference')->nullable(); // Referenz zur Quelle (z.B. Terminal-ID)
            
            // Metadaten
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Zusätzliche Daten (z.B. GPS, Terminal-ID)
            
            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indizes
            $table->unique(['contract_id', 'record_date']); // Ein Datensatz pro Tag/Contract
            $table->index(['employee_id', 'record_date']);
            $table->index(['team_id', 'record_date']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_contract_time_records');
    }
};
