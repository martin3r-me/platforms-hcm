<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_employee_issue_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('category', 100)->nullable(); // z.B. Kleidung, Schlüssel, IT, Sonstiges
            $table->boolean('requires_return')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_issue_types');
    }
};


