<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Export-Metadaten
            $table->string('name'); // Name des Exports (z.B. "INFONIQA Export")
            $table->string('type'); // Export-Typ (z.B. "infoniqa", "payroll", "custom")
            $table->string('format')->default('csv'); // Format: csv, xlsx, pdf, json
            
            // Template-Referenz (optional, für Baukasten-basierte Exports)
            $table->foreignId('export_template_id')->nullable()->constrained('hcm_export_templates')->nullOnDelete();
            
            // Export-Parameter (JSON)
            $table->json('parameters')->nullable(); // Filter, Datumsbereich, etc.
            
            // Ergebnis
            $table->string('file_path')->nullable(); // Pfad zur exportierten Datei
            $table->string('file_name')->nullable(); // Original-Dateiname
            $table->integer('record_count')->nullable(); // Anzahl exportierter Datensätze
            $table->bigInteger('file_size')->nullable(); // Dateigröße in Bytes
            
            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            
            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_exports');
    }
};

