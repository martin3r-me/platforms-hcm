<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hcm_contract_vacation_days', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Zugehörigkeit
            $table->foreignId('contract_id')->constrained('hcm_employee_contracts')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hcm_employees')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            
            // Urlaubstag
            $table->date('vacation_date'); // Tag des Urlaubs
            $table->enum('type', ['full_day', 'half_day_morning', 'half_day_afternoon'])->default('full_day');
            
            // Status & Genehmigung
            $table->enum('status', ['requested', 'approved', 'rejected', 'cancelled'])->default('requested');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Quelle
            $table->string('source', 50)->default('manual'); // manual, import, api
            $table->string('source_reference')->nullable();
            
            // Metadaten
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indizes
            $table->unique(['contract_id', 'vacation_date']); // Ein Urlaubstag pro Tag/Contract
            $table->index(['employee_id', 'vacation_date']);
            $table->index(['team_id', 'vacation_date']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_contract_vacation_days');
    }
};
