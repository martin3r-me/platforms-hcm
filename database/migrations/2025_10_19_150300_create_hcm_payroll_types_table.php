<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hcm_payroll_types', function (Blueprint $t) {
            $t->id();

            // Mandant / Team
            $t->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            // Stammdaten
            $t->string('code');                 // z.B. "5010"
            $t->string('lanr', 10)->nullable(); // Lohnarten-Nummer (z.B. "910", "3001")
            $t->string('name');                 // z.B. "Grundlohn Küche"
            $t->string('short_name')->nullable(); // Short name
            $t->string('typ')->nullable();      // Typ (grundgehalt, zulage, abzug, etc.)

            // Klassifikation (leichtgewichtig, erweiterbar)
            $t->string('category')->nullable(); // 'earning','deduction','employer','benefit', ...
            $t->string('basis')->nullable();    // 'hour','day','month','amount','percent', ...

            // Payroll-specific flags
            $t->boolean('relevant_gross')->default(false); // Gross salary relevant
            $t->boolean('relevant_social_sec')->default(false); // Social security relevant  
            $t->boolean('relevant_tax')->default(false); // Tax relevant
            $t->enum('addition_deduction', ['addition', 'deduction', 'neutral'])->default('addition'); // Addition/Deduction/Neutral

            // Optional: Default rate/percentage (convenience only)
            $t->decimal('default_rate', 10, 4)->nullable(); // e.g. 15.5000 €/h or 15.0000 %

            // Optional: Default accounts (default level only; final accounting via Finance rules)
            $t->foreignId('debit_finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $t->foreignId('credit_finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();

            // Validity / Activity
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->boolean('is_active')->default(true);
            
            // Display & Organization
            $t->string('display_group')->nullable(); // Display group (e.g. "Regular", "Allowances")
            $t->integer('sort_order')->nullable();   // Sort order
            $t->text('description')->nullable();      // Description

            // Metadata
            $t->json('meta')->nullable();       // Free field (e.g. formulas, external keys)
            $t->timestamps();

            // Constraints / Indizes
            $t->unique(['team_id','code']);     // Code unique per team
            $t->index(['team_id','category','is_active']);
            $t->index(['team_id','lanr']);      // LANR-Index for German payroll types
            $t->index(['team_id','display_group','sort_order']); // Grouping & Sorting
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_payroll_types');
    }
};
