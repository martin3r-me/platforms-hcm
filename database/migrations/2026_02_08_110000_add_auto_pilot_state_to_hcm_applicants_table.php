<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_auto_pilot_states', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'is_active']);
        });

        // Seed default states (global, team_id = NULL)
        $now = now();
        DB::table('hcm_auto_pilot_states')->insert([
            ['uuid' => \Str::uuid(), 'code' => 'new', 'name' => 'Neu (noch nicht bearbeitet)', 'description' => null, 'is_active' => true, 'team_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => \Str::uuid(), 'code' => 'contact_check', 'name' => 'Kontaktprüfung', 'description' => null, 'is_active' => true, 'team_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => \Str::uuid(), 'code' => 'data_collection', 'name' => 'Daten sammeln', 'description' => null, 'is_active' => true, 'team_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => \Str::uuid(), 'code' => 'waiting_for_applicant', 'name' => 'Warte auf Bewerber', 'description' => null, 'is_active' => true, 'team_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => \Str::uuid(), 'code' => 'review_needed', 'name' => 'Prüfung erforderlich', 'description' => null, 'is_active' => true, 'team_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => \Str::uuid(), 'code' => 'completed', 'name' => 'Abgeschlossen', 'description' => null, 'is_active' => true, 'team_id' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('hcm_applicants', function (Blueprint $table) {
            $table->foreignId('auto_pilot_state_id')
                ->nullable()
                ->after('auto_pilot_completed_at')
                ->constrained('hcm_auto_pilot_states')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_applicants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auto_pilot_state_id');
        });

        Schema::dropIfExists('hcm_auto_pilot_states');
    }
};
