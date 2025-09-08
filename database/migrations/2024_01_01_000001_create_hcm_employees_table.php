<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hcm_employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Keine direkten CRM-Referenzen - Verknüpfung über crm_contact_links
            
            // Employer-Verknüpfung
            $table->foreignId('employer_id')->nullable()->constrained('hcm_employers')->nullOnDelete();
            
            // Mitarbeiter-spezifische Felder
            $table->string('employee_number')->unique();
            
            // User/Team-Kontext
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexe für Performance
            $table->index(['team_id', 'is_active']);
            $table->index(['employer_id', 'is_active']);
            $table->index(['created_by_user_id', 'owned_by_user_id']);
            $table->index('employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hcm_employees');
    }
};
