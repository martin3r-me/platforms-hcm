<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop in reverse order (FKs)
        Schema::dropIfExists('hcm_interview_bookings');
        Schema::dropIfExists('hcm_interview_user');
        Schema::dropIfExists('hcm_interviews');
        Schema::dropIfExists('hcm_interview_types');

        Schema::create('hcm_interview_types', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index('name');
        });

        Schema::create('hcm_interviews', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('interview_type_id')->nullable()->constrained('hcm_interview_types')->cascadeOnDelete();
            $table->foreignId('hcm_job_title_id')->nullable()->constrained('hcm_job_titles')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('min_participants')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->string('status')->default('planned');
            $table->boolean('is_active')->default(true);
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['interview_type_id', 'starts_at']);
            $table->index('hcm_job_title_id');
            $table->index('starts_at');
            $table->index('status');
        });

        Schema::create('hcm_interview_user', function (Blueprint $table) {
            $table->foreignId('hcm_interview_id')->constrained('hcm_interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unique(['hcm_interview_id', 'user_id']);
        });

        Schema::create('hcm_interview_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('hcm_interview_id')->constrained('hcm_interviews')->cascadeOnDelete();
            $table->foreignId('hcm_onboarding_id')->constrained('hcm_onboardings')->cascadeOnDelete();
            $table->string('status')->default('registered');
            $table->text('notes')->nullable();
            $table->dateTime('booked_at')->nullable()->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hcm_interview_id', 'hcm_onboarding_id']);
            $table->index(['hcm_interview_id', 'status']);
            $table->index('hcm_onboarding_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_interview_bookings');
        Schema::dropIfExists('hcm_interview_user');
        Schema::dropIfExists('hcm_interviews');
        Schema::dropIfExists('hcm_interview_types');
    }
};
