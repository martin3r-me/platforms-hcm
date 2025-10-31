<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hcm_contract_compensation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('core_teams')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('hcm_employees')->onDelete('cascade');
            $table->foreignId('employee_contract_id')->constrained('hcm_employee_contracts')->onDelete('cascade');
            $table->date('effective_date');
            $table->string('wage_base_type')->nullable(); // z.B. Stundenlohn, Gehalt
            $table->decimal('hourly_wage', 10, 2)->nullable();
            $table->decimal('base_salary', 12, 2)->nullable();
            $table->string('reason')->default('import_initial');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('core_users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_contract_compensation_events');
    }
};


