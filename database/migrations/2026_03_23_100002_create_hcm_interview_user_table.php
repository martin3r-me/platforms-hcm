<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hcm_interview_user');

        Schema::create('hcm_interview_user', function (Blueprint $table) {
            $table->foreignId('hcm_interview_id')->constrained('hcm_interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unique(['hcm_interview_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_interview_user');
    }
};
