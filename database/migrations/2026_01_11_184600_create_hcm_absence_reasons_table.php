<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hcm_absence_reasons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Team-Scoping
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            
            // Stammdaten
            $table->string('code'); // z.B. "SICK", "SICK_CHILD", "DOCTOR", "PERSONAL"
            $table->string('name'); // z.B. "Krankheit", "Kind krank", "Arzttermin"
            $table->string('short_name')->nullable(); // Kurzform
            $table->text('description')->nullable();
            
            // Klassifikation
            $table->string('category')->nullable(); // 'sick', 'personal', 'unpaid_leave', 'other'
            $table->boolean('requires_sick_note')->default(false); // Attest erforderlich?
            $table->boolean('is_paid')->default(true); // Bezahlt?
            
            // Anzeige & Sortierung
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            // Audit
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indizes
            $table->unique(['team_id', 'code']); // Code unique per Team
            $table->index(['team_id', 'category', 'is_active']);
            $table->index(['team_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_absence_reasons');
    }
};
