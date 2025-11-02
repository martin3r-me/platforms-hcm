<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            // Neue Foreign Key Spalte
            $table->foreignId('church_tax_type_id')
                ->nullable()
                ->after('church_tax')
                ->constrained('hcm_church_tax_types')
                ->nullOnDelete();
            
            // Index für Performance
            $table->index('church_tax_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employees', function (Blueprint $table) {
            $table->dropForeign(['church_tax_type_id']);
            $table->dropIndex(['church_tax_type_id']);
            $table->dropColumn('church_tax_type_id');
        });
    }
};

