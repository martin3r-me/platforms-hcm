<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_interview_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('hcm_interview_id')->constrained('hcm_interviews')->cascadeOnDelete();
            $table->foreignId('hcm_onboarding_id')->constrained('hcm_onboardings')->cascadeOnDelete();
            $table->string('status')->default('registered');
            $table->text('notes')->nullable();
            $table->dateTime('booked_at')->nullable()->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->foreignId('team_id')->constrained('teams')->nullOnDelete();
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
    }
};
