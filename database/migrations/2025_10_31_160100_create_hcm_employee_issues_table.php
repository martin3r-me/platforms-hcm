<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_employee_issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('issue_type_id');
            $table->string('identifier', 100)->nullable(); // z.B. Seriennummer, Schlüsselnummer
            $table->string('status', 30)->default('issued'); // issued | returned | lost | defective
            $table->date('issued_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->json('metadata')->nullable(); // Größe, Farbe etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['team_id','employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_issues');
    }
};


