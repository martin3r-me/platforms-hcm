<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_contact_links')
            ->where('linkable_type', 'Platform\\Hcm\\Models\\HcmEmployee')
            ->update(['linkable_type' => 'hcm_employee']);
    }

    public function down(): void
    {
        DB::table('crm_contact_links')
            ->where('linkable_type', 'hcm_employee')
            ->update(['linkable_type' => 'Platform\\Hcm\\Models\\HcmEmployee']);
    }
};
