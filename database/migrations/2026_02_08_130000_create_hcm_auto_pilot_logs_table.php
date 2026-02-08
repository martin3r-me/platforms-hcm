<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_auto_pilot_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hcm_applicant_id')->constrained('hcm_applicants')->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->text('summary');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_auto_pilot_logs');
    }
};
