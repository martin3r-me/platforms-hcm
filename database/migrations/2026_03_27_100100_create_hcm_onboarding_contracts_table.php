<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_onboarding_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('hcm_onboarding_id')->constrained('hcm_onboardings')->cascadeOnDelete();
            $table->foreignId('hcm_contract_template_id')->constrained('hcm_contract_templates')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->longText('personalized_content')->nullable();
            $table->text('signature_data')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hcm_onboarding_id', 'status']);
            $table->index(['team_id', 'status']);
            $table->index('hcm_contract_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_onboarding_contracts');
    }
};
