<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_applicant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_applicant_settings');
    }
};
