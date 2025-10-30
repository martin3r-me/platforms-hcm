<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_insurance_statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_insurance_statuses');
    }
};


