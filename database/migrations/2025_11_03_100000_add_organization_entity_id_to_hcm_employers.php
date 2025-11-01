<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_employers', function (Blueprint $table) {
            if (!Schema::hasColumn('hcm_employers', 'organization_entity_id')) {
                $table->foreignId('organization_entity_id')
                    ->nullable()
                    ->after('team_id')
                    ->constrained('organization_entities')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_employers', function (Blueprint $table) {
            if (Schema::hasColumn('hcm_employers', 'organization_entity_id')) {
                $table->dropForeign(['organization_entity_id']);
                $table->dropColumn('organization_entity_id');
            }
        });
    }
};

