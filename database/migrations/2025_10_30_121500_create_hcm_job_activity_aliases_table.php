<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_job_activity_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_activity_id')->constrained('hcm_job_activities')->cascadeOnDelete();
            $table->string('alias', 255);
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'alias']);
            $table->index(['team_id', 'job_activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_job_activity_aliases');
    }
};


