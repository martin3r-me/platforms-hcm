<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_job_titles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_job_titles');
    }
};


