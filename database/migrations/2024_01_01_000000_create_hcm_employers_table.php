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
        Schema::create('hcm_employers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Employer-spezifische Felder
            $table->string('employer_number')->unique();
            
            // Keine direkte CRM-Referenz - Verknüpfung über crm_company_links
            
            // Employee-Nummerierung
            $table->string('employee_number_prefix')->nullable(); // Optional, nullable
            $table->integer('employee_number_start')->default(1);
            $table->integer('employee_number_next')->default(1);
            
            // Settings und Metadata
            $table->json('settings')->nullable();
            
            // User/Team-Kontext
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexe für Performance
            $table->index(['team_id', 'is_active']);
            $table->index(['created_by_user_id', 'owned_by_user_id']);
            $table->index('employer_number');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hcm_employers');
    }
};
