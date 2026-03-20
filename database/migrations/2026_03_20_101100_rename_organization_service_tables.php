<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizaton_services') && !Schema::hasTable('organization_services')) {
            Schema::rename('organizaton_services', 'organization_services');
        }

        if (Schema::hasTable('org_service_groups') && !Schema::hasTable('organization_service_groups')) {
            Schema::rename('org_service_groups', 'organization_service_groups');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('organization_services') && !Schema::hasTable('organizaton_services')) {
            Schema::rename('organization_services', 'organizaton_services');
        }

        if (Schema::hasTable('organization_service_groups') && !Schema::hasTable('org_service_groups')) {
            Schema::rename('organization_service_groups', 'org_service_groups');
        }
    }
};

