<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hcm_payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('core_teams')->onDelete('cascade');
            $table->string('code'); // e.g. PM_UEBERWEISUNG
            $table->string('name'); // e.g. Überweisung
            $table->unsignedInteger('external_code')->nullable(); // e.g. 5
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['team_id','code']);
            $table->unique(['team_id','external_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_payout_methods');
    }
};


