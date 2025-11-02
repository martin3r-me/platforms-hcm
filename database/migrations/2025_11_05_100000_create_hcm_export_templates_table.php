<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_export_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Template-Metadaten
            $table->string('name'); // Name der Vorlage
            $table->string('slug')->unique(); // Eindeutiger Slug (z.B. "infoniqa-employee-export")
            $table->text('description')->nullable();
            
            // Template-Konfiguration (JSON)
            // Enthält: Felder, Mapping, Format, Header-Struktur, etc.
            $table->json('configuration'); // Flexible Konfiguration für Baukasten
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_template')->default(false); // System-Templates können nicht gelöscht werden
            
            $table->timestamps();
            
            $table->index(['team_id', 'is_active']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_export_templates');
    }
};

