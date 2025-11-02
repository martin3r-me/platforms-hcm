<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_export_template_columns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('export_template_id')
                  ->constrained('hcm_export_templates')
                  ->onDelete('cascade');
            
            // Spalten-Position (Index im CSV, 0-basiert)
            $table->unsignedSmallInteger('column_index')->default(0);
            
            // Header-Name für Zeile 5 (z.B. "Nr.", "Vorname", etc.)
            $table->string('header_name')->nullable();
            
            // Mapping: Welches Feld aus Employee/Contract?
            // Format: "employee.employee_number" oder "contract.start_date" oder statischer Wert
            $table->string('source_field')->nullable();
            
            // Optional: Statischer Wert (wenn source_field leer)
            $table->string('static_value')->nullable();
            
            // Optional: Transform-Funktion (z.B. "date:d.m.Y", "bool:Ja/Nein", etc.)
            $table->string('transform')->nullable();
            
            // Reihenfolge (für Sortierung)
            $table->unsignedSmallInteger('sort_order')->default(0);
            
            $table->timestamps();
            
            // Indexe
            $table->index(['export_template_id', 'column_index']);
            $table->index(['export_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_export_template_columns');
    }
};

