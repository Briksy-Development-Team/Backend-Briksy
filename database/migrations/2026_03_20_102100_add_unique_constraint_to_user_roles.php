<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_roles', function (Blueprint $table): void {
            $table->unique(['user_id', 'role_id', 'organization_id'], 'user_roles_user_role_org_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table): void {
            $table->dropUnique('user_roles_user_role_org_unique');
        });
    }
};

